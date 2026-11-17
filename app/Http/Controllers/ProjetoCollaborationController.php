<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjetoCommentRequest;
use App\Http\Requests\StoreProjetoFileRequest;
use App\Http\Requests\UpdateProjetoCommentRequest;
use App\Models\Projeto;
use App\Models\ProjetoComment;
use App\Models\ProjetoFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjetoCollaborationController extends Controller
{
    public function comment(StoreProjetoCommentRequest $request, Projeto $projeto): RedirectResponse|JsonResponse
    {
        $projeto->comments()->create([...$request->validated(), 'user_id' => $request->user()->id]);
        $projeto->recordActivity('Adicionou um comentário.');

        return $request->wantsJson() ? $this->comments($request, $projeto) : back()->with('status', 'Comentário adicionado.');
    }

    public function destroyComment(Request $request, Projeto $projeto, ProjetoComment $comentario): RedirectResponse|JsonResponse
    {
        abort_unless($comentario->projeto_id === $projeto->id, 404);
        abort_unless($projeto->canBeManagedBy($request->user()) || ($projeto->canBeEditedBy($request->user()) && $comentario->user_id === $request->user()->id), 403);
        $comentario->delete();
        $projeto->recordActivity('Excluiu um comentário.');

        return $request->wantsJson() ? $this->comments($request, $projeto) : back();
    }

    public function updateComment(UpdateProjetoCommentRequest $request, Projeto $projeto, ProjetoComment $comentario): RedirectResponse|JsonResponse
    {
        $comentario->body = $request->validated('body');
        if ($comentario->isDirty('body')) {
            $comentario->edited_at = now();
            $comentario->save();
            $projeto->recordActivity('Editou um comentário.');
        }

        return $request->wantsJson() ? $this->comments($request, $projeto) : back()->with('status', 'Comentário atualizado.');
    }

    public function comments(Request $request, Projeto $projeto): JsonResponse
    {
        $projeto->load(['comments.user', 'comments.task', 'activities.user', 'participacoes']);
        $revision = hash('sha256', $projeto->comments->toJson().$projeto->activities->toJson().$projeto->participacoes->toJson());
        if ($request->query('revision') === $revision) {
            return response()->json(['revision' => $revision])->header('Cache-Control', 'private, no-store');
        }
        $data = ['projeto' => $projeto, 'canManage' => $projeto->canBeManagedBy($request->user()), 'canEdit' => $projeto->canBeEditedBy($request->user())];

        return response()->json([
            'revision' => $revision,
            'feed' => view('partials.projeto-comment-feed', $data)->render(),
            'history' => view('partials.projeto-history', $data)->render(),
            'count' => $projeto->comments->count(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function upload(StoreProjetoFileRequest $request, Projeto $projeto): RedirectResponse|JsonResponse
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

        return $request->wantsJson() ? $this->filesResponse($request, $projeto) : back()->with('status', 'Arquivo anexado.');
    }

    public function download(Projeto $projeto, ProjetoFile $arquivo): StreamedResponse
    {
        abort_unless($arquivo->projeto_id === $projeto->id, 404);
        abort_unless(Storage::disk('local')->exists($arquivo->path), 404);

        return Storage::disk('local')->download($arquivo->path, $arquivo->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function preview(Projeto $projeto, ProjetoFile $arquivo): BinaryFileResponse
    {
        abort_unless($arquivo->projeto_id === $projeto->id, 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($arquivo->path), 404);
        abort_unless($arquivo->isPreviewableImage() && in_array($disk->mimeType($arquivo->path), ['image/jpeg', 'image/png', 'image/webp'], true), 415);

        return response()->file($disk->path($arquivo->path), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ])->setContentDisposition('inline', $arquivo->original_name)->setPrivate();
    }

    public function destroyFile(Request $request, Projeto $projeto, ProjetoFile $arquivo): RedirectResponse|JsonResponse
    {
        abort_unless($arquivo->projeto_id === $projeto->id, 404);
        abort_unless($projeto->canBeEditedBy($request->user()), 403);
        Storage::disk('local')->delete($arquivo->path);
        $projeto->recordActivity('Excluiu o anexo “'.$arquivo->original_name.'”.');
        $arquivo->delete();

        return $request->wantsJson() ? $this->filesResponse($request, $projeto) : back();
    }

    private function filesResponse(Request $request, Projeto $projeto): JsonResponse
    {
        $projeto->load('files');

        return response()->json([
            'html' => view('partials.projeto-files', ['projeto' => $projeto, 'canEdit' => $projeto->canBeEditedBy($request->user())])->render(),
        ]);
    }
}
