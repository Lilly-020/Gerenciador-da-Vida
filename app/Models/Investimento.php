<?php

namespace App\Models;

use App\Support\Carteira;
use Carbon\Carbon;
use Database\Factories\InvestimentoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

#[Fillable([
    'name', 'institution', 'type', 'rate', 'rate_reference',
    'rate_period', 'liquidity', 'maturity_date', 'notes', 'status',
    'rate_mode', 'reference_rate', 'ir_rate', 'iof_rate', 'estimated_fees', 'reported_value', 'reported_at',
])]
class Investimento extends Model
{
    /** @use HasFactory<InvestimentoFactory> */
    use HasFactory;

    public const STATUS_ATIVO = 'ativo';

    public const STATUS_ENCERRADO = 'encerrado';

    /**
     * @var array<string, string>
     */
    public const TYPES = [
        'tesouro_selic' => 'Tesouro Selic',
        'cdb' => 'CDB',
        'lci' => 'LCI',
        'lca' => 'LCA',
        'fundo' => 'Fundo de investimento',
        'acoes' => 'Ações',
        'etfs' => 'ETFs',
        'cripto' => 'Criptomoedas',
        'outro' => 'Outro',
    ];

    /**
     * @var array<int, string>
     */
    public const RATE_PERIODS = ['mensal', 'anual'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'maturity_date' => 'date', 'reference_rate' => 'decimal:4',
            'ir_rate' => 'decimal:2', 'iof_rate' => 'decimal:2', 'estimated_fees' => 'decimal:2',
            'reported_value' => 'decimal:2', 'reported_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('owner', function (Builder $builder): void {
            if (Auth::check()) {
                $builder->where('user_id', Carteira::ownerId());
            }
        });

        static::creating(function (Investimento $investimento): void {
            $investimento->user_id ??= Carteira::ownerId();
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
     * @return HasMany<InvestimentoAporte, $this>
     */
    public function aportes(): HasMany
    {
        return $this->hasMany(InvestimentoAporte::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Sum of every contribution — never a single stored "total invested"
     * figure, since that would hide when each amount actually went in.
     */
    public function totalAportado(): float
    {
        return (float) $this->aportes->where('kind', '!=', 'resgate')->filter(fn (InvestimentoAporte $aporte): bool => $aporte->date->lte(today()))->sum('amount');
    }

    /**
     * Estimated compound-interest yield accrued up to `$asOf`, summed
     * across every contribution individually (each has its own elapsed
     * time). Always an estimate — never a guaranteed figure.
     */
    public function rendimentoEstimado(?Carbon $asOf = null): float
    {
        return round($this->aportesWithParentLoaded()->sum(fn (InvestimentoAporte $aporte): float => ($aporte->kind === 'resgate' ? -1 : 1) * $aporte->estimatedYield($asOf)), 2);
    }

    /**
     * Estimated yield generated strictly between two dates (e.g. "this
     * month"), by diffing the cumulative estimate at each end.
     */
    public function rendimentoEntre(Carbon $start, Carbon $end): float
    {
        return round(
            $this->aportesWithParentLoaded()->sum(
                fn (InvestimentoAporte $aporte): float => ($aporte->kind === 'resgate' ? -1 : 1) * ($aporte->estimatedYield($end) - $aporte->estimatedYield($start->copy()->subDay()))
            ),
            2
        );
    }

    /**
     * The loaded `aportes` collection, with this investment set as each
     * one's inverse relation so `estimatedYield()` doesn't trigger a fresh
     * query per contribution.
     *
     * @return Collection<int, InvestimentoAporte>
     */
    private function aportesWithParentLoaded(): Collection
    {
        foreach ($this->aportes as $aporte) {
            if (! $aporte->relationLoaded('investimento')) {
                $aporte->setRelation('investimento', $this);
            }
        }

        return $this->aportes;
    }

    public function patrimonioEstimado(?Carbon $asOf = null): float
    {
        return round($this->aportes->filter(fn (InvestimentoAporte $aporte): bool => $aporte->date->lte($asOf ?? today()))->sum(fn (InvestimentoAporte $aporte): float => ($aporte->kind === 'resgate' ? -1 : 1) * (float) $aporte->amount) + $this->rendimentoEstimado($asOf), 2);
    }

    public function effectiveRate(): float
    {
        $rate = (float) $this->rate;
        $reference = (float) $this->reference_rate;

        return match ($this->rate_mode) {
            'cdi' => (((1 + (((1 + $reference / 100) ** (1 / 252) - 1) * $rate / 100)) ** 252) - 1) * 100,
            'ipca' => ((1 + $reference / 100) * (1 + $rate / 100) - 1) * 100,
            default => $rate,
        };
    }

    public function totalResgatado(): float
    {
        return (float) $this->aportes->where('kind', 'resgate')->filter(fn (InvestimentoAporte $row): bool => $row->date->lte(today()))->sum('amount');
    }

    public function estimativaLiquida(): float
    {
        $yield = max(0, $this->rendimentoEstimado());
        $iof = $yield * (float) $this->iof_rate / 100;
        $ir = ($yield - $iof) * (float) $this->ir_rate / 100;

        return round(max(0, $this->patrimonioEstimado() - $iof - $ir - (float) $this->estimated_fees), 2);
    }
}
