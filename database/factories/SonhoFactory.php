<?php

namespace Database\Factories;

use App\Models\Sonho;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sonho>
 */
class SonhoFactory extends Factory
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
            'title' => ucfirst($this->faker->words(3, true)),
        ];
    }
}
