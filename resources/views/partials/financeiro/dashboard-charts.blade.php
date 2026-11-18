@php
    $monthlyMax = max($timeline->max('income'), $timeline->max('expense'));
    $expenseTotal = array_sum(array_column($despesasPorCategoria, 'amount'));
    $expenseSlices = array_slice($despesasPorCategoria, 0, 5);
    $remainingExpenses = array_sum(array_column(array_slice($despesasPorCategoria, 5), 'amount'));
    if ($remainingExpenses > 0) {
        $expenseSlices[] = ['category' => 'Demais categorias', 'amount' => $remainingExpenses];
    }

    $colors = ['#818cf8', '#38bdf8', '#2dd4bf', '#fbbf24', '#f472b6', '#94a3b8'];
    $stops = [];
    $position = 0;
    foreach ($expenseSlices as $index => $slice) {
        $nextPosition = $position + ($slice['amount'] / $expenseTotal) * 100;
        $stops[] = $colors[$index].' '.$position.'% '.$nextPosition.'%';
        $position = $nextPosition;
    }
@endphp

<div class="mb-6 grid gap-4 lg:grid-cols-2">
    <section class="min-w-0 rounded-xl border border-white/10 bg-slate-900/50 p-4">
        <h2 class="text-sm font-semibold text-white">Entradas e saídas por mês</h2>
        <p class="mt-1 text-xs text-slate-400">Valores realizados nos seis meses até o fim do período.</p>
        @if ($monthlyMax > 0)
            <div class="mt-4 flex flex-wrap gap-4 text-xs text-slate-300">
                <span class="flex items-center gap-2"><span class="size-2 rounded-full bg-emerald-400"></span>Entradas</span>
                <span class="flex items-center gap-2"><span class="size-2 rounded-full bg-rose-400"></span>Saídas</span>
            </div>
            <ul class="mt-4 grid grid-cols-6 gap-1.5 sm:gap-3">
                @foreach ($timeline as $point)
                    <li class="group relative min-w-0 rounded-sm focus-visible:outline-2 focus-visible:outline-indigo-400" tabindex="0">
                        <div class="flex h-36 items-end justify-center gap-1 border-b border-white/15" aria-hidden="true">
                            <div class="w-2/5 max-w-7 rounded-t-sm bg-emerald-400" style="height: {{ $point['income'] / $monthlyMax * 100 }}%"></div>
                            <div class="w-2/5 max-w-7 rounded-t-sm bg-rose-400" style="height: {{ $point['expense'] / $monthlyMax * 100 }}%"></div>
                        </div>
                        <p class="mt-2 text-center text-[10px] tabular-nums text-slate-400 sm:text-xs">{{ $point['month'] }}</p>
                        <div class="pointer-events-none absolute bottom-full z-10 mb-1 w-max rounded-lg border border-white/10 bg-slate-950 px-2 py-1 text-xs text-slate-200 opacity-0 group-hover:opacity-100 group-focus:opacity-100 {{ $loop->index < 3 ? 'left-0' : 'right-0' }}">
                            <p>Entradas @money($point['income'])</p>
                            <p>Saídas @money($point['expense'])</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="mt-5 text-sm text-slate-400">Sem entradas ou saídas realizadas nesses meses.</p>
        @endif
    </section>

    <section class="min-w-0 rounded-xl border border-white/10 bg-slate-900/50 p-4">
        <h2 class="text-sm font-semibold text-white">Despesas por categoria</h2>
        <p class="mt-1 text-xs text-slate-400">Saídas realizadas no período selecionado.</p>
        @if ($expenseTotal > 0)
            <div class="mt-5 flex flex-wrap items-center gap-5">
                <div class="mx-auto grid size-32 shrink-0 place-items-center rounded-full" style="background: conic-gradient({{ implode(', ', $stops) }})" aria-hidden="true">
                    <div class="grid size-23 place-items-center rounded-full bg-slate-900 p-1 text-center text-[11px] font-semibold tabular-nums wrap-anywhere text-white">@money($expenseTotal)</div>
                </div>
                <ul class="min-w-40 flex-1 space-y-2 text-xs">
                    @foreach ($expenseSlices as $index => $slice)
                        <li class="flex items-baseline justify-between gap-2">
                            <span class="flex min-w-0 items-baseline gap-2 text-slate-300"><span class="size-2 shrink-0 rounded-full" style="background-color: {{ $colors[$index] }}"></span><span class="wrap-anywhere">{{ $slice['category'] }}</span></span>
                            <span class="shrink-0 tabular-nums text-slate-200">@money($slice['amount'])</span>
                        </li>
                    @endforeach
                </ul>
            </div>
            <span class="sr-only">Total das despesas @money($expenseTotal)</span>
        @else
            <p class="mt-5 text-sm text-slate-400">Sem despesas realizadas no período.</p>
        @endif
    </section>
</div>
