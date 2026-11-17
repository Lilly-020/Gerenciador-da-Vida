<?php

namespace Database\Seeders;

use App\Models\ProjetoEtiqueta;
use Illuminate\Database\Seeder;

class ProjetoEtiquetaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ProjetoEtiqueta::factory()->count(3)->create();
    }
}
