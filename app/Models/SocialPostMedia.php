<?php

namespace App\Models;

use Database\Factories\SocialPostMediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['file_path', 'mime_type', 'sort_order'])]
class SocialPostMedia extends Model
{
    /** @use HasFactory<SocialPostMediaFactory> */
    use HasFactory;

    protected $table = 'social_post_media';

    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class);
    }
}
