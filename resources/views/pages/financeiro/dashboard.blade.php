@php
    $mainCards = [
        ['label' => 'Entradas', 'value' => $cards['entradas'], 'accent' => 'text-emerald-300'],
        ['label' => 'Saídas', 'value' => $cards['saidas'], 'accent' => 'text-rose-300'],
        ['label' => 'Investido', 'value' => $cards['investido'], 'accent' => 'text-sky-300'],
        ['label' => 'Resultado do período', 'value' => $cards['saldo'], 'accent' => $cards['saldo'] >= 0 ? 'text-white' : 'text-rose-300'],
    ];

    $investmentCards = [
        ['label' => 'Patrimônio investido (estimado)', 'value' => $cards['patrimonio_investido']],
        ['label' => 'Rendimento acumulado (estimado)', 'value' => $cards['rendimento_acumulado']],
        ['label' => 'Rendimento no mês', 'value' => $cards['rendimento_mes']],
        ['label' => 'Rendimento no ano', 'value' => $cards['rendimento_ano']],
    ];
@endphp

<x-layout title="Financeiro" align="start">
    <div class="w-full min-w-0 max-w-6xl">
        <x-financeiro-subnav active="financeiro" />

        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div><p class="mb-1 text-xs font-semibold tracking-widest text-indigo-300 uppercase">Sua visão financeira</p><h1 class="text-2xl font-semibold text-white sm:text-3xl">Financeiro</h1><p class="mt-2 text-sm text-slate-400">{{ $periodStart->format('d/m/Y') }} a {{ $periodEnd->format('d/m/Y') }}</p></div>
            @if (\App\Support\Carteira::canSee('entradas') || \App\Support\Carteira::canSee('saidas'))<a href="{{ route('financeiro.relatorio', ['tipo' => 'geral', 'mes' => $periodStart->format('Y-m')], absolute: false) }}" class="rounded-lg border border-white/10 px-4 py-2 text-sm text-slate-300 hover:bg-white/5">Relatório geral de entradas e saídas ↗</a>@endif
        </div>
        <form data-dashboard-period method="GET" action="{{ route('financeiro', absolute: false) }}" class="mb-6 grid items-end gap-3 rounded-xl border border-white/10 bg-slate-900/40 p-4 sm:grid-cols-[1.2fr_1fr_1fr_auto]">
            <label class="grid min-w-0 gap-1.5 text-xs text-slate-400">Período<select name="period" id="period">@foreach ($periods as $key => $label)<option value="{{ $key }}" @selected($period === $key)>{{ $label }}</option>@endforeach</select></label>
            <label class="grid min-w-0 gap-1.5 text-xs text-slate-400">Data de início<input name="start" type="date" value="{{ $periodStart->toDateString() }}" required></label>
            <label class="grid min-w-0 gap-1.5 text-xs text-slate-400">Data de fim<input name="end" type="date" value="{{ $periodEnd->toDateString() }}" required></label>
            <button type="submit" class="rounded-lg bg-indigo-500 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-400">Aplicar filtros</button>
        </form>
        @include('partials.financeiro.planning-overview', ['summaryOnly' => true])

        <h2 class="mb-3 text-sm font-medium text-slate-300">Movimentos do período</h2>
        <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($mainCards as $card)
                <div class="rounded-xl border border-white/10 bg-gradient-to-b from-slate-900/70 to-slate-900/40 p-4">
                    <p class="text-xs font-medium tracking-wide text-slate-400 uppercase">{{ $card['label'] }}</p>
                    <p class="mt-2 text-xl font-semibold {{ $card['accent'] }} sm:text-2xl">@money($card['value'])</p>
                </div>
            @endforeach
        </div>

        @include('partials.financeiro.planning-overview', ['chartsOnly' => true])

        @if ($participantes->isNotEmpty())
            <div class="mb-4 rounded-xl border border-white/10 bg-gradient-to-b from-slate-900/70 to-slate-900/40 p-4">
                <p class="text-xs font-medium tracking-wide text-slate-400 uppercase">Parte dos outros participantes</p>
                <p class="mt-1 text-xs text-slate-500">Os cards acima contam só o que é seu. O que é de cada participante desta carteira fica aqui, separado, e conta na conta dele.</p>

                <div class="mt-3 space-y-2">
                    @foreach ($participantes as $participante)
                        <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-1 border-t border-white/10 pt-2 text-sm first:border-t-0 first:pt-0">
                            <span class="font-semibold text-white">{{ $participante['user']->handle() }}</span>
                            <span class="flex flex-wrap items-center gap-x-5 gap-y-1">
                                <span class="text-slate-400">Entradas <span class="font-semibold text-emerald-300">@money($participante['entradas'])</span></span>
                                <span class="text-slate-400">Saídas <span class="font-semibold text-rose-300">@money($participante['saidas'])</span></span>
                                <span class="text-slate-400">Saldo <span class="font-semibold text-white">@money($participante['entradas'] - $participante['saidas'])</span></span>
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if (\App\Support\Carteira::canSee('investimentos'))
        <h2 class="mb-3 text-sm font-medium text-slate-300">Investimentos acumulados · independente do período</h2>
        <div class="mb-8 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($investmentCards as $card)
                <div class="rounded-xl border border-white/10 bg-gradient-to-b from-slate-900/70 to-slate-900/40 p-4">
                    <p class="text-xs font-medium tracking-wide text-slate-400 uppercase">{{ $card['label'] }}</p>
                    <p class="mt-2 text-xl font-semibold text-white sm:text-2xl">@money($card['value'])</p>
                </div>
            @endforeach
        </div>
        @endif

        <div class="mb-4">
            @include('partials.financeiro.categorias-chart', [
                'totaisPorCategoria' => $totaisPorCategoria,
                'title' => $gastosConjuntos === null ? 'Por categoria' : 'Por categoria — gastos individuais',
            ])
        </div>

        @if ($gastosConjuntos !== null)
            <div class="mb-4">
                @include('partials.financeiro.categorias-chart', [
                    'totaisPorCategoria' => $gastosConjuntos,
                    'title' => 'Por categoria — gastos conjuntos',
                ])
            </div>
        @endif

        @include('partials.financeiro.despesas-categoria', ['despesasPorCategoria' => $despesasPorCategoria])
    </div>
</x-layout>
