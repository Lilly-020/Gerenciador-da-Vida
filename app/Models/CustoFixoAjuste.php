<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a fixed cost looks like in one specific month, when the user edited
 * "só este mês" instead of the cost itself. See {@see CustoFixo::valuesFor()}.
 */
#[Fillable(['month', 'name', 'category', 'amount', 'due_day', 'notes'])]
class CustoFixoAjuste extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<CustoFixo, $this>
     */
    public function custoFixo(): BelongsTo
    {
        return $this->belongsTo(CustoFixo::class);
    }
}
