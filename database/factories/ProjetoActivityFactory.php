<?php

namespace Database\Factories;

use App\Models\Projeto;
use App\Models\ProjetoActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjetoActivity>
 */
class ProjetoActivityFactory extends Factory
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
            'user_id' => User::factory(),
            'description' => fake()->sentence(),
        ];
    }
}
