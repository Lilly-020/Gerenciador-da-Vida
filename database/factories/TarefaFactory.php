<?php

namespace Database\Factories;

use App\Models\Tarefa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tarefa>
 */
class TarefaFactory extends Factory
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
            'date' => today(),
            'completed' => false,
        ];
    }

    /**
     * Indicate that the task is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => ['completed' => true]);
    }

    /**
     * Indicate the date the task belongs to.
     */
    public function onDate(string $date): static
    {
        return $this->state(fn (array $attributes): array => ['date' => $date]);
    }
}
