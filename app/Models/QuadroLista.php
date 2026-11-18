<?php

namespace App\Models;

use Database\Factories\QuadroListaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'color', 'position'])]
class QuadroLista extends Model
{
    /** @use HasFactory<QuadroListaFactory> */
    use HasFactory;

    public function foregroundColor(): string
    {
        $brightness = (hexdec(substr($this->color, 1, 2)) * 299 + hexdec(substr($this->color, 3, 2)) * 587 + hexdec(substr($this->color, 5, 2)) * 114) / 1000;

        return $brightness > 160 ? '#0f172a' : '#f8fafc';
    }

    public function quadro(): BelongsTo
    {
        return $this->belongsTo(Quadro::class);
    }

    public function cartoes(): HasMany
    {
        return $this->hasMany(QuadroCartao::class, 'lista_id')->orderBy('position')->orderBy('id');
    }
}
