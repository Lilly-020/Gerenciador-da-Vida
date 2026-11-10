<?php

namespace App\Models;

use Database\Factories\CursoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

#[Fillable(['title', 'platform', 'link', 'objective', 'status', 'starts_at', 'due_at', 'category', 'progress_unit', 'total_lessons', 'completed_lessons', 'duration_hours', 'studied_hours', 'notes', 'rating', 'next_review_at'])]
class Curso extends Model
{
    /** @use HasFactory<CursoFactory> */
    use HasFactory;

    protected $attributes = ['progress_unit' => 'lessons', 'completed_lessons' => 0, 'studied_hours' => 0];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'due_at' => 'date',
            'next_review_at' => 'date',
            'completed_at' => 'datetime',
            'total_lessons' => 'integer',
            'completed_lessons' => 'integer',
            'duration_hours' => 'decimal:2',
            'studied_hours' => 'decimal:2',
            'rating' => 'integer',
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
        'paused' => ['label' => 'Pausado', 'dot' => 'bg-violet-400'],
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

        static::saving(function (Curso $curso): void {
            if ($curso->isDirty('status') && in_array($curso->status, ['completed', 'in_review'], true)) {
                $curso->completed_at ??= now();
                if ($curso->total_lessons) {
                    $curso->completed_lessons = $curso->total_lessons;
                }
            } elseif ($curso->isDirty(['total_lessons', 'completed_lessons', 'studied_hours'])
                && ! in_array($curso->status, ['paused', 'cancelled', 'in_review'], true)) {
                if ($curso->total_lessons && $curso->completed_lessons >= $curso->total_lessons) {
                    $curso->status = 'completed';
                    $curso->completed_at ??= now();
                } elseif ($curso->completed_lessons > 0 || $curso->studied_hours > 0) {
                    $curso->status = 'in_progress';
                } elseif ($curso->total_lessons) {
                    $curso->status = 'not_started';
                }
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

    protected function progress(): Attribute
    {
        return Attribute::make(get: fn (): int => $this->total_lessons > 0
            ? min(100, (int) floor($this->completed_lessons / $this->total_lessons * 100))
            : ($this->completed_at ? 100 : 0));
    }

    /**
     * @param  Collection<int, Curso>  $cursos
     * @return array{total: int, em_andamento: int, finalizados: int, horas: float}
     */
    public static function summarize(Collection $cursos): array
    {
        return [
            'total' => $cursos->count(),
            'em_andamento' => $cursos->where('status', 'in_progress')->count(),
            'finalizados' => $cursos->whereNotNull('completed_at')->count(),
            'horas' => (float) $cursos->sum('studied_hours'),
        ];
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
