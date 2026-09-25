<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\InvestimentoAporteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['amount', 'date', 'rate_override', 'notes'])]
class InvestimentoAporte extends Model
{
    /** @use HasFactory<InvestimentoAporteFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
            'rate_override' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<Investimento, $this>
     */
    public function investimento(): BelongsTo
    {
        return $this->belongsTo(Investimento::class);
    }

    /**
     * Days this contribution has been invested, as of `$asOf` (default:
     * today). Zero for a contribution dated in the future.
     */
    public function daysInvested(?Carbon $asOf = null): int
    {
        $asOf = $asOf ?? Carbon::today();

        if ($this->date->gt($asOf)) {
            return 0;
        }

        return (int) $this->date->diffInDays($asOf);
    }

    /**
     * Estimated compound-interest yield accrued as of `$asOf`. The
     * investment's configured rate is applied as if it were constant for
     * the whole period — a simplification made explicit everywhere this
     * number is shown ("estimativa").
     */
    public function estimatedYield(?Carbon $asOf = null): float
    {
        $days = $this->daysInvested($asOf);

        if ($days <= 0) {
            return 0.0;
        }

        $amount = (float) $this->amount;
        $futureValue = $amount * (1 + $this->dailyRate()) ** $days;

        return round($futureValue - $amount, 2);
    }

    public function estimatedValue(?Carbon $asOf = null): float
    {
        return round((float) $this->amount + $this->estimatedYield($asOf), 2);
    }

    /**
     * The configured annual/monthly rate converted to an equivalent daily
     * compound rate.
     */
    private function dailyRate(): float
    {
        $rate = (float) ($this->rate_override ?? $this->investimento->rate);
        $periodDays = $this->investimento->rate_period === 'mensal' ? 30 : 365;

        return (1 + $rate / 100) ** (1 / $periodDays) - 1;
    }
}
