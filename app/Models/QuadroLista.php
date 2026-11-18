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
        $channels = array_map(function (int $offset): float {
            $channel = hexdec(substr($this->color, $offset, 2)) / 255;

            return $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
        }, [1, 3, 5]);
        $luminance = $channels[0] * 0.2126 + $channels[1] * 0.7152 + $channels[2] * 0.0722;

        return $luminance > 0.45 ? '#0f172a' : '#f8fafc';
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
