<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScheduleProjetoTaskRequest;
use App\Http\Requests\StoreProjetoTaskRequest;
use App\Http\Requests\UpdateProjetoTaskRequest;
use App\Models\Projeto;
use App\Models\ProjetoTask;
use App\Models\Tarefa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjetoTaskController extends Controller
{
    /**
     * Add a new task to the given project's checklist.
     */
    public function store(StoreProjetoTaskRequest $request, Projeto $projeto): RedirectResponse|JsonResponse
    {
        abort_unless($projeto->canBeEditedBy($request->user()), 403, 'Você só pode visualizar este projeto.');

        $task = $projeto->tasks()->create($request->validated());
        $projeto->recordActivity('Criou a tarefa “'.$task->title.'”.');

        return $this->respondWith($request, $projeto);
    }

    /**
     * Update a task's completion state.
     */
    public function update(UpdateProjetoTaskRequest $request, Projeto $projeto, ProjetoTask $tarefa): RedirectResponse|JsonResponse
    {
        abort_unless($tarefa->projeto_id === $projeto->id, 404);

        abort_unless($projeto->canBeEditedBy($request->user()), 403, 'Você só pode visualizar este projeto.');

        $wasFinished = $tarefa->is_finished;
        $tarefa->fill($request->validated());
        if ($tarefa->goal_type !== 'checklist') {
            $tarefa->completed = $tarefa->is_finished;
        }
        $tarefa->save();
        $action = match (true) {
            ! $wasFinished && $tarefa->is_finished => 'Concluiu',
            $wasFinished && ! $tarefa->is_finished => 'Reabriu',
            default => 'Editou',
        };
        $projeto->recordActivity($action.' a tarefa “'.$tarefa->title.'”.');

        return $this->respondWith($request, $projeto);
    }

    public function schedule(ScheduleProjetoTaskRequest $request, Projeto $projeto, ProjetoTask $tarefa): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $calendarTask = DB::transaction(function () use ($request, $projeto, $tarefa, $data): Tarefa {
            $source = $projeto->tasks()->whereKey($tarefa->id)->lockForUpdate()->firstOrFail();
            $calendarTask = $source->calendarTask()->first();
            $attributes = [
                'title' => $source->title,
                'date' => $data['date'],
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'end_date' => null,
            ];
            if ($calendarTask) {
                $calendarTask->update($attributes);
            } else {
                $calendarTask = $request->user()->tarefas()->make([...$attributes, 'completed' => $source->is_finished]);
                $calendarTask->projetoTask()->associate($source);
                $calendarTask->save();
            }
            $projeto->recordActivity('Agendou a tarefa “'.$source->title.'” no próprio calendário.');

            return $calendarTask;
        });

        return $this->respondWith($request, $projeto, $calendarTask);
    }

    /**
     * Remove a task from the given project's checklist.
     */
    public function destroy(Request $request, Projeto $projeto, ProjetoTask $tarefa): RedirectResponse|JsonResponse
    {
        abort_unless($tarefa->projeto_id === $projeto->id, 404);

        abort_unless($projeto->canBeEditedBy($request->user()), 403, 'Você só pode visualizar este projeto.');

        $projeto->recordActivity('Excluiu a tarefa “'.$tarefa->title.'” e suas subtarefas.');
        $tarefa->delete();

        return $this->respondWith($request, $projeto);
    }

    /**
     * Render the card + stats partials for a JSON (fetch) request, or fall
     * back to a plain redirect for a normal form submission.
     */
    private function respondWith(Request $request, Projeto $projeto, ?Tarefa $calendarTask = null): RedirectResponse|JsonResponse
    {
        if (! $request->wantsJson()) {
            return back();
        }

        $projetos = Projeto::with('tasks')->latest()->get();

        return response()->json([
            'calendar_url' => $calendarTask ? route('tarefas', ['date' => $calendarTask->date->toDateString(), 'view' => 'week', 'focus_task' => $calendarTask->id], absolute: false) : null,
            'card' => view('partials.projeto-card', ['projeto' => $projeto->fresh(Projeto::CARD_RELATIONS)])->render(),
            'stats' => view('partials.projeto-stats', ['stats' => Projeto::summarize($projetos)])->render(),
        ]);
    }
}
