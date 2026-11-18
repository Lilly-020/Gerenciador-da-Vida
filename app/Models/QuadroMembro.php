<?php

namespace App\Models;

use Database\Factories\QuadroMembroFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'role', 'status'])]
class QuadroMembro extends Model
{
    /** @use HasFactory<QuadroMembroFactory> */
    use HasFactory;

    public function quadro(): BelongsTo
    {
        return $this->belongsTo(Quadro::class)->withoutGlobalScope('access');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
