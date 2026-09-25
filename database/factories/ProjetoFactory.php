<?php

namespace Database\Factories;

use App\Models\Projeto;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Projeto>
 */
class ProjetoFactory extends Factory
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
            'title' => ucfirst($this->faker->words(3, true)),
            'description' => $this->faker->sentence(),
            'starts_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'due_at' => $this->faker->dateTimeBetween('now', '+3 months'),
        ];
    }
}
