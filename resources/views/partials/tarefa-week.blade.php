@php
    $weekStart = $weekDays[0]['date'];
    $weekEnd = $weekDays[6]['date'];
@endphp
<div id="tarefas-week-calendar" class="task-week">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-1">
                <a href="{{ route('tarefas', ['date' => $selected->copy()->subWeek()->toDateString(), 'view' => 'week'], absolute: false) }}" aria-label="Semana anterior" class="rounded-lg border border-white/10 px-3 py-2 text-slate-300 hover:bg-white/5">‹</a>
                <a href="{{ route('tarefas', ['date' => today()->toDateString(), 'view' => 'week'], absolute: false) }}" class="rounded-lg border border-white/10 px-3 py-2 text-xs font-semibold text-slate-300">Hoje</a>
                <a href="{{ route('tarefas', ['date' => $selected->copy()->addWeek()->toDateString(), 'view' => 'week'], absolute: false) }}" aria-label="Próxima semana" class="rounded-lg border border-white/10 px-3 py-2 text-slate-300 hover:bg-white/5">›</a>
            </div>
            <h2 class="text-sm font-semibold text-white">{{ $weekStart->format('d') }} de {{ mb_strtolower(mb_substr(\App\Models\Tarefa::monthLabel($weekStart), 0, 3)) }}. — {{ $weekEnd->format('d') }} de {{ mb_strtolower(mb_substr(\App\Models\Tarefa::monthLabel($weekEnd), 0, 3)) }}. {{ $weekEnd->year }}</h2>
        </div>
        <button type="button" data-week-kind-filter="feriado" data-kind="feriado" aria-pressed="true" class="week-kind-chip"><span aria-hidden="true">●</span> Feriados nacionais</button>
    </div>
    <p class="mb-3 text-xs text-slate-500">Clique em um horário para agendar. Sem horário, a tarefa aparece em Dia todo. Marque o quadrado para concluir. Clique no título para editar, excluir ou adicionar anexos.</p>
    <div data-week-scroll class="week-scroll" tabindex="0" aria-label="Agenda semanal com horários, role para navegar">
        <div class="week-canvas">
            <div class="week-header">
                <div class="week-corner"></div>
                @foreach ($weekDays as $day)
                    <button type="button" data-calendar-open="{{ $day['date']->toDateString() }}" @if ($day['date']->month !== $selected->month) data-week-day @endif class="week-heading {{ $day['date']->isSameDay($selected) ? 'week-selected' : '' }}"><span>{{ mb_strtoupper(mb_substr(\App\Models\Tarefa::weekdayLabel($day['date']), 0, 3)) }}</span><strong @class(['week-today' => $day['date']->isToday()])>{{ $day['date']->day }}</strong></button>
                @endforeach
            </div>
            <div class="week-all-day">
                <span class="week-all-label">Dia todo</span>
                @foreach ($weekDays as $day)
                    <div class="min-w-0 border-r border-white/10 p-1.5">
                        @if ($holiday = \App\Support\CalendarioFeriados::on($day['date']))<div data-week-kind="feriado" data-kind="feriado" class="week-all-event whitespace-normal!" title="Feriado nacional">{{ $holiday }}</div>@endif
                        @foreach ($day['allDay']->take(3) as $task)
                            <div data-calendar-task data-kind="atividade" class="week-all-event flex! items-center gap-2">
                                @include('partials.tarefa-calendar-check', ['task' => $task])
                                <button type="button" data-task-details-url="{{ route('tarefas.show', $task, absolute: false) }}" aria-haspopup="dialog" aria-label="Editar ou excluir: {{ $task->title }}" class="min-w-0 flex-1 truncate text-left {{ $task->completed ? 'line-through opacity-60' : '' }}">{{ $task->title }}</button>
                            </div>
                        @endforeach
                        @if ($day['allDay']->count() > 3)<button data-calendar-more type="button" data-calendar-open="{{ $day['date']->toDateString() }}" class="block px-1 text-[10px] text-slate-400">+ {{ $day['allDay']->count() - 3 }} tarefas</button>@endif
                        <button type="button" data-calendar-open="{{ $day['date']->toDateString() }}" data-calendar-add data-week-all-day class="week-add-all" aria-label="Adicionar tarefa de dia todo em {{ $day['date']->format('d/m/Y') }}">+</button>
                    </div>
                @endforeach
            </div>
            <div class="week-time-grid">
                <div class="week-time-labels">@for ($hour = 0; $hour < 24; $hour++)<span style="top: {{ $hour * 60 }}px">{{ sprintf('%02d:00', $hour) }}</span>@endfor</div>
                @foreach ($weekDays as $day)
                    <div data-week-slot="{{ $day['date']->toDateString() }}" data-week-date="{{ $day['date']->toDateString() }}" @class(['week-day', 'week-day-today' => $day['date']->isToday()])>
                        @foreach ($day['events'] as $event)
                            @php($task = $event['task'])
                            <div data-calendar-task data-kind="atividade" class="week-event py-0.5!" style="top: {{ $event['start'] }}px; height: {{ $event['end'] - $event['start'] }}px; left: calc({{ $event['lane'] / $event['lanes'] * 100 }}% + 2px); width: calc({{ 100 / $event['lanes'] }}% - 4px)">
                                <div class="flex min-w-0 items-start gap-1.5">
                                    @include('partials.tarefa-calendar-check', ['task' => $task])
                                    <button type="button" data-task-details-url="{{ route('tarefas.show', $task, absolute: false) }}" aria-haspopup="dialog" aria-label="Editar ou excluir: {{ $task->title }}" class="min-w-0 flex-1 text-left {{ $task->completed ? 'line-through opacity-60' : '' }}"><strong>{{ $task->title }}</strong><small class="block">{{ substr($task->start_time, 0, 5) }}{{ $task->end_time ? ' — '.substr($task->end_time, 0, 5) : '' }}</small></button>
                                </div>
                            </div>
                        @endforeach
                        <div data-week-now class="week-now" hidden aria-hidden="true"></div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    <p class="mt-3 text-[11px] text-slate-500">Horário de Brasília. A linha vermelha indica o horário atual. Feriados nacionais são exibidos automaticamente. Datas estaduais e municipais não estão incluídas.</p>
</div>
