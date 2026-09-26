<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvestimentoRequest;
use App\Http\Requests\UpdateInvestimentoRequest;
use App\Models\Investimento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvestimentoController extends Controller
{
    /**
     * List the authenticated user's investments, each with its estimated
     * yield and current value.
     */
    public function index(Request $request): View
    {
        $investimentos = $request->user()->investimentos()->with('aportes')->orderByDesc('created_at')->get();

        return view('pages.financeiro.investimentos', [
            'investimentos' => $investimentos,
            'totalAportado' => $investimentos->sum(fn (Investimento $i): float => $i->totalAportado()),
            'totalRendimento' => $investimentos->sum(fn (Investimento $i): float => $i->rendimentoEstimado()),
        ]);
    }

    /**
     * Create a new investment.
     */
    public function store(StoreInvestimentoRequest $request): RedirectResponse|JsonResponse
    {
        $request->user()->investimentos()->create($request->validated());

        return back();
    }

    /**
     * Update an investment's own details (not its contributions).
     */
    public function update(UpdateInvestimentoRequest $request, Investimento $investimento): RedirectResponse
    {
        $investimento->update($request->validated());

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
