<?php

namespace Database\Factories;

use App\Models\Projeto;
use App\Models\ProjetoEtiqueta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjetoEtiqueta>
 */
class ProjetoEtiquetaFactory extends Factory
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
            'name' => fake()->unique()->words(2, true),
            'color' => 'violet',
            'selected' => true,
        ];
    }
}
