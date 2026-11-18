<?php

namespace Database\Factories;

use App\Models\Amizade;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Amizade> */
class AmizadeFactory extends Factory
{
    public function definition(): array
    {
        return ['user_low_id' => User::factory(), 'user_high_id' => User::factory(), 'requester_id' => fn (array $attributes): int => $attributes['user_low_id'], 'status' => 'accepted'];
    }
}
