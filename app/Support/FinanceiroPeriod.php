<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;

final readonly class FinanceiroPeriod
{
    /**
     * @var array<string, string>
     */
    public const PERIODS = [
        'mes_atual' => 'Este mês',
        'mes_anterior' => 'Mês anterior',
        'ultimos_3_meses' => 'Últimos 3 meses',
        'ultimos_6_meses' => 'Últimos 6 meses',
        'ano_atual' => 'Este ano',
        'ano_anterior' => 'Ano anterior',
        'personalizado' => 'Personalizado',
    ];

    private function __construct(public string $preset, public Carbon $start, public Carbon $end) {}

    public static function apply(Request $request): ?self
    {
        $preset = $request->query('period');
        if ($preset === 'limpar') {
            $request->session()->forget(self::sessionKey());

            return null;
        }

        if ($preset === null && $request->anyFilled(['start', 'end'])) {
            $preset = 'personalizado';
        }

        if (! is_string($preset) || ! array_key_exists($preset, self::PERIODS)) {
            return self::current($request);
        }

        $dates = $preset === 'personalizado' ? $request->validate([
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start'],
        ], [
            'start.required' => 'Informe uma data inicial.',
            'end.required' => 'Informe uma data final.',
            'start.date_format' => 'Informe uma data inicial válida.',
            'end.date_format' => 'Informe uma data final válida.',
            'end.after_or_equal' => 'A data final deve ser igual ou posterior à data inicial.',
        ]) : [];

        $request->session()->put(self::sessionKey(), ['period' => $preset, ...$dates]);

        return self::current($request);
    }

    public static function current(Request $request): ?self
    {
        $filter = $request->session()->get(self::sessionKey());
        if ($filter === null) {
            return null;
        }

        [$start, $end] = self::resolveDates($filter['period'], $filter);

        return new self($filter['period'], $start, $end->endOfDay());
    }

    private static function sessionKey(): string
    {
        return 'financeiro.periodo.'.Carteira::ownerId();
    }

    /**
     * @param  array{start?: string|null, end?: string|null}  $dates
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function resolveDates(string $preset, array $dates): array
    {
        $today = Carbon::today();

        return match ($preset) {
            'mes_anterior' => [
                $today->copy()->subMonthNoOverflow()->startOfMonth(),
                $today->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            'ultimos_3_meses' => [$today->copy()->subMonthsNoOverflow(2)->startOfMonth(), $today->copy()->endOfMonth()],
            'ultimos_6_meses' => [$today->copy()->subMonthsNoOverflow(5)->startOfMonth(), $today->copy()->endOfMonth()],
            'ano_atual' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            'ano_anterior' => [$today->copy()->subYear()->startOfYear(), $today->copy()->subYear()->endOfYear()],
            'personalizado' => [
                Carbon::createFromFormat('!Y-m-d', $dates['start']),
                Carbon::createFromFormat('!Y-m-d', $dates['end']),
            ],
            default => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
        };
    }
}
