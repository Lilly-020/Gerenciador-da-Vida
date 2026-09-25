<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustoFixoRequest;
use App\Http\Requests\UpdateCustoFixoRequest;
use App\Models\CustoFixo;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustoFixoController extends Controller
{
    /**
     * List the authenticated user's fixed costs, with this month's paid
     * status for each.
     */
    public function index(Request $request): View
    {
        $custoFixos = $request->user()->custoFixos()->with('lancamentos')->orderBy('due_day')->get();
        $today = Carbon::today();

        return view('pages.financeiro.custos-fixos', [
            'custoFixos' => $custoFixos,
            'total' => $custoFixos->where('status', CustoFixo::STATUS_ATIVO)->sum('amount'),
            'totalPago' => $custoFixos->where('status', CustoFixo::STATUS_ATIVO)
                ->filter(fn (CustoFixo $c): bool => $c->isPaidFor($today))
                ->sum('amount'),
        ]);
    }

    /**
     * Create a new fixed cost.
     */
    public function store(StoreCustoFixoRequest $request): RedirectResponse|JsonResponse
    {
        $request->user()->custoFixos()->create($request->validated());

        return $this->respondWith($request);
    }

    /**
     * Update a fixed cost's editable fields.
     */
    public function update(UpdateCustoFixoRequest $request, CustoFixo $custoFixo): RedirectResponse|JsonResponse
    {
        $custoFixo->update($request->validated());

        return $this->respondWith($request);
    }

    /**
     * Record this month's payment as an actual Saída.
     */
    public function pagar(Request $request, CustoFixo $custoFixo): RedirectResponse|JsonResponse
    {
        if (! $custoFixo->isPaidFor(Carbon::today())) {
            $custoFixo->markPaid(Carbon::today());
        }

        return $this->respondWith($request);
    }

    /**
     * Delete a fixed cost (its past payment records remain as Saídas).
     */
    public function destroy(Request $request, CustoFixo $custoFixo): RedirectResponse|JsonResponse
    {
        $custoFixo->delete();

        return $this->respondWith($request);
    }

    private function respondWith(Request $request): RedirectResponse|JsonResponse
    {
        if (! $request->wantsJson()) {
            return back();
        }

        $custoFixos = $request->user()->custoFixos()->with('lancamentos')->orderBy('due_day')->get();
        $today = Carbon::today();

        return response()->json([
            'list' => view('partials.financeiro.custos-fixos-list', ['custoFixos' => $custoFixos])->render(),
            'summary' => view('partials.financeiro.custos-fixos-summary', [
                'total' => $custoFixos->where('status', CustoFixo::STATUS_ATIVO)->sum('amount'),
                'totalPago' => $custoFixos->where('status', CustoFixo::STATUS_ATIVO)
                    ->filter(fn (CustoFixo $c): bool => $c->isPaidFor($today))
                    ->sum('amount'),
            ])->render(),
        ]);
    }
}
