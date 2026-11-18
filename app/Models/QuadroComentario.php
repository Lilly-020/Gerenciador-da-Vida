<?php

namespace App\Models;

use Database\Factories\QuadroComentarioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['body', 'user_id'])]
class QuadroComentario extends Model
{
    /** @use HasFactory<QuadroComentarioFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['edited_at' => 'datetime', 'file_ids' => 'array', 'pinned' => 'boolean', 'reactions' => 'array'];
    }

    public function cartao(): BelongsTo
    {
        return $this->belongsTo(QuadroCartao::class, 'cartao_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
