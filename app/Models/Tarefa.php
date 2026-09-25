<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\TarefaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

#[Fillable(['title', 'date', 'completed'])]
class Tarefa extends Model
{
    /** @use HasFactory<TarefaFactory> */
    use HasFactory;

    private const MONTHS = [
        'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
        'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
    ];

    private const WEEKDAYS = [
        'Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'completed' => 'boolean',
        ];
    }

    /**
     * Scope every query to the authenticated user's own tasks, and default
     * new tasks to belong to them.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('owner', function (Builder $builder): void {
            if (Auth::check()) {
                $builder->where('user_id', Auth::id());
            }
        });

        static::creating(function (Tarefa $tarefa): void {
            $tarefa->user_id ??= Auth::id();
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Portuguese month name (kept local to avoid depending on APP_LOCALE,
     * which is "en" and would otherwise render dates in English).
     */
    public static function monthLabel(Carbon $date): string
    {
        return self::MONTHS[$date->month - 1];
    }

    /**
     * Portuguese weekday name, see {@see self::monthLabel()}.
     */
    public static function weekdayLabel(Carbon $date): string
    {
        return self::WEEKDAYS[$date->dayOfWeek];
    }
}
