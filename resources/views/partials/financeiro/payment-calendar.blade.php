<section aria-labelledby="financeiro-calendario" class="mb-6 rounded-xl border border-white/10 bg-slate-900/50 p-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 id="financeiro-calendario" class="text-sm font-semibold text-white">Calendário financeiro</h2>
            <p class="mt-1 text-sm text-slate-300">{{ ucfirst($calendarMonth->locale('pt_BR')->translatedFormat('F Y')) }}</p>
        </div>
        <nav aria-label="Mês do calendário financeiro" class="flex gap-2">
            @foreach ([-1 => 'Mês anterior', 1 => 'Próximo mês'] as $offset => $label)
                @php($month = $calendarMonth->copy()->addMonthsNoOverflow($offset))
                <a href="{{ route('financeiro', ['period' => 'personalizado', 'start' => $month->toDateString(), 'end' => $month->copy()->endOfMonth()->toDateString()], absolute: false) }}"
                   class="rounded-lg border border-white/10 px-3 py-2 text-xs text-indigo-200 hover:bg-white/5">{{ $label }}</a>
            @endforeach
        </nav>
    </div>
    <p class="mt-2 text-xs text-slate-400">O saldo projetado no fim de cada dia considera o saldo disponível, os recebimentos e pagamentos previstos até aquela data, incluindo pendências anteriores. Trocar o mês também atualiza o filtro do Financeiro.</p>

    <div class="mt-4 grid gap-1.5 sm:grid-cols-2 lg:grid-cols-7">
        @foreach (['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'] as $weekday)
            <span class="hidden px-2 text-xs text-slate-400 lg:block" aria-hidden="true">{{ $weekday }}</span>
        @endforeach
        @for ($offset = 1; $offset < $calendarMonth->dayOfWeekIso; $offset++)
            <span class="hidden lg:block" aria-hidden="true"></span>
        @endfor
        @foreach ($calendarDays as $day)
            <div class="min-w-0 rounded-lg border p-2 {{ $day['date']->isToday() ? 'border-indigo-400/70 bg-indigo-500/10' : 'border-white/10 bg-slate-950/40' }}">
                <time datetime="{{ $day['date']->toDateString() }}" class="text-xs font-semibold text-slate-200">
                    {{ $day['date']->format('d/m') }}@if ($day['date']->isToday()) · Hoje @endif
                </time>
                <dl class="mt-1 grid gap-1 text-xs tabular-nums">
                    <div class="flex flex-wrap justify-between gap-x-2">
                        <dt class="text-slate-400">A pagar</dt>
                        <dd class="{{ $day['pay'] > 0 ? 'text-rose-300' : 'text-slate-400' }}">@money($day['pay'])</dd>
                    </div>
                    @if ($day['receive'] > 0)
                        <div class="flex flex-wrap justify-between gap-x-2">
                            <dt class="text-slate-400">A receber</dt>
                            <dd class="text-emerald-300">@money($day['receive'])</dd>
                        </div>
                    @endif
                    @if ($day['balance'] !== null)
                        <div class="border-t border-white/10 pt-1">
                            <dt class="text-slate-400">Saldo projetado</dt>
                            <dd class="font-semibold text-amber-300">@money($day['balance'])</dd>
                        </div>
                    @endif
                </dl>
            </div>
        @endforeach
    </div>
</section>
