<?php

namespace Database\Factories;

use App\Models\QuadroCartao;
use App\Models\QuadroComentario;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuadroComentario>
 */
class QuadroComentarioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cartao_id' => QuadroCartao::factory(), 'user_id' => User::factory(), 'body' => fake()->sentence(),
        ];
    }
}
