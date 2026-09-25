@php
    $mainCards = [
        ['label' => 'Entradas', 'value' => $cards['entradas'], 'accent' => 'text-emerald-300'],
        ['label' => 'Saídas', 'value' => $cards['saidas'], 'accent' => 'text-rose-300'],
        ['label' => 'Investido', 'value' => $cards['investido'], 'accent' => 'text-sky-300'],
        ['label' => 'Saldo disponível', 'value' => $cards['saldo'], 'accent' => $cards['saldo'] >= 0 ? 'text-white' : 'text-rose-300'],
    ];

    $investmentCards = [
        ['label' => 'Patrimônio investido', 'value' => $cards['patrimonio_investido']],
        ['label' => 'Rendimento acumulado', 'value' => $cards['rendimento_acumulado']],
        ['label' => 'Rendimento no mês', 'value' => $cards['rendimento_mes']],
        ['label' => 'Rendimento no ano', 'value' => $cards['rendimento_ano']],
    ];
@endphp

<x-layout title="Financeiro" align="start">
    <div class="w-full max-w-6xl">
        <x-financeiro-subnav active="financeiro" />

        <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-white sm:text-3xl">Financeiro</h1>
                <p class="mt-1 text-sm text-slate-400">Visão geral — {{ $periods[$period] }}.</p>
            </div>

            <form method="GET" action="{{ route('financeiro', absolute: false) }}" class="flex flex-wrap items-end gap-2">
                <div>
                    <label for="period" class="sr-only">Período</label>
                    <select
                        id="period"
                        name="period"
                        onchange="this.form.submit()"
                        class="rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                    >
                        @foreach ($periods as $key => $label)
                            <option value="{{ $key }}" @selected($period === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($period === 'personalizado')
                    <div>
                        <label for="start" class="sr-only">De</label>
                        <input
                            id="start"
                            type="date"
                            name="start"
                            value="{{ $periodStart->toDateString() }}"
                            class="rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20 scheme-dark"
                        >
                    </div>
                    <div>
                        <label for="end" class="sr-only">Até</label>
                        <input
                            id="end"
                            type="date"
                            name="end"
                            value="{{ $periodEnd->toDateString() }}"
                            class="rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20 scheme-dark"
                        >
                    </div>
                    <button type="submit" class="rounded-lg bg-indigo-500/90 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500">
                        Aplicar
                    </button>
                @endif
            </form>
        </div>

        <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($mainCards as $card)
                <div class="rounded-xl border border-white/10 bg-gradient-to-b from-slate-900/70 to-slate-900/40 p-4">
                    <p class="text-xs font-medium tracking-wide text-slate-400 uppercase">{{ $card['label'] }}</p>
                    <p class="mt-2 text-xl font-semibold {{ $card['accent'] }} sm:text-2xl">@money($card['value'])</p>
                </div>
            @endforeach
        </div>

        <div class="mb-8 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($investmentCards as $card)
                <div class="rounded-xl border border-white/10 bg-gradient-to-b from-slate-900/70 to-slate-900/40 p-4">
                    <p class="text-xs font-medium tracking-wide text-slate-400 uppercase">{{ $card['label'] }}</p>
                    <p class="mt-2 text-xl font-semibold text-white sm:text-2xl">@money($card['value'])</p>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            @include('partials.financeiro.fluxo-chart', ['fluxoMensal' => $fluxoMensal])
            @include('partials.financeiro.despesas-categoria', ['despesasPorCategoria' => $despesasPorCategoria])
        </div>
    </div>
</x-layout>
