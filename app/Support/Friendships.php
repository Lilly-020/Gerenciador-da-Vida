<?php

namespace App\Support;

use App\Models\Amizade;
use App\Models\Participacao;
use App\Models\Quadro;
use App\Models\QuadroMembro;
use App\Models\User;
use App\Notifications\SocialNotification;
use Illuminate\Database\Eloquent\Builder;

class Friendships
{
    public static function notify(User $recipient, User $actor, Amizade $friendship, bool $accepted = false): void
    {
        $recipient->notify(new SocialNotification([
            'kind' => $accepted ? 'friend_accepted' : 'friend_request',
            'friendship_id' => $friendship->id,
            'actor_id' => $actor->id,
            'actor' => $actor->name,
            'title' => $accepted ? 'Pedido de amizade aceito' : 'Pedido de amizade',
        ]));
    }

    public static function markRequestRead(User $recipient, Amizade $friendship): void
    {
        $recipient->unreadNotifications()->where('type', SocialNotification::class)
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $legacy) => $legacy->where('data->kind', 'friend_request')
                    ->where('data->friendship_id', $friendship->id))
                ->orWhere(fn (Builder $social) => $social->where('data->kind', 'social_request')
                    ->where('data->actor_id', $friendship->requester_id)))
            ->update(['read_at' => now()]);
    }

    public static function remove(Amizade $friendship): void
    {
        foreach ([[$friendship->user_low_id, $friendship->user_high_id], [$friendship->user_high_id, $friendship->user_low_id]] as [$owner, $friend]) {
            Participacao::where('owner_id', $owner)->where('participant_id', $friend)->whereIn('tipo', ['projeto', 'sonho', 'tarefa'])->delete();
            QuadroMembro::where('user_id', $friend)->whereIn('quadro_id', Quadro::withoutGlobalScopes()->where('user_id', $owner)->select('id'))->delete();
        }
        self::markRequestRead(User::findOrFail($friendship->otherId($friendship->requester_id)), $friendship);
        $friendship->delete();
    }
}
