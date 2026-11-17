<?php

namespace App\Models;

use Database\Factories\ProjetoEtiquetaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'color', 'selected'])]
class ProjetoEtiqueta extends Model
{
    /** @use HasFactory<ProjetoEtiquetaFactory> */
    use HasFactory;

    /** @var list<array{name: string, color: string}> */
    public const DEFAULTS = [
        ['name' => 'Realizado', 'color' => 'emerald'],
        ['name' => 'Importante', 'color' => 'amber'],
        ['name' => 'Melhorias', 'color' => 'orange'],
        ['name' => 'Não urgente', 'color' => 'rose'],
        ['name' => 'Novo módulo', 'color' => 'violet'],
        ['name' => 'Urgente', 'color' => 'blue'],
        ['name' => 'Ideia', 'color' => 'cyan'],
    ];

    public const COLORS = [
        'emerald' => ['label' => 'Verde', 'class' => 'bg-emerald-400 text-emerald-950'],
        'amber' => ['label' => 'Amarela', 'class' => 'bg-amber-300 text-amber-950'],
        'orange' => ['label' => 'Laranja', 'class' => 'bg-orange-300 text-orange-950'],
        'rose' => ['label' => 'Vermelha', 'class' => 'bg-rose-400 text-rose-950'],
        'violet' => ['label' => 'Violeta', 'class' => 'bg-violet-400 text-violet-950'],
        'blue' => ['label' => 'Azul', 'class' => 'bg-blue-400 text-blue-950'],
        'cyan' => ['label' => 'Ciano', 'class' => 'bg-cyan-300 text-cyan-950'],
    ];

    protected $attributes = ['color' => 'violet', 'selected' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['selected' => 'boolean'];
    }

    /** @return BelongsTo<Projeto, $this> */
    public function projeto(): BelongsTo
    {
        return $this->belongsTo(Projeto::class);
    }
}
