<?php

namespace App\Models;

use Database\Factories\QuadroFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

#[Fillable(['title', 'category', 'cover', 'cover_path'])]
class Quadro extends Model
{
    /** @use HasFactory<QuadroFactory> */
    use HasFactory;

    public const COVERS = ['violet' => 'Violeta e rosa', 'navy' => 'Azul escuro', 'sunset' => 'Pôr do sol', 'ocean' => 'Oceano', 'forest' => 'Floresta', 'slate' => 'Grafite', 'blue' => 'Azul', 'sky' => 'Céu', 'indigo' => 'Índigo', 'purple' => 'Roxo', 'lilac' => 'Lilás', 'rose' => 'Rosa'];

    protected static function booted(): void
    {
        static::creating(function (Quadro $board): void {
            $board->label_catalog ??= collect(ProjetoEtiqueta::DEFAULTS)->map(fn (array $label): array => [...$label, 'id' => (string) Str::uuid()])->all();
        });
        static::addGlobalScope('access', function (Builder $query): void {
            if (Auth::check()) {
                $query->where(fn (Builder $q) => $q->where('quadros.user_id', Auth::id())->orWhereIn('quadros.id', QuadroMembro::where('user_id', Auth::id())->where('status', 'accepted')->select('quadro_id')));
            }
        });
    }

    protected function casts(): array
    {
        return ['label_catalog' => 'array'];
    }

    public function labelClass(string $name): string
    {
        $color = collect($this->label_catalog ?? [])->firstWhere('name', $name)['color'] ?? 'violet';

        return ProjetoEtiqueta::COLORS[$color]['class'] ?? ProjetoEtiqueta::COLORS['violet']['class'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function listas(): HasMany
    {
        return $this->hasMany(QuadroLista::class)->orderBy('position')->orderBy('id');
    }

    public function cartoes(): HasMany
    {
        return $this->hasMany(QuadroCartao::class);
    }

    public function membros(): HasMany
    {
        return $this->hasMany(QuadroMembro::class);
    }

    public function roleFor(User $user): ?string
    {
        return $this->user_id === $user->id ? 'owner' : $this->membros->first(fn (QuadroMembro $m): bool => $m->user_id === $user->id && $m->status === 'accepted')?->role;
    }

    public function canEdit(User $user): bool
    {
        return in_array($this->roleFor($user), ['owner', 'admin', 'editor'], true);
    }

    public function canManage(User $user): bool
    {
        return in_array($this->roleFor($user), ['owner', 'admin'], true);
    }
}
