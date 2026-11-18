<?php

namespace Database\Seeders;

use App\Models\QuadroComentario;
use Illuminate\Database\Seeder;

class QuadroComentarioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        QuadroComentario::factory()->create();
    }
}
