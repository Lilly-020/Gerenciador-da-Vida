<?php

namespace App\Models;

use Database\Factories\AmizadeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('amizades')]
#[Fillable(['user_low_id', 'user_high_id', 'requester_id', 'status'])]
class Amizade extends Model
{
    /** @use HasFactory<AmizadeFactory> */
    use HasFactory;

    public function lowUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_low_id');
    }

    public function highUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_high_id');
    }

    public function otherId(int $id): int
    {
        return $id === (int) $this->user_low_id ? $this->user_high_id : $this->user_low_id;
    }

    public function includes(int $id): bool
    {
        return in_array($id, [(int) $this->user_low_id, (int) $this->user_high_id], true);
    }
}
