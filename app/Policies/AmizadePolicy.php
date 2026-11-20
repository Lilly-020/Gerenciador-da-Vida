<?php

namespace App\Policies;

use App\Models\Amizade;
use App\Models\User;
use App\Support\SocialAudience;
use Illuminate\Auth\Access\Response;

class AmizadePolicy
{
    public function delete(User $user, Amizade $friendship): Response
    {
        return in_array($user->id, [$friendship->user_low_id, $friendship->user_high_id], true)
            && SocialAudience::allows($user, $friendship->otherId($user->id))
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, Amizade $friendship): Response
    {
        if ($this->delete($user, $friendship)->denied() || $friendship->requester_id === $user->id) {
            return Response::denyAsNotFound();
        }

        return $friendship->status === 'pending' ? Response::allow() : Response::denyWithStatus(409);
    }
}
