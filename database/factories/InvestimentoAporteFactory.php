<?php

namespace Database\Factories;

use App\Models\Investimento;
use App\Models\InvestimentoAporte;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvestimentoAporte>
 */
class InvestimentoAporteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'investimento_id' => Investimento::factory(),
            'amount' => $this->faker->randomFloat(2, 100, 5000),
            'date' => today(),
        ];
    }

    public function onDate(string $date): static
    {
        return $this->state(fn (array $attributes): array => ['date' => $date]);
    }
}
