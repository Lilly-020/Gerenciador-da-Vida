<?php

namespace App\Models;

use App\Support\Carteira;
use Database\Factories\TransferenciaFinanceiraFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

#[Table('transferencias_financeiras')]
#[Fillable(['source_id', 'destination_id', 'amount', 'date', 'notes'])]
class TransferenciaFinanceira extends Model
{
    /** @use HasFactory<TransferenciaFinanceiraFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope('owner', function (Builder $builder): void {
            if (Auth::check()) {
                $builder->where('user_id', Carteira::ownerId());
            }
        });
        static::creating(function (self $model): void {
            $model->user_id ??= Carteira::ownerId();
        });
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'date' => 'date'];
    }

    /** @return BelongsTo<ContaFinanceira, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(ContaFinanceira::class, 'source_id')->withTrashed();
    }

    /** @return BelongsTo<ContaFinanceira, $this> */
    public function destination(): BelongsTo
    {
        return $this->belongsTo(ContaFinanceira::class, 'destination_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
