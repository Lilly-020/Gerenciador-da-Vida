<?php

namespace App\Models;

use Database\Factories\ProjetoActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['projeto_id', 'description', 'user_id'])]
class ProjetoActivity extends Model
{
    /** @use HasFactory<ProjetoActivityFactory> */
    use HasFactory;

    /** @return BelongsTo<Projeto, $this> */
    public function projeto(): BelongsTo
    {
        return $this->belongsTo(Projeto::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
