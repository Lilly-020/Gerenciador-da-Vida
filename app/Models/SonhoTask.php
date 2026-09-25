<?php

namespace App\Models;

use Database\Factories\SonhoTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title', 'completed'])]
class SonhoTask extends Model
{
    /** @use HasFactory<SonhoTaskFactory> */
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
     * @return BelongsTo<Sonho, $this>
     */
    public function sonho(): BelongsTo
    {
        return $this->belongsTo(Sonho::class);
    }
}
