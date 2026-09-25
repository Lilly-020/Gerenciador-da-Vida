<?php

namespace App\Models;

use Database\Factories\ProjetoTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title', 'completed'])]
class ProjetoTask extends Model
{
    /** @use HasFactory<ProjetoTaskFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Projeto, $this>
     */
    public function projeto(): BelongsTo
    {
        return $this->belongsTo(Projeto::class);
    }
}
