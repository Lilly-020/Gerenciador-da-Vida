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
        $lancamento = $this->lancamentos()->make([
            'type' => Lancamento::TYPE_SAIDA,
            'category' => $this->category,
            'description' => $this->name,
            'amount' => $amount ?? $this->amount,
            'date' => $date->toDateString(),
            'status' => Lancamento::STATUS_REALIZADO,
        ]);
        $lancamento->user_id = $this->user_id;
        $lancamento->save();

        return $lancamento;
    }
}
