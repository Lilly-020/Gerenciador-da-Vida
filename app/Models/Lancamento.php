<?php

namespace App\Models;

use App\Support\Carteira;
use Carbon\Carbon;
use Database\Factories\LancamentoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

/**
 * A single Entrada or Saída (distinguished by `type`). Kept in one table
 * because they share almost every field and the dashboard needs to query
 * "income vs. expense" together.
 */
#[Fillable([
    'type', 'category', 'subcategory', 'description', 'amount', 'date',
    'payment_method', 'status', 'is_recurring_template', 'recurrence_period',
    'recurrence_day', 'parent_recurring_id', 'custo_fixo_id', 'notes', 'partes_conta',
])]
class Lancamento extends Model
{
    /** @use HasFactory<LancamentoFactory> */
    use HasFactory;

    public const TYPE_ENTRADA = 'entrada';

    public const TYPE_SAIDA = 'saida';

    public const STATUS_PREVISTO = 'previsto';

    public const STATUS_REALIZADO = 'realizado';

    /**
     * `partes_conta`: a divided expense's participant parts come out of
     * each participant's own account...
     */
    public const CONTA_PARTICIPANTE = 'participante';

    /**
     * ...or out of the carteira owner's, who then pays the whole amount.
     */
    public const CONTA_DONO = 'dono';

    /**
     * @var array<int, string>
     */
    public const RECURRENCE_PERIODS = ['semanal', 'mensal', 'anual'];

    /**
     * @var array<int, string>
     */
    public const INCOME_CATEGORIES = ['Salário', 'Freelance', 'Cliente', 'Venda', 'Rendimentos', 'Outros'];

    /**
     * @var array<int, string>
     */
    public const EXPENSE_CATEGORIES = [
        'Moradia', 'Alimentação', 'Transporte', 'Saúde', 'Educação',
        'Lazer', 'Assinaturas', 'Compras', 'Contas', 'Outros',
    ];

    /**
     * Categories whose spending counts as "essential" for the emergency
     * fund coverage calculation (a future addition).
     *
     * @var array<int, string>
     */
    public const ESSENTIAL_CATEGORIES = ['Moradia', 'Alimentação', 'Transporte', 'Saúde', 'Contas'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
            'is_recurring_template' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('owner', function (Builder $builder): void {
            if (Auth::check()) {
                $builder->where('user_id', Carteira::ownerId());
            }
        });

        static::creating(function (Lancamento $lancamento): void {
            $lancamento->user_id ??= Carteira::ownerId();
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
     * @return BelongsTo<CustoFixo, $this>
     */
    public function custoFixo(): BelongsTo
    {
        return $this->belongsTo(CustoFixo::class);
    }

    /**
     * @return BelongsTo<Lancamento, $this>
     */
    public function parentRecurring(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_recurring_id');
    }

    /**
     * @return HasMany<Lancamento, $this>
     */
    public function occurrences(): HasMany
    {
        return $this->hasMany(self::class, 'parent_recurring_id');
    }

    /**
     * @return HasMany<LancamentoParte, $this>
     */
    public function partes(): HasMany
    {
        return $this->hasMany(LancamentoParte::class);
    }

    /**
     * Lançamentos from other people's carteiras that carry a part for the
     * given user. That part belongs in the user's own account, so their
     * own Financeiro counts it alongside what is in their carteira.
     *
     * @return Builder<self>
     */
    public static function sharedWith(User $user): Builder
    {
        return self::withoutGlobalScope('owner')
            ->where('user_id', '!=', $user->id)
            ->whereHas('partes', fn (Builder $query) => $query->where('user_id', $user->id));
    }

    /**
     * Replace the participants' parts of this lançamento. The carteira's
     * owner never gets a row of their own: theirs is what is left over.
     *
     * @param  array<int|string, mixed>  $partes  amount per user id; empty means "not divided"
     */
    public function syncPartes(array $partes): void
    {
        $this->partes()->delete();

        foreach ($partes as $userId => $amount) {
            if ((int) $userId !== (int) $this->user_id) {
                $this->partes()->create(['user_id' => $userId, 'amount' => $amount]);
            }
        }

        $this->unsetRelation('partes');
    }

    public function isDivided(): bool
    {
        return $this->partes->isNotEmpty();
    }

    public function partesSaemDoDono(): bool
    {
        return $this->partes_conta === self::CONTA_DONO;
    }

    /**
     * The given user's part as the form shows it: a participant's own
     * part, or — for the carteira's owner — what the participants leave.
     */
    public function parteOf(User $user): float
    {
        if ((int) $this->user_id === (int) $user->id) {
            return round((float) $this->amount - (float) $this->partes->sum('amount'), 2);
        }

        return round((float) $this->partes->where('user_id', $user->id)->sum('amount'), 2);
    }

    /**
     * How much this lançamento adds to the given user's account. An
     * entrada is always the carteira owner's, in full — a participant on
     * it is who the owner received (part of) it from.
     */
    public function entradaFor(User $user): float
    {
        $isOwner = (int) $this->user_id === (int) $user->id;

        return $this->type === self::TYPE_ENTRADA && $isOwner ? (float) $this->amount : 0.0;
    }

    /**
     * How much this lançamento takes out of the given user's account.
     *
     * - Entrada: the part the owner received from a participant leaves
     *   that participant's account.
     * - Saída: each participant's part leaves their own account and the
     *   owner pays the rest — unless the parts were set to come out of the
     *   owner's account, who then pays everything.
     */
    public function saidaFor(User $user): float
    {
        $isOwner = (int) $this->user_id === (int) $user->id;

        if ($this->type === self::TYPE_ENTRADA) {
            return $isOwner ? 0.0 : $this->parteOf($user);
        }

        if ($this->partesSaemDoDono()) {
            return $isOwner ? (float) $this->amount : 0.0;
        }

        return $this->parteOf($user);
    }

    /**
     * @return array<int, string>
     */
    public static function categoriesFor(string $type): array
    {
        return $type === self::TYPE_ENTRADA ? self::INCOME_CATEGORIES : self::EXPENSE_CATEGORIES;
    }

    /**
     * Generate the next `$months` projected ("previsto") occurrences for a
     * recurring template. Idempotent-ish: only fills the gap beyond the
     * furthest occurrence that already exists.
     */
    public function generateUpcomingOccurrences(int $months = 12): void
    {
        if (! $this->is_recurring_template || ! $this->recurrence_day) {
            return;
        }

        $latest = $this->occurrences()->orderByDesc('date')->first()?->date ?? $this->date;
        $cursor = $latest->copy();

        for ($i = 0; $i < $months; $i++) {
            $cursor = match ($this->recurrence_period) {
                'semanal' => $cursor->copy()->addWeek(),
                'anual' => $cursor->copy()->addYear()->day(min($this->recurrence_day, $cursor->copy()->addYear()->daysInMonth)),
                default => $cursor->copy()->addMonthNoOverflow()->day(min($this->recurrence_day, $cursor->copy()->addMonthNoOverflow()->daysInMonth)),
            };

            // Built via make() + explicit property assignment (not create())
            // because `user_id` isn't mass-assignable, and this needs to
            // work even when called outside an authenticated request (e.g.
            // a future scheduled job) where the model's own creating()
            // hook has no Auth::id() to fall back on.
            $occurrence = $this->occurrences()->make([
                'type' => $this->type,
                'category' => $this->category,
                'subcategory' => $this->subcategory,
                'description' => $this->description,
                'amount' => $this->amount,
                'date' => $cursor->toDateString(),
                'payment_method' => $this->payment_method,
                'status' => self::STATUS_PREVISTO,
                'notes' => $this->notes,
            ]);
            $occurrence->user_id = $this->user_id;
            $occurrence->save();
        }
    }

    /**
     * SQLite stores `date`-cast columns as "Y-m-d 00:00:00", which sorts
     * *after* a bare "Y-m-d" string — pair a start-of-day string with an
     * end-of-day string so an upper bound of "today" doesn't exclude rows
     * dated exactly today.
     *
     * @return array{0: string, 1: string}
     */
    public static function dateRange(Carbon $start, Carbon $end): array
    {
        return [$start->toDateString(), $end->format('Y-m-d 23:59:59')];
    }
}
