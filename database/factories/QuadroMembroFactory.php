<?php

namespace Database\Factories;

use App\Models\Quadro;
use App\Models\QuadroMembro;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuadroMembro>
 */
class QuadroMembroFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quadro_id' => Quadro::factory(), 'user_id' => User::factory(), 'role' => 'editor', 'status' => 'accepted',
        ];
    }
}
