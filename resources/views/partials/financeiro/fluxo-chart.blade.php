@php
    $maxValue = max(1, collect($fluxoMensal)->flatMap(fn ($m) => [$m['entradas'], $m['saidas']])->max());
    $hasData = collect($fluxoMensal)->sum('entradas') + collect($fluxoMensal)->sum('saidas') > 0;
@endphp

<div class="rounded-2xl border border-white/10 bg-slate-900/70 p-4 shadow-lg shadow-black/20 sm:p-5">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-sm font-semibold text-white">Entradas × Saídas</h3>
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

    @if (! $hasData)
        <p class="flex h-40 items-center justify-center text-xs text-slate-500">Sem lançamentos no período</p>
    @else
        <div class="flex h-40 items-end gap-2">
            @foreach ($fluxoMensal as $month)
                @php
                    $entradaPct = ($month['entradas'] / $maxValue) * 100;
                    $saidaPct = ($month['saidas'] / $maxValue) * 100;
                @endphp
                <div
                    class="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1"
                    title="{{ $month['sublabel'] }}: entradas @money($month['entradas']) · saídas @money($month['saidas']) · saldo @money($month['saldo'])"
                >
                    <div class="flex h-full w-full items-end justify-center gap-0.5">
                        <div class="w-1/2 min-w-0 rounded-t-sm bg-emerald-400/90" style="height: {{ $entradaPct }}%"></div>
                        <div class="w-1/2 min-w-0 rounded-t-sm bg-rose-400/90" style="height: {{ $saidaPct }}%"></div>
                    </div>
                    <span class="truncate text-[10px] {{ $month['isCurrent'] ? 'font-semibold text-indigo-300' : 'text-slate-500' }}">
                        {{ $month['label'] }}
                    </span>
                </div>
            @endforeach
        </div>
    @endif
</div>
