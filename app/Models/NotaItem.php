<?php

namespace App\Models;

use Database\Factories\NotaItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['text', 'completed', 'position'])]
class NotaItem extends Model
{
    /** @use HasFactory<NotaItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['completed' => 'boolean', 'position' => 'integer'];
    }

    /** @return BelongsTo<Nota, $this> */
    public function nota(): BelongsTo
    {
        return $this->belongsTo(Nota::class);
    }
}
