<?php

namespace App\Models;

use Database\Factories\SonhoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

#[Fillable(['title'])]
class Sonho extends Model
{
    /** @use HasFactory<SonhoFactory> */
    use HasFactory;

    /**
     * Scope every query to the authenticated user's own dreams, and default
     * new dreams to belong to them. This keeps one user from ever reading or
     * mutating another user's data, even if an id is guessed in the URL.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('owner', function (Builder $builder): void {
            if (Auth::check()) {
                $builder->where('user_id', Auth::id());
            }
        });

        static::creating(function (Sonho $sonho): void {
            $sonho->user_id ??= Auth::id();
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
     * @return HasMany<SonhoTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(SonhoTask::class);
    }

    /**
     * Percentage of tasks completed, from 0 to 100.
     */
    protected function progress(): Attribute
    {
        return Attribute::make(
            get: function (): int {
                $total = $this->tasks->count();

                if ($total === 0) {
                    return 0;
                }

                return (int) round($this->tasks->where('completed', true)->count() / $total * 100);
            },
        );
    }

    /**
     * One of `not_started`, `in_progress`, or `completed`.
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn (): string => match (true) {
                $this->progress === 100 && $this->tasks->isNotEmpty() => 'completed',
                $this->progress > 0 => 'in_progress',
                default => 'not_started',
            },
        );
    }

    /**
     * Label and color classes for the current status, ready for the view.
     *
     * @return array{label: string, dot: string, text: string}
     */
    public function statusMeta(): array
    {
        return match ($this->status) {
            'completed' => ['label' => 'Concluído', 'dot' => 'bg-emerald-400', 'text' => 'text-emerald-300'],
            'in_progress' => ['label' => 'Em andamento', 'dot' => 'bg-amber-400', 'text' => 'text-amber-300'],
            default => ['label' => 'Não iniciado', 'dot' => 'bg-slate-500', 'text' => 'text-slate-400'],
        };
    }

    /**
     * Aggregate stats for a collection of dreams, used for the analytics tiles.
     *
     * @param  Collection<int, Sonho>  $sonhos
     * @return array{total: int, em_andamento: int, concluidos: int, progresso_medio: int}
     */
    public static function summarize(Collection $sonhos): array
    {
        return [
            'total' => $sonhos->count(),
            'em_andamento' => $sonhos->where('status', 'in_progress')->count(),
            'concluidos' => $sonhos->where('status', 'completed')->count(),
            'progresso_medio' => $sonhos->isEmpty() ? 0 : (int) round($sonhos->avg('progress')),
        ];
    }
}
