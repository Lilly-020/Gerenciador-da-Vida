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

    public const LABEL_COLORS = [
        '#baf3db', '#f8e6a0', '#fedec8', '#ffd5d2', '#dfd8fd',
        '#cce0ff', '#c6edfb', '#d3f1a7', '#fdd0ec', '#dcdfe4',
        '#4bce97', '#f5cd47', '#fea362', '#f87168', '#9f8fef',
        '#579dff', '#6cc3e0', '#94c748', '#e774bb', '#8590a2',
        '#1f845a', '#946f00', '#b65c02', '#c9372c', '#6e5dc6',
        '#0c66e4', '#227d9b', '#5b7f24', '#ae4787', '#626f86',
    ];

    public static function labelColorValue(string $color): string
    {
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            return strtolower($color);
        }

        return ['emerald' => '#4bce97', 'amber' => '#f5cd47', 'orange' => '#fea362', 'rose' => '#f87168', 'violet' => '#9f8fef', 'blue' => '#579dff', 'cyan' => '#6cc3e0'][$color] ?? '#9f8fef';
    }

    public function labelStyle(string $name): string
    {
        $color = collect($this->label_catalog ?? [])->firstWhere('name', $name)['color'] ?? 'violet';
        $hex = self::labelColorValue($color);
        $brightness = (hexdec(substr($hex, 1, 2)) * 299 + hexdec(substr($hex, 3, 2)) * 587 + hexdec(substr($hex, 5, 2)) * 114) / 1000;

        return 'background-color:'.$hex.';color:'.($brightness > 160 ? '#0f172a' : '#ffffff');
    }

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
