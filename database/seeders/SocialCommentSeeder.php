<?php

namespace Database\Seeders;

use App\Models\SocialComment;
use Illuminate\Database\Seeder;

class SocialCommentSeeder extends Seeder
{
    public function run(): void
    {
        SocialComment::factory()->create();
    }
}
