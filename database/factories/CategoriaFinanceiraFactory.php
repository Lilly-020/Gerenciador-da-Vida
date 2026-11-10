<?php

namespace Database\Factories;

use App\Models\CategoriaFinanceira;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoriaFinanceira>
 */
class CategoriaFinanceiraFactory extends Factory
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
            'name' => fake()->unique()->word(),
            'type' => 'saida',
        ];
    }
}
