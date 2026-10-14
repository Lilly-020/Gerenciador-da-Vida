<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMonth;
use App\Http\Requests\StoreCustoFixoRequest;
use App\Http\Requests\UpdateCustoFixoRequest;
use App\Models\CustoFixo;
use App\Models\User;
use App\Support\Carteira;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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
        Carteira::owner()->custoFixos()->create($request->validated());

        return $this->respondWith($request);
    }

    /**
     * Update a fixed cost — either only as it stands in the month being
     * viewed (`scope=mes`), or the cost itself, for every month. The latter
     * also drops any single-month ajustes, so "todos os meses" really does
     * leave every month with the values just saved.
     */
    public function update(UpdateCustoFixoRequest $request, CustoFixo $custoFixo): RedirectResponse|JsonResponse
    {
        $data = Arr::except($request->validated(), 'scope');

        if ($request->input('scope') === UpdateCustoFixoRequest::SCOPE_MES) {
            $custoFixo->adjustFor($this->viewedMonth($request), $data);
        } else {
            $custoFixo->update($data);
            $custoFixo->ajustes()->delete();
        }

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
        $month = $this->viewedMonth($request);
        $custoFixos = Carteira::owner()->custoFixos()->with(['lancamentos', 'ajustes'])->orderBy('due_day')->get();
        $dueInMonth = $custoFixos->where('status', CustoFixo::STATUS_ATIVO)
            ->filter(fn (CustoFixo $c): bool => $c->isDueIn($month));

        // Totals count only the logged-in user's share of each cost; the
        // other members of the carteira get the same pair of totals apart.
        $totalsOf = fn (User $member): array => [
            'total' => (float) $dueInMonth->sum(fn (CustoFixo $c): float => $c->shareFor($month, $member)),
            'totalPago' => (float) $dueInMonth
                ->filter(fn (CustoFixo $c): bool => $c->isPaidFor($month))
                ->sum(fn (CustoFixo $c): float => $c->shareFor($month, $member)),
        ];
        $membros = Carteira::members();

        return [
            'custoFixos' => $custoFixos,
            'membros' => $membros,
            'month' => $month,
            'mes' => $this->monthParam($month),
            'monthTitle' => self::monthTitle($month),
            'isCurrentMonth' => $month->isSameMonth(Carbon::today()),
            ...$totalsOf($request->user()),
            'participantes' => $membros
                ->reject(fn (User $member): bool => $member->is($request->user()))
                ->map(fn (User $member): array => ['user' => $member, ...$totalsOf($member)])
                ->values(),
        ];
    }

    private function viewedMonth(Request $request): Carbon
    {
        return $this->resolveMonth($request) ?? Carbon::today()->startOfMonth();
    }
}
