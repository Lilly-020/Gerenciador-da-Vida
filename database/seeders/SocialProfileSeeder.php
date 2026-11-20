<?php

namespace Database\Seeders;

use App\Models\SocialProfile;
use Illuminate\Database\Seeder;

class SocialProfileSeeder extends Seeder
{
    public function run(): void
    {
        SocialProfile::factory()->create();
    }
}
