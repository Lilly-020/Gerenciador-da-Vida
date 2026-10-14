<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMonth;
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
    use ResolvesMonth;

    /**
     * List the authenticated user's fixed costs as they stand in the
     * selected month (the current one by default): which are due, and
     * which of those are already paid.
     */
    public function index(Request $request): View
    {
        return view('pages.financeiro.custos-fixos', $this->viewData($request));
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
        $today = Carbon::today();

        abort_unless($custoFixo->isDueIn($today), 422, 'Este custo fixo não vence neste mês.');

        if (! $custoFixo->isPaidFor($today)) {
            $custoFixo->markPaid($today);
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

        $data = $this->viewData($request);

        return response()->json([
            'list' => view('partials.financeiro.custos-fixos-list', $data)->render(),
            'summary' => view('partials.financeiro.custos-fixos-summary', $data)->render(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(Request $request): array
    {
        $month = $this->resolveMonth($request) ?? Carbon::today()->startOfMonth();
        $custoFixos = $request->user()->custoFixos()->with('lancamentos')->orderBy('due_day')->get();
        $dueInMonth = $custoFixos->where('status', CustoFixo::STATUS_ATIVO)
            ->filter(fn (CustoFixo $c): bool => $c->isDueIn($month));

        return [
            'custoFixos' => $custoFixos,
            'month' => $month,
            'mes' => $this->monthParam($month),
            'monthTitle' => self::monthTitle($month),
            'isCurrentMonth' => $month->isSameMonth(Carbon::today()),
            'total' => $dueInMonth->sum('amount'),
            'totalPago' => $dueInMonth->filter(fn (CustoFixo $c): bool => $c->isPaidFor($month))->sum('amount'),
        ];
    }
}
