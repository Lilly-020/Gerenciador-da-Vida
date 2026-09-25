<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvestimentoAporteRequest;
use App\Models\Investimento;
use App\Models\InvestimentoAporte;
use Illuminate\Http\RedirectResponse;

class InvestimentoAporteController extends Controller
{
    /**
     * Add a new contribution to an investment.
     */
    public function store(StoreInvestimentoAporteRequest $request, Investimento $investimento): RedirectResponse
    {
        $investimento->aportes()->create($request->validated());

        return back();
    }

    /**
     * Remove a contribution.
     */
    public function destroy(Investimento $investimento, InvestimentoAporte $aporte): RedirectResponse
    {
        abort_unless($aporte->investimento_id === $investimento->id, 404);

        $aporte->delete();

        return back();
    }
}
