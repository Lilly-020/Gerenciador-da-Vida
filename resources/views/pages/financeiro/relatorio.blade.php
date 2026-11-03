<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Relatório financeiro — {{ $monthTitle }}</title>
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
            <a href="{{ route('financeiro', absolute: false) }}" class="text-sm text-indigo-300">← Voltar ao financeiro</a>
            <form method="GET" action="{{ route('financeiro.relatorio', absolute: false) }}" class="flex flex-wrap items-center gap-3">
                <input type="hidden" name="personalizar" value="1">
                <label for="report-month">Mês</label>
                <input id="report-month" type="month" name="mes" value="{{ $month->format('Y-m') }}" required class="rounded-lg border border-white/20 bg-slate-900 px-3 py-2 text-white">
                <fieldset class="w-full rounded-lg border border-white/20 p-4">
                    <legend class="px-2 font-semibold">Informações do relatório</legend>
                    <p class="mb-3 text-sm">Marque os campos desejados e clique em Gerar relatório. Os totais finais continuam incluídos.</p>
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
                <button class="rounded-lg border border-white/20 px-4 py-2">Gerar relatório</button>
                <button type="button" onclick="window.print()" class="rounded-lg bg-indigo-500 px-4 py-2 font-semibold text-white">Imprimir / Salvar PDF</button>
            </form>
        </div>
        <header>
            <h1 class="text-2xl font-semibold">Relatório mensal de entradas e saídas</h1>
            <p class="mt-2">{{ $monthTitle }} · Carteira de {{ $owner->handle() }} · Titular dos cálculos: {{ $user->handle() }}</p>
            <p class="mt-1 text-sm">Período: {{ $month->format('d/m/Y') }} a {{ $month->copy()->endOfMonth()->format('d/m/Y') }}.</p>
            <p class="mt-1 text-sm">Os cálculos representam o impacto na sua conta. A tabela apresenta somente as informações selecionadas.</p>
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
                                        @case('status') {{ $item->status === 'realizado' ? 'Realizado' : 'Previsto' }} @break
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
        <section>
            <h2 class="mb-3 text-lg font-semibold">Cálculo final do mês</h2>
            <table class="w-full max-w-3xl border-collapse text-sm">
                <thead><tr><th class="border-b border-slate-600 p-3 text-left">Situação</th><th class="border-b border-slate-600 p-3 text-right">Entradas</th><th class="border-b border-slate-600 p-3 text-right">Saídas</th><th class="border-b border-slate-600 p-3 text-right">Saldo</th></tr></thead>
                <tbody>
                    @foreach (['realizado' => 'Realizado', 'previsto' => 'Previsto'] as $key => $label)
                        <tr><th class="border-b border-slate-700 p-3 text-left">{{ $label }}</th><td class="border-b border-slate-700 p-3 text-right">@money($totals[$key]['entrada'])</td><td class="border-b border-slate-700 p-3 text-right">@money($totals[$key]['saida'])</td><td class="border-b border-slate-700 p-3 text-right">@money($totals[$key]['saldo'])</td></tr>
                    @endforeach
                    <tr class="font-semibold"><th class="p-3 text-left">Total projetado</th><td class="p-3 text-right">@money($totals['realizado']['entrada'] + $totals['previsto']['entrada'])</td><td class="p-3 text-right">@money($totals['realizado']['saida'] + $totals['previsto']['saida'])</td><td class="p-3 text-right">@money($totals['realizado']['saldo'] + $totals['previsto']['saldo'])</td></tr>
                </tbody>
            </table>
            <p class="mt-3 text-sm">Saldo = entradas − saídas. O total projetado soma realizados e previstos; não representa saldo bancário acumulado.</p>
        </section>
    </main>
</body>
</html>
