<?php

namespace Database\Factories;

use App\Models\FinanceiroActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinanceiroActivity>
 */
class FinanceiroActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'actor_id' => User::factory(),
            'description' => fake()->sentence(),
        ];
    }
}
