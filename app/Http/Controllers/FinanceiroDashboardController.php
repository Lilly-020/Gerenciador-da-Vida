<?php

namespace App\Http\Controllers;

use App\Models\Investimento;
use App\Models\Lancamento;
use App\Models\User;
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

        // In their own carteira the user's account also includes their
        // parts of lançamentos in the carteiras they take part in.
        $compartilhados = Carteira::isOwn()
            ? Lancamento::sharedWith($request->user())
                ->with('partes')
                ->where('status', Lancamento::STATUS_REALIZADO)
                ->whereBetween('date', Lancamento::dateRange($start, $end))
                ->get()
            : collect();
        $conta = $lancamentos->concat($compartilhados);

        // In a carteira with participants, the cards count only the
        // logged-in user's share of each lançamento; everyone else's is
        // listed apart (see $participantes below).
        $viewer = $request->user();
        $membros = Carteira::members();
        $sharesOf = fn (User $member, Collection $from): array => [
            'entradas' => (float) $from->where('type', Lancamento::TYPE_ENTRADA)->sum(fn (Lancamento $l): float => $l->shareOf($member)),
            'saidas' => (float) $from->where('type', Lancamento::TYPE_SAIDA)->sum(fn (Lancamento $l): float => $l->shareOf($member)),
        ];
        $viewerShare = fn (Lancamento $l): float => $l->shareOf($viewer);

        ['entradas' => $entradas, 'saidas' => $saidas] = $sharesOf($viewer, $conta);

        // Two analyses once anything is divided: what the user spends on
        // their own, and what is spent together (at its full value).
        $temDivisao = $membros->count() > 1 || $compartilhados->isNotEmpty();
        [$conjuntos, $individuais] = $conta->partition(fn (Lancamento $l): bool => $l->partes->isNotEmpty());

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
            // The carteira's investments are its owner's money.
            'saldo' => $entradas - $saidas - ($viewer->is($user) ? $investido : 0),
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
            'participantes' => $membros
                ->reject(fn (User $member): bool => $member->is($viewer))
                ->map(fn (User $member): array => ['user' => $member, ...$sharesOf($member, $lancamentos)])
                ->values(),
            'despesasPorCategoria' => $this->despesasPorCategoria($conta, $viewerShare),
            'totaisPorCategoria' => $temDivisao
                ? $this->totaisPorCategoria($individuais, $viewerShare)
                : $this->totaisPorCategoria($conta),
            'gastosConjuntos' => $temDivisao ? $this->totaisPorCategoria($conjuntos) : null,
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
     * The user's expenses by category, each with the lançamentos behind
     * it. `$amountOf` gives how much of a lançamento counts (the user's
     * own share of it).
     *
     * @param  Collection<int, Lancamento>  $lancamentos
     * @param  \Closure(Lancamento): float  $amountOf
     * @return array<int, array{category: string, amount: float, percentage: float, lancamentos: Collection<int, object{description: string, date: Carbon, amount: float}>}>
     */
    private function despesasPorCategoria(Collection $lancamentos, \Closure $amountOf): array
    {
        $saidas = $lancamentos
            ->where('type', Lancamento::TYPE_SAIDA)
            ->map(fn (Lancamento $l): object => (object) [
                'category' => $l->category,
                'description' => $l->description,
                'date' => $l->date,
                'amount' => $amountOf($l),
            ])
            ->filter(fn (object $item): bool => $item->amount > 0);
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
