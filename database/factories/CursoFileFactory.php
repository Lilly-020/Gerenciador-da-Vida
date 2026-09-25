<?php

namespace Database\Factories;

use App\Models\Curso;
use App\Models\CursoFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CursoFile>
 */
class CursoFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'curso_id' => Curso::factory(),
            'path' => 'curso-files/'.$this->faker->uuid().'.pdf',
            'original_name' => $this->faker->word().'.pdf',
            'size' => $this->faker->numberBetween(10_000, 2_000_000),
        ];
    }
}
