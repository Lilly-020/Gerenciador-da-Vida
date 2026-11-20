<?php

namespace Database\Factories;

use App\Models\SocialProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SocialProfile> */
class SocialProfileFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'bio' => fake()->sentence()];
    }
}
