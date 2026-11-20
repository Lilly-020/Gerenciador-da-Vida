<?php

namespace App\Policies;

use App\Models\SocialPost;
use App\Models\User;
use App\Support\SocialAudience;
use Illuminate\Auth\Access\Response;

class SocialPostPolicy
{
    public function view(User $user, SocialPost $post): Response
    {
        return SocialAudience::posts($user)->whereKey($post->id)->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, SocialPost $post): Response
    {
        return $user->id === $post->user_id ? Response::allow() : Response::denyAsNotFound();
    }

    public function delete(User $user, SocialPost $post): Response
    {
        return $this->update($user, $post);
    }
}
