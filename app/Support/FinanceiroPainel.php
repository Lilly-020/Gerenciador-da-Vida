<?php

namespace App\Support;

use App\Models\ContaFinanceira;
use App\Models\Investimento;
use App\Models\Lancamento;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FinanceiroPainel
{
    /**
     * @param  Collection<int, ContaFinanceira>  $accounts
     * @param  Collection<int, Investimento>  $investimentos
     */
    public function available(User $owner, User $viewer, Carbon $asOf, Collection $accounts, Collection $investimentos): float
    {
        $available = $accounts->sum(fn ($account): float => $account->balanceAsOf($asOf));
        $baseline = $accounts->min('opening_date') ?? Carbon::create(1900, 1, 1);
        $unassigned = $owner->lancamentos()->with('partes')->whereNull('account_id')->where('status', 'realizado')->whereDate('date', '>=', $baseline)->whereDate('date', '<=', $asOf)->get();
        $available += $unassigned->sum(fn (Lancamento $row): float => $row->entradaFor($viewer) - $row->saidaFor($viewer));
        if (Carteira::isOwn()) {
            $available -= Lancamento::sharedWith($viewer)->with('partes')->where('status', 'realizado')->whereDate('date', '>=', $baseline)->whereDate('date', '<=', $asOf)->get()->sum(fn (Lancamento $row): float => $row->saidaFor($viewer));
        }
        $available -= $investimentos->flatMap(fn (Investimento $investment) => $investment->aportes)->filter(fn ($row): bool => ! $row->account_id && $row->date->gte($baseline) && $row->date->lte($asOf))
            ->sum(fn ($row): float => ($row->kind === 'resgate' ? -1 : 1) * (float) $row->amount);

        return round($available, 2);
    }

    /**
     * @return array{calendarMonth: Carbon, showAgendaDays: bool, focusedDate: Carbon, agendaRows: list<array{date: Carbon, pay: float, receive: float, received: float, paid: float, result: float, balance: float|null}>}
     */
    public function agenda(User $owner, User $viewer, Carbon $start, float $available, mixed $agenda, mixed $focus = null): array
    {
        $showDays = is_string($agenda) && preg_match('/^[1-9]\d{3}-(0[1-9]|1[0-2])$/D', $agenda) === 1;
        $calendarMonth = $start->copy()->startOfMonth();
        if ($showDays) {
            $calendarMonth = Carbon::createFromFormat('!Y-m', $agenda);
        } elseif (is_string($agenda) && preg_match('/^[1-9]\d{3}$/D', $agenda) === 1) {
            $calendarMonth->year((int) $agenda);
        }

        $agendaStart = $showDays ? $calendarMonth : $calendarMonth->copy()->startOfYear();
        $agendaEnd = $showDays ? $calendarMonth->copy()->endOfMonth() : $calendarMonth->copy()->endOfYear();
        $dateFormat = $showDays ? 'Y-m-d' : 'Y-m';
        $projectionStart = $showDays ? today() : today()->startOfMonth();
        $scheduled = $this->agendaMovements($owner, $viewer, Lancamento::STATUS_PREVISTO, $agendaEnd, $agendaStart->copy()->min($projectionStart));
        $scheduledByDate = $scheduled->groupBy(fn (object $movement): string => $movement->date->format($dateFormat));
        $completedByDate = $this->agendaMovements($owner, $viewer, Lancamento::STATUS_REALIZADO, $agendaEnd, $agendaStart)
            ->groupBy(fn (object $movement): string => $movement->date->format($dateFormat));
        $runningBalance = $available;
        if ($agendaStart->gt($projectionStart)) {
            $beforeAgenda = $scheduled->filter(fn (object $movement): bool => $movement->date->lt($agendaStart));
            $runningBalance += $beforeAgenda->where('type', Lancamento::TYPE_ENTRADA)->sum('amount')
                - $beforeAgenda->where('type', Lancamento::TYPE_SAIDA)->sum('amount');
        }

        $today = today();
        $focusedDate = $today->betweenIncluded($agendaStart, $agendaEnd) ? $today : $calendarMonth;
        $agendaRows = [];
        for ($date = $agendaStart->copy(); $date->lte($agendaEnd); $date = $showDays ? $date->addDay() : $date->addMonthNoOverflow()) {
            $key = $date->format($dateFormat);
            if ($focus === $key) {
                $focusedDate = $date->copy();
            }
            $movements = $scheduledByDate->get($key, collect());
            $completed = $completedByDate->get($key, collect());
            $pay = round($movements->where('type', Lancamento::TYPE_SAIDA)->sum('amount'), 2);
            $receive = round($movements->where('type', Lancamento::TYPE_ENTRADA)->sum('amount'), 2);
            $result = round($receive - $pay, 2);
            $balance = null;
            if ($date->gte($projectionStart)) {
                $runningBalance = round($runningBalance + $result, 2);
                $balance = $runningBalance;
            }

            $agendaRows[] = [
                'date' => $date->copy(),
                'pay' => $pay,
                'receive' => $receive,
                'received' => round($completed->where('type', Lancamento::TYPE_ENTRADA)->sum('amount'), 2),
                'paid' => round($completed->where('type', Lancamento::TYPE_SAIDA)->sum('amount'), 2),
                'result' => $result,
                'balance' => $balance,
            ];
        }

        return [
            'calendarMonth' => $calendarMonth,
            'showAgendaDays' => $showDays,
            'focusedDate' => $focusedDate,
            'agendaRows' => $agendaRows,
        ];
    }

    /**
     * @return Collection<int, object{type: string, category: string, description: string, date: Carbon, amount: float, conjunto: bool}>
     */
    public function agendaMovements(User $owner, User $viewer, string $status, Carbon $end, ?Carbon $start = null): Collection
    {
        $matchingRows = fn ($query) => $query->with('partes')
            ->where('status', $status)
            ->where('date', '<=', $end->format('Y-m-d 23:59:59'))
            ->when($start, fn ($query) => $query->where('date', '>=', $start->toDateString()))
            ->get();

        $rows = $matchingRows($owner->lancamentos());
        if (Carteira::isOwn()) {
            $rows = $rows->concat($matchingRows(Lancamento::sharedWith($viewer)));
        }

        return $this->movimentosFor($viewer, $rows);
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
    public function movimentosFor(User $user, Collection $lancamentos): Collection
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
}
