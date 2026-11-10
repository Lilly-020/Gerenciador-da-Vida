<?php

namespace App\Http\Controllers;

use App\Models\ContaFinanceira;
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
        if (! array_key_exists($period, self::PERIODS)) {
            $period = 'mes_atual';
        }
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

        // Everything is counted per account: what each lançamento adds
        // to, or takes out of, one person's — see Lancamento::entradaFor()
        // and saidaFor(). The cards are the logged-in user's account; the
        // other members of the carteira are listed apart.
        $viewer = $request->user();
        $membros = Carteira::members();
        $movimentos = $this->movimentosFor($viewer, $conta);

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

        $accounts = ContaFinanceira::with('user')->get();
        $asOf = $end->copy()->min(today()->endOfDay());
        $available = $cards['saldo'];
        if ($viewer->is($user)) {
            $available = $accounts->sum(fn ($account): float => $account->balanceAsOf($asOf));
            $baseline = $accounts->min('opening_date') ?? Carbon::create(1900, 1, 1);
            $unassigned = $user->lancamentos()->with('partes')->whereNull('account_id')->where('status', 'realizado')->whereDate('date', '>=', $baseline)->whereDate('date', '<=', $asOf)->get();
            $available += $unassigned->sum(fn (Lancamento $row): float => $row->entradaFor($viewer) - $row->saidaFor($viewer));
            if (Carteira::isOwn()) {
                $available -= Lancamento::sharedWith($viewer)->with('partes')->where('status', 'realizado')->whereDate('date', '>=', $baseline)->whereDate('date', '<=', $asOf)->get()->sum(fn (Lancamento $row): float => $row->saidaFor($viewer));
            }
            $available -= $investimentos->flatMap(fn (Investimento $investment) => $investment->aportes)->filter(fn ($row): bool => ! $row->account_id && $row->date->gte($baseline) && $row->date->lte($asOf))
                ->sum(fn ($row): float => ($row->kind === 'resgate' ? -1 : 1) * (float) $row->amount);
        }
        $pending = $user->lancamentos()->with('partes')->where('status', 'previsto')->whereDate('date', '<=', $end)->get();
        if (Carteira::isOwn()) {
            $pending = $pending->concat(Lancamento::sharedWith($viewer)->with('partes')->where('status', 'previsto')->whereDate('date', '<=', $end)->get());
        }
        $pending = $pending->filter(fn (Lancamento $row): bool => $row->entradaFor($viewer) > 0 || $row->saidaFor($viewer) > 0);
        $toPay = $pending->sum(fn (Lancamento $row): float => $row->saidaFor($viewer));
        $toReceive = $pending->sum(fn (Lancamento $row): float => $row->entradaFor($viewer));
        $cards['saldo_disponivel'] = round($available, 2);
        $cards['a_pagar'] = round($toPay, 2);
        $cards['a_receber'] = round($toReceive, 2);
        $cards['saldo_projetado'] = round($available + $toReceive - $toPay, 2);
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

            return ['month' => $month->format('m/Y'), 'income' => $income, 'expense' => $expense, 'balance' => $income - $expense];
        });

        return view('pages.financeiro.dashboard', [
            'accounts' => $accounts, 'timeline' => $timeline,
            'upcoming' => $pending->sortBy('date')->take(10),
            'period' => $period,
            'periods' => self::PERIODS,
            'periodStart' => $start,
            'periodEnd' => $end,
            'cards' => $cards,
            'participantes' => $membros
                ->reject(fn (User $member): bool => $member->is($viewer))
                ->map(function (User $member) use ($lancamentos): array {
                    $movimentos = $this->movimentosFor($member, $lancamentos);

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
     * What each lançamento means for one person's account, as plain
     * "movimentos": an entrada for what it adds, a saída for what it takes
     * out (a single lançamento can be either, depending on who is asking).
     * `conjunto` marks the ones that come from a divided lançamento.
     *
     * @param  Collection<int, Lancamento>  $lancamentos
     * @return Collection<int, object{type: string, category: string, description: string, date: Carbon, amount: float, conjunto: bool}>
     */
    private function movimentosFor(User $user, Collection $lancamentos): Collection
    {
        return $lancamentos->flatMap(fn (Lancamento $l): array => collect([
            Lancamento::TYPE_ENTRADA => $l->entradaFor($user),
            Lancamento::TYPE_SAIDA => $l->saidaFor($user),
        ])->filter(fn (float $amount): bool => $amount > 0)->map(fn (float $amount, string $type): object => (object) [
            'type' => $type,
            'category' => $l->category,
            'description' => $l->description,
            'date' => $l->date,
            'amount' => $amount,
            'conjunto' => $l->isDivided(),
        ])->values()->all())->values();
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
