<?php

namespace Database\Seeders;

use App\Models\SocialPostMedia;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class SocialPostMediaSeeder extends Seeder
{
    public function run(): void
    {
        $path = 'social/example.png';
        if (! Storage::disk('local')->exists($path)) {
            Storage::disk('local')->put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jM1sAAAAASUVORK5CYII='));
        }
        SocialPostMedia::factory()->create(['file_path' => $path]);
    }
}
