<?php

namespace Database\Factories;

use App\Models\Sonho;
use App\Models\SonhoTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SonhoTask>
 */
class SonhoTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sonho_id' => Sonho::factory(),
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
