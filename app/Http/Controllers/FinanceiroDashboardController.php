<?php

namespace App\Http\Controllers;

use App\Models\Investimento;
use App\Models\Lancamento;
use App\Support\Carteira;
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
        $user = Carteira::owner();
        $period = $request->query('period', 'mes_atual');
        [$start, $end] = $this->resolvePeriod($period, $request);

        $lancamentos = $user->lancamentos()
            ->with('partes')
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
            'despesasPorCategoria' => $this->despesasPorCategoria($lancamentos),
            'totaisPorCategoria' => $this->totaisPorCategoria($lancamentos),
            // Only meaningful in a carteira with participants: the same
            // chart again, counting just the logged-in user's share.
            'minhaPartePorCategoria' => Carteira::members()->count() > 1
                ? $this->totaisPorCategoria($lancamentos, fn (Lancamento $l): float => $l->shareOf($request->user()))
                : null,
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
     * One bar per category for the period: entrada categories first, then
     * saída ones, each group from largest to smallest. A name used by both
     * types (e.g. "Outros") yields two separate bars. `$amountOf` lets a
     * caller count only part of each lançamento (one participant's share).
     *
     * @param  Collection<int, Lancamento>  $lancamentos
     * @param  (\Closure(Lancamento): float)|null  $amountOf
     * @return array<int, array{category: string, type: string, amount: float}>
     */
    private function totaisPorCategoria(Collection $lancamentos, ?\Closure $amountOf = null): array
    {
        $amountOf ??= fn (Lancamento $l): float => (float) $l->amount;

        return collect([Lancamento::TYPE_ENTRADA, Lancamento::TYPE_SAIDA])
            ->flatMap(fn (string $type) => $lancamentos
                ->where('type', $type)
                ->groupBy('category')
                ->map(fn (Collection $group, string $category): array => [
                    'category' => $category,
                    'type' => $type,
                    'amount' => (float) $group->sum($amountOf),
                ])
                ->filter(fn (array $item): bool => $item['amount'] > 0)
                ->sortByDesc('amount')
                ->values())
            ->all();
    }

    /**
     * @param  Collection<int, Lancamento>  $lancamentos
     * @return array<int, array{category: string, amount: float, percentage: float, lancamentos: Collection<int, Lancamento>}>
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
                    'percentage' => $amount / $total * 100,
                    'lancamentos' => $group->sortByDesc('date')->values(),
                ];
            })
            ->sortByDesc('amount')
            ->values()
            ->all();
    }
}
