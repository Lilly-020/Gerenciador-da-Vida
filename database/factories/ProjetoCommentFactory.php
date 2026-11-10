<?php

namespace Database\Factories;

use App\Models\Projeto;
use App\Models\ProjetoComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjetoComment>
 */
class ProjetoCommentFactory extends Factory
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
            'body' => fake()->sentence(),
        ];
    }
}
