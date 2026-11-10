<?php

namespace App\Models;

use Database\Factories\ProjetoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

#[Fillable(['title', 'description', 'starts_at', 'due_at', 'details', 'priority', 'archived'])]
class Projeto extends Model
{
    /** @use HasFactory<ProjetoFactory> */
    use HasFactory;

    /**
     * What the project card needs loaded.
     *
     * @var array<int, string>
     */
    public const CARD_RELATIONS = ['tasks.assignee', 'user', 'participacoes.participant', 'comments.user', 'comments.task', 'activities.user', 'files'];

    protected $attributes = ['priority' => 'medium', 'archived' => false];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'due_at' => 'date',
            'archived' => 'boolean',
        ];
    }

    /**
     * Scope every query to the authenticated user's own projects, and
     * default new projects to belong to them.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('owner', function (Builder $builder): void {
            if (! Auth::check()) {
                return;
            }

            // Own projects, plus the ones the user accepted an invitation to.
            $builder->where(fn (Builder $query) => $query
                ->where('projetos.user_id', Auth::id())
                ->orWhereIn('projetos.id', Participacao::query()
                    ->select('projeto_id')
                    ->where('tipo', Participacao::TIPO_PROJETO)
                    ->where('participant_id', Auth::id())
                    ->where('status', Participacao::STATUS_ACEITO)));
        });

        static::creating(function (Projeto $projeto): void {
            $projeto->user_id ??= Auth::id();
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
     * @return HasMany<ProjetoTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(ProjetoTask::class);
    }

    /**
     * @return HasMany<Participacao, $this>
     */
    public function participacoes(): HasMany
    {
        return $this->hasMany(Participacao::class);
    }

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->user_id === (int) $user->id;
    }

    /**
     * The owner, or a participant who accepted an invitation that lets
     * them edit.
     */
    public function canBeEditedBy(User $user): bool
    {
        return $this->isOwnedBy($user) || $this->participacoes->contains(
            fn (Participacao $p): bool => (int) $p->participant_id === (int) $user->id
                && $p->status === Participacao::STATUS_ACEITO
                && $p->canEdit(),
        );
    }

    public function canBeManagedBy(User $user): bool
    {
        return $this->isOwnedBy($user) || $this->participacoes->contains(
            fn (Participacao $participacao): bool => (int) $participacao->participant_id === (int) $user->id
                && $participacao->status === Participacao::STATUS_ACEITO
                && $participacao->permission === Participacao::PERMISSION_ADMIN,
        );
    }

    /** @return HasMany<ProjetoComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(ProjetoComment::class)->latest('id')->limit(30);
    }

    /** @return HasMany<ProjetoActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(ProjetoActivity::class)->latest('id')->limit(50);
    }

    /** @return HasMany<ProjetoFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(ProjetoFile::class);
    }

    public function recordActivity(string $description): void
    {
        $this->activities()->create(['description' => $description, 'user_id' => Auth::id()]);
    }

    protected function overdue(): Attribute
    {
        return Attribute::make(get: fn (): bool => ! $this->archived && $this->status !== 'completed'
            && $this->due_at !== null && $this->due_at->lt(today()));
    }

    protected function financialSaved(): Attribute
    {
        return Attribute::make(get: fn (): float => (float) $this->tasks->where('goal_type', 'financial')
            ->sum(fn (ProjetoTask $task): float => min((float) $task->current_value, (float) $task->target_value)));
    }

    protected function financialProgress(): Attribute
    {
        return Attribute::make(get: function (): int {
            $tasks = $this->tasks->where('goal_type', 'financial');
            $target = $tasks->sum('target_value');

            return $target > 0 ? (int) floor($this->financial_saved / $target * 100) : 0;
        });
    }

    protected function progress(): Attribute
    {
        return Attribute::make(
            get: function (): int {
                $tasks = $this->tasks->where('goal_type', '!=', 'financial');
                $total = $tasks->count();

                if ($total === 0) {
                    return 0;
                }

                return (int) floor($tasks->avg('execution_progress'));
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
                $this->tasks->isNotEmpty() && $this->tasks->every(fn (ProjetoTask $task): bool => $task->is_finished) => 'completed',
                $this->tasks->contains(fn (ProjetoTask $task): bool => $task->execution_progress > 0) => 'in_progress',
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
     * Aggregate stats for a collection of projects, used for the analytics tiles.
     *
     * @param  Collection<int, Projeto>  $projetos
     * @return array{total: int, em_andamento: int, concluidos: int, progresso_medio: int}
     */
    public static function summarize(Collection $projetos): array
    {
        $projetos = $projetos->where('archived', false);

        return [
            'total' => $projetos->count(),
            'em_andamento' => $projetos->where('status', 'in_progress')->count(),
            'concluidos' => $projetos->where('status', 'completed')->count(),
            'progresso_medio' => $projetos->isEmpty() ? 0 : (int) round($projetos->avg('progress')),
        ];
    }
}
