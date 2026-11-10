<?php

namespace App\Http\Controllers;

use App\Models\CategoriaFinanceira;
use App\Models\ContaFinanceira;
use App\Models\FinanceiroActivity;
use App\Models\Lancamento;
use App\Models\Participacao;
use App\Models\TransferenciaFinanceira;
use App\Support\Carteira;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FinanceiroContaController extends Controller
{
    public function index(): View
    {
        return view('pages.financeiro.contas', [
            'accounts' => ContaFinanceira::with('user')->get(),
            'categories' => CategoriaFinanceira::all(),
            'transfers' => Carteira::hasRestrictions() ? collect() : TransferenciaFinanceira::latest('date')->limit(30)->get(),
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

    public function transfer(Request $request): RedirectResponse
    {
        abort_unless(Carteira::canEdit() && ! Carteira::hasRestrictions(), 403);
        $data = $request->validate([
            'source_id' => ['required', 'integer', Rule::exists('contas_financeiras', 'id')->where('user_id', Carteira::ownerId())],
            'destination_id' => ['required', 'integer', 'different:source_id', Rule::exists('contas_financeiras', 'id')->where('user_id', Carteira::ownerId())],
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

    public function category(Request $request): RedirectResponse
    {
        abort_unless(Carteira::canManage() && ! Carteira::hasRestrictions(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'type' => ['required', 'in:entrada,saida'],
        ]);
        CategoriaFinanceira::firstOrCreate($data);
        FinanceiroActivity::record('Criou a categoria “'.$data['name'].'”.');

        return back();
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
