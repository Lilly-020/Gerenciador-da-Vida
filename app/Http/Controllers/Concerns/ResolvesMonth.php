<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Tarefa;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Month navigation shared by the Financeiro pages, driven by `?mes=Y-m`.
 * The fetch urls those pages render carry the same param, so an
 * edit/delete re-renders the month being looked at rather than the
 * default one.
 */
trait ResolvesMonth
{
    public const ALL_MONTHS = 'todos';

    /**
     * The first day of the month selected through `?mes=Y-m`, defaulting
     * to the current one. `?mes=todos` returns null ("no month filter").
     */
    protected function resolveMonth(Request $request): ?Carbon
    {
        $mes = $request->query('mes');

        if ($mes === self::ALL_MONTHS) {
            return null;
        }

        if (is_string($mes) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) {
            return Carbon::createFromFormat('!Y-m', $mes);
        }

        return Carbon::today()->startOfMonth();
    }

    protected function monthParam(?Carbon $month): string
    {
        return $month?->format('Y-m') ?? self::ALL_MONTHS;
    }

    public static function monthTitle(Carbon $month): string
    {
        return Tarefa::monthLabel($month).' de '.$month->year;
    }
}
