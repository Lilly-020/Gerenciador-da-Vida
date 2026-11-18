<?php

namespace Database\Seeders;

use App\Models\QuadroCartao;
use Illuminate\Database\Seeder;

class QuadroCartaoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        QuadroCartao::factory()->create();
    }
}
