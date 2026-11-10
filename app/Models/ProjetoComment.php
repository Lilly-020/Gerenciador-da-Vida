<?php

namespace App\Models;

use Database\Factories\ProjetoCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['projeto_id', 'body', 'user_id', 'projeto_task_id'])]
class ProjetoComment extends Model
{
    /** @use HasFactory<ProjetoCommentFactory> */
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

    /** @return BelongsTo<ProjetoTask, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjetoTask::class, 'projeto_task_id');
    }
}
