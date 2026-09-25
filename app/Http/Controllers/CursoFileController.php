<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCursoFileRequest;
use App\Models\Curso;
use App\Models\CursoFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CursoFileController extends Controller
{
    /**
     * Attach a new file (diploma, certificate, ...) to a course.
     */
    public function store(StoreCursoFileRequest $request, Curso $curso): RedirectResponse|JsonResponse
    {
        $file = $request->file('file');

        $curso->files()->create([
            'path' => $file->store('curso-files', 'public'),
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ]);

        return $this->respondWith($request, $curso);
    }

    /**
     * Remove an attached file from a course.
     */
    public function destroy(Request $request, Curso $curso, CursoFile $arquivo): RedirectResponse|JsonResponse
    {
        abort_unless($arquivo->curso_id === $curso->id, 404);

        Storage::disk('public')->delete($arquivo->path);
        $arquivo->delete();

        return $this->respondWith($request, $curso);
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
