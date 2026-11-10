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
    @if ($task->goal_type !== 'checklist')
        <p class="mt-2 text-xs text-indigo-200">{{ $task->goal_type === 'financial' ? 'R$' : $task->unit }} {{ number_format($task->current_value, 2, ',', '.') }} / {{ number_format($task->target_value, 2, ',', '.') }} · {{ $task->execution_progress }}%</p>
        <div role="progressbar" aria-label="Meta da tarefa" aria-valuenow="{{ $task->execution_progress }}" aria-valuemin="0" aria-valuemax="100" class="mt-2 h-1 overflow-hidden rounded-full bg-white/10"><div class="h-full {{ $task->goal_type === 'financial' ? 'bg-emerald-400' : 'bg-indigo-400' }}" style="width: {{ $task->execution_progress }}%"></div></div>
    @endif
    @if ($canEdit)
        <details data-details-key="task-{{ $task->id }}" class="mt-2">
            <summary class="cursor-pointer text-xs text-indigo-300">Editar tarefa e acompanhamento</summary>
            <form method="POST" action="{{ route('projetos.tarefas.update', [$projeto, $task], absolute: false) }}" data-project-task-form class="mt-3 grid gap-3">
                @csrf
                @method('PATCH')
                @include('partials.projeto-task-fields', ['task' => $task])
                <button type="submit" class="justify-self-start rounded-lg bg-indigo-500 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-400">Salvar tarefa</button>
            </form>
        </details>
    @endif
</div>
