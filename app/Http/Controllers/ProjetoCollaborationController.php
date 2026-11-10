<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjetoCommentRequest;
use App\Http\Requests\StoreProjetoFileRequest;
use App\Models\Projeto;
use App\Models\ProjetoComment;
use App\Models\ProjetoFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjetoCollaborationController extends Controller
{
    public function comment(StoreProjetoCommentRequest $request, Projeto $projeto): RedirectResponse
    {
        $projeto->comments()->create([...$request->validated(), 'user_id' => $request->user()->id]);
        $projeto->recordActivity('Adicionou um comentário.');

        return back()->with('status', 'Comentário adicionado.');
    }

    public function destroyComment(Request $request, Projeto $projeto, ProjetoComment $comentario): RedirectResponse
    {
        abort_unless($comentario->projeto_id === $projeto->id, 404);
        abort_unless($projeto->canBeManagedBy($request->user()) || ($projeto->canBeEditedBy($request->user()) && $comentario->user_id === $request->user()->id), 403);
        $comentario->delete();
        $projeto->recordActivity('Excluiu um comentário.');

        return back();
    }

    public function upload(StoreProjetoFileRequest $request, Projeto $projeto): RedirectResponse
    {
        $file = $request->file('file');
        $path = $file->store('projeto-files', 'local');
        abort_unless($path, 500, 'Não foi possível salvar o arquivo.');
        $projeto->files()->create([
            'user_id' => $request->user()->id,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ]);
        $projeto->recordActivity('Anexou o arquivo “'.$file->getClientOriginalName().'”.');

        return back()->with('status', 'Arquivo anexado.');
    }

    public function download(Projeto $projeto, ProjetoFile $arquivo): StreamedResponse
    {
        abort_unless($arquivo->projeto_id === $projeto->id, 404);
        abort_unless(Storage::disk('local')->exists($arquivo->path), 404);

        return Storage::disk('local')->download($arquivo->path, $arquivo->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function destroyFile(Request $request, Projeto $projeto, ProjetoFile $arquivo): RedirectResponse
    {
        abort_unless($arquivo->projeto_id === $projeto->id, 404);
        abort_unless($projeto->canBeEditedBy($request->user()), 403);
        Storage::disk('local')->delete($arquivo->path);
        $projeto->recordActivity('Excluiu o anexo “'.$arquivo->original_name.'”.');
        $arquivo->delete();

        return back();
    }
}
