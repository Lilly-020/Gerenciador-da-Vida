<?php

namespace Database\Factories;

use App\Models\CustoFixo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustoFixo>
 */
class CustoFixoFactory extends Factory
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
            'name' => $this->faker->randomElement(CustoFixo::CATEGORIES),
            'category' => $this->faker->randomElement(CustoFixo::CATEGORIES),
            'amount' => $this->faker->randomFloat(2, 30, 2000),
            'due_day' => $this->faker->numberBetween(1, 28),
            'periodicity' => 'mensal',
            'starts_on' => today()->startOfMonth(),
            'status' => CustoFixo::STATUS_ATIVO,
        ];
    }
}
