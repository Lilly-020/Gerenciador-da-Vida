<?php

namespace App\Support;

use App\Models\SocialBlock;
use App\Models\SocialPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class SocialAudience
{
    public static function people(User $viewer): Builder
    {
        return self::communityPeople($viewer)
            ->whereNotIn('users.id', SocialBlock::where('blocker_id', $viewer->id)->select('blocked_id'))
            ->whereNotIn('users.id', SocialBlock::where('blocked_id', $viewer->id)->select('blocker_id'));
    }

    public static function communityPeople(User $viewer): Builder
    {
        $query = User::query();
        $communities = config('social.communities');
        if ($communities !== null) {
            $membership = $communities[$viewer->id] ?? null;
            $members = is_string($membership) && $membership !== ''
                ? array_keys(array_filter($communities, fn ($value): bool => $value === $membership))
                : [];
            $query->whereIn('users.id', [...$members, $viewer->id]);
        }

        return $query;
    }

    public static function allows(User $viewer, int $otherId): bool
    {
        return $viewer->id === $otherId || self::people($viewer)->whereKey($otherId)->exists();
    }

    public static function friendIds(User $user): Builder
    {
        return $user->friendships()->where('status', 'accepted')
            ->selectRaw('case when user_low_id = ? then user_high_id else user_low_id end', [$user->id]);
    }

    public static function posts(User $viewer): Builder
    {
        return SocialPost::query()
            ->whereIn('user_id', self::people($viewer)->select('users.id'))
            ->where(fn (Builder $query) => $query
                ->where('user_id', $viewer->id)
                ->orWhere(fn (Builder $friends) => $friends->whereIn('visibility', ['friends', 'public'])
                    ->whereIn('user_id', self::friendIds($viewer))));
    }

    /** @return array{user_low_id: int, user_high_id: int} */
    public static function pair(int $first, int $second): array
    {
        return ['user_low_id' => min($first, $second), 'user_high_id' => max($first, $second)];
    }

    public static function lockPair(int $first, int $second): void
    {
        User::whereKey([$first, $second])->orderBy('id')->lockForUpdate()->get(['id']);
    }
}
