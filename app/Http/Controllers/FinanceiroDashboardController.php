<?php

namespace App\Http\Controllers;

use App\Models\ContaFinanceira;
use App\Models\Investimento;
use App\Models\Lancamento;
use App\Models\User;
use App\Support\Carteira;
use App\Support\FinanceiroPainel;
use App\Support\FinanceiroPeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class FinanceiroDashboardController extends Controller
{
    public function index(Request $request, FinanceiroPainel $panel): View
    {
        $periodFilter = FinanceiroPeriod::apply($request);
        $user = Carteira::owner();
        $start = $periodFilter?->start ?? Carbon::today()->startOfMonth();
        $end = $periodFilter?->end ?? Carbon::today()->endOfMonth();

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

        // Everything is counted per account: what each lançamento adds
        // to, or takes out of, one person's — see Lancamento::entradaFor()
        // and saidaFor(). The cards are the logged-in user's account; the
        // other members of the carteira are listed apart.
        $viewer = $request->user();
        $membros = Carteira::members();
        $movimentos = $panel->movimentosFor($viewer, $conta);

        $entradas = (float) $movimentos->where('type', Lancamento::TYPE_ENTRADA)->sum('amount');
        $saidas = (float) $movimentos->where('type', Lancamento::TYPE_SAIDA)->sum('amount');

        // Two analyses once anything is divided: what the user spends on
        // their own, and what is spent together (at its full value).
        $temDivisao = $membros->count() > 1 || $compartilhados->isNotEmpty();

        $investimentos = Carteira::canSee('investimentos') ? $user->investimentos()->with('aportes')->get() : collect();
        $investido = (float) $investimentos
            ->flatMap(fn (Investimento $i) => $i->aportes)
            ->filter(fn ($aporte) => $aporte->date->between($start, $end))
            ->sum(fn ($aporte): float => ($aporte->kind === 'resgate' ? -1 : 1) * (float) $aporte->amount);

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

        $accounts = ContaFinanceira::withTrashed()->with('user')->get();
        $asOf = $end->copy()->min(today()->endOfDay());
        $available = $viewer->is($user) ? $panel->available($user, $viewer, $asOf, $accounts, $investimentos) : $cards['saldo'];
        $pending = $panel->agendaMovements($user, $viewer, Lancamento::STATUS_PREVISTO, $end);
        $toPay = $pending->where('type', Lancamento::TYPE_SAIDA)->sum('amount');
        $toReceive = $pending->where('type', Lancamento::TYPE_ENTRADA)->sum('amount');
        $cards['saldo_disponivel'] = round($available, 2);
        $cards['a_pagar'] = round($toPay, 2);
        $cards['a_receber'] = round($toReceive, 2);
        $cards['saldo_projetado'] = round($available + $toReceive - $toPay, 2);

        $previstosNoPeriodo = $pending->filter(fn (object $movement): bool => $movement->date->gte($start));
        $previsao = [
            'entradas' => round($previstosNoPeriodo->where('type', Lancamento::TYPE_ENTRADA)->sum('amount'), 2),
            'saidas' => round($previstosNoPeriodo->where('type', Lancamento::TYPE_SAIDA)->sum('amount'), 2),
        ];

        $timelineStart = $end->copy()->subMonthsNoOverflow(5)->startOfMonth();
        $timelineRows = $user->lancamentos()->with('partes')->where('status', 'realizado')->whereBetween('date', Lancamento::dateRange($timelineStart, $end))->get();
        if (Carteira::isOwn()) {
            $timelineRows = $timelineRows->concat(Lancamento::sharedWith($viewer)->with('partes')->where('status', 'realizado')->whereBetween('date', Lancamento::dateRange($timelineStart, $end))->get());
        }
        $timeline = collect(range(0, 5))->map(function (int $index) use ($timelineStart, $timelineRows, $viewer): array {
            $month = $timelineStart->copy()->addMonthsNoOverflow($index);
            $rows = $timelineRows->filter(fn (Lancamento $row): bool => $row->date->format('Y-m') === $month->format('Y-m'));
            $income = $rows->sum(fn (Lancamento $row): float => $row->entradaFor($viewer));
            $expense = $rows->sum(fn (Lancamento $row): float => $row->saidaFor($viewer));

            return ['month' => $month->format('m/Y'), 'income' => $income, 'expense' => $expense];
        });

        return view('pages.financeiro.dashboard', [
            'accounts' => $accounts, 'timeline' => $timeline,
            'period' => $periodFilter?->preset ?? 'mes_atual',
            'periodFilter' => $periodFilter,
            'periodStart' => $start,
            'periodEnd' => $end,
            'cards' => $cards,
            'previsao' => $previsao,
            ...$panel->agenda($user, $viewer, $start, $cards['saldo_disponivel'], $request->query('agenda'), $request->query('foco')),
            'participantes' => $membros
                ->reject(fn (User $member): bool => $member->is($viewer))
                ->map(function (User $member) use ($lancamentos, $panel): array {
                    $movimentos = $panel->movimentosFor($member, $lancamentos);

                    return [
                        'user' => $member,
                        'entradas' => (float) $movimentos->where('type', Lancamento::TYPE_ENTRADA)->sum('amount'),
                        'saidas' => (float) $movimentos->where('type', Lancamento::TYPE_SAIDA)->sum('amount'),
                    ];
                })
                ->values(),
            'despesasPorCategoria' => $this->despesasPorCategoria($movimentos),
            'totaisPorCategoria' => $this->totaisPorCategoria($temDivisao ? $movimentos->where('conjunto', false) : $movimentos),
            'gastosConjuntos' => $temDivisao ? $this->totaisPorCategoria($conta->filter->isDivided()) : null,
        ]);
    }

    /**
     * One bar per category: entrada categories first, then saída ones,
     * each group from largest to smallest. A name used by both types
     * (e.g. "Outros") yields two separate bars. Takes lançamentos (full
     * amounts) or movimentos (one account's).
     *
     * @param  Collection<int, object>  $items  each with type, category and amount
     * @return array<int, array{category: string, type: string, amount: float}>
     */
    private function totaisPorCategoria(Collection $items): array
    {
        return collect([Lancamento::TYPE_ENTRADA, Lancamento::TYPE_SAIDA])
            ->flatMap(fn (string $type) => $items
                ->where('type', $type)
                ->groupBy('category')
                ->map(fn (Collection $group, string $category): array => [
                    'category' => $category,
                    'type' => $type,
                    'amount' => (float) $group->sum(fn (object $item): float => (float) $item->amount),
                ])
                ->sortByDesc('amount')
                ->values())
            ->all();
    }

    /**
     * An account's expenses by category, each with the movimentos behind it.
     *
     * @param  Collection<int, object>  $movimentos
     * @return array<int, array{category: string, amount: float, percentage: float, lancamentos: Collection<int, object>}>
     */
    private function despesasPorCategoria(Collection $movimentos): array
    {
        $saidas = $movimentos->where('type', Lancamento::TYPE_SAIDA);
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
