<?php

namespace App\Models;

use Database\Factories\CursoFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['path', 'original_name', 'size'])]
class CursoFile extends Model
{
    /** @use HasFactory<CursoFileFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Curso, $this>
     */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /**
     * Public URL to download/view the attached file.
     */
    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn (): string => Storage::disk('public')->url($this->path),
        );
    }
}
