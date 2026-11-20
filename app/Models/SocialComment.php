<?php

namespace App\Models;

use Database\Factories\SocialCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'parent_id', 'content'])]
class SocialComment extends Model
{
    /** @use HasFactory<SocialCommentFactory> */
    use HasFactory, SoftDeletes;

    public const REACTIONS = ['like', 'love', 'support'];

    public function reactors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'social_comment_reactions', 'comment_id', 'user_id')
            ->withPivot('reaction');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class);
    }
}
