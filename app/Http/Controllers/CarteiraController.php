<?php

namespace App\Http\Controllers;

use App\Models\ContaFinanceira;
use App\Models\FinanceiroActivity;
use App\Models\Participacao;
use App\Support\Carteira;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CarteiraController extends Controller
{
    /**
     * Who the user shares their own Financeiro with, and the form to
     * invite someone else.
     */
    public function participantes(Request $request): View
    {
        $owner = Carteira::canManage() && ! Carteira::hasRestrictions() ? Carteira::owner() : $request->user();

        return view('pages.financeiro.participantes', [
            'accounts' => ContaFinanceira::where('user_id', $owner->id)->get(),
            'categories' => $owner->lancamentos()->select('category')->distinct()->pluck('category'),
            'lancamentos' => $owner->lancamentos()->latest('date')->limit(200)->get(),
            'activities' => FinanceiroActivity::with('actor')->where('user_id', $owner->id)->latest('id')->limit(50)->get(),
            'participacoes' => $owner->participacoesEnviadas()
                ->with('participant')
                ->where('tipo', Participacao::TIPO_FINANCAS)
                ->where('status', '!=', Participacao::STATUS_RECUSADO)
                ->latest()
                ->get(),
        ]);
    }

    /**
     * Switch the Financeiro to another person's carteira the user was
     * given access to, or (with no owner) back to their own.
     */
    public function trocar(Request $request): RedirectResponse
    {
        $ownerId = (int) $request->input('owner_id');

        if ($ownerId && Carteira::available()->contains('owner_id', $ownerId)) {
            $request->session()->put(Carteira::SESSION_KEY, $ownerId);
        } else {
            $request->session()->forget(Carteira::SESSION_KEY);
        }

        return redirect()->route('financeiro');
    }
}
