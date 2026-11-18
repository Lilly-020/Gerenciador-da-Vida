<?php

namespace Database\Factories;

use App\Models\Quadro;
use App\Models\QuadroLista;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuadroLista>
 */
class QuadroListaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quadro_id' => Quadro::factory(), 'title' => fake()->words(2, true), 'color' => '#334155', 'position' => 0,
        ];
    }
}
