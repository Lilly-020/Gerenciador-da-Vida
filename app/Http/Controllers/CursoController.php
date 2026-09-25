<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCursoRequest;
use App\Http\Requests\UpdateCursoRequest;
use App\Http\Requests\UpdateCursoStatusRequest;
use App\Models\Curso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CursoController extends Controller
{
    /**
     * Display the authenticated user's courses as a Kanban board.
     */
    public function index(Request $request): View
    {
        $cursos = $request->user()->cursos()->with('files')->latest()->get()->groupBy('status');

        return view('pages.cursos', [
            'columns' => Curso::STATUSES,
            'cursos' => $cursos,
        ]);
    }

    /**
     * Create a new course for the authenticated user.
     */
    public function store(StoreCursoRequest $request): RedirectResponse|JsonResponse
    {
        $curso = $request->user()->cursos()->create($request->validated());

        return $this->respondWith($request, $curso);
    }

    /**
     * Replace a course's editable fields (title, platform, link, objective, dates).
     */
    public function update(UpdateCursoRequest $request, Curso $curso): RedirectResponse|JsonResponse
    {
        $curso->update($request->validated());

        return $this->respondWith($request, $curso);
    }

    /**
     * Move a course to a different Kanban column.
     */
    public function move(UpdateCursoStatusRequest $request, Curso $curso): RedirectResponse|JsonResponse
    {
        $curso->update($request->validated());

        return $this->respondWith($request, $curso);
    }

    /**
     * Delete a course, along with its attached files.
     */
    public function destroy(Request $request, Curso $curso): RedirectResponse|JsonResponse
    {
        foreach ($curso->files as $file) {
            Storage::disk('public')->delete($file->path);
        }

        $curso->delete();

        if (! $request->wantsJson()) {
            return back();
        }

        return response()->json(['deleted' => true]);
    }

    /**
     * Render the card partial for a JSON (fetch) request, or fall back to
     * a plain redirect for a normal form submission.
     */
    private function respondWith(Request $request, Curso $curso): RedirectResponse|JsonResponse
    {
        if (! $request->wantsJson()) {
            return back();
        }

        return response()->json([
            'card' => view('partials.curso-card', ['curso' => $curso->load('files')])->render(),
        ]);
    }
}
