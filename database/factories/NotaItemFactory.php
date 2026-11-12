<?php

namespace Database\Factories;

use App\Models\Nota;
use App\Models\NotaItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NotaItem> */
class NotaItemFactory extends Factory
{
    public function definition(): array
    {
        return ['nota_id' => Nota::factory(), 'text' => fake()->words(3, true), 'completed' => false, 'position' => 0];
    }
}
