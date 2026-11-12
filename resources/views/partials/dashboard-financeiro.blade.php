@if ($financeiro)
    <div class="mb-5 flex flex-wrap items-center justify-between gap-2 text-xs"><span class="text-slate-400">{{ \App\Models\Tarefa::monthLabel(today()) }} {{ today()->year }} · {{ $financeiro['owner'] }}</span><a href="{{ route('financeiro', absolute: false) }}" class="font-semibold text-indigo-300">Abrir financeiro ↗</a></div>
    <div class="grid grid-cols-2 gap-3">
        @foreach (['income' => 'Entradas do mês', 'expense' => 'Saídas do mês', 'to_receive' => 'A receber até o fim do mês', 'to_pay' => 'A pagar até o fim do mês'] as $key => $label)
            <div class="rounded-xl border border-white/5 bg-slate-950/30 p-3 sm:p-4"><p class="text-[11px] text-slate-400">{{ $label }}</p><p @class(['mt-2 text-lg font-semibold tracking-tight', 'text-emerald-300' => in_array($key, ['income', 'to_receive']), 'text-rose-300' => in_array($key, ['expense', 'to_pay'])])>{{ $financeiro[$key] === null ? 'Sem acesso' : 'R$ '.number_format($financeiro[$key], 2, ',', '.') }}</p></div>
        @endforeach
    </div>
    @if ($financeiro['income'] !== null && $financeiro['expense'] !== null)
        @php($largest = max($financeiro['income'], $financeiro['expense'], 1))
        <div class="mt-5 space-y-3" aria-label="Comparação de entradas e saídas do mês">
            @foreach (['income' => 'Entradas', 'expense' => 'Saídas'] as $key => $label)
                <div class="flex items-center gap-3"><span class="w-14 text-[11px] text-slate-500">{{ $label }}</span><div class="h-2 flex-1 overflow-hidden rounded-full bg-white/5"><div @class(['h-full rounded-full', 'bg-emerald-400/70' => $key === 'income', 'bg-rose-400/70' => $key === 'expense']) style="width: {{ $financeiro[$key] / $largest * 100 }}%"></div></div></div>
            @endforeach
        </div>
        <p class="mt-4 text-xs text-slate-400">Resultado do mês <span @class(['float-right font-semibold', 'text-emerald-300' => $financeiro['income'] >= $financeiro['expense'], 'text-rose-300' => $financeiro['income'] < $financeiro['expense']])>R$ {{ number_format($financeiro['income'] - $financeiro['expense'], 2, ',', '.') }}</span></p>
    @endif
    <p class="mt-4 text-[10px] leading-relaxed text-slate-500">Entradas e saídas realizadas, considerando sua parte nos lançamentos. Valores pendentes incluem atrasados.</p>
@else
    <p class="py-8 text-center text-sm text-slate-400">Você não tem acesso ao resumo da carteira selecionada.</p>
@endif
