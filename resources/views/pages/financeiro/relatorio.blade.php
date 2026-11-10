<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $reportTitle }} — {{ $monthTitle }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            @page { size: A4 landscape; margin: 10mm; }
            body { background: white !important; color: black !important; }
            .report-controls { display: none !important; }
            .report-scroll { overflow: visible !important; }
            table { font-size: 9px; }
            th, td { padding: 5px !important; border-color: #bbb !important; overflow-wrap: anywhere; }
            thead { display: table-header-group; }
            tr { break-inside: avoid; }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-950 p-4 font-sans text-slate-200 sm:p-8">
    <main class="mx-auto max-w-screen-2xl space-y-6">
        <div class="report-controls flex flex-wrap items-center justify-between gap-4">
            <a href="{{ route($reportType === 'geral' ? 'financeiro' : ($reportType === 'entrada' ? 'financeiro.entradas' : 'financeiro.saidas'), absolute: false) }}" class="text-sm text-indigo-300">← Voltar ao financeiro</a>
            <form method="GET" action="{{ route('financeiro.relatorio', absolute: false) }}" class="flex flex-wrap items-center gap-3">
                <input type="hidden" name="tipo" value="{{ $reportType }}">
                <input type="hidden" name="personalizar" value="1">
                <input type="hidden" name="layout" value="1">
                <label class="flex w-full flex-col gap-2 text-sm">Título do relatório
                    <input name="titulo" value="{{ $reportTitle }}" maxlength="150" class="rounded-lg border border-white/20 bg-slate-900 px-3 py-2 text-white">
                </label>
                <label for="report-month">Mês</label>
                <input id="report-month" type="month" name="mes" value="{{ $month->format('Y-m') }}" required class="rounded-lg border border-white/20 bg-slate-900 px-3 py-2 text-white">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="comparar" value="1" @checked($comparison !== null) class="accent-indigo-500">Comparar com o mês anterior</label>
                <fieldset class="w-full rounded-lg border border-white/20 p-4">
                    <legend class="px-2 font-semibold">Informações do relatório</legend>
                    <p class="mb-3 text-sm">Marque os campos desejados e clique em Gerar relatório. Você também pode ocultar a identificação e o cálculo final.</p>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($columns as $key => $label)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="campos[]" value="{{ $key }}" @checked(array_key_exists($key, $selectedColumns)) class="accent-indigo-500">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @error('campos')
                        <p role="alert" class="mt-3 text-rose-300">{{ $message }}</p>
                    @enderror
                </fieldset>
                <fieldset class="w-full rounded-lg border border-white/20 p-4">
                    <legend class="px-2 font-semibold">Cabeçalho e resumo</legend>
                    <div class="flex flex-wrap gap-4">
                        @foreach (['identificacao' => 'Nomes da carteira e do titular', 'periodo' => 'Período do relatório', 'explicacao' => 'Explicação dos cálculos', 'totais' => 'Cálculo final do mês'] as $key => $label)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="exibir[]" value="{{ $key }}" @checked(in_array($key, $displayOptions, true)) class="accent-indigo-500">{{ $label }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <button class="rounded-lg border border-white/20 px-4 py-2">Gerar relatório</button>
                <button type="submit" name="imprimir" value="1" class="rounded-lg bg-indigo-500 px-4 py-2 font-semibold text-white">Imprimir / Salvar PDF</button>
            </form>
        </div>
        <header>
            <h1 class="text-2xl font-semibold">{{ $reportTitle }}</h1>
            <p class="mt-2">{{ $monthTitle }}</p>
            @if (in_array('identificacao', $displayOptions, true))
                <p>Carteira de {{ $owner->handle() }} · Titular dos cálculos: {{ $user->handle() }}</p>
            @endif
            @if (in_array('periodo', $displayOptions, true))
            <p class="mt-1 text-sm">Período: {{ $month->format('d/m/Y') }} a {{ $month->copy()->endOfMonth()->format('d/m/Y') }}.</p>
            @endif
            @if (in_array('explicacao', $displayOptions, true))
            <p class="mt-1 text-sm">Os cálculos representam o impacto na sua conta. A tabela apresenta somente as informações selecionadas.</p>
            @endif
            @if ($isPartial)
                <p class="mt-2 font-semibold">Relatório parcial: inclui somente as seções compartilhadas com você.</p>
            @endif
        </header>
        <div class="report-scroll overflow-x-auto rounded-lg border border-slate-600">
            <table class="w-full border-collapse text-left text-xs">
                <thead class="bg-slate-800 print:bg-white">
                    <tr>
                        @foreach ($selectedColumns as $heading)
                            <th scope="col" class="border-b border-slate-600 p-3">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php($item = $row['lancamento'])
                        <tr>
                            @foreach ($selectedColumns as $key => $heading)
                                <td class="max-w-64 border-b border-slate-700 p-3 whitespace-pre-line wrap-break-word">
                                    @switch($key)
                                        @case('id') #{{ $item->id }} @break
                                        @case('date') {{ $item->date->format('d/m/Y') }} @break
                                        @case('type') {{ $item->type === 'entrada' ? 'Entrada' : 'Saída' }} @break
                                        @case('status') {{ $item->status === 'cancelado' ? 'Cancelado' : ($item->status === 'realizado' ? 'Realizado' : 'Previsto') }} @break
                                        @case('owner') {{ $item->user->handle() }} @break
                                        @case('shares')
                                            @foreach ($item->partes as $parte)
                                                <p>{{ $parte->user->handle() }}: @money($parte->amount)</p>
                                            @endforeach
                                            @if ($item->partes->isNotEmpty())
                                                <p>Parte do titular: @money($item->parteOf($item->user))</p>
                                                <p>{{ $item->type === 'entrada' ? 'Recebido dos participantes' : ($item->partesSaemDoDono() ? 'Titular paga tudo' : 'Cada participante paga sua parte') }}</p>
                                            @else
                                                Sem divisão
                                            @endif
                                            @break
                                        @case('recurrence')
                                            @if ($item->is_recurring_template)
                                                {{ ucfirst($item->recurrence_period ?? '') }} · dia {{ $item->recurrence_day ?: $item->date->day }}
                                            @elseif ($item->parent_recurring_id)
                                                Gerado por recorrência #{{ $item->parent_recurring_id }}
                                            @else
                                                Não recorrente
                                            @endif
                                            @break
                                        @case('amount') @money($item->amount) @break
                                        @case('entrada') @money($row['entrada']) @break
                                        @case('saida') @money($row['saida']) @break
                                        @case('created_at')
                                        @case('updated_at') {{ $item->{$key}?->format('d/m/Y H:i') }} @break
                                        @default {{ $item->{$key} ?: '—' }}
                                    @endswitch
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($selectedColumns) }}" class="p-6 text-center">Nenhum lançamento neste mês.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if (in_array('totais', $displayOptions, true))
        <section>
            <h2 class="mb-3 text-lg font-semibold">Cálculo final do mês</h2>
            <table class="w-full max-w-3xl border-collapse text-sm">
                <thead>
                    <tr><th class="border-b border-slate-600 p-3 text-left">Situação</th>
                        @foreach ($summaryColumns as $label)
                            <th class="border-b border-slate-600 p-3 text-right">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach (['realizado' => 'Realizado', 'previsto' => 'Previsto'] as $key => $label)
                        <tr><th class="border-b border-slate-700 p-3 text-left">{{ $label }}</th>
                            @foreach ($summaryColumns as $column => $heading)
                                <td class="border-b border-slate-700 p-3 text-right">@money($totals[$key][$column])</td>
                            @endforeach
                        </tr>
                    @endforeach
                    <tr class="font-semibold"><th class="p-3 text-left">Total projetado</th>
                        @foreach ($summaryColumns as $column => $heading)
                            <td class="p-3 text-right">@money($totals['realizado'][$column] + $totals['previsto'][$column])</td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
            <p class="mt-3 text-sm">{{ $reportType === 'geral' ? 'Saldo = entradas − saídas. ' : '' }}O total projetado soma realizados e previstos; não representa saldo bancário acumulado.</p>
        </section>
        @endif
        @if ($comparison !== null)
            <section class="rounded-xl border border-white/10 p-4">
                <h2 class="font-semibold">Comparativo de valores realizados · {{ $comparison['month'] }} × {{ $monthTitle }}</h2>
                <div class="mt-4 overflow-x-auto"><table class="w-full text-sm"><thead><tr><th class="p-2 text-left">Indicador</th><th class="p-2 text-right">Mês anterior</th><th class="p-2 text-right">Mês selecionado</th><th class="p-2 text-right">Variação em R$</th></tr></thead><tbody>
                    @foreach ($summaryColumns as $key => $label)<tr><td class="p-2">{{ $label }}</td><td class="p-2 text-right">@money($comparison[$key])</td><td class="p-2 text-right">@money($totals['realizado'][$key])</td><td class="p-2 text-right">@money($totals['realizado'][$key] - $comparison[$key])</td></tr>@endforeach
                </tbody></table></div>
            </section>
        @endif
    </main>
    @if ($printReport)
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>
