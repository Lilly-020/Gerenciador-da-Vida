<?php

namespace Database\Factories;

use App\Models\ContaFinanceira;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContaFinanceira>
 */
class ContaFinanceiraFactory extends Factory
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
            'name' => fake()->company(),
            'kind' => 'banco',
            'opening_balance' => 0,
            'opening_date' => '2020-01-01',
        ];
    }
}
