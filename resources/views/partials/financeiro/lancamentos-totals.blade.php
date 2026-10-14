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

    @if ($participantes->isNotEmpty())
        <div class="col-span-full rounded-xl border border-white/10 bg-gradient-to-b from-slate-900/70 to-slate-900/40 p-4">
            <p class="text-xs font-medium tracking-wide text-slate-400 uppercase">Parte dos outros participantes</p>
            <p class="mt-1 text-xs text-slate-500">Os totais acima contam só a sua parte.</p>

            <div class="mt-3 space-y-2">
                @foreach ($participantes as $participante)
                    <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-1 border-t border-white/10 pt-2 text-sm first:border-t-0 first:pt-0">
                        <span class="font-semibold text-white">{{ $participante['user']->handle() }}</span>
                        <span class="flex flex-wrap items-center gap-x-5 gap-y-1">
                            <span class="text-slate-400">Realizado <span class="font-semibold text-white">@money($participante['realizado'])</span></span>
                            <span class="text-slate-400">Previsto <span class="font-semibold text-amber-300">@money($participante['previsto'])</span></span>
                            <span class="text-slate-400">Total <span class="font-semibold text-white">@money($participante['realizado'] + $participante['previsto'])</span></span>
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
