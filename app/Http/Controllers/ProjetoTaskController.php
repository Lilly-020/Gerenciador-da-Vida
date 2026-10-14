<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjetoTaskRequest;
use App\Http\Requests\UpdateProjetoTaskRequest;
use App\Models\Projeto;
use App\Models\ProjetoTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjetoTaskController extends Controller
{
    /**
     * Add a new task to the given project's checklist.
     */
    public function store(StoreProjetoTaskRequest $request, Projeto $projeto): RedirectResponse|JsonResponse
    {
        abort_unless($projeto->canBeEditedBy($request->user()), 403, 'Você só pode visualizar este projeto.');

        $projeto->tasks()->create($request->validated());

        return $this->respondWith($request, $projeto);
    }

    /**
     * Update a task's completion state.
     */
    public function update(UpdateProjetoTaskRequest $request, Projeto $projeto, ProjetoTask $tarefa): RedirectResponse|JsonResponse
    {
        abort_unless($tarefa->projeto_id === $projeto->id, 404);

        abort_unless($projeto->canBeEditedBy($request->user()), 403, 'Você só pode visualizar este projeto.');

        $tarefa->update($request->validated());

        return $this->respondWith($request, $projeto);
    }

    /**
     * Remove a task from the given project's checklist.
     */
    public function destroy(Request $request, Projeto $projeto, ProjetoTask $tarefa): RedirectResponse|JsonResponse
    {
        abort_unless($tarefa->projeto_id === $projeto->id, 404);

        abort_unless($projeto->canBeEditedBy($request->user()), 403, 'Você só pode visualizar este projeto.');

        $tarefa->delete();

        return $this->respondWith($request, $projeto);
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

        $projetos = Projeto::with('tasks')->latest()->get();

        return response()->json([
            'card' => view('partials.projeto-card', ['projeto' => $projeto->fresh(Projeto::CARD_RELATIONS)])->render(),
            'stats' => view('partials.projeto-stats', ['stats' => Projeto::summarize($projetos)])->render(),
        ]);
    }
}
