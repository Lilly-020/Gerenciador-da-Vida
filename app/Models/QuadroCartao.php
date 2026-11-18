<?php

namespace App\Models;

use Database\Factories\QuadroCartaoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('quadro_cartoes')]
#[Fillable(['title', 'description', 'status', 'labels', 'assignee_ids', 'due_at', 'starts_on', 'reminder_minutes', 'lista_id', 'position', 'checklist', 'checklists', 'location', 'cover_file_id', 'cover_color'])]
class QuadroCartao extends Model
{
    /** @use HasFactory<QuadroCartaoFactory> */
    use HasFactory;

    public const STATUSES = ['undefined' => 'Indefinido', 'todo' => 'A fazer', 'doing' => 'Em andamento', 'done' => 'Concluído'];

    public const REMINDERS = [0 => 'Na hora da entrega', 5 => '5 minutos antes', 15 => '15 minutos antes', 30 => '30 minutos antes', 60 => '1 hora antes', 120 => '2 horas antes', 1440 => '1 dia antes', 2880 => '2 dias antes', 10080 => '1 semana antes'];

    protected static function booted(): void
    {
        static::saving(function (QuadroCartao $card): void {
            if (! $card->due_at) {
                $card->reminder_minutes = null;
            }
            if ($card->isDirty(['due_at', 'reminder_minutes'])) {
                $card->reminder_at = $card->due_at && $card->reminder_minutes !== null ? $card->due_at->copy()->subMinutes($card->reminder_minutes) : null;
                $card->reminder_notified_at = null;
                $card->reminder_mailed_at = null;
            }
        });
    }

    protected function casts(): array
    {
        return ['labels' => 'array', 'assignee_ids' => 'array', 'legacy_data' => 'array', 'due_at' => 'datetime', 'starts_on' => 'date', 'reminder_minutes' => 'integer', 'reminder_at' => 'datetime', 'reminder_notified_at' => 'datetime', 'reminder_mailed_at' => 'datetime', 'checklist' => 'array', 'checklists' => 'array', 'attachments' => 'array', 'activity' => 'array'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recordActivity(User $user, string $message): void
    {
        $this->activity = array_slice([...($this->activity ?? []), ['author' => $user->name, 'message' => $message, 'at' => now()->toIso8601String()]], -200);
    }

    public function quadro(): BelongsTo
    {
        return $this->belongsTo(Quadro::class);
    }

    public function lista(): BelongsTo
    {
        return $this->belongsTo(QuadroLista::class, 'lista_id');
    }

    public function comentarios(): HasMany
    {
        return $this->hasMany(QuadroComentario::class, 'cartao_id')->latest('id')->limit(100);
    }

    public function pinnedComentarios(): HasMany
    {
        return $this->hasMany(QuadroComentario::class, 'cartao_id')->where('pinned', true)->latest('id');
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'legacy_curso_id')->withoutGlobalScope('owner');
    }
}
