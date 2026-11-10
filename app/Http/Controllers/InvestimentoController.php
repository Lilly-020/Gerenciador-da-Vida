<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvestimentoRequest;
use App\Http\Requests\UpdateInvestimentoRequest;
use App\Models\ContaFinanceira;
use App\Models\FinanceiroActivity;
use App\Models\Investimento;
use App\Support\Carteira;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InvestimentoController extends Controller
{
    /**
     * List the authenticated user's investments, each with its estimated
     * yield and current value.
     */
    public function index(Request $request): View
    {
        $investimentos = Carteira::owner()->investimentos()->with('aportes')->orderByDesc('created_at')->get();

        return view('pages.financeiro.investimentos', [
            'investimentos' => $investimentos,
            'accounts' => ContaFinanceira::all(),
            'totalAportado' => $investimentos->sum(fn (Investimento $i): float => $i->totalAportado()),
            'totalRendimento' => $investimentos->sum(fn (Investimento $i): float => $i->rendimentoEstimado()),
        ]);
    }

    /**
     * Create a new investment.
     */
    public function store(StoreInvestimentoRequest $request): RedirectResponse|JsonResponse
    {
        DB::transaction(function () use ($request): void {
            $investimento = Carteira::owner()->investimentos()->create(Arr::except($request->validated(), ['initial_amount', 'initial_date', 'account_id']));
            if ($request->input('initial_amount') > 0) {
                $investimento->aportes()->create(['amount' => $request->input('initial_amount'), 'date' => $request->input('initial_date') ?: today(), 'account_id' => $request->input('account_id')]);
            }
            FinanceiroActivity::record('Cadastrou o investimento “'.$investimento->name.'”.');
        });

        return back();
    }

    /**
     * Update an investment's own details (not its contributions).
     */
    public function update(UpdateInvestimentoRequest $request, Investimento $investimento): RedirectResponse
    {
        $investimento->update(Arr::except($request->validated(), ['initial_amount', 'initial_date', 'account_id']));
        FinanceiroActivity::record('Editou o investimento “'.$investimento->name.'”.');

        return back();
    }

    /**
     * Delete an investment and its contributions.
     */
    public function destroy(Investimento $investimento): RedirectResponse
    {
        $investimento->delete();

        return back();
    }
}
