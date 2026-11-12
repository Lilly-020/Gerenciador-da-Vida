<div id="tarefas-month-calendar" data-month="{{ $month->format('Y-m') }}" class="rounded-2xl border border-white/10 bg-slate-900/60 p-3 sm:p-5">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div><h2 class="text-lg font-semibold text-white">{{ \App\Models\Tarefa::monthLabel($month) }} {{ $month->year }}</h2><p class="mt-1 text-xs text-slate-400">Veja o mês inteiro. Clique em um dia para adicionar. Marque o quadrado para concluir ou abra o título para editar e excluir.</p></div>
        <div class="flex items-center gap-2">
            <a href="{{ route('tarefas', ['date' => $month->copy()->subMonth()->toDateString(), 'view' => 'month'], absolute: false) }}" aria-label="Mês anterior" class="rounded-lg border border-white/10 px-3 py-2 text-slate-300 hover:bg-white/5">‹</a>
            <a href="{{ route('tarefas', ['date' => today()->toDateString(), 'view' => 'month'], absolute: false) }}" class="rounded-lg border border-white/10 px-3 py-2 text-xs text-indigo-200">Mês atual</a>
            <a href="{{ route('tarefas', ['date' => $month->copy()->addMonth()->toDateString(), 'view' => 'month'], absolute: false) }}" aria-label="Próximo mês" class="rounded-lg border border-white/10 px-3 py-2 text-slate-300 hover:bg-white/5">›</a>
        </div>
    </div>
    <p class="mb-2 text-xs text-slate-500 sm:hidden">↔ Deslize para visualizar a semana completa.</p>
    <div class="overflow-x-auto pb-2">
        <div class="min-w-[700px]">
            <div class="grid grid-cols-7 text-center text-xs text-slate-400">@foreach (\App\Models\Tarefa::WEEKDAY_CHIPS as $weekday)<div class="py-3">{{ $weekday }}</div>@endforeach</div>
            <div class="grid grid-cols-7 overflow-hidden rounded-xl border border-white/10">
                @foreach ($weeks as $week)
                    @foreach ($week as $day)
                        <div data-calendar-create="{{ $day['date']->toDateString() }}" @class(['min-h-36 min-w-0 border-r border-b border-white/5 p-2', 'bg-indigo-500/5' => $day['date']->isSameDay($selected), 'bg-slate-950/30' => ! $day['inMonth']])>
                            <div class="mb-2 flex items-center justify-between gap-1">
                                <button type="button" data-calendar-open="{{ $day['date']->toDateString() }}" @if (! $day['inMonth']) data-outside-month @endif aria-label="Abrir {{ $day['date']->format('d/m/Y') }}" @class(['flex h-7 min-w-7 items-center justify-center rounded-full px-1 text-xs', 'bg-indigo-500 text-white' => $day['date']->isToday(), 'text-slate-300' => $day['inMonth'] && ! $day['date']->isToday(), 'text-slate-600' => ! $day['inMonth'] && ! $day['date']->isToday()])>{{ $day['date']->day }}</button>
                                @if ($day['total'])<span class="text-[10px] text-slate-500">{{ $day['completed'] }}/{{ $day['total'] }}</span>@endif
                            </div>
                            <div class="grid max-h-52 gap-1 overflow-y-auto">
                                @if ($holiday = \App\Support\CalendarioFeriados::on($day['date']))<div class="rounded-md border border-rose-400/20 bg-rose-400/10 px-2 py-1.5 text-[11px] text-rose-200">{{ $holiday }}</div>@endif
                                @foreach ($day['tasks'] as $task)
                                    <div data-calendar-task data-task-card-url="{{ route('tarefas.show', $task, absolute: false) }}" @class(['flex min-w-0 items-center gap-1.5 rounded-md border px-2 py-1.5 text-[11px]', 'border-emerald-400/10 bg-emerald-400/10 text-emerald-300' => $task->completed, 'border-indigo-400/10 bg-indigo-400/10 text-indigo-200' => ! $task->completed])>
                                        @include('partials.tarefa-calendar-check', ['task' => $task])
                                        <button type="button" data-task-details-url="{{ route('tarefas.show', $task, absolute: false) }}" aria-haspopup="dialog" aria-label="Editar ou excluir: {{ $task->title }}" class="flex min-w-0 flex-1 items-center gap-1.5 text-left {{ $task->completed ? 'line-through' : '' }}"><span class="min-w-0 flex-1 truncate">{{ $task->title }}</span>@include('partials.tarefa-note-icon', ['task' => $task])</button>
                                    </div>
                                @endforeach
                                <button type="button" data-calendar-open="{{ $day['date']->toDateString() }}" @if (! $day['inMonth']) data-outside-month @endif data-calendar-add class="rounded-md px-1 py-1 text-left text-[10px] text-slate-500 hover:bg-white/5 hover:text-indigo-200">+ Adicionar</button>
                            </div>
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>
    </div>
    <div class="mt-3 flex flex-wrap gap-4 text-[11px] text-slate-400"><span class="text-indigo-200">• Pendente</span><span class="text-emerald-300">✓ Concluída</span><span>A contagem mostra concluídas / total de cada dia.</span></div>
</div>
