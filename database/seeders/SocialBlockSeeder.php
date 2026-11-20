<?php

namespace Database\Seeders;

use App\Models\SocialBlock;
use Illuminate\Database\Seeder;

class SocialBlockSeeder extends Seeder
{
    public function run(): void
    {
        SocialBlock::factory()->create();
    }
}
