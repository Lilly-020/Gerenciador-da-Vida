<div id="lancamentos-totals" class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
    <div class="rounded-xl border border-white/10 bg-gradient-to-b from-slate-900/70 to-slate-900/40 p-4">
        <p class="text-xs font-medium tracking-wide text-slate-400 uppercase">Realizado</p>
        <p class="mt-2 text-2xl font-semibold text-white">@money($totalRealizado)</p>
    </div>
    <div class="rounded-xl border border-white/10 bg-gradient-to-b from-slate-900/70 to-slate-900/40 p-4">
        <p class="text-xs font-medium tracking-wide text-slate-400 uppercase">Previsto</p>
        <p class="mt-2 text-2xl font-semibold text-amber-300">@money($totalPrevisto)</p>
    </div>
    <div class="rounded-xl border border-white/10 bg-gradient-to-b from-slate-900/70 to-slate-900/40 p-4">
        <p class="text-xs font-medium tracking-wide text-slate-400 uppercase">Total</p>
        <p class="mt-2 text-2xl font-semibold text-white">@money($totalRealizado + $totalPrevisto)</p>
    </div>
</div>
