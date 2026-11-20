<?php

namespace Database\Factories;

use App\Models\SocialBlock;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SocialBlock> */
class SocialBlockFactory extends Factory
{
    public function definition(): array
    {
        return ['blocker_id' => User::factory(), 'blocked_id' => User::factory()];
    }
}
