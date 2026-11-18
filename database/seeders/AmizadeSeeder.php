<?php

namespace Database\Seeders;

use App\Models\Amizade;
use Illuminate\Database\Seeder;

class AmizadeSeeder extends Seeder
{
    public function run(): void
    {
        Amizade::factory()->count(3)->create();
    }
}
