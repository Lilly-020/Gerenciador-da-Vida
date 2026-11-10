<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMonth;
use App\Http\Requests\StoreLancamentoRequest;
use App\Http\Requests\UpdateLancamentoRequest;
use App\Models\CategoriaFinanceira;
use App\Models\ContaFinanceira;
use App\Models\FinanceiroActivity;
use App\Models\Lancamento;
use App\Models\User;
use App\Support\Carteira;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    public function relatorio(Request $request): View
    {
        $columns = [
            'id' => 'ID', 'date' => 'Data', 'type' => 'Tipo', 'status' => 'Status',
            'description' => 'Nome / Descrição', 'notes' => 'Observações', 'category' => 'Categoria',
            'subcategory' => 'Subcategoria', 'payment_method' => 'Pagamento',
            'owner' => 'Carteira', 'shares' => 'Divisão entre participantes',
            'recurrence' => 'Recorrência', 'amount' => 'Valor integral',
            'entrada' => 'Entrada na sua conta', 'saida' => 'Saída da sua conta',
            'created_at' => 'Criado em', 'updated_at' => 'Atualizado em',
        ];
        $request->validate([
            'comparar' => ['sometimes', 'in:1'],
            'tipo' => ['sometimes', Rule::in(['entrada', 'saida', 'geral'])],
            'mes' => ['sometimes', 'required', 'date_format:Y-m'],
            'personalizar' => ['sometimes', 'in:1'],
            'titulo' => ['nullable', 'string', 'max:150'],
            'layout' => ['sometimes', 'in:1'],
            'exibir' => ['sometimes', 'array'],
            'exibir.*' => ['string', Rule::in(['identificacao', 'periodo', 'explicacao', 'totais'])],
            'imprimir' => ['sometimes', 'in:1'],
            'campos' => ['required_with:personalizar', 'array', 'min:1'],
            'campos.*' => ['string', Rule::in(array_keys($columns))],
        ], ['campos.required_with' => 'Selecione pelo menos uma informação para o relatório.']);
        $reportType = $request->input('tipo', 'geral');
        if ($reportType !== 'geral') {
            $this->ensureSectionShared($reportType);
            unset($columns[$reportType === 'entrada' ? 'saida' : 'entrada']);
        }
        $summaryColumns = $reportType === 'geral'
            ? ['entrada' => 'Entradas', 'saida' => 'Saídas', 'saldo' => 'Saldo']
            : [$reportType => $reportType === 'entrada' ? 'Entradas' : 'Saídas'];
        $defaultTitle = match ($reportType) {
            'entrada' => 'Relatório mensal de entradas',
            'saida' => 'Relatório mensal de saídas',
            default => 'Relatório geral de entradas e saídas',
        };
        $selectedColumns = array_intersect_key($columns, array_flip($request->input('campos', array_keys($columns))));
        $month = $this->resolveMonth($request);
        $lancamentos = new Collection;

        foreach ([Lancamento::TYPE_ENTRADA => 'entradas', Lancamento::TYPE_SAIDA => 'saidas'] as $type => $section) {
            if (($reportType === 'geral' || $reportType === $type) && Carteira::canSee($section)) {
                $lancamentos = $lancamentos->concat($this->lancamentosFor($request, $type, $month));
            }
        }

        $rows = $lancamentos->unique('id')->sortBy([['date', 'asc'], ['id', 'asc']])
            ->map(function (Lancamento $lancamento) use ($request): array {
                return [
                    'lancamento' => $lancamento,
                    'entrada' => $lancamento->entradaFor($request->user()),
                    'saida' => $lancamento->saidaFor($request->user()),
                ];
            })->values();
        $totals = collect([Lancamento::STATUS_REALIZADO, Lancamento::STATUS_PREVISTO])
            ->mapWithKeys(function (string $status) use ($rows): array {
                $items = $rows->filter(fn (array $row): bool => $row['lancamento']->status === $status);
                $entrada = round($items->sum('entrada'), 2);
                $saida = round($items->sum('saida'), 2);

                return [$status => ['entrada' => $entrada, 'saida' => $saida, 'saldo' => round($entrada - $saida, 2)]];
            })->all();

        $comparison = null;
        if ($request->boolean('comparar') && $month) {
            $previousMonth = $month->copy()->subMonthNoOverflow();
            $previousRows = new Collection;
            foreach ([Lancamento::TYPE_ENTRADA => 'entradas', Lancamento::TYPE_SAIDA => 'saidas'] as $type => $section) {
                if (($reportType === 'geral' || $reportType === $type) && Carteira::canSee($section)) {
                    $previousRows = $previousRows->concat($this->lancamentosFor($request, $type, $previousMonth));
                }
            }
            $realized = $previousRows->unique('id')->where('status', 'realizado');
            $income = (float) $realized->sum(fn (Lancamento $row): float => $row->entradaFor($request->user()));
            $expense = (float) $realized->sum(fn (Lancamento $row): float => $row->saidaFor($request->user()));
            $comparison = ['month' => self::monthTitle($previousMonth), 'entrada' => $income, 'saida' => $expense, 'saldo' => round($income - $expense, 2)];
        }

        return view('pages.financeiro.relatorio', [
            'comparison' => $comparison,
            'reportType' => $reportType,
            'summaryColumns' => $summaryColumns,
            'reportTitle' => $request->input('titulo') ?: $defaultTitle,
            'displayOptions' => $request->has('layout') ? $request->input('exibir', []) : ['identificacao', 'periodo', 'explicacao', 'totais'],
            'printReport' => $request->boolean('imprimir'),
            'columns' => $columns,
            'selectedColumns' => $selectedColumns,
            'rows' => $rows,
            'totals' => $totals,
            'month' => $month,
            'monthTitle' => self::monthTitle($month),
            'owner' => Carteira::owner(),
            'user' => $request->user(),
            'isPartial' => $reportType === 'geral' && (! Carteira::canSee('entradas') || ! Carteira::canSee('saidas')),
        ]);
    }

    /**
     * Create a new Entrada or Saída. When marked as recurring, immediately
     * generates the next 12 "previsto" occurrences.
     */
    public function store(StoreLancamentoRequest $request): RedirectResponse|JsonResponse
    {
        $data = Arr::except($request->validated(), ['partes', 'installments']);
        $data['is_recurring_template'] = $request->boolean('is_recurring_template');
        $data['status'] ??= $data['is_recurring_template'] ? Lancamento::STATUS_PREVISTO : Lancamento::STATUS_REALIZADO;

        $this->ensureSectionShared($data['type']);

        DB::transaction(function () use ($request, $data): void {
            User::whereKey(Carteira::ownerId())->lockForUpdate()->firstOrFail();
            if (CategoriaFinanceira::where('type', $data['type'])->where('name', $data['category'])->where('is_deleted', true)->exists()) {
                throw ValidationException::withMessages(['category' => 'Esta categoria foi excluída. Selecione ou crie outra categoria.']);
            }
            $count = $request->integer('installments', 1);
            $cents = (int) round((float) $data['amount'] * 100);
            $group = $count > 1 ? (string) Str::uuid() : null;
            $anchor = Carbon::parse($data['date']);
            $partsByInstallment = array_fill(0, $count, []);
            $capacities = [];
            for ($index = 0; $index < $count; $index++) {
                $capacities[$index] = intdiv($cents, $count) + ($index < $cents % $count ? 1 : 0);
            }
            foreach ($request->input('partes', []) as $userId => $amount) {
                if ((int) $userId === (int) Carteira::ownerId()) {
                    continue;
                }
                $remaining = (int) round((float) $amount * 100);
                $base = intdiv($remaining, $count);
                for ($index = 0; $index < $count; $index++) {
                    $assigned = min($base, $capacities[$index]);
                    $partsByInstallment[$index][$userId] = $assigned;
                    $capacities[$index] -= $assigned;
                    $remaining -= $assigned;
                }
                for ($index = 0; $index < $count && $remaining > 0; $index++) {
                    $extra = min($remaining, $capacities[$index]);
                    $partsByInstallment[$index][$userId] += $extra;
                    $capacities[$index] -= $extra;
                    $remaining -= $extra;
                }
            }
            for ($index = 0; $index < $count; $index++) {
                $installment = $data;
                $installment['amount'] = (intdiv($cents, $count) + ($index < $cents % $count ? 1 : 0)) / 100;
                $installment['date'] = $anchor->copy()->addMonthsNoOverflow($index)->toDateString();
                if ($count > 1) {
                    $installment['installment_group'] = $group;
                    $installment['installment_number'] = $index + 1;
                    $installment['installment_count'] = $count;
                    $installment['status'] = $index === 0 ? $data['status'] : 'previsto';
                }
                $lancamento = Carteira::owner()->lancamentos()->create($installment);
                $lancamento->syncPartes(array_map(fn (int $amount): float => $amount / 100, $partsByInstallment[$index]));
                if ($lancamento->is_recurring_template) {
                    $lancamento->generateUpcomingOccurrences();
                }
            }
            FinanceiroActivity::record('Cadastrou '.$count.' lançamento(s): '.$data['description'].'.');
        });

        return $this->respondWith($request, $data['type']);
    }

    /**
     * Toggle a lançamento between previsto and realizado.
     */
    public function update(UpdateLancamentoRequest $request, Lancamento $lancamento): RedirectResponse|JsonResponse
    {
        $this->ensureSectionShared($lancamento->type);

        DB::transaction(function () use ($request, $lancamento): void {
            User::whereKey(Carteira::ownerId())->lockForUpdate()->firstOrFail();
            if ($request->has('category') && CategoriaFinanceira::where('type', $lancamento->type)->where('name', $request->input('category'))->where('is_deleted', true)->exists()) {
                throw ValidationException::withMessages(['category' => 'Esta categoria foi excluída. Selecione ou crie outra categoria.']);
            }
            $accountId = $request->input('account_id', $lancamento->account_id);
            if ($accountId && ContaFinanceira::onlyTrashed()->whereKey($accountId)->exists()) {
                throw ValidationException::withMessages(['account_id' => 'Esta conta foi excluída. Selecione uma conta ativa para alterar o lançamento.']);
            }
            $lancamento->update(Arr::except($request->validated(), 'partes'));
            FinanceiroActivity::record('Atualizou o lançamento “'.$lancamento->description.'”.');

            if ($request->has('partes')) {
                $lancamento->syncPartes($request->input('partes'));
            }

        });

        return $this->respondWith($request, $lancamento->type);
    }

    /**
     * Delete a lançamento.
     */
    public function destroy(Request $request, Lancamento $lancamento): RedirectResponse|JsonResponse
    {
        $type = $lancamento->type;
        $this->ensureSectionShared($type);
        Storage::disk('local')->delete($lancamento->files->pluck('path')->all());
        FinanceiroActivity::record('Excluiu o lançamento “'.$lancamento->description.'”.');
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
            'categories' => CategoriaFinanceira::namesFor($type),
            'accounts' => ContaFinanceira::all(),
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

        $lancamentos = $inMonth(Carteira::owner()->lancamentos()->with(['partes.user', 'user', 'account', 'files'])->where('type', $type))->get();

        // In their own carteira the user also sees — read-only — what
        // other carteiras' lançamentos take out of their account: their
        // part of an expense, or of an income its owner received from them.
        if (Carteira::isOwn() && $type === Lancamento::TYPE_SAIDA) {
            $lancamentos = $lancamentos->concat(
                $inMonth(Lancamento::sharedWith($request->user())->with(['partes.user', 'user', 'account', 'files']))
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
