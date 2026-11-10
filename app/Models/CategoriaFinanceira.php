<?php

namespace App\Models;

use App\Support\Carteira;
use Database\Factories\CategoriaFinanceiraFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

#[Table('categorias_financeiras')]
#[Fillable(['name', 'type'])]
class CategoriaFinanceira extends Model
{
    /** @use HasFactory<CategoriaFinanceiraFactory> */
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
        return [];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
