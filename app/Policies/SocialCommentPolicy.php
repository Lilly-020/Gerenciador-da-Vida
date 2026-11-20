<?php

namespace App\Policies;

use App\Models\SocialComment;
use App\Models\User;
use App\Support\SocialAudience;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

class SocialCommentPolicy
{
    public function view(User $user, SocialComment $comment): Response
    {
        if (! SocialAudience::allows($user, $comment->user_id) || ! $comment->post) {
            return Response::denyAsNotFound();
        }

        return Gate::forUser($user)->inspect('view', $comment->post);
    }

    public function update(User $user, SocialComment $comment): Response
    {
        if ($comment->user_id !== $user->id) {
            return Response::denyAsNotFound();
        }

        return $this->view($user, $comment);
    }

    public function delete(User $user, SocialComment $comment): Response
    {
        return $this->update($user, $comment);
    }
}
