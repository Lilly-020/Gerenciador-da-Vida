<?php

namespace Database\Factories;

use App\Models\Nota;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Nota> */
class NotaFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'title' => fake()->sentence(3), 'content' => fake()->paragraph()];
    }
}
