<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $communities = config('social.communities');
        DB::table('social_friendships')->orderBy('id')->chunkById(200, function (Collection $friendships) use ($communities): void {
            foreach ($friendships as $friendship) {
                $pair = ['user_low_id' => $friendship->user_low_id, 'user_high_id' => $friendship->user_high_id];
                if ($communities !== null) {
                    $community = $communities[$friendship->user_low_id] ?? null;
                    if (! is_string($community) || $community === '' || $community !== ($communities[$friendship->user_high_id] ?? null)) {
                        continue;
                    }
                }
                $blocked = DB::table('social_blocks')
                    ->whereIn('blocker_id', array_values($pair))->whereIn('blocked_id', array_values($pair))->exists();
                if ($blocked) {
                    continue;
                }

                $existing = DB::table('amizades')->where($pair)->first();
                if ($existing && ($existing->status === 'accepted'
                    || ($friendship->status !== 'accepted' && $existing->updated_at >= $friendship->updated_at))) {
                    continue;
                }

                $data = ['requester_id' => $friendship->requester_id, 'status' => $friendship->status, 'updated_at' => $friendship->updated_at];
                if ($existing) {
                    DB::table('amizades')->where('id', $existing->id)->update($data);
                } else {
                    DB::table('amizades')->insert([...$pair, ...$data, 'created_at' => $friendship->created_at]);
                }
            }
        });

        DB::table('notifications')->whereIn('data->kind', ['friend_request', 'friend_accepted'])
            ->whereNull('data->actor_id')->orderBy('id')->chunkById(200, function (Collection $notifications): void {
                foreach ($notifications as $notification) {
                    $data = json_decode($notification->data, true);
                    $friendship = isset($data['friendship_id']) ? DB::table('amizades')->find($data['friendship_id']) : null;
                    if (! $friendship || ! in_array((int) $notification->notifiable_id, [(int) $friendship->user_low_id, (int) $friendship->user_high_id], true)) {
                        continue;
                    }

                    $data['actor_id'] = (int) ((int) $friendship->user_low_id === (int) $notification->notifiable_id ? $friendship->user_high_id : $friendship->user_low_id);
                    DB::table('notifications')->where('id', $notification->id)->update(['data' => json_encode($data)]);
                }
            });
    }

    public function down(): void
    {
        // Merged friendships can be edited after import, so rollback retains the records.
    }
};
