<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScheduleNotaRequest;
use App\Http\Requests\StoreNotaRequest;
use App\Http\Requests\UpdateNotaRequest;
use App\Models\Nota;
use App\Models\NotaItem;
use App\Models\Tarefa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class NotaController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'filter' => ['nullable', 'in:all,lists,text,archived']]);
        $filter = $request->query('filter', 'all');
        $term = trim((string) $request->query('q'));
        $notas = $request->user()->notas()->with(['items', 'tarefa'])
            ->where('archived', $filter === 'archived')
            ->when($filter === 'lists', fn (Builder $query): Builder => $query->has('items'))
            ->when($filter === 'text', fn (Builder $query): Builder => $query->doesntHave('items'))
            ->when($term !== '', function (Builder $query) use ($term): void {
                $search = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn (Builder $query): Builder => $query->whereLike('title', $search)->orWhereLike('content', $search)
                    ->orWhereHas('items', fn (Builder $query): Builder => $query->whereLike('text', $search)));
            })
            ->orderByDesc('pinned')->orderByDesc('updated_at')->orderByDesc('id')->paginate(24)->withQueryString();
        $data = ['notas' => $notas, 'filter' => $filter, 'term' => $term];

        return $request->wantsJson()
            ? response()->json(['html' => view('partials.nota-grid', $data)->render()])
            : view('pages.notas', $data);
    }

    public function store(StoreNotaRequest $request): JsonResponse
    {
        $data = $request->validated();
        $nota = DB::transaction(function () use ($request, $data): Nota {
            $nota = $request->user()->notas()->make(Arr::only($data, ['title', 'content', 'color', 'pinned', 'archived']));
            if (! empty($data['tarefa_id'])) {
                $nota->tarefa()->associate($request->user()->tarefas()->findOrFail($data['tarefa_id']));
            }
            $nota->save();
            $this->saveItems($nota, $data['items'] ?? []);

            return $nota;
        });

        return $this->respondWith($nota);
    }

    public function show(Nota $nota): JsonResponse
    {
        return $this->respondWith($nota);
    }

    public function update(UpdateNotaRequest $request, Nota $nota): JsonResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($nota, $data): void {
            $locked = Nota::whereKey($nota->id)->lockForUpdate()->firstOrFail();
            $locked->update(Arr::only($data, ['title', 'content', 'color', 'pinned', 'archived']));
            if (array_key_exists('items', $data)) {
                $this->saveItems($locked, $data['items']);
            }
        });

        return $this->respondWith($nota->fresh());
    }

    public function destroy(Nota $nota): JsonResponse
    {
        $nota->delete();

        return response()->json(['removed' => $nota->id]);
    }

    public function updateItem(Request $request, Nota $nota, int $item): JsonResponse
    {
        $data = $request->validate(['completed' => ['required', 'boolean']]);
        DB::transaction(function () use ($nota, $item, $data): void {
            $locked = Nota::whereKey($nota->id)->lockForUpdate()->firstOrFail();
            $locked->items()->findOrFail($item)->update($data);
            $locked->touch();
        });

        return $this->respondWith($nota->fresh());
    }

    public function link(Request $request, Nota $nota): JsonResponse
    {
        $data = $request->validate(['tarefa_id' => ['present', 'nullable', 'integer']]);
        $task = $data['tarefa_id'] === null ? null : $request->user()->tarefas()->findOrFail($data['tarefa_id']);
        $nota->tarefa()->associate($task);
        $nota->save();

        return $this->respondWith($nota);
    }

    public function schedule(ScheduleNotaRequest $request, Nota $nota): JsonResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($request, $nota, $data): void {
            $locked = Nota::whereKey($nota->id)->lockForUpdate()->firstOrFail();
            $task = $locked->tarefa;
            if ($task) {
                $task->update([...$data, 'start_time' => $data['start_time'] ?? null, 'end_time' => $data['end_time'] ?? null, 'end_date' => $data['end_date'] ?? null]);
            } else {
                $task = $request->user()->tarefas()->create($data);
                $locked->tarefa()->associate($task);
                $locked->save();
            }
        });

        return $this->respondWith($nota->fresh(), scheduled: true);
    }

    public function options(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'tarefa_id' => ['required', 'integer']]);
        $task = $request->user()->tarefas()->findOrFail($data['tarefa_id']);
        $search = '%'.addcslashes(trim($data['q'] ?? ''), '%_\\').'%';
        $notas = $request->user()->notas()->where('archived', false)->whereLike('title', $search)
            ->where(fn (Builder $query): Builder => $query->whereNull('tarefa_id')->orWhere('tarefa_id', $task->id))
            ->orderByDesc('pinned')->latest('updated_at')->limit(50)->get(['id', 'title']);

        return response()->json(['options' => $notas->map(fn (Nota $nota): array => ['id' => $nota->id, 'label' => $nota->title, 'url' => route('notas.tarefa', $nota, absolute: false)])]);
    }

    public function tasks(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $search = '%'.addcslashes(trim($data['q'] ?? ''), '%_\\').'%';
        $tasks = $request->user()->tarefas()->whereLike('title', $search)->orderByDesc('date')->orderByDesc('id')->limit(50)->get();

        return response()->json(['options' => $tasks->map(fn (Tarefa $task): array => ['id' => $task->id, 'label' => $task->title.' · '.$task->date->format('d/m/Y').($task->start_time ? ' · '.substr($task->start_time, 0, 5) : '')])]);
    }

    /** @param list<array{id?: int|null, text: string, completed: bool}> $items */
    private function saveItems(Nota $nota, array $items): void
    {
        $ids = [];
        foreach ($items as $position => $data) {
            $attributes = ['text' => $data['text'], 'completed' => $data['completed'], 'position' => $position];
            $item = isset($data['id']) ? $nota->items()->findOrFail($data['id']) : $nota->items()->make();
            $item->fill($attributes)->save();
            $ids[] = $item->id;
        }
        $nota->items()->whereNotIn('id', $ids)->delete();
    }

    private function respondWith(Nota $nota, bool $scheduled = false): JsonResponse
    {
        $nota->load(['items', 'tarefa']);
        $task = $nota->tarefa;

        return response()->json([
            'calendar_url' => $scheduled && $task ? route('dashboard', ['date' => $task->date->toDateString(), 'view' => 'week', 'focus_task' => $task->id], absolute: false) : null,
            'nota' => [
                'id' => $nota->id, 'title' => $nota->title, 'content' => $nota->content, 'color' => $nota->color,
                'pinned' => $nota->pinned, 'archived' => $nota->archived,
                'items' => $nota->items->map(fn (NotaItem $item): array => ['id' => $item->id, 'text' => $item->text, 'completed' => $item->completed]),
                'tarefa' => $task ? ['id' => $task->id, 'title' => $task->title, 'date' => $task->date->toDateString(), 'start_time' => $task->start_time ? substr($task->start_time, 0, 5) : null, 'end_time' => $task->end_time ? substr($task->end_time, 0, 5) : null, 'end_date' => $task->end_date?->toDateString(), 'url' => route('tarefas', ['date' => $task->date->toDateString(), 'view' => 'week', 'task_id' => $task->id], absolute: false)] : null,
                'url' => route('notas.show', $nota, absolute: false),
                'link_url' => route('notas.tarefa', $nota, absolute: false),
                'schedule_url' => route('notas.agendar', $nota, absolute: false),
            ],
            'card' => view('partials.nota-card', ['nota' => $nota])->render(),
        ]);
    }
}
