<?php

namespace Database\Seeders;

use App\Models\QuadroLista;
use Illuminate\Database\Seeder;

class QuadroListaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        QuadroLista::factory()->create();
    }
}
