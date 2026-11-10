<?php

namespace App\Models;

use Database\Factories\LancamentoFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['path', 'original_name', 'size'])]
class LancamentoFile extends Model
{
    /** @use HasFactory<LancamentoFileFactory> */
    use HasFactory;

    /** @return BelongsTo<Lancamento, $this> */
    public function lancamento(): BelongsTo
    {
        return $this->belongsTo(Lancamento::class);
    }
}
