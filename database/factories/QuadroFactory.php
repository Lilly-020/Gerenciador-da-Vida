<?php

namespace Database\Factories;

use App\Models\Quadro;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quadro>
 */
class QuadroFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(), 'title' => fake()->sentence(3), 'cover' => 'violet',
        ];
    }
}
