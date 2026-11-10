<?php

namespace Database\Factories;

use App\Models\Projeto;
use App\Models\ProjetoFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjetoFile>
 */
class ProjetoFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'projeto_id' => Projeto::factory(),
            'user_id' => User::factory(),
            'path' => 'projeto-files/example.pdf',
            'original_name' => 'example.pdf',
            'size' => 1024,
        ];
    }
}
