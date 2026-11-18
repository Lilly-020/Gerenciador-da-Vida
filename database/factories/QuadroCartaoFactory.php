<?php

namespace Database\Factories;

use App\Models\Quadro;
use App\Models\QuadroCartao;
use App\Models\QuadroLista;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuadroCartao>
 */
class QuadroCartaoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quadro_id' => Quadro::factory(), 'lista_id' => fn (array $attributes) => QuadroLista::factory()->create(['quadro_id' => $attributes['quadro_id']])->id, 'title' => fake()->sentence(4), 'status' => 'undefined', 'labels' => [], 'assignee_ids' => [], 'position' => 0,
        ];
    }
}
