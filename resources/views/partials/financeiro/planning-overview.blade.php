<div class="mb-6 grid gap-4 lg:grid-cols-3">
    <div class="rounded-2xl border border-indigo-400/25 bg-indigo-500/10 p-5">
        <p class="text-sm font-medium text-indigo-200">Saldo disponível</p>
        <p class="mt-2 text-2xl font-semibold tabular-nums text-white">@money($cards['saldo_disponivel'])</p>
        <p class="mt-2 text-xs text-slate-400">Saldo em {{ $periodEnd->copy()->min(today())->format('d/m/Y') }}.</p>
    </div>

    <section aria-labelledby="financeiro-pendencias" class="rounded-2xl border border-white/10 bg-slate-900/60 p-5">
        <h2 id="financeiro-pendencias" class="text-sm font-medium text-slate-200">Pendências até {{ $periodEnd->format('d/m/Y') }}</h2>
        <dl class="mt-2 grid grid-cols-2 gap-x-4">
            <div>
                <dt class="text-xs text-slate-400">A receber</dt>
                <dd class="mt-1 wrap-anywhere text-lg font-semibold tabular-nums text-emerald-300">@money($cards['a_receber'])</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-400">A pagar</dt>
                <dd class="mt-1 wrap-anywhere text-lg font-semibold tabular-nums text-rose-300">@money($cards['a_pagar'])</dd>
            </div>
        </dl>
        <p class="mt-2 text-xs text-slate-400">Inclui pendências de períodos anteriores.</p>
    </section>

    <div class="rounded-2xl border border-white/10 bg-slate-900/60 p-5">
        <p class="text-sm font-medium text-slate-200">Saldo projetado</p>
        <p class="mt-2 text-2xl font-semibold tabular-nums text-amber-300">@money($cards['saldo_projetado'])</p>
        <p class="mt-2 text-xs text-slate-400">Saldo disponível + a receber − a pagar.</p>
    </div>
</div>
