<?php

namespace Database\Seeders;

use App\Models\Quadro;
use Illuminate\Database\Seeder;

class QuadroSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Quadro::factory()->create();
    }
}
