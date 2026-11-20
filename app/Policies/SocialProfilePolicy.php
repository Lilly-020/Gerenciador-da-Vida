<?php

namespace App\Policies;

use App\Models\SocialProfile;
use App\Models\User;
use App\Support\SocialAudience;
use Illuminate\Auth\Access\Response;

class SocialProfilePolicy
{
    public function view(User $user, SocialProfile $profile): Response
    {
        return SocialAudience::allows($user, $profile->user_id) ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, SocialProfile $profile): bool
    {
        return $user->id === $profile->user_id;
    }
}
