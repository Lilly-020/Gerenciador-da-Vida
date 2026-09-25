<?php

namespace App\Models;

use Database\Factories\CursoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

#[Fillable(['title', 'platform', 'link', 'objective', 'status', 'starts_at', 'due_at'])]
class Curso extends Model
{
    /** @use HasFactory<CursoFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'due_at' => 'date',
        ];
    }

    /**
     * The Kanban columns, in display order, each with a label and dot color.
     *
     * @var array<string, array{label: string, dot: string}>
     */
    public const STATUSES = [
        'not_started' => ['label' => 'Não iniciado', 'dot' => 'bg-slate-500'],
        'in_progress' => ['label' => 'Em andamento', 'dot' => 'bg-amber-400'],
        'completed' => ['label' => 'Finalizado', 'dot' => 'bg-emerald-400'],
        'in_review' => ['label' => 'Em revisão', 'dot' => 'bg-sky-400'],
        'cancelled' => ['label' => 'Cancelado', 'dot' => 'bg-rose-400'],
    ];

    /**
     * Scope every query to the authenticated user's own courses, and
     * default new courses to belong to them.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('owner', function (Builder $builder): void {
            if (Auth::check()) {
                $builder->where('user_id', Auth::id());
            }
        });

        static::creating(function (Curso $curso): void {
            $curso->user_id ??= Auth::id();
            $curso->status ??= array_key_first(self::STATUSES);
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<CursoFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(CursoFile::class);
    }

    /**
     * Label and dot color for the current status, ready for the view.
     *
     * @return array{label: string, dot: string}
     */
    public function statusMeta(): array
    {
        return self::STATUSES[$this->status] ?? self::STATUSES['not_started'];
    }
}
