<?php

namespace App\Http\Controllers;

use App\Models\Amizade;
use App\Models\SocialBlock;
use App\Models\User;
use App\Support\Friendships;
use App\Support\SocialAudience;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SocialFriendshipController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $target = (int) $request->validate(['user_id' => ['required', 'integer', 'min:1']])['user_id'];
        $viewer = $request->user();
        abort_if($target === $viewer->id, 422, 'Você não pode adicionar a si mesmo.');

        return DB::transaction(function () use ($viewer, $target): JsonResponse {
            SocialAudience::lockPair($viewer->id, $target);
            $other = SocialAudience::people($viewer)->findOrFail($target);
            $pair = SocialAudience::pair($viewer->id, $target);
            $friendship = Amizade::firstOrNew($pair);
            abort_if($friendship->exists && $friendship->status !== 'declined', 409, 'Já existe uma solicitação ou amizade.');
            $friendship->fill(['requester_id' => $viewer->id, 'status' => 'pending'])->save();
            Friendships::notify($other, $viewer, $friendship);

            return response()->json(['id' => $friendship->id], 201);
        }, 3);
    }

    public function update(Request $request, Amizade $friendship): Response
    {
        $status = $request->validate(['status' => ['required', 'in:accepted,declined']])['status'];
        $viewer = $request->user();
        Gate::authorize('update', $friendship);

        DB::transaction(function () use ($friendship, $viewer, $status): void {
            SocialAudience::lockPair($friendship->user_low_id, $friendship->user_high_id);
            $friendship = Amizade::query()->findOrFail($friendship->id);
            Gate::authorize('update', $friendship);
            $friendship->update(['status' => $status]);
            Friendships::markRequestRead($viewer, $friendship);
            if ($status === 'accepted') {
                Friendships::notify(User::findOrFail($friendship->requester_id), $viewer, $friendship, accepted: true);
            }
        }, 3);

        return response()->noContent();
    }

    public function destroy(Amizade $friendship): Response
    {
        Gate::authorize('delete', $friendship);
        DB::transaction(function () use ($friendship): void {
            SocialAudience::lockPair($friendship->user_low_id, $friendship->user_high_id);
            $current = Amizade::findOrFail($friendship->id);
            Gate::authorize('delete', $current);
            Friendships::remove($current);
        }, 3);

        return response()->noContent();
    }

    public function block(Request $request, User $person): Response
    {
        $viewer = $request->user();
        abort_if($viewer->id === $person->id, 422);

        DB::transaction(function () use ($viewer, $person): void {
            SocialAudience::lockPair($viewer->id, $person->id);
            $block = ['blocker_id' => $viewer->id, 'blocked_id' => $person->id];
            if (SocialBlock::where($block)->exists()) {
                return;
            }
            abort_unless(SocialAudience::allows($viewer, $person->id), 404);
            SocialBlock::create($block);
            $friendship = Amizade::where(SocialAudience::pair($viewer->id, $person->id))->first();
            if ($friendship) {
                Friendships::remove($friendship);
            }
        }, 3);

        return response()->noContent();
    }

    public function unblock(Request $request, User $person): Response
    {
        SocialBlock::where('blocker_id', $request->user()->id)->where('blocked_id', $person->id)->delete();

        return response()->noContent();
    }
}
