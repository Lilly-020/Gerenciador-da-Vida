<?php

namespace App\Http\Controllers;

use App\Models\Amizade;
use App\Models\SocialBlock;
use App\Models\SocialPost;
use App\Models\SocialPostMedia;
use App\Models\SocialProfile;
use App\Models\User;
use App\Support\SocialAudience;
use App\Support\SocialImages;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SocialController extends Controller
{
    public function index(): View
    {
        return view('pages.social');
    }

    public function page(Request $request, User $person): View
    {
        Gate::authorize('view', $this->profileFor($person));

        return view('pages.social', ['person' => $person]);
    }

    public function feed(Request $request): JsonResponse
    {
        $viewer = $request->user();
        $data = $request->validate([
            'person' => ['nullable', 'integer', 'min:1'],
            'post' => ['nullable', 'integer', 'min:1'],
            'after' => ['nullable', 'integer', 'min:0'],
            'author' => ['nullable', 'in:friends,me'],
            'order' => ['nullable', 'in:recent,oldest'],
        ]);
        $query = SocialAudience::posts($viewer);
        if (isset($data['person'])) {
            abort_unless(SocialAudience::allows($viewer, (int) $data['person']), 404);
            $query->where('user_id', $data['person']);
        }
        if (isset($data['post'])) {
            $query->whereKey($data['post']);
        }
        match ($data['author'] ?? null) {
            'friends' => $query->where('user_id', '!=', $viewer->id),
            'me' => $query->where('user_id', $viewer->id),
            default => null,
        };
        $after = $data['after'] ?? null;
        $ascending = $after !== null || ($data['order'] ?? null) === 'oldest';
        if ($after !== null) {
            $query->where('id', '>', $after);
        }

        $people = fn (Builder $query) => $query->whereIn('users.id', SocialAudience::people($viewer)->select('users.id'));
        $posts = $query->with(['user:id,name', 'user.socialProfile:user_id,avatar_path', 'media'])
            ->withCount(['likes' => $people, 'comments' => fn (Builder $query) => $query->whereHas('user', $people)])
            ->withExists(['likes as liked' => fn (Builder $query) => $query->whereKey($viewer->id)])
            ->orderBy('id', $ascending ? 'asc' : 'desc')->cursorPaginate(15);
        abort_if(isset($data['post']) && $posts->isEmpty(), 404);

        return response()->json([
            'items' => $posts->getCollection()->map(fn (SocialPost $post): array => [
                'id' => $post->id,
                'author' => $this->personData($post->user),
                'content' => $post->content,
                'content_html' => $post->content_html,
                'visibility' => $post->visibility === 'private' ? 'private' : 'friends',
                'when' => $post->created_at->locale('pt_BR')->diffForHumans(),
                'editable' => $post->user_id === $viewer->id,
                'likes_count' => $post->likes_count,
                'comments_count' => $post->comments_count,
                'liked' => $post->liked,
                'images' => $post->media->map(fn (SocialPostMedia $media): string => route('social.media', $media, absolute: false)),
            ]),
            'next' => $posts->nextCursor()?->encode(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function people(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'type' => ['nullable', 'in:search,friends,requests,suggestions,blocked']]);
        $viewer = $request->user();
        $type = $data['type'] ?? 'search';
        $requests = $viewer->friendships()->whereIn('status', ['pending', 'accepted']);
        $pending = (clone $requests)->where('status', 'pending')
            ->selectRaw('case when user_low_id = ? then user_high_id else user_low_id end', [$viewer->id]);
        $people = SocialAudience::people($viewer)->where('users.id', '!=', $viewer->id);
        $people = match ($type) {
            'friends' => $people->whereIn('users.id', SocialAudience::friendIds($viewer)),
            'requests' => $people->whereIn('users.id', $pending),
            'suggestions' => $people->whereNotIn('users.id', SocialAudience::friendIds($viewer))->whereNotIn('users.id', $pending),
            'blocked' => SocialAudience::communityPeople($viewer)->whereIn('users.id', SocialBlock::where('blocker_id', $viewer->id)->select('blocked_id')),
            default => $people,
        };
        if (filled($data['q'] ?? null)) {
            $people->whereLike('name', '%'.$data['q'].'%');
        }
        $page = $people->with('socialProfile:user_id,avatar_path')->orderBy('name')->orderBy('id')->cursorPaginate(15);
        $ids = $page->getCollection()->modelKeys();
        $relationships = $requests->where(fn (Builder $query) => $query->whereIn('user_low_id', $ids)->orWhereIn('user_high_id', $ids))
            ->get()->keyBy(fn (Amizade $friendship): int => $friendship->otherId($viewer->id));

        return response()->json([
            'items' => $page->getCollection()->map(fn (User $person): array => [
                ...($type === 'blocked' ? ['id' => $person->id, 'name' => $person->name, 'avatar' => null] : $this->personData($person)),
                ...$this->relationship($viewer, $relationships->get($person->id)),
            ]),
            'next' => $page->nextCursor()?->encode(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function profile(Request $request, User $person): JsonResponse
    {
        $profile = $this->profileFor($person);
        Gate::authorize('view', $profile);
        $person->setRelation('socialProfile', $profile);
        $viewer = $request->user();
        $friendship = Amizade::where(SocialAudience::pair($viewer->id, $person->id))->first();

        return response()->json([
            ...$this->personData($person),
            ...$this->relationship($viewer, $friendship),
            'bio' => $profile->bio,
            'handle' => $person->apelido ? '@'.$person->apelido : null,
            'cover' => $profile->cover_path ? route('social.profile.image', [$person, 'cover'], absolute: false) : null,
            'friends_count' => SocialAudience::people($viewer)->whereIn('users.id', SocialAudience::friendIds($person))->count(),
            'posts_count' => SocialAudience::posts($viewer)->where('user_id', $person->id)->count(),
            'requests_count' => $viewer->is($person)
                ? $viewer->friendships()->where('status', 'pending')
                    ->whereIn('requester_id', SocialAudience::people($viewer)->whereKeyNot($viewer->id)->select('users.id'))->count()
                : null,
            'editable' => $viewer->id === $person->id,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function profileImage(User $person, string $image): StreamedResponse
    {
        $profile = $this->profileFor($person);
        Gate::authorize('view', $profile);

        return SocialImages::response($image === 'cover' ? $profile->cover_path : $profile->avatar_path);
    }

    public function notifications(Request $request): JsonResponse
    {
        $query = $request->user()->notifications()->reorder()->where(fn (Builder $query) => $query->where('data->kind', 'like', 'social_%')
            ->orWhereIn('data->kind', ['friend_request', 'friend_accepted']))
            ->whereIn('data->actor_id', SocialAudience::people($request->user())->select('users.id'));
        $unread = (clone $query)->whereNull('read_at')->count();
        $page = $query->orderByDesc('created_at')->orderByDesc('id')->cursorPaginate(15);

        return response()->json([
            'items' => $page->getCollection()->map(fn ($item): array => [
                'id' => $item->id, 'title' => $item->data['title'], 'read' => $item->read_at !== null,
                'when' => $item->created_at->locale('pt_BR')->diffForHumans(),
            ]),
            'next' => $page->nextCursor()?->encode(),
            'unread' => $unread,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function openNotification(Request $request, string $notification): RedirectResponse
    {
        $viewer = $request->user();
        $item = $viewer->notifications()->where(fn (Builder $query) => $query->where('data->kind', 'like', 'social_%')
            ->orWhereIn('data->kind', ['friend_request', 'friend_accepted']))->findOrFail($notification);
        abort_unless(SocialAudience::allows($viewer, (int) $item->data['actor_id']), 404);
        $item->markAsRead();

        if (isset($item->data['post_id']) && SocialAudience::posts($viewer)->whereKey($item->data['post_id'])->exists()) {
            return redirect()->route('social.index', ['post' => $item->data['post_id']]);
        }

        return redirect()->route('social.requests');
    }

    private function profileFor(User $person): SocialProfile
    {
        return SocialProfile::query()->firstOrNew(['user_id' => $person->id]);
    }

    /** @return array{id: int, name: string, avatar: ?string} */
    private function personData(User $person): array
    {
        return ['id' => $person->id, 'name' => $person->name, 'avatar' => $person->socialProfile?->avatar_path
            ? route('social.profile.image', [$person, 'avatar'], absolute: false) : null];
    }

    /** @return array{friendship_id: ?int, relationship: string} */
    private function relationship(User $viewer, ?Amizade $friendship): array
    {
        $state = match (true) {
            $friendship === null || $friendship->status === 'declined' => 'none',
            $friendship->status === 'accepted' => 'friends',
            $friendship->requester_id === $viewer->id => 'sent',
            default => 'received',
        };

        return ['friendship_id' => $state === 'none' ? null : $friendship->id, 'relationship' => $state];
    }
}
