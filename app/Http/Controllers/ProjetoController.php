<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjetoRequest;
use App\Models\Projeto;
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
        $projetos = $request->user()->projetos()->with('tasks')->latest()->get();

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
     * Render the card + stats partials for a JSON (fetch) request, or fall
     * back to a plain redirect for a normal form submission.
     */
    private function respondWith(Request $request, Projeto $projeto): RedirectResponse|JsonResponse
    {
        if (! $request->wantsJson()) {
            return back();
        }

        $projetos = $request->user()->projetos()->with('tasks')->latest()->get();

        return response()->json([
            'card' => view('partials.projeto-card', ['projeto' => $projeto->load('tasks')])->render(),
            'stats' => view('partials.projeto-stats', ['stats' => Projeto::summarize($projetos)])->render(),
        ]);
    }
}
