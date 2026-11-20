<?php

namespace Database\Factories;

use App\Models\SocialPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SocialPost> */
class SocialPostFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'content' => fake()->paragraph(), 'visibility' => 'friends'];
    }
}
