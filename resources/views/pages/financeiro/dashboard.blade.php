@php
    $mainCards = [
        ['label' => 'Entradas', 'value' => $cards['entradas'], 'expected' => $previsao['entradas'], 'accent' => 'text-emerald-300'],
        ['label' => 'Saídas', 'value' => $cards['saidas'], 'expected' => $previsao['saidas'], 'accent' => 'text-rose-300'],
        ['label' => 'Investido', 'value' => $cards['investido'], 'accent' => 'text-sky-300'],
        ['label' => 'Resultado', 'value' => $cards['saldo'], 'accent' => $cards['saldo'] >= 0 ? 'text-white' : 'text-rose-300'],
    ];

    $investmentCards = [
        ['label' => 'Patrimônio investido (estimado)', 'value' => $cards['patrimonio_investido']],
        ['label' => 'Rendimento acumulado (estimado)', 'value' => $cards['rendimento_acumulado']],
        ['label' => 'Rendimento no mês', 'value' => $cards['rendimento_mes']],
        ['label' => 'Rendimento no ano', 'value' => $cards['rendimento_ano']],
    ];
@endphp

<x-layout title="Financeiro" align="start">
    <div class="app-expand-with-sidebar w-full min-w-0 max-w-6xl">
        <x-financeiro-subnav active="financeiro" />

        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <x-page-heading eyebrow="Visão geral do orçamento" title="Financeiro" description="Acompanhe seu saldo e as movimentações do período.">
                <p class="mt-2 text-xs text-slate-500">{{ $periodStart->format('d/m/Y') }} a {{ $periodEnd->format('d/m/Y') }}</p>
            </x-page-heading>
            @if (\App\Support\Carteira::canSee('entradas') || \App\Support\Carteira::canSee('saidas'))
                <a href="{{ route('financeiro.relatorio', ['tipo' => 'geral', 'mes' => $periodStart->format('Y-m')], absolute: false) }}" class="rounded-lg border border-white/10 px-4 py-2 text-sm text-slate-300 hover:bg-white/5">Relatório mensal ↗</a>
            @endif
        </div>
        <x-financeiro-period-filter route-name="financeiro" :filter="$periodFilter" />
        @include('partials.financeiro.planning-overview')

        <section class="mb-6 rounded-xl border border-white/10 bg-slate-900/50">
            <h2 id="financeiro-movimentos" class="px-4 pt-4 text-sm font-semibold text-white">Movimentos do período</h2>
            <table aria-labelledby="financeiro-movimentos" class="w-full table-fixed text-sm tabular-nums">
                <thead>
                    <tr class="text-xs text-slate-400">
                        <th scope="col" class="w-2/5 px-4 py-2 text-left font-medium">Movimento</th>
                        <th scope="col" class="px-2 py-2 text-right font-medium">Realizado</th>
                        <th scope="col" class="px-4 py-2 text-right font-medium">Previsto</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($mainCards as $card)
                        <tr class="border-t border-white/10">
                            <th scope="row" class="px-4 py-2.5 text-left font-medium text-slate-300">{{ $card['label'] }}</th>
                            <td class="wrap-anywhere px-2 py-2.5 text-right font-semibold {{ $card['accent'] }}">@money($card['value'])</td>
                            <td class="wrap-anywhere px-4 py-2.5 text-right text-slate-300">
                                @isset($card['expected'])
                                    @money($card['expected'])
                                @else
                                    <span aria-label="Não se aplica">—</span>
                                @endisset
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        @include('partials.financeiro.payment-calendar')

        @include('partials.financeiro.dashboard-charts', ['timeline' => $timeline, 'despesasPorCategoria' => $despesasPorCategoria])

        <div class="grid gap-3">
            @if ($participantes->isNotEmpty())
                <details class="rounded-xl border border-white/10 bg-slate-900/40">
                    <summary class="cursor-pointer rounded-xl px-4 py-3 text-sm font-semibold text-slate-200 hover:bg-white/5">Parte dos outros participantes</summary>
                    <div class="space-y-2 px-4 pb-4">
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
                </details>
            @endif

            @if (\App\Support\Carteira::canSee('investimentos'))
                <details class="rounded-xl border border-white/10 bg-slate-900/40">
                    <summary class="cursor-pointer rounded-xl px-4 py-3 text-sm font-semibold text-slate-200 hover:bg-white/5">Investimentos acumulados</summary>
                    <p class="px-4 text-xs text-slate-400">Valores acumulados, independentemente do período selecionado.</p>
                    <div class="grid grid-cols-2 gap-3 px-4 pt-3 pb-4 sm:grid-cols-4">
                        @foreach ($investmentCards as $card)
                            <div class="rounded-xl border border-white/10 bg-gradient-to-b from-slate-900/70 to-slate-900/40 p-4">
                                <p class="text-xs font-medium tracking-wide text-slate-400 uppercase">{{ $card['label'] }}</p>
                                <p class="mt-2 text-xl font-semibold text-white sm:text-2xl">@money($card['value'])</p>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endif

            @if ($totaisPorCategoria !== [] || $gastosConjuntos !== null || $despesasPorCategoria !== [])
                <details class="rounded-xl border border-white/10 bg-slate-900/40">
                    <summary class="cursor-pointer rounded-xl px-4 py-3 text-sm font-semibold text-slate-200 hover:bg-white/5">Categorias e despesas</summary>
                    <div class="px-4 pb-4 pt-2">
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
                </details>
            @endif
        </div>
    </div>
</x-layout>
