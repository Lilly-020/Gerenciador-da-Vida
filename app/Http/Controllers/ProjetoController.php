<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjetoRequest;
use App\Http\Requests\UpdateProjetoRequest;
use App\Models\Projeto;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjetoController extends Controller
{
    /**
     * Display the authenticated user's projects, with progress analytics.
     */
    public function index(Request $request): View
    {
        $projetos = $this->visibleProjetos();

        return view('pages.projetos', [
            'projetos' => $projetos,
            'stats' => Projeto::summarize($projetos),
        ]);
    }

    /**
     * Create a new project for the authenticated user.
     */
    public function store(StoreProjetoRequest $request): RedirectResponse|JsonResponse
    {
        $projeto = $request->user()->projetos()->create($request->validated());

        return $this->respondWith($request, $projeto);
    }

    /**
     * Update a project's own details (title, objective, and dates).
     */
    public function update(UpdateProjetoRequest $request, Projeto $projeto): RedirectResponse|JsonResponse
    {
        abort_unless($projeto->canBeEditedBy($request->user()), 403, 'Você só pode visualizar este projeto.');

        $projeto->update($request->validated());

        return $this->respondWith($request, $projeto);
    }

    /**
     * Delete a project and its checklist (tasks cascade-delete at the DB level).
     */
    public function destroy(Request $request, Projeto $projeto): RedirectResponse|JsonResponse
    {
        abort_unless($projeto->isOwnedBy($request->user()), 403, 'Só quem criou o projeto pode excluí-lo.');

        $projetoId = $projeto->id;
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
        return Projeto::with(Projeto::CARD_RELATIONS)->latest()->get();
    }
}
