<?php

namespace Database\Factories;

use App\Models\SocialPost;
use App\Models\SocialPostMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SocialPostMedia> */
class SocialPostMediaFactory extends Factory
{
    public function definition(): array
    {
        return ['post_id' => SocialPost::factory(), 'file_path' => 'social/example.png', 'mime_type' => 'image/png', 'sort_order' => 0];
    }
}
