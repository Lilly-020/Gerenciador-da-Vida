<?php

namespace Database\Factories;

use App\Models\Investimento;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Investimento>
 */
class InvestimentoFactory extends Factory
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
            'name' => ucfirst($this->faker->words(2, true)),
            'type' => $this->faker->randomElement(array_keys(Investimento::TYPES)),
            'rate' => $this->faker->randomFloat(2, 6, 14),
            'rate_reference' => $this->faker->randomElement(['CDI', 'Selic', 'IPCA', 'Fixa']),
            'rate_period' => 'anual',
            'status' => Investimento::STATUS_ATIVO,
        ];
    }
}
