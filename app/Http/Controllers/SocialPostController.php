<?php

namespace App\Http\Controllers;

use App\Models\SocialComment;
use App\Models\SocialPost;
use App\Models\SocialPostMedia;
use App\Models\User;
use App\Notifications\SocialNotification;
use App\Support\SocialAudience;
use App\Support\SocialImages;
use App\Support\SocialRichText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SocialPostController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content' => ['nullable', 'string', 'max:5000'],
            'content_html' => ['nullable', 'string', 'max:50000'],
            'visibility' => ['sometimes', 'in:friends'],
            'images' => ['sometimes', 'array', 'list', 'max:6'],
            'images.*' => ['required', SocialImages::rule()],
        ]);
        $content = $this->postContent($data, ! empty($data['images']));
        $paths = [];

        try {
            $post = DB::transaction(function () use ($request, $data, $content, &$paths): SocialPost {
                $post = SocialPost::create([
                    'user_id' => $request->user()->id,
                    ...$content,
                    'visibility' => 'friends',
                ]);
                foreach ($data['images'] ?? [] as $index => $image) {
                    $path = SocialImages::store($image);
                    $paths[] = $path;
                    $post->media()->create(['file_path' => $path, 'mime_type' => $image->getMimeType(), 'sort_order' => $index]);
                }

                return $post;
            });
        } catch (Throwable $failure) {
            Storage::disk('local')->delete($paths);
            throw $failure;
        }

        return response()->json(['id' => $post->id], 201);
    }

    public function update(Request $request, SocialPost $post): JsonResponse|Response
    {
        Gate::authorize('update', $post);
        $data = $request->validate([
            'content' => ['present', 'nullable', 'string', 'max:5000'],
            'content_html' => ['nullable', 'string', 'max:50000'],
            'visibility' => ['sometimes', 'in:friends'],
        ]);
        $content = $this->postContent($data, $post->media()->exists());
        $post->update($content);

        return array_key_exists('content_html', $data) ? response()->json($content) : response()->noContent();
    }

    /** @return array{content: ?string, content_html: ?string} */
    private function postContent(array $data, bool $allowsEmpty): array
    {
        $content = isset($data['content_html'])
            ? app(SocialRichText::class)->sanitize($data['content_html'])
            : ['content' => $data['content'] ?? null, 'content_html' => null];

        if (blank($content['content'])) {
            if (! $allowsEmpty) {
                throw ValidationException::withMessages(['content' => 'Escreva uma publicação ou adicione uma imagem.']);
            }

            return ['content' => null, 'content_html' => null];
        }

        if (mb_strlen($content['content']) > 5000) {
            throw ValidationException::withMessages(['content' => 'A publicação pode ter até 5.000 caracteres.']);
        }

        return $content;
    }

    public function destroy(SocialPost $post): Response
    {
        Gate::authorize('delete', $post);
        $post->delete();

        return response()->noContent();
    }

    public function media(SocialPostMedia $media): StreamedResponse
    {
        abort_unless($media->post, 404);
        Gate::authorize('view', $media->post);

        return SocialImages::response($media->file_path);
    }

    public function like(Request $request, SocialPost $post): JsonResponse
    {
        Gate::authorize('view', $post);
        $data = $request->validate(['liked' => ['sometimes', 'boolean']]);
        $viewer = $request->user();

        return DB::transaction(function () use ($post, $viewer, $data): JsonResponse {
            SocialAudience::lockPair($viewer->id, $post->user_id);
            $post = SocialPost::whereKey($post->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('view', $post);
            $alreadyLiked = $post->likes()->whereKey($viewer->id)->exists();
            $liked = $data['liked'] ?? ! $alreadyLiked;
            if ($liked && ! $alreadyLiked) {
                $post->likes()->attach($viewer->id);
                $this->notify($post->user, $viewer, $post, 'social_like', 'Nova curtida na sua publicação');
            } elseif (! $liked && $alreadyLiked) {
                $post->likes()->detach($viewer->id);
            }

            return response()->json(['liked' => (bool) $liked, 'likes_count' => $post->likes()->whereIn('users.id', SocialAudience::people($viewer)->select('users.id'))->count()]);
        }, 3);
    }

    public function comments(Request $request, SocialPost $post): JsonResponse
    {
        Gate::authorize('view', $post);
        $viewer = $request->user();
        $people = fn (Builder $query) => $query->whereIn('users.id', SocialAudience::people($viewer)->select('users.id'));
        $query = $post->comments()->whereHas('user', $people)
            ->with(['user:id,name', 'parent' => fn (BelongsTo $query) => $query->whereHas('user', $people)->with('user:id,name')]);

        $next = null;
        if ($request->boolean('preview')) {
            $candidates = $query->withCount(['reactors' => $people])->orderByDesc('reactors_count')
                ->orderByDesc('id')->limit(2)->get();
            $hasMore = $candidates->count() > 1;
            $comments = $candidates->take(1);
        } else {
            $page = $query->orderByDesc('id')->cursorPaginate(20);
            $comments = $page->getCollection();
            $next = $page->nextCursor()?->encode();
            $hasMore = $next !== null;
        }

        $summary = $this->commentReactions($comments->modelKeys(), $viewer);

        return response()->json([
            'items' => $comments->map(fn (SocialComment $comment): array => [
                'id' => $comment->id,
                'user' => ['id' => $comment->user->id, 'name' => $comment->user->name],
                'content' => $comment->content,
                'reply_to' => $comment->parent?->user->name,
                'when' => $comment->created_at->locale('pt_BR')->diffForHumans(),
                'editable' => $comment->user_id === $viewer->id,
                ...$summary[$comment->id],
            ]),
            'has_more' => $hasMore,
            'next' => $next,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function reactToComment(Request $request, SocialComment $comment): JsonResponse
    {
        Gate::authorize('view', $comment);
        $data = $request->validate(['reaction' => ['present', 'nullable', Rule::in(SocialComment::REACTIONS)]]);
        $viewer = $request->user();
        $key = ['comment_id' => $comment->id, 'user_id' => $viewer->id];
        if ($data['reaction'] === null) {
            DB::table('social_comment_reactions')->where($key)->delete();
        } else {
            DB::table('social_comment_reactions')->upsert(
                [[...$key, 'reaction' => $data['reaction']]],
                ['comment_id', 'user_id'],
                ['reaction'],
            );
        }

        return response()->json($this->commentReactions([$comment->id], $viewer)[$comment->id]);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{reactions: array<string, int>, my_reaction: ?string}>
     */
    private function commentReactions(array $ids, User $viewer): array
    {
        if ($ids === []) {
            return [];
        }

        $summary = array_fill_keys($ids, ['reactions' => array_fill_keys(SocialComment::REACTIONS, 0), 'my_reaction' => null]);
        $counts = DB::table('social_comment_reactions')->whereIn('comment_id', $ids)
            ->whereIn('user_id', SocialAudience::people($viewer)->select('users.id'))
            ->selectRaw('comment_id, reaction, count(*) as total, max(case when user_id = ? then 1 else 0 end) as mine', [$viewer->id])
            ->groupBy('comment_id', 'reaction')->get();

        foreach ($counts as $count) {
            $summary[$count->comment_id]['reactions'][$count->reaction] = (int) $count->total;
            if ($count->mine) {
                $summary[$count->comment_id]['my_reaction'] = $count->reaction;
            }
        }

        return $summary;
    }

    public function storeComment(Request $request, SocialPost $post): JsonResponse
    {
        Gate::authorize('view', $post);
        $data = $request->validate(['content' => ['required', 'string', 'max:2000'], 'parent_id' => ['nullable', 'integer', 'min:1']]);
        $viewer = $request->user();
        $comment = DB::transaction(function () use ($post, $viewer, $data): SocialComment {
            SocialAudience::lockPair($viewer->id, $post->user_id);
            $post = SocialPost::whereKey($post->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('view', $post);
            $parent = isset($data['parent_id'])
                ? $post->comments()->whereIn('user_id', SocialAudience::people($viewer)->select('users.id'))->findOrFail($data['parent_id'])
                : null;
            $comment = $post->comments()->create(['content' => $data['content'], 'parent_id' => $parent?->id, 'user_id' => $viewer->id]);
            if ($parent) {
                $this->notify($parent->user, $viewer, $post, 'social_reply', 'Nova resposta ao seu comentário');
            }
            if ($parent?->user_id !== $post->user_id) {
                $this->notify($post->user, $viewer, $post, 'social_comment', 'Novo comentário na sua publicação');
            }

            return $comment;
        }, 3);

        return response()->json(['id' => $comment->id], 201);
    }

    public function updateComment(Request $request, SocialComment $comment): Response
    {
        Gate::authorize('update', $comment);
        $comment->update($request->validate(['content' => ['required', 'string', 'max:2000']]));

        return response()->noContent();
    }

    public function destroyComment(SocialComment $comment): Response
    {
        Gate::authorize('delete', $comment);
        $comment->delete();

        return response()->noContent();
    }

    private function notify(User $recipient, User $actor, SocialPost $post, string $kind, string $title): void
    {
        if ($recipient->id !== $actor->id && SocialAudience::allows($recipient, $actor->id)
            && SocialAudience::posts($recipient)->whereKey($post->id)->exists()) {
            $recipient->notify(new SocialNotification([
                'kind' => $kind, 'title' => $title, 'actor_id' => $actor->id, 'post_id' => $post->id,
            ]));
        }
    }
}
