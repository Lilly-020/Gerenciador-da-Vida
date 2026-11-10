<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['path', 'original_name', 'mime_type', 'size'])]
class TarefaFile extends Model
{
    /** @return BelongsTo<Tarefa, $this> */
    public function tarefa(): BelongsTo
    {
        return $this->belongsTo(Tarefa::class);
    }
}
