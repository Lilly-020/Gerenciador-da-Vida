<?php

namespace App\Models;

use Database\Factories\NotaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

#[Fillable(['title', 'content', 'color', 'pinned', 'archived'])]
class Nota extends Model
{
    /** @use HasFactory<NotaFactory> */
    use HasFactory;

    public const COLORS = [
        'slate' => ['label' => 'Neutra', 'class' => 'border-slate-600/40 bg-slate-900/70', 'dot' => 'bg-slate-400'],
        'violet' => ['label' => 'Violeta', 'class' => 'border-violet-400/25 bg-violet-500/10', 'dot' => 'bg-violet-400'],
        'amber' => ['label' => 'Amarela', 'class' => 'border-amber-400/25 bg-amber-500/10', 'dot' => 'bg-amber-400'],
        'emerald' => ['label' => 'Verde', 'class' => 'border-emerald-400/25 bg-emerald-500/10', 'dot' => 'bg-emerald-400'],
        'sky' => ['label' => 'Azul', 'class' => 'border-sky-400/25 bg-sky-500/10', 'dot' => 'bg-sky-400'],
        'rose' => ['label' => 'Rosa', 'class' => 'border-rose-400/25 bg-rose-500/10', 'dot' => 'bg-rose-400'],
    ];

    protected $attributes = ['color' => 'slate', 'pinned' => false, 'archived' => false];

    protected function casts(): array
    {
        return ['pinned' => 'boolean', 'archived' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('owner', function (Builder $builder): void {
            if (Auth::check()) {
                $builder->where('user_id', Auth::id());
            }
        });
        static::creating(function (Nota $nota): void {
            $nota->user_id ??= Auth::id();
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Tarefa, $this> */
    public function tarefa(): BelongsTo
    {
        return $this->belongsTo(Tarefa::class);
    }

    /** @return HasMany<NotaItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(NotaItem::class)->orderBy('position')->orderBy('id');
    }
}
