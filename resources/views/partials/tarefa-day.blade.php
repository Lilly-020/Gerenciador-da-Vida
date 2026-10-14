@php
    $completed = $tasks->where('completed', true)->count();
    $total = $tasks->count();
@endphp

<div id="tarefas-day" class="rounded-2xl border border-white/10 bg-slate-900/70 p-4 shadow-lg shadow-black/20 sm:p-5">
    <div class="mb-4">
        <h2 class="text-base font-semibold text-white">
            {{ \App\Models\Tarefa::weekdayLabel($date) }}, {{ $date->format('d') }} de {{ Str::lower(\App\Models\Tarefa::monthLabel($date)) }}
        </h2>
        <p class="mt-1 text-xs text-slate-400">
            @if ($total > 0)
                {{ $completed }} de {{ $total }} tarefas concluídas
            @else
                Nenhuma tarefa para este dia
            @endif
        </p>
    </div>

    @if ($tasks->isNotEmpty())
        <ul class="max-h-96 space-y-2 overflow-y-auto pr-1">
            @foreach ($tasks as $task)
                <li class="flex items-center justify-between gap-2 rounded-lg border border-white/10 bg-slate-950/40 px-3 py-2" data-task-row>
                    <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-2.5 text-sm text-slate-300">
                        <input
                            type="checkbox"
                            data-task-toggle-url="{{ route('tarefas.update', $task, absolute: false) }}"
                            @checked($task->completed)
                            class="h-4 w-4 shrink-0 rounded border-white/20 bg-slate-950/60 text-indigo-500 focus:ring-indigo-400/40"
                        >
                        <span class="wrap-break-word {{ $task->completed ? 'text-slate-500 line-through' : '' }}" data-task-title>
                            {{ $task->title }}
                        </span>
                    </label>
                    @if ($task->series_id)
                        <span class="shrink-0 text-xs text-indigo-300" title="Tarefa repetida">↻</span>
                    @endif
                    <button
                        type="button"
                        data-task-edit-trigger
                        data-task-edit-url="{{ route('tarefas.update', $task, absolute: false) }}"
                        class="shrink-0 text-slate-500 transition hover:text-indigo-300"
                        aria-label="Editar tarefa"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                            <path d="m5.433 13.917 1.262-3.155A4 4 0 0 1 7.58 9.42l6.92-6.918a2.121 2.121 0 0 1 3 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 0 1-.65-.65Z" />
                            <path d="M3.5 5.75c0-.69.56-1.25 1.25-1.25H10A.75.75 0 0 0 10 3H4.75A2.75 2.75 0 0 0 2 5.75v9.5A2.75 2.75 0 0 0 4.75 18h9.5A2.75 2.75 0 0 0 17 15.25V10a.75.75 0 0 0-1.5 0v5.25c0 .69-.56 1.25-1.25 1.25h-9.5c-.69 0-1.25-.56-1.25-1.25v-9.5Z" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        data-delete-task-url="{{ route('tarefas.destroy', $task, absolute: false) }}"
                        @if ($task->series_id) data-task-series @endif
                        class="shrink-0 text-slate-500 transition hover:text-rose-400"
                        aria-label="Excluir tarefa"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </li>
            @endforeach
        </ul>
    @endif

    <form data-add-tarefa-form action="{{ route('tarefas.store', absolute: false) }}" method="POST" class="mt-3">
        @csrf
        <input type="hidden" name="date" value="{{ $date->toDateString() }}">
        <div class="flex items-center gap-2">
            <input
                type="text"
                name="title"
                required
                maxlength="255"
                placeholder="Nova tarefa para o dia"
                class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white placeholder-slate-500 outline-none focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
            >
            <button type="submit" class="shrink-0 rounded-lg bg-indigo-500/90 px-3 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500">
                Adicionar
            </button>
        </div>

        <details class="group mt-2" data-repeat>
            <summary class="cursor-pointer list-none text-xs text-slate-400 transition hover:text-indigo-300 [&::-webkit-details-marker]:hidden">
                <span class="inline-block transition group-open:rotate-90">›</span>
                Repetir esta tarefa
            </summary>

            <div class="mt-2 space-y-3 rounded-lg border border-white/10 bg-slate-950/40 p-3">
                <div>
                    <p class="mb-1.5 text-xs font-medium text-slate-300">Dias da semana</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach (\App\Models\Tarefa::WEEKDAY_CHIPS as $weekday => $label)
                            <label class="cursor-pointer">
                                <input
                                    type="checkbox"
                                    name="weekdays[]"
                                    value="{{ $weekday }}"
                                    class="peer sr-only"
                                >
                                <span class="inline-block rounded-full border border-white/10 px-2.5 py-1 text-xs text-slate-400 transition peer-checked:border-indigo-400/50 peer-checked:bg-indigo-500/25 peer-checked:text-indigo-100 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-400/40">
                                    {{ $label }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <div class="mt-2 flex flex-wrap gap-3 text-xs text-indigo-300">
                        <button type="button" data-repeat-preset="1,2,3,4,5,6,0" class="transition hover:text-indigo-200">Semana toda</button>
                        <button type="button" data-repeat-preset="1,2,3,4,5" class="transition hover:text-indigo-200">Dias úteis</button>
                        <button type="button" data-repeat-preset="6,0" class="transition hover:text-indigo-200">Fim de semana</button>
                    </div>
                </div>

                <div>
                    <label for="tarefa-repeat-until" class="mb-1.5 block text-xs font-medium text-slate-300">Repetir até</label>
                    <input
                        id="tarefa-repeat-until"
                        type="date"
                        name="repeat_until"
                        value="{{ $date->copy()->addWeeks(4)->toDateString() }}"
                        min="{{ $date->toDateString() }}"
                        max="{{ $date->copy()->addDays(\App\Models\Tarefa::MAX_REPEAT_DAYS)->toDateString() }}"
                        class="rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white outline-none focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20 scheme-dark"
                    >
                </div>
            </div>
        </details>
    </form>
</div>
