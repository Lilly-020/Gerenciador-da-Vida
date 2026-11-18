<?php

namespace Database\Seeders;

use App\Models\QuadroMembro;
use Illuminate\Database\Seeder;

class QuadroMembroSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        QuadroMembro::factory()->create();
    }
}
