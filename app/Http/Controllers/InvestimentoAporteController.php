<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvestimentoAporteRequest;
use App\Models\FinanceiroActivity;
use App\Models\Investimento;
use App\Models\InvestimentoAporte;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvestimentoAporteController extends Controller
{
    /**
     * Add a new contribution to an investment.
     */
    public function store(StoreInvestimentoAporteRequest $request, Investimento $investimento): RedirectResponse
    {
        DB::transaction(function () use ($request, $investimento): void {
            Investimento::whereKey($investimento->id)->lockForUpdate()->firstOrFail();
            $date = Carbon::parse($request->input('date'));
            if ($request->input('kind') === 'resgate' && (float) $request->input('amount') > $investimento->load('aportes')->patrimonioEstimado($date)) {
                throw ValidationException::withMessages(['amount' => 'O resgate supera o patrimônio estimado nessa data.']);
            }
            $investimento->aportes()->create($request->validated());
            if ($request->input('kind') === 'resgate') {
                $investimento->load('aportes');
                foreach ($investimento->aportes->pluck('date')->unique() as $movementDate) {
                    if ($investimento->patrimonioEstimado($movementDate) < -0.005) {
                        throw ValidationException::withMessages(['amount' => 'O resgate deixaria uma movimentação posterior sem saldo.']);
                    }
                }
            }
            FinanceiroActivity::record('Registrou '.($request->input('kind') === 'resgate' ? 'um resgate' : 'um aporte').' em “'.$investimento->name.'”.');
        });

        return back();
    }

    /**
     * Remove a contribution.
     */
    public function destroy(Investimento $investimento, InvestimentoAporte $aporte): RedirectResponse
    {
        abort_unless($aporte->investimento_id === $investimento->id, 404);

        DB::transaction(function () use ($aporte, $investimento): void {
            Investimento::whereKey($investimento->id)->lockForUpdate()->firstOrFail();
            $aporte->delete();
            $investimento->load('aportes');
            foreach ($investimento->aportes->pluck('date')->unique() as $movementDate) {
                if ($investimento->patrimonioEstimado($movementDate) < -0.005) {
                    throw ValidationException::withMessages(['amount' => 'A remoção deixaria um resgate sem saldo.']);
                }
            }
            FinanceiroActivity::record('Removeu uma movimentação de “'.$investimento->name.'”.');
        });

        return back();
    }
}
