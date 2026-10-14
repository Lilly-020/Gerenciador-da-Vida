@php
    $maxValue = max(1, collect($totaisPorCategoria)->max('amount'));
@endphp

<div class="rounded-2xl border border-white/10 bg-slate-900/70 p-4 shadow-lg shadow-black/20 sm:p-5">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-sm font-semibold text-white">{{ $title ?? 'Por categoria' }}</h3>
        <div class="flex items-center gap-3 text-[11px] text-slate-400">
            <span class="inline-flex items-center gap-1">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                Entradas
            </span>
            <span class="inline-flex items-center gap-1">
                <span class="h-1.5 w-1.5 rounded-full bg-rose-400"></span>
                Saídas
            </span>
        </div>
    </div>

    @if (empty($totaisPorCategoria))
        <p class="flex h-48 items-center justify-center text-xs text-slate-500">Sem lançamentos no período</p>
    @else
        <div class="flex h-48 items-end justify-center gap-2">
            @foreach ($totaisPorCategoria as $item)
                @php
                    $isEntrada = $item['type'] === 'entrada';
                @endphp
                <div
                    class="flex h-full max-w-20 min-w-0 flex-1 flex-col items-center justify-end gap-1"
                    title="{{ $item['category'] }} ({{ $isEntrada ? 'entrada' : 'saída' }}): @money($item['amount'])"
                >
                    <span class="max-w-full truncate text-[10px] text-slate-400">{{ \Illuminate\Support\Number::abbreviate($item['amount'], maxPrecision: 1) }}</span>
                    <div
                        class="w-full rounded-t-sm {{ $isEntrada ? 'bg-emerald-400/90' : 'bg-rose-400/90' }}"
                        style="height: {{ max(1, ($item['amount'] / $maxValue) * 100) }}%"
                    ></div>
                    <span class="max-w-full truncate text-[10px] text-slate-500">{{ $item['category'] }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
