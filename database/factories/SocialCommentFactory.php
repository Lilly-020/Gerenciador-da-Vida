<?php

namespace Database\Factories;

use App\Models\SocialComment;
use App\Models\SocialPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SocialComment> */
class SocialCommentFactory extends Factory
{
    public function definition(): array
    {
        return ['post_id' => SocialPost::factory(), 'user_id' => User::factory(), 'content' => fake()->sentence()];
    }
}
