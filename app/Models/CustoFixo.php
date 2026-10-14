<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\CustoFixoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

#[Fillable(['name', 'category', 'amount', 'due_day', 'periodicity', 'starts_on', 'ends_on', 'notes', 'status'])]
class CustoFixo extends Model
{
    /** @use HasFactory<CustoFixoFactory> */
    use HasFactory;

    public const STATUS_ATIVO = 'ativo';

    public const STATUS_INATIVO = 'inativo';

    /**
     * @var array<int, string>
     */
    public const PERIODICITIES = ['mensal', 'bimestral', 'trimestral', 'semestral', 'anual'];

    /**
     * How many months apart two consecutive charges of each periodicity are.
     *
     * @var array<string, int>
     */
    public const PERIODICITY_MONTHS = [
        'mensal' => 1,
        'bimestral' => 2,
        'trimestral' => 3,
        'semestral' => 6,
        'anual' => 12,
    ];

    /**
     * The fields that can be changed for a single month (see adjustFor());
     * the rest describe the cost's schedule and always apply to every month.
     *
     * @var array<int, string>
     */
    public const MONTHLY_FIELDS = ['name', 'category', 'amount', 'due_day', 'notes'];

    /**
     * @var array<int, string>
     */
    public const CATEGORIES = [
        'Aluguel', 'Condomínio', 'Internet', 'Telefone', 'Energia', 'Água',
        'Assinaturas', 'Plano de saúde', 'Financiamentos', 'Mensalidades', 'Outros',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('owner', function (Builder $builder): void {
            if (Auth::check()) {
                $builder->where('user_id', Auth::id());
            }
        });

        static::creating(function (CustoFixo $custoFixo): void {
            $custoFixo->user_id ??= Auth::id();
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
     * @return HasMany<Lancamento, $this>
     */
    public function lancamentos(): HasMany
    {
        return $this->hasMany(Lancamento::class);
    }

    /**
     * @return HasMany<CustoFixoAjuste, $this>
     */
    public function ajustes(): HasMany
    {
        return $this->hasMany(CustoFixoAjuste::class);
    }

    public function ajusteFor(Carbon $month): ?CustoFixoAjuste
    {
        return $this->ajustes->first(fn (CustoFixoAjuste $ajuste): bool => $ajuste->month->isSameMonth($month));
    }

    /**
     * This cost's MONTHLY_FIELDS as they stand in the given month: the
     * month's own ajuste if it has one, otherwise the cost's own values.
     *
     * @return array{name: string, category: string, amount: string, due_day: int, notes: ?string}
     */
    public function valuesFor(Carbon $month): array
    {
        return ($this->ajusteFor($month) ?? $this)->only(self::MONTHLY_FIELDS);
    }

    /**
     * Change this cost for the given month only. Values that end up
     * matching the cost's own just drop the month's ajuste.
     *
     * @param  array<string, mixed>  $values
     */
    public function adjustFor(Carbon $month, array $values): void
    {
        $values = [...$this->valuesFor($month), ...Arr::only($values, self::MONTHLY_FIELDS)];
        $ajuste = $this->ajusteFor($month);

        $matchesOwnValues = $values['name'] === $this->name
            && $values['category'] === $this->category
            && round((float) $values['amount'], 2) === round((float) $this->amount, 2)
            && (int) $values['due_day'] === (int) $this->due_day
            && (string) $values['notes'] === (string) $this->notes;

        if ($matchesOwnValues) {
            $ajuste?->delete();
        } elseif ($ajuste) {
            $ajuste->update($values);
        } else {
            $this->ajustes()->create([...$values, 'month' => $month->copy()->startOfMonth()->toDateString()]);
        }

        $this->unsetRelation('ajustes');
    }

    /**
     * Whether this fixed cost is within its own start/end window during the
     * given month — i.e. it has already started (starts_on is on or before
     * the month's end) and, if it has an end date, hasn't finished yet
     * (ends_on is on or after the month's start).
     */
    public function isActiveIn(Carbon $month): bool
    {
        if ($this->starts_on->gt($month->copy()->endOfMonth())) {
            return false;
        }

        if ($this->ends_on !== null && $this->ends_on->lt($month->copy()->startOfMonth())) {
            return false;
        }

        return true;
    }

    /**
     * Whether this fixed cost is actually charged during the given month:
     * within its start/end window (isActiveIn) AND lining up with its own
     * periodicity — e.g. a "trimestral" cost that started in January is
     * only due in January, April, July, October, not every month in
     * between.
     */
    public function isDueIn(Carbon $month): bool
    {
        if (! $this->isActiveIn($month)) {
            return false;
        }

        $monthsSinceStart = $this->starts_on->copy()->startOfMonth()
            ->diffInMonths($month->copy()->startOfMonth());

        return $monthsSinceStart % self::PERIODICITY_MONTHS[$this->periodicity] === 0;
    }

    /**
     * The first month, at or after `$from`, this cost is next due — or null
     * if that would fall after its end date (it won't be charged again).
     */
    public function nextDueMonth(Carbon $from): ?Carbon
    {
        $interval = self::PERIODICITY_MONTHS[$this->periodicity];
        $startMonth = $this->starts_on->copy()->startOfMonth();
        $cursor = $from->copy()->startOfMonth();

        if ($cursor->lt($startMonth)) {
            $cursor = $startMonth;
        } else {
            $remainder = $startMonth->diffInMonths($cursor) % $interval;

            if ($remainder !== 0) {
                $cursor = $cursor->addMonths($interval - $remainder);
            }
        }

        if ($this->ends_on !== null && $cursor->gt($this->ends_on->copy()->endOfMonth())) {
            return null;
        }

        return $cursor;
    }

    /**
     * Whether a payment (a linked, realizado Saída) exists within the
     * given month.
     */
    public function isPaidFor(Carbon $month): bool
    {
        return $this->lancamentos()
            ->where('status', Lancamento::STATUS_REALIZADO)
            ->whereBetween('date', Lancamento::dateRange($month->copy()->startOfMonth(), $month->copy()->endOfMonth()))
            ->exists();
    }

    /**
     * Record this month's payment as an actual Saída, linked back to this
     * fixed cost.
     */
    public function markPaid(Carbon $date, ?float $amount = null): Lancamento
    {
        $values = $this->valuesFor($date);

        $lancamento = $this->lancamentos()->make([
            'type' => Lancamento::TYPE_SAIDA,
            'category' => $values['category'],
            'description' => $values['name'],
            'amount' => $amount ?? $values['amount'],
            'date' => $date->toDateString(),
            'status' => Lancamento::STATUS_REALIZADO,
        ]);
        $lancamento->user_id = $this->user_id;
        $lancamento->save();

        return $lancamento;
    }
}
