<?php

namespace App\Models;

use App\Support\Carteira;
use Carbon\Carbon;
use Database\Factories\ContaFinanceiraFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

#[Table('contas_financeiras')]
#[Fillable(['name', 'kind', 'opening_balance', 'opening_date'])]
class ContaFinanceira extends Model
{
    /** @use HasFactory<ContaFinanceiraFactory> */
    use HasFactory;

    use SoftDeletes;

    protected static function booted(): void
    {
        static::addGlobalScope('owner', function (Builder $builder): void {
            if (Auth::check()) {
                $builder->where('user_id', Carteira::ownerId());
                if (Carteira::participacao()?->allowed_accounts !== null) {
                    $builder->whereIn('id', Carteira::participacao()->allowed_accounts);
                }
            }
        });
        static::creating(function (self $model): void {
            $model->user_id ??= Carteira::ownerId();
        });
    }

    protected function casts(): array
    {
        return ['opening_balance' => 'decimal:2', 'opening_date' => 'date'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function balanceAsOf(?Carbon $asOf = null, bool $fullLedger = false): float
    {
        $asOf ??= today();
        if ($this->opening_date->gt($asOf)) {
            return 0.0;
        }
        $rows = Lancamento::with('partes')->where('account_id', $this->id)->where('status', 'realizado')
            ->whereBetween('date', Lancamento::dateRange($this->opening_date, $asOf))->get();
        $rows = $rows->filter(fn (Lancamento $row): bool => $fullLedger || Carteira::canSee('dashboard') || Carteira::canSee($row->type === 'entrada' ? 'entradas' : 'saidas'));
        $balance = ! $fullLedger && Carteira::hasRestrictions() ? 0 : (float) $this->opening_balance;
        $balance += $rows->sum(fn (Lancamento $row): float => $row->entradaFor($this->user) - $row->saidaFor($this->user));
        if ($fullLedger || ! Carteira::hasRestrictions()) {
            $transfers = TransferenciaFinanceira::whereBetween('date', Lancamento::dateRange($this->opening_date, $asOf))->get();
            $balance += $transfers->where('destination_id', $this->id)->sum('amount') - $transfers->where('source_id', $this->id)->sum('amount');
        }
        $movements = InvestimentoAporte::where('account_id', $this->id)
            ->whereHas('investimento')->whereBetween('date', Lancamento::dateRange($this->opening_date, $asOf))->get();
        if ($fullLedger || (Carteira::canSee('investimentos') && ! Carteira::hasRestrictions())) {
            $balance += $movements->sum(fn (InvestimentoAporte $movement): float => $movement->kind === 'resgate' ? (float) $movement->amount : -(float) $movement->amount);
        }

        return round($balance, 2);
    }
}
