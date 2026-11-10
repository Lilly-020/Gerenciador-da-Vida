<?php

namespace App\Http\Controllers;

use App\Models\CategoriaFinanceira;
use App\Models\ContaFinanceira;
use App\Models\FinanceiroActivity;
use App\Models\Investimento;
use App\Models\InvestimentoAporte;
use App\Models\Lancamento;
use App\Models\Participacao;
use App\Models\TransferenciaFinanceira;
use App\Models\User;
use App\Support\Carteira;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FinanceiroContaController extends Controller
{
    public function index(): View
    {
        return view('pages.financeiro.contas', [
            'accounts' => ContaFinanceira::with('user')->get(),
            'categories' => CategoriaFinanceira::where('is_deleted', false)->get(),
            'transfers' => Carteira::hasRestrictions() ? collect() : TransferenciaFinanceira::with(['source', 'destination'])->latest('date')->limit(30)->get(),
            'activities' => Carteira::canManage() && ! Carteira::hasRestrictions() ? FinanceiroActivity::with('actor')->latest('id')->limit(50)->get() : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(Carteira::canManage() && ! Carteira::hasRestrictions(), 403);
        ContaFinanceira::create($request->validate([
            'name' => ['required', 'string', 'max:255'], 'kind' => ['required', 'in:banco,dinheiro,outro'],
            'opening_balance' => ['required', 'numeric', 'between:-999999999999.99,999999999999.99'],
            'opening_date' => ['required', 'date'],
        ]));
        FinanceiroActivity::record('Criou uma conta financeira.');

        return back()->with('status', 'Conta criada.');
    }

    public function update(Request $request, ContaFinanceira $conta): RedirectResponse
    {
        abort_unless(Carteira::canManage() && ! Carteira::hasRestrictions(), 403);
        $conta->update($request->validate(['name' => ['required', 'string', 'max:255']]));
        FinanceiroActivity::record('Renomeou uma conta financeira.');

        return back();
    }

    public function deletionImpact(ContaFinanceira $conta): JsonResponse
    {
        abort_unless(Carteira::canManage() && ! Carteira::hasRestrictions(), 403);

        return response()->json($this->accountDeletionImpact($conta));
    }

    /** @return array{entries: int, transfers: int, investments: int, attachments: int, token: string} */
    private function accountDeletionImpact(ContaFinanceira $conta): array
    {
        $entries = Lancamento::with(['files', 'partes'])->where('account_id', $conta->id)->orderBy('id')->get();
        $transfers = TransferenciaFinanceira::where(fn (Builder $query): Builder => $query->where('source_id', $conta->id)->orWhere('destination_id', $conta->id))->orderBy('id')->get();
        $investments = InvestimentoAporte::where('account_id', $conta->id)->orderBy('id')->get();

        return [
            'entries' => $entries->count(),
            'transfers' => $transfers->count(),
            'investments' => $investments->count(),
            'attachments' => $entries->sum(fn (Lancamento $entry): int => $entry->files->count()),
            'token' => hash('sha256', json_encode([$conta->getAttributes(), $entries->toArray(), $transfers->toArray(), $investments->toArray()], JSON_THROW_ON_ERROR)),
        ];
    }

    private function deleteAccountMovements(ContaFinanceira $conta, string $token): void
    {
        if (! hash_equals($this->accountDeletionImpact($conta)['token'], $token)) {
            throw ValidationException::withMessages(['conta' => 'As movimentações da conta mudaram. Confira novamente o aviso antes de excluir.']);
        }
        $entries = Lancamento::with('files')->where('account_id', $conta->id)->get();
        $paths = $entries->flatMap(fn (Lancamento $entry): Collection => $entry->files->pluck('path'))->all();
        $investmentIds = InvestimentoAporte::where('account_id', $conta->id)->pluck('investimento_id')->unique();
        $investments = Investimento::whereIn('id', $investmentIds)->lockForUpdate()->get();
        InvestimentoAporte::where('account_id', $conta->id)->delete();
        foreach ($investments as $investment) {
            $investment->load('aportes');
            foreach ($investment->aportes->pluck('date')->unique() as $date) {
                if ($investment->patrimonioEstimado($date) < -0.005) {
                    throw ValidationException::withMessages(['conta' => 'A exclusão deixaria resgates de um investimento sem saldo. Corrija as movimentações desse investimento antes de excluir a conta.']);
                }
            }
        }
        Lancamento::where('account_id', $conta->id)->delete();
        TransferenciaFinanceira::where(fn (Builder $query): Builder => $query->where('source_id', $conta->id)->orWhere('destination_id', $conta->id))->delete();
        $name = $conta->name;
        $conta->forceDelete();
        FinanceiroActivity::record('Excluiu definitivamente a conta “'.$name.'” cadastrada por engano e todas as movimentações vinculadas.');
        DB::afterCommit(fn (): bool => Storage::disk('local')->delete($paths));
    }

    public function destroy(Request $request, ContaFinanceira $conta): RedirectResponse|JsonResponse
    {
        abort_unless(Carteira::canManage() && ! Carteira::hasRestrictions(), 403);

        $data = $request->validate([
            'delete_movements' => ['sometimes', 'boolean'],
            'confirm_mistake' => $request->boolean('delete_movements') ? ['required', 'accepted'] : ['sometimes', 'boolean'],
            'impact_token' => $request->boolean('delete_movements') ? ['required', 'string', 'size:64'] : ['sometimes', 'string'],
        ]);
        $deleteMovements = $request->boolean('delete_movements');

        DB::transaction(function () use ($conta, $deleteMovements, $data): void {
            User::whereKey(Carteira::ownerId())->lockForUpdate()->firstOrFail();
            $conta = ContaFinanceira::whereKey($conta->id)->lockForUpdate()->firstOrFail();
            if ($deleteMovements) {
                $this->deleteAccountMovements($conta, $data['impact_token']);

                return;
            }
            $balance = $conta->opening_date->isFuture() ? (float) $conta->opening_balance : $conta->balanceAsOf(fullLedger: true);
            if ((int) round($balance * 100) !== 0) {
                throw ValidationException::withMessages(['conta' => $balance > 0
                    ? 'Esta conta só pode ser excluída quando todo o dinheiro tiver saído. Transfira ou retire o saldo antes de excluir.'
                    : 'Esta conta só pode ser excluída com saldo zerado. Regularize o saldo negativo antes de excluir.']);
            }
            $conta->delete();
            FinanceiroActivity::record('Excluiu a conta “'.$conta->name.'”, com saldo zerado.');
        });

        $request->session()->flash('status', $deleteMovements ? 'Conta e movimentações excluídas definitivamente.' : 'Conta excluída. O histórico foi preservado.');

        return $request->expectsJson() ? response()->json(['message' => 'Conta excluída.']) : back();
    }

    public function transfer(Request $request): RedirectResponse
    {
        abort_unless(Carteira::canEdit() && ! Carteira::hasRestrictions(), 403);
        $data = $request->validate([
            'source_id' => ['required', 'integer', Rule::exists('contas_financeiras', 'id')->whereNull('deleted_at')->where('user_id', Carteira::ownerId())],
            'destination_id' => ['required', 'integer', 'different:source_id', Rule::exists('contas_financeiras', 'id')->whereNull('deleted_at')->where('user_id', Carteira::ownerId())],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'date' => ['required', 'date', 'before_or_equal:today'], 'notes' => ['nullable', 'string', 'max:255'],
        ], [
            'source_id.required' => 'Escolha a conta de origem.',
            'source_id.exists' => 'Escolha uma conta de origem válida.',
            'destination_id.required' => 'Escolha a conta de destino.',
            'destination_id.exists' => 'Escolha uma conta de destino válida.',
            'destination_id.different' => 'Escolha uma conta de destino diferente da origem.',
            'amount.required' => 'Informe o valor da transferência.',
            'amount.numeric' => 'Informe um valor válido.',
            'amount.min' => 'O valor da transferência deve ser maior que zero.',
            'amount.max' => 'O valor da transferência ultrapassa o limite permitido.',
            'date.required' => 'Informe a data da transferência.',
            'date.date' => 'Informe uma data válida.',
            'date.before_or_equal' => 'A data da transferência não pode estar no futuro.',
        ]);
        $accounts = ContaFinanceira::whereIn('id', [$data['source_id'], $data['destination_id']])->get();
        if ($accounts->contains(fn (ContaFinanceira $account): bool => $account->opening_date->gt($data['date']))) {
            throw ValidationException::withMessages(['date' => 'Escolha uma data igual ou posterior ao saldo inicial das duas contas.']);
        }
        DB::transaction(function () use ($data): void {
            TransferenciaFinanceira::create($data);
            FinanceiroActivity::record('Registrou uma transferência entre contas, sem receita ou despesa.');
        });

        return back()->with('status', 'Transferência registrada.');
    }

    public function category(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless(Carteira::canManage() && ! Carteira::hasRestrictions(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'type' => ['required', 'in:entrada,saida'],
        ]);
        CategoriaFinanceira::updateOrCreate($data, ['is_deleted' => false]);
        FinanceiroActivity::record('Criou a categoria “'.$data['name'].'”.');

        return $request->expectsJson() ? $this->categories($request) : back();
    }

    public function categories(Request $request): JsonResponse
    {
        $type = $request->validate(['type' => ['required', 'in:entrada,saida']])['type'];
        abort_unless(Carteira::canSee($type === 'entrada' ? 'entradas' : 'saidas'), 403);
        $counts = Lancamento::where('type', $type)->select('category')->selectRaw('COUNT(*) as links')->groupBy('category')->pluck('links', 'category');
        $canManage = Carteira::canManage() && ! Carteira::hasRestrictions();

        $invitations = $canManage ? Participacao::where('owner_id', Carteira::ownerId())->where('tipo', 'financas')->get() : collect();

        return response()->json([
            'canManage' => $canManage,
            'categories' => collect(CategoriaFinanceira::namesFor($type))->map(function (string $name) use ($counts, $invitations): array {
                $entries = (int) ($counts[$name] ?? 0);
                $sharing = $invitations->filter(fn (Participacao $invite): bool => in_array($name, $invite->allowed_categories ?? [], true))->count();

                return ['name' => $name, 'links' => $entries + $sharing, 'entries_count' => $entries, 'sharing_count' => $sharing];
            })->values(),
        ]);
    }

    public function destroyCategory(Request $request): JsonResponse
    {
        abort_unless(Carteira::canManage() && ! Carteira::hasRestrictions(), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'type' => ['required', 'in:entrada,saida']]);
        abort_unless(Carteira::canSee($data['type'] === 'entrada' ? 'entradas' : 'saidas'), 403);

        return DB::transaction(function () use ($data): JsonResponse {
            User::whereKey(Carteira::ownerId())->lockForUpdate()->firstOrFail();
            $links = Lancamento::where('type', $data['type'])->where('category', $data['name'])->count();
            $sharingLinks = Participacao::where('owner_id', Carteira::ownerId())->where('tipo', 'financas')->get()
                ->filter(fn (Participacao $invite): bool => in_array($data['name'], $invite->allowed_categories ?? [], true))->count();
            if ($links > 0 || $sharingLinks > 0) {
                return response()->json(['message' => "Não é possível excluir: esta categoria tem {$links} lançamento(s) e {$sharingLinks} compartilhamento(s) vinculados.", 'links' => $links, 'sharingLinks' => $sharingLinks], 422);
            }
            abort_unless(in_array($data['name'], CategoriaFinanceira::namesFor($data['type']), true), 404);
            CategoriaFinanceira::updateOrCreate($data, ['is_deleted' => true]);
            FinanceiroActivity::record('Excluiu a categoria “'.$data['name'].'”.');

            return response()->json(['message' => 'Categoria excluída.']);
        });
    }

    public function updateCategory(Request $request, CategoriaFinanceira $categoria): RedirectResponse
    {
        abort_unless(Carteira::canManage() && ! Carteira::hasRestrictions(), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:255', Rule::unique('categorias_financeiras')->where('user_id', Carteira::ownerId())->where('type', $categoria->type)->ignore($categoria)]]);
        DB::transaction(function () use ($categoria, $data): void {
            Lancamento::where('type', $categoria->type)->where('category', $categoria->name)->update(['category' => $data['name']]);
            foreach (Participacao::where('owner_id', Carteira::ownerId())->where('tipo', 'financas')->whereNotNull('allowed_categories')->get() as $invitation) {
                $invitation->update(['allowed_categories' => array_map(fn (string $name): string => $name === $categoria->name ? $data['name'] : $name, $invitation->allowed_categories)]);
            }
            $categoria->update($data);
            FinanceiroActivity::record('Renomeou uma categoria financeira.');
        });

        return back();
    }
}
