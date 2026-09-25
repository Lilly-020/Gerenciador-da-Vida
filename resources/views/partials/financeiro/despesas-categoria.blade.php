<div class="rounded-2xl border border-white/10 bg-slate-900/70 p-4 shadow-lg shadow-black/20 sm:p-5">
    <h3 class="mb-4 text-sm font-semibold text-white">Despesas por categoria</h3>

    @if (empty($despesasPorCategoria))
        <p class="flex h-40 items-center justify-center text-xs text-slate-500">Sem despesas no período</p>
    @else
        <div class="space-y-3">
            @foreach ($despesasPorCategoria as $item)
                <div>
                    <div class="mb-1 flex items-center justify-between gap-2 text-xs text-slate-300">
                        <span class="wrap-break-word">{{ $item['category'] }}</span>
                        <span class="shrink-0 text-slate-400">{{ $item['percentage'] }}% · @money($item['amount'])</span>
                    </div>
                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-white/5">
                        <div class="h-full rounded-full bg-indigo-400/80" style="width: {{ $item['percentage'] }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
