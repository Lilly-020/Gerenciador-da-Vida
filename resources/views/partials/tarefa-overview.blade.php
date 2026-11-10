@php
    $todayCompleted = $todayTasks->where('completed', true)->count();
    $todayTotal = $todayTasks->count();
    $todayPercentage = $todayTotal ? (int) round($todayCompleted / $todayTotal * 100) : 0;
@endphp
<div id="tarefas-overview" class="mb-6 grid gap-4">
    <div class="grid gap-4 lg:grid-cols-[1.3fr_1fr_1fr]">
        <section class="rounded-2xl border border-indigo-400/25 bg-indigo-500/10 p-5">
            <p class="text-xs font-medium text-indigo-200">Seu foco de hoje · {{ today()->format('d/m') }}</p>
            <div class="mt-3 flex items-end justify-between gap-3"><strong class="text-3xl text-white">{{ $todayCompleted }}<span class="text-lg font-normal text-slate-400"> / {{ $todayTotal }}</span></strong><span class="text-sm font-semibold text-indigo-200">{{ $todayPercentage }}%</span></div>
            <p class="mt-2 text-xs text-slate-400">{{ $todayTotal ? ($todayCompleted === $todayTotal ? 'Tudo concluído por hoje. Bom trabalho!' : ($todayTotal - $todayCompleted).' tarefa(s) para concluir hoje.') : 'Planeje seu dia adicionando a primeira tarefa.' }}</p>
            <div role="progressbar" aria-label="Conclusão de hoje" aria-valuenow="{{ $todayPercentage }}" aria-valuemin="0" aria-valuemax="100" class="mt-4 h-2 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-indigo-400" style="width: {{ $todayPercentage }}%"></div></div>
            <a href="{{ route('tarefas', ['date' => today()->toDateString(), 'view' => 'calendar'], absolute: false) }}" class="mt-4 inline-flex text-xs font-semibold text-indigo-200">Organizar tarefas de hoje →</a>
        </section>
        <section class="rounded-2xl border border-white/10 bg-slate-900/60 p-5"><p class="text-xs text-slate-400">Tarefas atrasadas</p><p class="mt-3 text-3xl font-semibold {{ $overdueCount ? 'text-rose-300' : 'text-white' }}">{{ $overdueCount }}</p><p class="mt-2 text-xs text-slate-500">Pendentes de dias anteriores.</p><a href="#tarefas-overdue" class="mt-4 inline-flex text-xs text-rose-200">Revisar pendências →</a></section>
        <section class="rounded-2xl border border-white/10 bg-slate-900/60 p-5"><p class="text-xs text-slate-400">Próximos 7 dias</p><p class="mt-3 text-3xl font-semibold text-sky-200">{{ $upcomingCount }}</p><p class="mt-2 text-xs text-slate-500">Tarefas pendentes já planejadas.</p><a href="{{ route('tarefas', ['view' => 'month'], absolute: false) }}" class="mt-4 inline-flex text-xs text-sky-200">Ver planejamento mensal →</a></section>
    </div>
    <div class="grid items-start gap-4 lg:grid-cols-3">
        @foreach ([['title' => 'Hoje', 'tasks' => $todayTasks->take(8), 'empty' => 'Nenhuma tarefa para hoje.', 'id' => 'tarefas-today-focus'], ['title' => 'Atrasadas', 'tasks' => $overdue, 'empty' => 'Nenhuma pendência atrasada.', 'id' => 'tarefas-overdue'], ['title' => 'A seguir', 'tasks' => $upcoming, 'empty' => 'Sua próxima semana está livre.', 'id' => 'tarefas-upcoming']] as $group)
            <section id="{{ $group['id'] }}" class="min-w-0 rounded-xl border border-white/10 bg-slate-900/50 p-4">
                <h2 class="mb-3 text-sm font-semibold text-white">{{ $group['title'] }}</h2>
                <ul class="grid gap-2">
                    @forelse ($group['tasks'] as $task)
                        <li><a href="{{ route('tarefas', ['date' => $task->date->toDateString(), 'view' => 'calendar'], absolute: false) }}" class="flex items-start gap-2 rounded-lg border border-white/5 bg-slate-950/30 p-3 hover:border-indigo-400/30"><span class="shrink-0 {{ $task->completed ? 'text-emerald-300' : 'text-indigo-300' }}">{{ $task->completed ? '✓' : '○' }}</span><span class="min-w-0"><span class="block wrap-anywhere text-xs {{ $task->completed ? 'text-slate-500 line-through' : 'text-slate-200' }}">{{ $task->title }}</span><span class="mt-1 block text-[10px] text-slate-500">{{ $task->date->format('d/m/Y') }} @if ($task->series_id) · Recorrente @endif</span></span></a></li>
                    @empty
                        <li class="py-3 text-xs text-slate-500">{{ $group['empty'] }}</li>
                    @endforelse
                </ul>
                @if ($group['id'] === 'tarefas-overdue' && $overdueCount > 6)<p class="mt-3 text-[10px] text-slate-500">Mostrando as 6 pendências mais antigas. Use a busca por datas para ver as demais.</p>@endif
            </section>
        @endforeach
    </div>
</div>
