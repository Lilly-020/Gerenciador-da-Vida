<?php

namespace Database\Factories;

use App\Models\Projeto;
use App\Models\ProjetoTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjetoTask>
 */
class ProjetoTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'projeto_id' => Projeto::factory(),
            'title' => ucfirst($this->faker->words(3, true)),
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
}
