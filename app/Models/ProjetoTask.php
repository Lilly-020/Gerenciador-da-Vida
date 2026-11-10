<?php

namespace App\Models;

use Database\Factories\ProjetoTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title', 'completed', 'assignee_id', 'parent_id', 'due_at', 'goal_type', 'target_value', 'current_value', 'unit'])]
class ProjetoTask extends Model
{
    /** @use HasFactory<ProjetoTaskFactory> */
    use HasFactory;

    protected $attributes = ['goal_type' => 'checklist', 'current_value' => 0, 'completed' => false];

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    protected function isFinished(): Attribute
    {
        return Attribute::make(get: fn (): bool => $this->goal_type === 'checklist'
            ? $this->completed : $this->target_value > 0 && (float) $this->current_value >= (float) $this->target_value);
    }

    protected function executionProgress(): Attribute
    {
        return Attribute::make(get: fn (): int => $this->goal_type === 'checklist'
            ? ($this->completed ? 100 : 0)
            : ($this->target_value > 0 ? min(100, (int) floor($this->current_value / $this->target_value * 100)) : 0));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed' => 'boolean',
            'due_at' => 'date',
            'target_value' => 'decimal:2',
            'current_value' => 'decimal:2',
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
