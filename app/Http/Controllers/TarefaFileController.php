<?php

namespace App\Http\Controllers;

use App\Models\Tarefa;
use App\Models\TarefaFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TarefaFileController extends Controller
{
    public function store(Request $request, Tarefa $tarefa): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,txt,csv,doc,docx,xls,xlsx,ppt,pptx,zip']], [
            'file.max' => 'O arquivo pode ter até 10 MB.',
            'file.mimes' => 'Envie uma imagem, PDF, documento, planilha, apresentação ou ZIP.',
        ]);
        $file = $request->file('file');
        $path = $file->store('tarefa-files', 'local');
        try {
            $tarefa->files()->create([
                'path' => $path, 'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return response()->json(['html' => view('partials.tarefa-details', ['task' => $tarefa->load('files')])->render()]);
    }

    public function show(Request $request, Tarefa $tarefa, TarefaFile $arquivo): BinaryFileResponse|StreamedResponse
    {
        abort_unless($arquivo->tarefa_id === $tarefa->id, 404);
        if ($request->boolean('preview') && in_array($arquivo->mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return response()->file(Storage::disk('local')->path($arquivo->path), ['Content-Type' => $arquivo->mime_type, 'X-Content-Type-Options' => 'nosniff']);
        }

        return Storage::disk('local')->download($arquivo->path, $arquivo->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function destroy(Tarefa $tarefa, TarefaFile $arquivo): JsonResponse
    {
        abort_unless($arquivo->tarefa_id === $tarefa->id, 404);
        Storage::disk('local')->delete($arquivo->path);
        $arquivo->delete();

        return response()->json(['html' => view('partials.tarefa-details', ['task' => $tarefa->load('files')])->render()]);
    }
}
