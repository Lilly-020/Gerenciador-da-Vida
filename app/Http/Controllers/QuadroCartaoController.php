<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuadroCartaoRequest;
use App\Http\Requests\UpdateQuadroCartaoRequest;
use App\Models\CursoFile;
use App\Models\Quadro;
use App\Models\QuadroCartao;
use App\Models\QuadroComentario;
use App\Models\User;
use App\Notifications\QuadroCommentMention;
use App\Support\QuadroRichText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuadroCartaoController extends Controller
{
    public function store(StoreQuadroCartaoRequest $request, Quadro $quadro): JsonResponse
    {
        $card = DB::transaction(function () use ($request, $quadro): QuadroCartao {
            Quadro::whereKey($quadro->id)->lockForUpdate()->firstOrFail();

            $card = $quadro->cartoes()->create([...$request->validated(), 'position' => ($quadro->cartoes()->where('lista_id', $request->integer('lista_id'))->max('position') ?? -1) + 1]);
            $card->created_by = $request->user()->id;
            $card->recordActivity($request->user(), 'Criou o cartão.');
            $card->save();

            return $card;
        });

        return response()->json(['id' => $card->id]);
    }

    public function create(Request $request, Quadro $quadro): JsonResponse
    {
        abort_unless($quadro->canEdit($request->user()), 403);
        $data = $request->validate(['lista_id' => ['required', 'integer', Rule::exists('quadro_listas', 'id')->where('quadro_id', $quadro->id)]]);
        $quadro->load(['listas', 'user', 'membros.user']);

        return response()->json(['html' => view('partials.quadro-new-card', ['quadro' => $quadro, 'listaId' => (int) $data['lista_id']])->render()]);
    }

    public function show(Request $request, Quadro $quadro, QuadroCartao $cartao): JsonResponse
    {
        abort_unless($cartao->quadro_id === $quadro->id, 404);
        $quadro->load(['user', 'membros.user', 'listas']);
        $cartao->load(['comentarios.user', 'pinnedComentarios.user', 'curso.files']);

        return response()->json(['html' => view('partials.quadro-cartao-detail', ['quadro' => $quadro, 'cartao' => $cartao, 'canEdit' => $quadro->canEdit($request->user()), 'canManage' => $quadro->canManage($request->user()), 'unreadMentions' => $this->unreadMentions($request, $cartao)])->render()]);
    }

    public function update(UpdateQuadroCartaoRequest $request, Quadro $quadro, QuadroCartao $cartao): JsonResponse
    {
        DB::transaction(function () use ($request, $quadro, $cartao): void {
            Quadro::whereKey($quadro->id)->lockForUpdate()->firstOrFail();
            $cartao->refresh();
            $cartao->fill($request->validated());
            if ($cartao->isDirty('lista_id')) {
                $cartao->position = ($quadro->cartoes()->where('lista_id', $cartao->lista_id)->max('position') ?? -1) + 1;
            }
            $labels = ['title' => 'título', 'description' => 'observações', 'status' => 'status', 'labels' => 'etiquetas', 'assignee_ids' => 'responsáveis', 'due_at' => 'data de entrega', 'starts_on' => 'data de início', 'reminder_minutes' => 'lembrete', 'lista_id' => 'lista', 'checklist' => 'checklist', 'checklists' => 'checklists', 'location' => 'local', 'cover_file_id' => 'capa', 'cover_color' => 'cor da capa'];
            $changed = array_values(array_intersect_key($labels, $cartao->getDirty()));
            if ($changed !== []) {
                $cartao->recordActivity($request->user(), 'Atualizou: '.implode(', ', $changed).'.');
            }
            $cartao->save();
        });

        return response()->json(['saved' => true]);
    }

    public function move(Request $request, Quadro $quadro, QuadroCartao $cartao): JsonResponse
    {
        abort_unless($cartao->quadro_id === $quadro->id, 404);
        abort_unless($quadro->canEdit($request->user()), 403);
        $data = $request->validate([
            'lista_id' => ['required', 'integer', Rule::exists('quadro_listas', 'id')->where('quadro_id', $quadro->id)],
            'before_id' => ['nullable', 'integer', Rule::exists('quadro_cartoes', 'id')->where('quadro_id', $quadro->id)->where('lista_id', $request->integer('lista_id'))],
        ]);
        DB::transaction(function () use ($request, $quadro, $cartao, $data): void {
            Quadro::whereKey($quadro->id)->lockForUpdate()->firstOrFail();
            $ids = $quadro->cartoes()->where('lista_id', $data['lista_id'])->whereKeyNot($cartao->id)->orderBy('position')->orderBy('id')->pluck('id')->all();
            $index = isset($data['before_id']) ? array_search((int) $data['before_id'], $ids, true) : false;
            array_splice($ids, $index === false ? count($ids) : $index, 0, [$cartao->id]);
            foreach ($ids as $position => $id) {
                $quadro->cartoes()->whereKey($id)->update(['lista_id' => $data['lista_id'], 'position' => $position]);
            }
            $cartao->refresh();
            $cartao->recordActivity($request->user(), 'Moveu o cartão para '.$cartao->lista->title.'.');
            $cartao->save();
        });

        return response()->json(['saved' => true]);
    }

    public function destroy(Request $request, Quadro $quadro, QuadroCartao $cartao): JsonResponse
    {
        abort_unless($cartao->quadro_id === $quadro->id, 404);
        abort_unless($quadro->canEdit($request->user()), 403);
        $paths = collect($cartao->attachments ?? [])->pluck('path')->all();
        $cartao->delete();
        Storage::disk('local')->delete($paths);

        return response()->json(['saved' => true]);
    }

    public function comments(Request $request, Quadro $quadro, QuadroCartao $cartao): JsonResponse
    {
        abort_unless($cartao->quadro_id === $quadro->id, 404);
        $cartao->load(['comentarios.user', 'pinnedComentarios.user', 'curso.files']);
        $unreadMentions = $this->unreadMentions($request, $cartao);
        $revision = hash('sha256', $cartao->comentarios->toJson().json_encode([$cartao->activity, $cartao->attachments, $unreadMentions]));
        $data = ['revision' => $revision];
        if ($request->query('revision') !== $revision) {
            $data['unread_count'] = $request->user()->unreadNotifications()->count();
            $data['count'] = $cartao->comentarios->count();
            $data['html'] = view('partials.quadro-comments', ['quadro' => $quadro, 'cartao' => $cartao, 'canEdit' => $quadro->canEdit($request->user()), 'canManage' => $quadro->canManage($request->user()), 'unreadMentions' => $unreadMentions])->render();
            $data['history'] = view('partials.quadro-card-history', ['cartao' => $cartao])->render();
            $data['files'] = view('partials.quadro-files', ['quadro' => $quadro, 'cartao' => $cartao, 'canEdit' => $quadro->canEdit($request->user())])->render();
            $data['pinned'] = view('partials.quadro-pinned', ['quadro' => $quadro, 'cartao' => $cartao, 'canEdit' => $quadro->canEdit($request->user())])->render();
        }

        return response()->json($data)->header('Cache-Control', 'private, no-store');
    }

    public function comment(Request $request, Quadro $quadro, QuadroCartao $cartao): JsonResponse
    {
        abort_unless($cartao->quadro_id === $quadro->id, 404);
        abort_unless($quadro->canEdit($request->user()), 403);
        $data = $this->commentData($request, $quadro, $cartao);
        $comment = new QuadroComentario(['body' => $data['body'], 'user_id' => $request->user()->id]);
        $comment->body_html = $data['body_html'];
        $comment->file_ids = $data['file_ids'];
        DB::transaction(function () use ($quadro, $cartao, $comment, $data, $request): void {
            $cartao->comentarios()->save($comment);
            $this->notifyMentions($quadro, $cartao, $comment, $data['mention_ids'], $request->user());
        });

        return $this->comments($request, $quadro, $cartao);
    }

    public function updateComment(Request $request, Quadro $quadro, QuadroCartao $cartao, QuadroComentario $comentario): JsonResponse
    {
        abort_unless($cartao->quadro_id === $quadro->id && $comentario->cartao_id === $cartao->id, 404);
        abort_unless($quadro->canEdit($request->user()) && $comentario->user_id === $request->user()->id, 403);
        $data = $this->commentData($request, $quadro, $cartao);
        DB::transaction(function () use ($quadro, $cartao, $comentario, $data, $request): void {
            Quadro::whereKey($quadro->id)->lockForUpdate()->firstOrFail();
            $comentario->refresh();
            $comentario->body = $data['body'];
            $comentario->body_html = $data['body_html'];
            $comentario->file_ids = $data['file_ids'];
            if ($comentario->isDirty(['body', 'body_html', 'file_ids'])) {
                $comentario->edited_at = now();
                $comentario->save();
            }
            $this->notifyMentions($quadro, $cartao, $comentario, $data['mention_ids'], $request->user());
        });

        return $this->comments($request, $quadro, $cartao);
    }

    public function destroyComment(Request $request, Quadro $quadro, QuadroCartao $cartao, QuadroComentario $comentario): JsonResponse
    {
        abort_unless($cartao->quadro_id === $quadro->id && $comentario->cartao_id === $cartao->id, 404);
        abort_unless($quadro->canManage($request->user()) || ($quadro->canEdit($request->user()) && $comentario->user_id === $request->user()->id), 403);
        DB::transaction(function () use ($request, $quadro, $cartao, $comentario): void {
            Quadro::whereKey($quadro->id)->lockForUpdate()->firstOrFail();
            $comentario->delete();
            DatabaseNotification::where('type', QuadroCommentMention::class)->where('data->board_id', $quadro->id)->where('data->comment_id', $comentario->id)->delete();
            $cartao->refresh();
            $cartao->recordActivity($request->user(), 'Excluiu um comentário.');
            $cartao->save();
        });

        return $this->comments($request, $quadro, $cartao);
    }

    public function legacyFile(Request $request, Quadro $quadro, QuadroCartao $cartao, CursoFile $arquivo): StreamedResponse
    {
        abort_unless($cartao->quadro_id === $quadro->id && $cartao->legacy_curso_id === $arquivo->curso_id, 404);
        abort_unless(Storage::disk('public')->exists($arquivo->path), 404);

        $mime = Storage::disk('public')->mimeType($arquivo->path);
        abort_if($mime === 'image/svg+xml' || strtolower(pathinfo($arquivo->original_name, PATHINFO_EXTENSION)) === 'svg', 415);
        if ($request->boolean('preview') && in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'video/mp4', 'video/webm', 'video/quicktime', 'application/pdf'], true)) {
            return Storage::disk('public')->response($arquivo->path, $arquivo->original_name, ['Content-Type' => $mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'], 'inline')->setPrivate();
        }

        return Storage::disk('public')->download($arquivo->path, $arquivo->original_name, ['X-Content-Type-Options' => 'nosniff'])->setPrivate();
    }

    /** @return array{body: string, body_html: ?string, file_ids: list<string>, mention_ids: list<int>} */
    private function commentData(Request $request, Quadro $board, QuadroCartao $card): array
    {
        $data = $request->validate(['body' => ['required_without:rich_body', 'nullable', 'string', 'max:5000'], 'rich_body' => ['nullable', 'string', 'max:50000']]);
        if (! empty($data['rich_body'])) {
            $rich = app(QuadroRichText::class)->sanitize($data['rich_body'], $board, $card);
            abort_if($rich['text'] === '' && $rich['file_ids'] === [], 422, 'Escreva um comentário ou adicione uma imagem.');
            abort_if(mb_strlen($rich['text']) > 5000, 422, 'O comentário pode ter até 5.000 caracteres.');

            return ['body' => $rich['text'] ?: 'Imagem ou arquivo anexado', 'body_html' => $rich['html'], 'file_ids' => $rich['file_ids'], 'mention_ids' => $rich['mention_ids']];
        }

        return ['body' => $data['body'], 'body_html' => null, 'file_ids' => [], 'mention_ids' => []];
    }

    /** @return list<int> */
    private function unreadMentions(Request $request, QuadroCartao $card): array
    {
        return $request->user()->unreadNotifications()->where('type', QuadroCommentMention::class)->where('data->card_id', $card->id)->get()->map(fn ($notification): int => (int) $notification->data['comment_id'])->all();
    }

    /** @param list<int> $ids */
    private function notifyMentions(Quadro $board, QuadroCartao $card, QuadroComentario $comment, array $ids, User $author): void
    {
        foreach (User::whereIn('id', $ids)->whereKeyNot($author->id)->get() as $person) {
            if (! $person->notifications()->where('type', QuadroCommentMention::class)->where('data->comment_id', $comment->id)->exists()) {
                $person->notify(new QuadroCommentMention($board->id, $card->id, $comment->id, $card->title, $author->name));
            }
        }
    }

    public function readMention(Request $request, Quadro $quadro, QuadroCartao $cartao, QuadroComentario $comentario): JsonResponse
    {
        abort_unless($cartao->quadro_id === $quadro->id && $comentario->cartao_id === $cartao->id, 404);
        $request->user()->unreadNotifications()->where('type', QuadroCommentMention::class)->where('data->board_id', $quadro->id)->where('data->card_id', $cartao->id)->where('data->comment_id', $comentario->id)->update(['read_at' => now()]);

        return $this->comments($request, $quadro, $cartao);
    }

    public function pinComment(Request $request, Quadro $quadro, QuadroCartao $cartao, QuadroComentario $comentario): JsonResponse
    {
        abort_unless($cartao->quadro_id === $quadro->id && $comentario->cartao_id === $cartao->id, 404);
        abort_unless($quadro->canEdit($request->user()), 403);
        $data = $request->validate(['pinned' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $quadro, $cartao, $comentario, $data): void {
            Quadro::whereKey($quadro->id)->lockForUpdate()->firstOrFail();
            $comentario->refresh();
            if ($comentario->pinned !== $data['pinned']) {
                $comentario->pinned = $data['pinned'];
                $comentario->save();
                $cartao->refresh();
                $cartao->recordActivity($request->user(), $data['pinned'] ? 'Fixou um comentário.' : 'Desafixou um comentário.');
                $cartao->save();
            }
        });

        return $this->comments($request, $quadro, $cartao);
    }

    public function reactComment(Request $request, Quadro $quadro, QuadroCartao $cartao, QuadroComentario $comentario): JsonResponse
    {
        abort_unless($cartao->quadro_id === $quadro->id && $comentario->cartao_id === $cartao->id, 404);
        abort_unless($quadro->canEdit($request->user()), 403);
        DB::transaction(function () use ($request, $comentario): void {
            $comment = QuadroComentario::whereKey($comentario->id)->lockForUpdate()->firstOrFail();
            $reactions = $comment->reactions ?? [];
            $id = (string) $request->user()->id;
            if (isset($reactions[$id])) {
                unset($reactions[$id]);
            } else {
                $reactions[$id] = true;
            }
            $comment->reactions = $reactions;
            $comment->save();
        });

        return $this->comments($request, $quadro, $cartao);
    }
}
