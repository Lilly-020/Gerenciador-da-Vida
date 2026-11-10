<?php

namespace Database\Factories;

use App\Models\Lancamento;
use App\Models\LancamentoFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LancamentoFile>
 */
class LancamentoFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lancamento_id' => Lancamento::factory(),
            'path' => 'financeiro-comprovantes/example.pdf',
            'original_name' => 'example.pdf',
            'size' => 1024,
        ];
    }
}
