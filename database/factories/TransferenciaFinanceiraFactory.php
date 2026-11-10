<?php

namespace Database\Factories;

use App\Models\ContaFinanceira;
use App\Models\TransferenciaFinanceira;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransferenciaFinanceira>
 */
class TransferenciaFinanceiraFactory extends Factory
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
            'source_id' => fn (array $attributes): int => ContaFinanceira::factory()->for(User::findOrFail($attributes['user_id']))->create()->id,
            'destination_id' => fn (array $attributes): int => ContaFinanceira::factory()->for(User::findOrFail($attributes['user_id']))->create()->id,
            'amount' => 100,
            'date' => today(),
        ];
    }
}
