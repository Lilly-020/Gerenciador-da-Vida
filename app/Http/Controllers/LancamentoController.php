<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMonth;
use App\Http\Requests\StoreLancamentoRequest;
use App\Http\Requests\UpdateLancamentoRequest;
use App\Models\Lancamento;
use App\Models\User;
use App\Support\Carteira;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class LancamentoController extends Controller
{
    use ResolvesMonth;

    /**
     * List Entradas for the authenticated user.
     */
    public function entradas(Request $request): View
    {
        return $this->list($request, Lancamento::TYPE_ENTRADA);
    }

    /**
     * List Saídas for the authenticated user.
     */
    public function saidas(Request $request): View
    {
        return $this->list($request, Lancamento::TYPE_SAIDA);
    }

    /**
     * Create a new Entrada or Saída. When marked as recurring, immediately
     * generates the next 12 "previsto" occurrences.
     */
    public function store(StoreLancamentoRequest $request): RedirectResponse|JsonResponse
    {
        $data = Arr::except($request->validated(), 'partes');
        $data['is_recurring_template'] = $request->boolean('is_recurring_template');
        $data['status'] = Lancamento::STATUS_REALIZADO;

        $this->ensureSectionShared($data['type']);

        $lancamento = Carteira::owner()->lancamentos()->create($data);
        $lancamento->syncPartes($request->input('partes', []));

        if ($lancamento->is_recurring_template) {
            $lancamento->generateUpcomingOccurrences();
        }

        return $this->respondWith($request, $lancamento->type);
    }

    /**
     * Toggle a lançamento between previsto and realizado.
     */
    public function update(UpdateLancamentoRequest $request, Lancamento $lancamento): RedirectResponse|JsonResponse
    {
        $this->ensureSectionShared($lancamento->type);

        $lancamento->update(Arr::except($request->validated(), 'partes'));

        if ($request->has('partes')) {
            $lancamento->syncPartes($request->input('partes'));
        }

        return $this->respondWith($request, $lancamento->type);
    }

    /**
     * Delete a lançamento.
     */
    public function destroy(Request $request, Lancamento $lancamento): RedirectResponse|JsonResponse
    {
        $type = $lancamento->type;
        $this->ensureSectionShared($type);
        $lancamento->delete();

        return $this->respondWith($request, $type);
    }

    /**
     * The write routes serve both types, so the route middleware can only
     * check "Entradas or Saídas"; this pins it to the lançamento's own.
     */
    private function ensureSectionShared(string $type): void
    {
        abort_unless(
            Carteira::canSee($type === Lancamento::TYPE_ENTRADA ? 'entradas' : 'saidas'),
            403,
            'Esta seção não foi compartilhada com você.',
        );
    }

    private function list(Request $request, string $type): View
    {
        $month = $this->resolveMonth($request);
        $lancamentos = $this->lancamentosFor($request, $type, $month);

        return view('pages.financeiro.lancamentos', [
            'type' => $type,
            'categories' => Lancamento::categoriesFor($type),
            'lancamentos' => $lancamentos,
            ...$this->totals($request, $type, $lancamentos),
            'month' => $month,
            'mes' => $this->monthParam($month),
            'meses' => $this->monthOptions($request, $type, $month),
            'membros' => Carteira::members(),
        ]);
    }

    private function respondWith(Request $request, string $type): RedirectResponse|JsonResponse
    {
        if (! $request->wantsJson()) {
            return back();
        }

        $month = $this->resolveMonth($request);
        $lancamentos = $this->lancamentosFor($request, $type, $month);

        return response()->json([
            'list' => view('partials.financeiro.lancamentos-list', [
                'type' => $type,
                'lancamentos' => $lancamentos,
                'month' => $month,
                'mes' => $this->monthParam($month),
                'membros' => Carteira::members(),
            ])->render(),
            'totals' => view('partials.financeiro.lancamentos-totals', $this->totals($request, $type, $lancamentos))->render(),
        ]);
    }

    /**
     * Realizado/previsto totals counting only the logged-in user's share
     * of each lançamento, plus the same pair for every other member of
     * the carteira (empty when it has no participants).
     *
     * @param  Collection<int, Lancamento>  $lancamentos
     * @return array{totalPrevisto: float, totalRealizado: float, participantes: \Illuminate\Support\Collection<int, array{user: User, previsto: float, realizado: float}>}
     */
    private function totals(Request $request, string $type, Collection $lancamentos): array
    {
        // What a lançamento means for a member's account on this page: on
        // Saídas, what it takes out of it; on Entradas, what it adds — or,
        // for the other members, how much of it was received from them.
        $amountFor = fn (Lancamento $l, User $member, bool $asOther): float => $type === Lancamento::TYPE_ENTRADA && ! $asOther
            ? $l->entradaFor($member)
            : $l->saidaFor($member);
        // Other members' numbers only make sense within this carteira —
        // not in the lançamentos that came from someone else's.
        $ofCarteira = $lancamentos->where('user_id', Carteira::ownerId());
        $sharesOf = fn (User $member, bool $asOther = false): array => [
            'previsto' => (float) ($asOther ? $ofCarteira : $lancamentos)
                ->where('status', Lancamento::STATUS_PREVISTO)->sum(fn (Lancamento $l): float => $amountFor($l, $member, $asOther)),
            'realizado' => (float) ($asOther ? $ofCarteira : $lancamentos)
                ->where('status', Lancamento::STATUS_REALIZADO)->sum(fn (Lancamento $l): float => $amountFor($l, $member, $asOther)),
        ];
        $mine = $sharesOf($request->user());

        return [
            'totalPrevisto' => $mine['previsto'],
            'totalRealizado' => $mine['realizado'],
            'participantes' => Carteira::members()
                ->reject(fn (User $member): bool => $member->is($request->user()))
                ->map(fn (User $member): array => ['user' => $member, ...$sharesOf($member, true)])
                ->values(),
        ];
    }

    /**
     * @return Collection<int, Lancamento>
     */
    private function lancamentosFor(Request $request, string $type, ?Carbon $month): Collection
    {
        $inMonth = fn ($query) => $query->when($month, fn ($query) => $query->whereBetween(
            'date',
            Lancamento::dateRange($month, $month->copy()->endOfMonth()),
        ));

        $lancamentos = $inMonth(Carteira::owner()->lancamentos()->with('partes.user')->where('type', $type))->get();

        // In their own carteira the user also sees — read-only — what
        // other carteiras' lançamentos take out of their account: their
        // part of an expense, or of an income its owner received from them.
        if (Carteira::isOwn() && $type === Lancamento::TYPE_SAIDA) {
            $lancamentos = $lancamentos->concat(
                $inMonth(Lancamento::sharedWith($request->user())->with(['partes.user', 'user']))
                    ->get()
                    ->filter(fn (Lancamento $l): bool => $l->saidaFor($request->user()) > 0),
            );
        }

        return $lancamentos
            ->sortBy([['date', 'desc'], ['id', 'desc']])
            ->values();
    }

    /**
     * Months offered in the filter, newest first: every month that has a
     * lançamento of this type, plus the current and the selected one so
     * neither disappears from the select just for being empty.
     *
     * @return array<string, string>
     */
    private function monthOptions(Request $request, string $type, ?Carbon $month): array
    {
        $keys = Carteira::owner()->lancamentos()
            ->where('type', $type)
            ->toBase()
            ->pluck('date')
            ->map(fn ($date): string => substr((string) $date, 0, 7))
            ->push(Carbon::today()->format('Y-m'))
            ->when($month, fn ($keys) => $keys->push($month->format('Y-m')))
            ->unique()
            ->sortDesc();

        return $keys
            ->mapWithKeys(fn (string $key): array => [$key => self::monthTitle(Carbon::createFromFormat('!Y-m', $key))])
            ->all();
    }
}
