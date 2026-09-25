<?php

namespace Database\Factories;

use App\Models\Lancamento;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lancamento>
 */
class LancamentoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement([Lancamento::TYPE_ENTRADA, Lancamento::TYPE_SAIDA]);

        return [
            'user_id' => User::factory(),
            'type' => $type,
            'category' => $this->faker->randomElement(Lancamento::categoriesFor($type)),
            'description' => ucfirst($this->faker->words(3, true)),
            'amount' => $this->faker->randomFloat(2, 20, 2000),
            'date' => today(),
            'status' => Lancamento::STATUS_REALIZADO,
        ];
    }

    public function entrada(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => Lancamento::TYPE_ENTRADA,
            'category' => $this->faker->randomElement(Lancamento::INCOME_CATEGORIES),
        ]);
    }

    public function saida(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => Lancamento::TYPE_SAIDA,
            'category' => $this->faker->randomElement(Lancamento::EXPENSE_CATEGORIES),
        ]);
    }

    public function previsto(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => Lancamento::STATUS_PREVISTO]);
    }

    public function onDate(string $date): static
    {
        return $this->state(fn (array $attributes): array => ['date' => $date]);
    }
}
