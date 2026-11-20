<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SocialImages
{
    public static function rule(): File
    {
        return File::types(['jpg', 'jpeg', 'png', 'webp'])->max('5mb');
    }

    public static function store(UploadedFile $image): string
    {
        $path = $image->store('social', 'local');
        if (! $path) {
            throw ValidationException::withMessages(['images' => 'Não foi possível guardar a imagem.']);
        }

        return $path;
    }

    public static function response(?string $path): StreamedResponse
    {
        $disk = Storage::disk('local');
        abort_unless($path && $disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
