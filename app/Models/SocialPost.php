<?php

namespace App\Models;

use Database\Factories\SocialPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'content', 'content_html', 'visibility'])]
class SocialPost extends Model
{
    /** @use HasFactory<SocialPostFactory> */
    use HasFactory, SoftDeletes;

    public function media(): HasMany
    {
        return $this->hasMany(SocialPostMedia::class, 'post_id')->orderBy('sort_order');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(SocialComment::class, 'post_id');
    }

    public function likes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'social_post_likes', 'post_id', 'user_id')->withTimestamps();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
