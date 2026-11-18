@php
    $agendaRoute ??= 'financeiro';
    $agendaQuery ??= [];
    $agendaFormat = $showAgendaDays ? 'Y-m' : 'Y';
    $focusFormat = $showAgendaDays ? 'Y-m-d' : 'Y-m';
    $mobilePrevious = $showAgendaDays ? $focusedDate->copy()->subDay() : $focusedDate->copy()->subMonthNoOverflow();
    $mobileNext = $showAgendaDays ? $focusedDate->copy()->addDay() : $focusedDate->copy()->addMonthNoOverflow();
    $previous = $showAgendaDays ? $calendarMonth->copy()->subMonthNoOverflow() : $calendarMonth->copy()->subYear();
    $next = $showAgendaDays ? $calendarMonth->copy()->addMonthNoOverflow() : $calendarMonth->copy()->addYear();
@endphp

<section aria-labelledby="financeiro-calendario" class="@container mb-6 rounded-xl border border-white/10 bg-slate-900/50 p-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 id="financeiro-calendario" class="scroll-mt-20 text-sm font-semibold text-white">Agenda financeira</h2>
        <nav aria-label="Visualização da agenda financeira" class="flex gap-1 rounded-lg border border-white/10 p-1">
            <a href="{{ route($agendaRoute, [...$agendaQuery, 'agenda' => $calendarMonth->format('Y')], absolute: false) }}#financeiro-calendario"
               @unless ($showAgendaDays) aria-current="true" @endunless
               class="rounded-md px-3 py-1.5 text-xs font-medium {{ $showAgendaDays ? 'text-slate-300 hover:bg-white/5' : 'bg-indigo-500/25 text-indigo-100' }}">Por mês</a>
            <a href="{{ route($agendaRoute, [...$agendaQuery, 'agenda' => $focusedDate->format('Y-m')], absolute: false) }}#financeiro-calendario"
               @if ($showAgendaDays) aria-current="true" @endif
               class="rounded-md px-3 py-1.5 text-xs font-medium {{ $showAgendaDays ? 'bg-indigo-500/25 text-indigo-100' : 'text-slate-300 hover:bg-white/5' }}">Por dia</a>
        </nav>
    </div>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-lg font-semibold text-white">
                <time datetime="{{ $calendarMonth->format($agendaFormat) }}">{{ $showAgendaDays ? ucfirst($calendarMonth->locale('pt_BR')->translatedFormat('F Y')) : $calendarMonth->format('Y') }}</time>
            </h3>
            <p class="mt-1 text-xs text-slate-400">
                {{ $showAgendaDays ? 'Entradas e saídas realizadas e programadas por dia.' : 'Resultado programado do mês = entradas programadas − saídas programadas.' }}
            </p>
        </div>
        <nav aria-label="{{ $showAgendaDays ? 'Mês da agenda' : 'Ano da agenda' }}" class="hidden items-center gap-2 sm:flex">
            <a href="{{ route($agendaRoute, [...$agendaQuery, 'agenda' => $previous->format($agendaFormat)], absolute: false) }}#financeiro-calendario"
               aria-label="{{ $showAgendaDays ? 'Mês anterior' : 'Ano anterior' }}" class="rounded-lg border border-white/10 px-3 py-2 text-slate-300 hover:bg-white/5">‹</a>
            <a href="{{ route($agendaRoute, [...$agendaQuery, 'agenda' => today()->format($agendaFormat)], absolute: false) }}#financeiro-calendario"
               class="rounded-lg border border-white/10 px-3 py-2 text-xs text-indigo-200 hover:bg-white/5">{{ $showAgendaDays ? 'Mês atual' : 'Ano atual' }}</a>
            <a href="{{ route($agendaRoute, [...$agendaQuery, 'agenda' => $next->format($agendaFormat)], absolute: false) }}#financeiro-calendario"
               aria-label="{{ $showAgendaDays ? 'Próximo mês' : 'Próximo ano' }}" class="rounded-lg border border-white/10 px-3 py-2 text-slate-300 hover:bg-white/5">›</a>
        </nav>
        <nav aria-label="{{ $showAgendaDays ? 'Dia da agenda' : 'Mês da agenda' }}" class="flex items-center gap-2 sm:hidden">
            <a href="{{ route($agendaRoute, [...$agendaQuery, 'agenda' => $mobilePrevious->format($agendaFormat), 'foco' => $mobilePrevious->format($focusFormat)], absolute: false) }}#financeiro-calendario"
               aria-label="{{ $showAgendaDays ? 'Dia anterior' : 'Mês anterior' }}" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-white/10 text-slate-300 hover:bg-white/5">‹</a>
            <a href="{{ route($agendaRoute, [...$agendaQuery, 'agenda' => today()->format($agendaFormat)], absolute: false) }}#financeiro-calendario"
               class="inline-flex min-h-11 items-center rounded-lg border border-white/10 px-3 text-xs text-indigo-200 hover:bg-white/5">{{ $showAgendaDays ? 'Hoje' : 'Mês atual' }}</a>
            <a href="{{ route($agendaRoute, [...$agendaQuery, 'agenda' => $mobileNext->format($agendaFormat), 'foco' => $mobileNext->format($focusFormat)], absolute: false) }}#financeiro-calendario"
               aria-label="{{ $showAgendaDays ? 'Próximo dia' : 'Próximo mês' }}" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-white/10 text-slate-300 hover:bg-white/5">›</a>
        </nav>
    </div>

    <p class="mt-3 text-xs text-slate-400">
        @if ($showAgendaDays)
            A projeção parte do saldo disponível de @money($cards['saldo_disponivel']) e acumula o que está programado a partir de hoje.
        @else
            A projeção parte do saldo disponível de @money($cards['saldo_disponivel']) e acumula o que está programado a partir do mês atual.
        @endif
    </p>

    @if ($showAgendaDays)
        <div class="mt-4 hidden grid-cols-7 gap-1.5 @4xl:grid" aria-hidden="true">
            @foreach (['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'] as $weekday)
                <span class="px-2 text-xs text-slate-400">{{ $weekday }}</span>
            @endforeach
        </div>
    @endif

    <div class="mt-2 grid grid-cols-1 gap-1.5 sm:auto-rows-fr sm:grid-cols-2 {{ $showAgendaDays ? '@xl:grid-cols-4 @4xl:grid-cols-7' : '@xl:grid-cols-3 @4xl:grid-cols-4' }}">
        @if ($showAgendaDays)
            @for ($offset = 1; $offset < $calendarMonth->dayOfWeekIso; $offset++)
                <span class="hidden @4xl:block" aria-hidden="true"></span>
            @endfor
        @endif

        @foreach ($agendaRows as $row)
            @php
                $date = $row['date'];
                $isCurrent = $showAgendaDays ? $date->isToday() : $date->isSameMonth(today());
                $isFocused = $date->format($focusFormat) === $focusedDate->format($focusFormat);
                $visibility = $isFocused ? 'flex' : 'hidden sm:flex';
                $cardClasses = $visibility.' min-w-0 flex-col rounded-lg border p-3 sm:p-2 '.($isCurrent ? 'border-indigo-400/70 bg-indigo-500/10' : 'border-white/10 bg-slate-950/40');
            @endphp
            @if ($showAgendaDays)
                <div class="{{ $cardClasses }}" @if ($isFocused) data-agenda-focus="{{ $date->format($focusFormat) }}" @endif>
            @else
                <a href="{{ route($agendaRoute, [...$agendaQuery, 'agenda' => $date->format('Y-m')], absolute: false) }}#financeiro-calendario"
                   class="{{ $cardClasses }} hover:border-indigo-400/70 focus-visible:ring-2 focus-visible:ring-indigo-400"
                   @if ($isFocused) data-agenda-focus="{{ $date->format($focusFormat) }}" @endif>
            @endif

                <time datetime="{{ $date->format($showAgendaDays ? 'Y-m-d' : 'Y-m') }}" class="text-xs font-semibold text-slate-200">
                    @if ($showAgendaDays)
                        {{ $date->format('d/m') }}@if ($isCurrent) · Hoje @endif
                    @else
                        {{ ucfirst($date->locale('pt_BR')->translatedFormat('F Y')) }}
                    @endif
                </time>
                @if ($showAgendaDays || $row['received'] || $row['paid'])
                    <dl class="mt-1 grid gap-1 text-xs tabular-nums">
                        @if ($showAgendaDays || $row['received'])
                            <div class="flex flex-wrap justify-between gap-x-2">
                                <dt class="text-slate-400">Já recebido</dt>
                                <dd class="{{ $row['received'] ? 'text-emerald-300' : 'text-slate-400' }}">@money($row['received'])</dd>
                            </div>
                        @endif
                        @if ($showAgendaDays || $row['paid'])
                            <div class="flex flex-wrap justify-between gap-x-2">
                                <dt class="text-slate-400">Já pago</dt>
                                <dd class="{{ $row['paid'] ? 'text-rose-300' : 'text-slate-400' }}">@money($row['paid'])</dd>
                            </div>
                        @endif
                    </dl>
                @endif
                @if ($showAgendaDays || $row['pay'] || $row['receive'])
                    <dl class="mt-1 grid gap-1 text-xs tabular-nums">
                        @if ($showAgendaDays || $row['receive'])
                            <div class="flex flex-wrap justify-between gap-x-2">
                                <dt class="text-slate-400">A receber</dt>
                                <dd class="{{ $row['receive'] ? 'text-emerald-300' : 'text-slate-400' }}">@money($row['receive'])</dd>
                            </div>
                        @endif
                        @if ($showAgendaDays || $row['pay'])
                            <div class="flex flex-wrap justify-between gap-x-2">
                                <dt class="text-slate-400">A pagar</dt>
                                <dd class="{{ $row['pay'] ? 'text-rose-300' : 'text-slate-400' }}">@money($row['pay'])</dd>
                            </div>
                        @endif
                        <div class="border-t border-white/10 pt-1">
                            <dt class="text-slate-400">{{ $showAgendaDays ? 'Resultado programado do dia' : 'Resultado programado do mês' }}</dt>
                            <dd class="font-semibold text-amber-300">@money($row['result'])</dd>
                        </div>
                    </dl>
                @elseif (! $row['received'] && ! $row['paid'])
                    <p class="mt-1 text-xs text-slate-400">Sem lançamentos</p>
                @endif
                @if ($row['balance'] !== null)
                    <dl class="mt-auto pt-2 text-xs tabular-nums">
                        <div class="border-t border-white/10 pt-1">
                            <dt class="text-slate-400">Saldo em conta projetado</dt>
                            <dd class="font-semibold text-amber-300">@money($row['balance'])</dd>
                        </div>
                    </dl>
                @endif
            @if ($showAgendaDays)
                </div>
            @else
                </a>
            @endif
        @endforeach
    </div>
</section>
