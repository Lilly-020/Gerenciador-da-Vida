<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSonhoTaskRequest;
use App\Http\Requests\UpdateSonhoTaskRequest;
use App\Models\Sonho;
use App\Models\SonhoTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SonhoTaskController extends Controller
{
    /**
     * Add a new task to the given dream's checklist.
     */
    public function store(StoreSonhoTaskRequest $request, Sonho $sonho): RedirectResponse|JsonResponse
    {
        $sonho->tasks()->create($request->validated());

        return $this->respondWith($request, $sonho);
    }

    /**
     * Update a task's completion state.
     */
    public function update(UpdateSonhoTaskRequest $request, Sonho $sonho, SonhoTask $tarefa): RedirectResponse|JsonResponse
    {
        abort_unless($tarefa->sonho_id === $sonho->id, 404);

        $tarefa->update($request->validated());

        return $this->respondWith($request, $sonho);
    }

    /**
     * Render the card + stats partials for a JSON (fetch) request, or fall
     * back to a plain redirect for a normal form submission.
     */
    private function respondWith(Request $request, Sonho $sonho): RedirectResponse|JsonResponse
    {
        if (! $request->wantsJson()) {
            return back();
        }

        $sonhos = $request->user()->sonhos()->with('tasks')->latest()->get();

        return response()->json([
            'card' => view('partials.sonho-card', ['sonho' => $sonho->fresh('tasks')])->render(),
            'stats' => view('partials.sonho-stats', ['stats' => Sonho::summarize($sonhos)])->render(),
        ]);
    }
}
