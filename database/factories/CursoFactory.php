<?php

namespace Database\Factories;

use App\Models\Curso;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Curso>
 */
class CursoFactory extends Factory
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
            'platform' => $this->faker->randomElement(['Udemy', 'Alura', 'YouTube', 'Coursera']),
            'link' => $this->faker->url(),
            'objective' => $this->faker->sentence(),
            'status' => array_key_first(Curso::STATUSES),
            'starts_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'due_at' => $this->faker->dateTimeBetween('now', '+3 months'),
        ];
    }

    /**
     * Indicate the course is in a specific Kanban column.
     */
    public function status(string $status): static
    {
        return $this->state(fn (array $attributes): array => ['status' => $status]);
    }
}
