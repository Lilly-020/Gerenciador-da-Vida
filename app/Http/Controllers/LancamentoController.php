<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLancamentoRequest;
use App\Http\Requests\UpdateLancamentoRequest;
use App\Models\Lancamento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LancamentoController extends Controller
{
    /**
     * List Entradas for the authenticated user.
     */
    public function entradas(Request $request): View
    {
        return $this->list($request, Lancamento::TYPE_ENTRADA);
    }

    /**
     * List Saídas for the authenticated user.
     */
    public function saidas(Request $request): View
    {
        return $this->list($request, Lancamento::TYPE_SAIDA);
    }

    /**
     * Create a new Entrada or Saída. When marked as recurring, immediately
     * generates the next 12 "previsto" occurrences.
     */
    public function store(StoreLancamentoRequest $request): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $data['is_recurring_template'] = $request->boolean('is_recurring_template');
        $data['status'] = Lancamento::STATUS_REALIZADO;

        $lancamento = $request->user()->lancamentos()->create($data);

        if ($lancamento->is_recurring_template) {
            $lancamento->generateUpcomingOccurrences();
        }

        return $this->respondWith($request, $lancamento->type);
    }

    /**
     * Toggle a lançamento between previsto and realizado.
     */
    public function update(UpdateLancamentoRequest $request, Lancamento $lancamento): RedirectResponse|JsonResponse
    {
        $lancamento->update($request->validated());

        return $this->respondWith($request, $lancamento->type);
    }

    /**
     * Delete a lançamento.
     */
    public function destroy(Request $request, Lancamento $lancamento): RedirectResponse|JsonResponse
    {
        $type = $lancamento->type;
        $lancamento->delete();

        return $this->respondWith($request, $type);
    }

    private function list(Request $request, string $type): View
    {
        $lancamentos = $request->user()->lancamentos()
            ->where('type', $type)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        return view('pages.financeiro.lancamentos', [
            'type' => $type,
            'categories' => Lancamento::categoriesFor($type),
            'lancamentos' => $lancamentos,
            'totalPrevisto' => $lancamentos->where('status', Lancamento::STATUS_PREVISTO)->sum('amount'),
            'totalRealizado' => $lancamentos->where('status', Lancamento::STATUS_REALIZADO)->sum('amount'),
        ]);
    }

    private function respondWith(Request $request, string $type): RedirectResponse|JsonResponse
    {
        if (! $request->wantsJson()) {
            return back();
        }

        $lancamentos = $request->user()->lancamentos()
            ->where('type', $type)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'list' => view('partials.financeiro.lancamentos-list', [
                'type' => $type,
                'lancamentos' => $lancamentos,
            ])->render(),
            'totals' => view('partials.financeiro.lancamentos-totals', [
                'totalPrevisto' => $lancamentos->where('status', Lancamento::STATUS_PREVISTO)->sum('amount'),
                'totalRealizado' => $lancamentos->where('status', Lancamento::STATUS_REALIZADO)->sum('amount'),
            ])->render(),
        ]);
    }
}
