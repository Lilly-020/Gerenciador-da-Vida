<?php

namespace App\Http\Controllers;

use App\Models\Investimento;
use App\Models\Lancamento;
use App\Models\Tarefa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class FinanceiroDashboardController extends Controller
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

    public function index(Request $request): View
    {
        $user = $request->user();
        $period = $request->query('period', 'mes_atual');
        [$start, $end] = $this->resolvePeriod($period, $request);

        $lancamentos = $user->lancamentos()
            ->where('status', Lancamento::STATUS_REALIZADO)
            ->whereBetween('date', Lancamento::dateRange($start, $end))
            ->get();

        $entradas = (float) $lancamentos->where('type', Lancamento::TYPE_ENTRADA)->sum('amount');
        $saidas = (float) $lancamentos->where('type', Lancamento::TYPE_SAIDA)->sum('amount');

        $investimentos = $user->investimentos()->with('aportes')->get();
        $investido = (float) $investimentos
            ->flatMap(fn (Investimento $i) => $i->aportes)
            ->filter(fn ($aporte) => $aporte->date->between($start, $end))
            ->sum('amount');

        $today = Carbon::today();

        $cards = [
            'entradas' => $entradas,
            'saidas' => $saidas,
            'investido' => $investido,
            'saldo' => $entradas - $saidas - $investido,
            'patrimonio_investido' => $investimentos->sum(fn (Investimento $i): float => $i->patrimonioEstimado()),
            'rendimento_acumulado' => $investimentos->sum(fn (Investimento $i): float => $i->rendimentoEstimado()),
            'rendimento_mes' => $investimentos->sum(fn (Investimento $i): float => $i->rendimentoEntre($today->copy()->startOfMonth(), $today)),
            'rendimento_ano' => $investimentos->sum(fn (Investimento $i): float => $i->rendimentoEntre($today->copy()->startOfYear(), $today)),
        ];

        return view('pages.financeiro.dashboard', [
            'period' => $period,
            'periods' => self::PERIODS,
            'periodStart' => $start,
            'periodEnd' => $end,
            'cards' => $cards,
            'fluxoMensal' => $this->fluxoMensal($user, $start, $end),
            'despesasPorCategoria' => $this->despesasPorCategoria($lancamentos),
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolvePeriod(string $preset, Request $request): array
    {
        $today = Carbon::today();

        return match ($preset) {
            'mes_anterior' => [
                $today->copy()->subMonthNoOverflow()->startOfMonth(),
                $today->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            'ultimos_3_meses' => [$today->copy()->subMonths(2)->startOfMonth(), $today->copy()->endOfMonth()],
            'ultimos_6_meses' => [$today->copy()->subMonths(5)->startOfMonth(), $today->copy()->endOfMonth()],
            'ano_atual' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            'ano_anterior' => [$today->copy()->subYear()->startOfYear(), $today->copy()->subYear()->endOfYear()],
            'personalizado' => [
                $this->parseDate($request->query('start')) ?? $today->copy()->startOfMonth(),
                $this->parseDate($request->query('end')) ?? $today->copy()->endOfMonth(),
            ],
            default => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
        };
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Monthly entradas/saídas/saldo series covering the selected period
     * (at least one month, capped at 24 to keep the chart readable).
     *
     * @return array<int, array{label: string, sublabel: string, entradas: float, saidas: float, saldo: float, isCurrent: bool}>
     */
    private function fluxoMensal(User $user, Carbon $start, Carbon $end): array
    {
        $monthStart = $start->copy()->startOfMonth();
        $monthEnd = $end->copy()->startOfMonth();
        $months = max(1, min(24, $monthStart->diffInMonths($monthEnd) + 1));

        $rangeStart = $monthStart->copy();
        $rangeEnd = $monthEnd->copy()->endOfMonth();

        $lancamentos = $user->lancamentos()
            ->where('status', Lancamento::STATUS_REALIZADO)
            ->whereBetween('date', Lancamento::dateRange($rangeStart, $rangeEnd))
            ->get()
            ->groupBy(fn (Lancamento $l): string => $l->date->format('Y-m'));

        $series = [];
        $cursor = $monthStart->copy();
        $currentMonthKey = Carbon::today()->format('Y-m');

        for ($i = 0; $i < $months; $i++) {
            $key = $cursor->format('Y-m');
            $monthLancamentos = $lancamentos->get($key, collect());

            $entradas = (float) $monthLancamentos->where('type', Lancamento::TYPE_ENTRADA)->sum('amount');
            $saidas = (float) $monthLancamentos->where('type', Lancamento::TYPE_SAIDA)->sum('amount');

            $series[] = [
                'label' => mb_substr(Tarefa::monthLabel($cursor), 0, 3),
                'sublabel' => $cursor->format('m/Y'),
                'entradas' => $entradas,
                'saidas' => $saidas,
                'saldo' => $entradas - $saidas,
                'isCurrent' => $key === $currentMonthKey,
            ];

            $cursor->addMonth();
        }

        return $series;
    }

    /**
     * @param  Collection<int, Lancamento>  $lancamentos
     * @return array<int, array{category: string, amount: float, percentage: int}>
     */
    private function despesasPorCategoria(Collection $lancamentos): array
    {
        $saidas = $lancamentos->where('type', Lancamento::TYPE_SAIDA);
        $total = (float) $saidas->sum('amount');

        if ($total <= 0) {
            return [];
        }

        return $saidas
            ->groupBy('category')
            ->map(function (Collection $group, string $category) use ($total): array {
                $amount = (float) $group->sum('amount');

                return [
                    'category' => $category,
                    'amount' => $amount,
                    'percentage' => (int) round($amount / $total * 100),
                ];
            })
            ->sortByDesc('amount')
            ->values()
            ->all();
    }
}
