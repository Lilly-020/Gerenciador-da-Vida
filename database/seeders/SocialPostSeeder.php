<?php

namespace Database\Seeders;

use App\Models\SocialPost;
use Illuminate\Database\Seeder;

class SocialPostSeeder extends Seeder
{
    public function run(): void
    {
        SocialPost::factory()->create();
    }
}
