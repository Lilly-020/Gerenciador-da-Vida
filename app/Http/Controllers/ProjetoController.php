<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjetoRequest;
use App\Http\Requests\UpdateProjetoRequest;
use App\Models\Projeto;
use App\Models\ProjetoEtiqueta;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProjetoController extends Controller
{
    private const PER_PAGE = 10;

    /**
     * Display the authenticated user's projects, with progress analytics.
     */
    public function index(Request $request): View|JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'de' => ['nullable', 'date_format:Y-m-d'],
            'ate' => ['nullable', 'date_format:Y-m-d', ...($request->filled('de') ? ['after_or_equal:de'] : [])],
            'status' => ['nullable', 'in:not_started,in_progress,completed,archived'],
            'prazo' => ['nullable', 'in:overdue,soon,none'],
            'sort' => ['nullable', 'in:due_at,progress,name,priority'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $allProjects = $this->visibleProjetos();
        $stats = Projeto::summarize($allProjects);
        $term = Str::ascii(Str::lower(trim($filters['q'] ?? '')));
        $status = $filters['status'] ?? '';
        $deadline = $filters['prazo'] ?? '';
        $filtered = $allProjects->filter(function (Projeto $project) use ($filters, $term, $status, $deadline): bool {
            if ($status === 'archived' ? ! $project->archived : $project->archived || ($status !== '' && $project->status !== $status)) {
                return false;
            }
            if ($term !== '' && ! str_contains(Str::ascii(Str::lower($project->title)), $term)) {
                return false;
            }
            $date = $project->due_at?->toDateString();
            if (! empty($filters['de']) && (! $date || $date < $filters['de'])) {
                return false;
            }
            if (! empty($filters['ate']) && (! $date || $date > $filters['ate'])) {
                return false;
            }

            return match ($deadline) {
                'none' => $date === null,
                'overdue' => $project->overdue,
                'soon' => ! $project->archived && $project->status !== 'completed' && $project->due_at?->betweenIncluded(today(), today()->addDays(7)),
                default => true,
            };
        });
        $filtered = match ($filters['sort'] ?? '') {
            'due_at' => $filtered->sortBy(fn (Projeto $project): string => $project->due_at?->toDateString() ?? '9999-12-31'),
            'progress' => $filtered->sortByDesc(fn (Projeto $project): int => $project->progress),
            'name' => $filtered->sortBy(fn (Projeto $project): string => Str::ascii(Str::lower($project->title))),
            'priority' => $filtered->sortBy(fn (Projeto $project): int => array_search($project->priority, ['high', 'medium', 'low'], true)),
            default => $filtered,
        };
        $page = min((int) ($filters['page'] ?? 1), max(1, (int) ceil($filtered->count() / self::PER_PAGE)));
        $projetos = new LengthAwarePaginator(
            $filtered->forPage($page, self::PER_PAGE)->values()->loadMissing(Projeto::CARD_RELATIONS),
            $filtered->count(),
            self::PER_PAGE,
            $page,
            ['path' => route('projetos', absolute: false)],
        );
        $projetos->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'cards' => view('partials.projeto-list', ['projetos' => $projetos])->render(),
                'pagination' => (string) $projetos->onEachSide(1)->links('partials.projeto-pagination'),
                'stats' => view('partials.projeto-stats', ['stats' => $stats])->render(),
                'page' => $page,
            ]);
        }

        return view('pages.projetos', ['projetos' => $projetos, 'stats' => $stats]);
    }

    /**
     * Create a new project for the authenticated user.
     */
    public function store(StoreProjetoRequest $request): RedirectResponse|JsonResponse
    {
        $projeto = DB::transaction(function () use ($request): Projeto {
            $projeto = $request->user()->projetos()->create($request->validated());
            $projeto->etiquetas()->createMany(array_map(
                fn (array $label): array => [...$label, 'selected' => false],
                ProjetoEtiqueta::DEFAULTS,
            ));
            $projeto->recordActivity('Criou o projeto.');

            return $projeto;
        });

        return $this->respondWith($request, $projeto);
    }

    /**
     * Update a project's own details (title, objective, and dates).
     */
    public function update(UpdateProjetoRequest $request, Projeto $projeto): RedirectResponse|JsonResponse
    {
        abort_unless($projeto->canBeManagedBy($request->user()), 403, 'Apenas administradores podem editar os dados do projeto.');

        $projeto->update($request->validated());
        $projeto->recordActivity($projeto->wasChanged('archived') ? ($projeto->archived ? 'Arquivou o projeto.' : 'Restaurou o projeto.') : 'Editou os dados do projeto.');

        return $this->respondWith($request, $projeto);
    }

    /**
     * Delete a project and its checklist (tasks cascade-delete at the DB level).
     */
    public function destroy(Request $request, Projeto $projeto): RedirectResponse|JsonResponse
    {
        abort_unless($projeto->canBeManagedBy($request->user()), 403, 'Apenas administradores podem excluir o projeto.');

        $projetoId = $projeto->id;
        Storage::disk('local')->delete($projeto->files->pluck('path')->all());
        $projeto->delete();

        if (! $request->wantsJson()) {
            return back();
        }

        $projetos = $this->visibleProjetos();

        return response()->json([
            'removeCardId' => "projeto-card-{$projetoId}",
            'stats' => view('partials.projeto-stats', ['stats' => Projeto::summarize($projetos)])->render(),
        ]);
    }

    /**
     * Render the card + stats partials for a JSON (fetch) request, or fall
     * back to a plain redirect for a normal form submission.
     */
    private function respondWith(Request $request, Projeto $projeto): RedirectResponse|JsonResponse
    {
        if (! $request->wantsJson()) {
            return back();
        }

        $projetos = $this->visibleProjetos();

        return response()->json([
            'card' => view('partials.projeto-card', ['projeto' => $projeto->load(Projeto::CARD_RELATIONS)])->render(),
            'stats' => view('partials.projeto-stats', ['stats' => Projeto::summarize($projetos)])->render(),
        ]);
    }

    /**
     * Every project the user can see — their own and the ones shared with
     * them (the model's global scope decides which).
     *
     * @return Collection<int, Projeto>
     */
    private function visibleProjetos(): Collection
    {
        return Projeto::with('tasks')->latest('id')->get();
    }
}
