@php
    $calendarTask = $task->calendarTask;
@endphp
<div data-task-row class="group rounded-xl border border-white/5 bg-slate-950/30 p-3 {{ $task->parent_id ? 'ml-5 border-l-indigo-400/40' : '' }}">
    <div class="flex items-start gap-2">
        <label class="flex min-w-0 flex-1 cursor-pointer items-start gap-2.5 text-sm text-slate-300">
            <span class="relative inline-flex h-5 w-5 shrink-0 items-center justify-center">
                <input type="checkbox" data-task-toggle-url="{{ route('projetos.tarefas.update', [$projeto, $task], absolute: false) }}" @checked($task->is_finished) @disabled(! $canEdit || $task->goal_type !== 'checklist') class="peer h-5 w-5 cursor-pointer appearance-none rounded-md border border-slate-600 bg-slate-950/60 shadow-inner transition checked:border-indigo-400 checked:bg-indigo-500 hover:border-indigo-400 focus-visible:outline-2 focus-visible:outline-offset-3 focus-visible:outline-indigo-400 disabled:cursor-default motion-reduce:transition-none">
                <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" class="pointer-events-none absolute h-3.5 w-3.5 text-white opacity-0 transition peer-checked:opacity-100"><path d="m4 10 4 4 8-8" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </span>
            <span class="min-w-0 wrap-break-word" data-task-title>{{ $task->title }}</span>
        </label>
        @if ($canEdit)
            <button type="button" data-task-delete-url="{{ route('projetos.tarefas.destroy', [$projeto, $task], absolute: false) }}" data-confirm-message="Excluir esta tarefa e suas subtarefas?" aria-label="Excluir tarefa" class="text-slate-500 transition hover:text-rose-400 sm:opacity-0 sm:group-hover:opacity-100 sm:group-focus-within:opacity-100">×</button>
        @endif
    </div>
    <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-400">
        <span>{{ $task->assignee?->handle() ?? 'Sem responsável' }}</span>
        @if ($task->due_at)
            <span class="{{ ! $task->is_finished && $task->due_at->lt(today()) ? 'text-rose-300' : '' }}">Prazo {{ $task->due_at->format('d/m/Y') }} @if (! $task->is_finished && $task->due_at->lt(today())) · Atrasada @endif</span>
        @endif
    </div>
    @if ($calendarTask)
        <a href="{{ route('tarefas', ['date' => $calendarTask->date->toDateString(), 'view' => 'week', 'focus_task' => $calendarTask->id], absolute: false) }}" class="mt-2 inline-flex items-center gap-1.5 rounded-lg border border-indigo-400/20 bg-indigo-500/10 px-2.5 py-1.5 text-xs text-indigo-200 hover:bg-indigo-500/20">↗ No meu calendário · {{ $calendarTask->date->format('d/m/Y') }}{{ $calendarTask->start_time ? ' · '.substr($calendarTask->start_time, 0, 5) : ' · Dia todo' }}</a>
    @endif
    @if ($task->goal_type !== 'checklist')
        @php
            $isFinancialGoal = $task->goal_type === 'financial';
            $currentAmount = number_format($task->current_value, 2, ',', '.');
            $targetAmount = number_format($task->target_value, 2, ',', '.');
            if (! $isFinancialGoal) {
                $currentAmount = rtrim(rtrim($currentAmount, '0'), ',');
                $targetAmount = rtrim(rtrim($targetAmount, '0'), ',');
            }
        @endphp
        <dl class="mt-3 grid grid-cols-3 gap-3 rounded-lg border border-white/5 bg-slate-900/50 p-3 text-xs">
            <div class="min-w-0"><dt class="text-slate-500">{{ $isFinancialGoal ? 'Acumulado' : 'Realizado' }}</dt><dd class="mt-1 wrap-break-word font-semibold text-slate-200">{{ $isFinancialGoal ? 'R$ ' : '' }}{{ $currentAmount }}</dd></div>
            <div class="min-w-0"><dt class="text-slate-500">Meta</dt><dd class="mt-1 wrap-break-word font-semibold text-slate-200">{{ $isFinancialGoal ? 'R$ ' : '' }}{{ $targetAmount }}</dd></div>
            <div class="min-w-0"><dt class="text-slate-500">Progresso</dt><dd class="mt-1 font-semibold text-indigo-200">{{ $task->execution_progress }}%</dd></div>
        </dl>
        @if (! $isFinancialGoal && $task->unit)
            <p class="mt-2 text-xs text-slate-400">Unidade: {{ $task->unit }}</p>
        @endif
        <div role="progressbar" aria-label="Meta da tarefa" aria-valuenow="{{ $task->execution_progress }}" aria-valuemin="0" aria-valuemax="100" class="mt-2 h-1 overflow-hidden rounded-full bg-white/10"><div class="h-full {{ $task->goal_type === 'financial' ? 'bg-emerald-400' : 'bg-indigo-400' }}" style="width: {{ $task->execution_progress }}%"></div></div>
    @endif
    @if ($canEdit)
        <details data-details-key="task-{{ $task->id }}" class="mt-2">
            <summary class="cursor-pointer text-xs text-indigo-300">Editar tarefa e acompanhamento</summary>
            <form method="POST" action="{{ route('projetos.tarefas.update', [$projeto, $task], absolute: false) }}" data-project-task-form class="mt-3 grid gap-3">
                @csrf
                @method('PATCH')
                @include('partials.projeto-task-fields', ['task' => $task])
                <div class="flex flex-wrap items-center gap-2">
                    <button type="submit" class="rounded-lg bg-indigo-500 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-400">Salvar tarefa</button>
                    <button type="button" data-project-calendar-toggle aria-expanded="false" aria-controls="project-calendar-{{ $task->id }}" class="rounded-lg border border-indigo-400/30 bg-indigo-500/10 px-3 py-2 text-xs font-semibold text-indigo-200 hover:bg-indigo-500/20">{{ $calendarTask ? 'Alterar agendamento' : 'Agendar no calendário' }}</button>
                </div>
            </form>
            <section id="project-calendar-{{ $task->id }}" data-project-calendar-panel hidden class="mt-4 border-t border-white/10 pt-4">
                <h4 class="text-xs font-semibold text-slate-200">{{ $calendarTask ? 'Alterar agendamento no calendário' : 'Agendar no calendário' }}</h4>
                <p class="mt-1 text-[11px] leading-relaxed text-slate-500">Escolha quando você vai realizar esta tarefa. Ela aparecerá na sua agenda, mantendo o item e o prazo do projeto.</p>
                <form method="POST" action="{{ route('projetos.tarefas.agendar', [$projeto, $task], absolute: false) }}" data-project-task-form data-project-task-calendar class="mt-3 grid gap-3">
                    @csrf
                    <div class="grid gap-3 sm:grid-cols-3">
                        <label class="grid gap-1 text-xs text-slate-400">Data de realização <span class="sr-only">obrigatória</span><input name="date" type="date" required value="{{ $calendarTask?->date->toDateString() }}" class="w-full min-w-0 rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm text-white outline-none focus:border-indigo-400 scheme-dark"></label>
                        <label class="grid gap-1 text-xs text-slate-400">Horário inicial (opcional)<input name="start_time" type="time" value="{{ $calendarTask?->start_time ? substr($calendarTask->start_time, 0, 5) : '' }}" class="w-full min-w-0 rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm text-white outline-none focus:border-indigo-400 scheme-dark"></label>
                        <label class="grid gap-1 text-xs text-slate-400">Horário final (opcional)<input name="end_time" type="time" value="{{ $calendarTask?->end_time ? substr($calendarTask->end_time, 0, 5) : '' }}" class="w-full min-w-0 rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm text-white outline-none focus:border-indigo-400 scheme-dark"></label>
                    </div>
                    <p class="text-[11px] text-slate-500">Sem horário, a tarefa aparece em Dia todo.</p>
                    <p data-project-calendar-error role="alert" hidden class="text-xs text-rose-300"></p>
                    <button type="submit" class="justify-self-start rounded-lg border border-indigo-400/30 bg-indigo-500/10 px-3 py-2 text-xs font-semibold text-indigo-200 hover:bg-indigo-500/20 disabled:opacity-50">{{ $calendarTask ? 'Atualizar agendamento' : 'Agendar no calendário' }}</button>
                </form>
            </section>
        </details>
    @endif
</div>
