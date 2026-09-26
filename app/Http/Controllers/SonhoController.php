<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSonhoRequest;
use App\Http\Requests\UpdateSonhoRequest;
use App\Models\Sonho;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SonhoController extends Controller
{
    /**
     * Display the authenticated user's dreams, with progress analytics.
     */
    public function index(Request $request): View
    {
        $sonhos = $request->user()->sonhos()->with('tasks')->latest()->get();

        return view('pages.sonhos', [
            'sonhos' => $sonhos,
            'stats' => Sonho::summarize($sonhos),
        ]);
    }

    /**
     * Create a new dream for the authenticated user.
     */
    public function store(StoreSonhoRequest $request): RedirectResponse|JsonResponse
    {
        $sonho = $request->user()->sonhos()->create($request->validated());

        return $this->respondWith($request, $sonho);
    }

    /**
     * Update a dream's own details (its title).
     */
    public function update(UpdateSonhoRequest $request, Sonho $sonho): RedirectResponse|JsonResponse
    {
        $sonho->update($request->validated());

        return $this->respondWith($request, $sonho);
    }

    /**
     * Delete a dream and its checklist (tasks cascade-delete at the DB level).
     */
    public function destroy(Request $request, Sonho $sonho): RedirectResponse|JsonResponse
    {
        $sonhoId = $sonho->id;
        $sonho->delete();

        if (! $request->wantsJson()) {
            return back();
        }

        $sonhos = $request->user()->sonhos()->with('tasks')->latest()->get();

        return response()->json([
            'removeCardId' => "sonho-card-{$sonhoId}",
            'stats' => view('partials.sonho-stats', ['stats' => Sonho::summarize($sonhos)])->render(),
        ]);
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
            'card' => view('partials.sonho-card', ['sonho' => $sonho->load('tasks')])->render(),
            'stats' => view('partials.sonho-stats', ['stats' => Sonho::summarize($sonhos)])->render(),
        ]);
    }
}
