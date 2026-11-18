<?php

namespace App\Models;

use App\Support\Carteira;
use Carbon\Carbon;
use Database\Factories\LancamentoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * A single Entrada or Saída (distinguished by `type`). Kept in one table
 * because they share almost every field and the dashboard needs to query
 * "income vs. expense" together.
 */
#[Fillable([
    'type', 'category', 'subcategory', 'description', 'amount', 'date',
    'payment_method', 'status', 'is_recurring_template', 'recurrence_period',
    'recurrence_day', 'parent_recurring_id', 'notes', 'partes_conta',
    'account_id', 'expense_kind', 'recurrence_end', 'recurrence_count', 'recurrence_status', 'recurrence_active',
    'occurrence_key', 'installment_group', 'installment_number', 'installment_count',
])]
class Lancamento extends Model
{
    /** @use HasFactory<LancamentoFactory> */
    use HasFactory;

    public const TYPE_ENTRADA = 'entrada';

    public const TYPE_SAIDA = 'saida';

    public const STATUS_PREVISTO = 'previsto';

    public const STATUS_CANCELADO = 'cancelado';

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
            'recurrence_end' => 'date',
            'recurrence_active' => 'boolean',
            'recurrence_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('owner', function (Builder $builder): void {
            if (Auth::check()) {
                $builder->where('user_id', Carteira::ownerId());
                Carteira::scopeLancamentos($builder);
            }
        });

        static::creating(function (Lancamento $lancamento): void {
            $lancamento->user_id ??= Carteira::ownerId();
        });
    }

    /** @return BelongsTo<ContaFinanceira, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ContaFinanceira::class, 'account_id')->withTrashed();
    }

    /** @return HasMany<LancamentoFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(LancamentoFile::class);
    }

    protected function situation(): Attribute
    {
        return Attribute::make(get: fn (): string => match (true) {
            $this->status === self::STATUS_CANCELADO => 'Cancelado',
            $this->status === self::STATUS_REALIZADO => $this->type === self::TYPE_ENTRADA ? 'Recebido' : 'Pago',
            $this->date->lt(today()) => 'Atrasado',
            default => 'Pendente',
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
        return self::query()->whereRaw('1 = 0');
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
        return false;
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
        return (int) $this->user_id === (int) $user->id ? round((float) $this->amount, 2) : 0.0;
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

    public function setRecurrenceActive(bool $active): void
    {
        if (! $this->is_recurring_template) {
            return;
        }

        $template = self::withoutGlobalScope('owner')->whereKey($this->id)->lockForUpdate()->firstOrFail();
        if ($template->recurrence_active === $active) {
            return;
        }

        $template->update(['recurrence_active' => $active]);
        // On a child row, recurrence_active=false identifies a cancellation made by pausing the series.
        $template->occurrences()->whereDate('date', '>=', today())
            ->where('recurrence_active', ! $active)
            ->where('status', $active ? self::STATUS_CANCELADO : self::STATUS_PREVISTO)
            ->update([
                'recurrence_active' => $active,
                'status' => $active ? self::STATUS_PREVISTO : self::STATUS_CANCELADO,
            ]);

        if ($active) {
            $template->generateUpcomingOccurrences(10000, skipPast: true);
        }
    }

    /**
     * Generate the next `$months` projected ("previsto") occurrences for a
     * recurring template. Idempotent-ish: only fills the gap beyond the
     * furthest occurrence that already exists.
     */
    public function generateUpcomingOccurrences(int $months = 12, bool $skipPast = false): void
    {
        DB::transaction(function () use ($months, $skipPast): void {
            $template = self::withoutGlobalScope('owner')->whereKey($this->id)->lockForUpdate()->firstOrFail();
            if (! $template->is_recurring_template || ! $template->recurrence_day || $template->recurrence_active === false || $template->status === self::STATUS_CANCELADO) {
                return;
            }

            $latest = $template->occurrences()->orderByDesc('date')->first()?->date ?? $template->date;
            $generated = $template->occurrences()->count();
            $cursor = $latest->copy();
            $limit = today()->addYear()->endOfDay()->max($template->date->copy()->addYear());

            for ($index = 0; $index < $months; $index++) {
                if ($generated >= $months || ($template->recurrence_count && $generated >= $template->recurrence_count - 1)) {
                    break;
                }
                $cursor = match ($template->recurrence_period) {
                    'semanal' => $cursor->copy()->addWeek(),
                    'anual' => $cursor->copy()->addYearNoOverflow()->month($template->date->month)->day(1),
                    default => $cursor->copy()->startOfMonth()->addMonth(),
                };
                if ($template->recurrence_period !== 'semanal') {
                    $cursor->day(min($template->recurrence_day, $cursor->daysInMonth));
                }
                if ($cursor->gt($limit) || ($template->recurrence_end && $cursor->gt($template->recurrence_end))) {
                    break;
                }
                $date = $cursor->toDateString();
                $exists = $template->occurrences()->whereDate('date', $date)->exists();
                if ($exists) {
                    continue;
                }
                $occurrence = $template->occurrences()->make([
                    'type' => $template->type, 'category' => $template->category, 'subcategory' => $template->subcategory,
                    'description' => $template->description, 'amount' => $template->amount, 'date' => $date,
                    'payment_method' => $template->payment_method, 'status' => $skipPast && $cursor->isBefore(today()) ? self::STATUS_CANCELADO : ($template->recurrence_status ?? self::STATUS_PREVISTO),
                    'notes' => $template->notes, 'account_id' => $template->account_id, 'expense_kind' => $template->expense_kind ?? 'fixo',
                    'partes_conta' => $template->partes_conta, 'occurrence_key' => $template->id.':'.$date,
                ]);
                $occurrence->user_id = $template->user_id;
                $occurrence->save();
                $occurrence->syncPartes($template->partes->pluck('amount', 'user_id')->all());
                $generated++;
            }
        });
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
