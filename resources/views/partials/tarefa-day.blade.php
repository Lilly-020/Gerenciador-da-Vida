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

    <button type="button" data-calendar-create="{{ $date->toDateString() }}" class="mb-4 rounded-lg bg-indigo-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-400">Nova tarefa</button>

    @if ($holiday = \App\Support\CalendarioFeriados::on($date))
        <div class="mb-4 rounded-xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200"><span class="text-xs font-semibold">Feriado nacional</span><p class="mt-1">{{ $holiday }}</p></div>
    @endif
    @if ($tasks->isNotEmpty())
        <ul class="max-h-96 space-y-2 overflow-y-auto pr-1">
            @foreach ($tasks as $task)
                <li class="flex items-center justify-between gap-2 rounded-lg border border-white/10 bg-slate-950/40 px-3 py-2" data-task-row>
                    <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-2.5 text-sm text-slate-300">
                        <span class="relative inline-flex h-5 w-5 shrink-0 items-center justify-center">
                        <input
                            type="checkbox"
                            data-task-toggle-url="{{ route('tarefas.update', $task, absolute: false) }}"
                            @checked($task->completed)
                            class="peer h-5 w-5 cursor-pointer appearance-none rounded-md border border-slate-600 bg-slate-950 checked:border-indigo-400 checked:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400 disabled:opacity-60"
                        >
                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" class="pointer-events-none absolute h-3.5 w-3.5 text-white opacity-0 peer-checked:opacity-100"><path d="m4 10 4 4 8-8" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </span>
                        <span class="wrap-break-word {{ $task->completed ? 'text-slate-500 line-through' : '' }}" data-task-title>
                            {{ $task->title }}
                        </span>
                    </label>
                    @if ($task->series_id)
                        <span class="shrink-0 text-xs text-indigo-300" title="Tarefa repetida">↻</span>
                    @endif
                    <button
                        type="button"
                        data-task-editor-url="{{ route('tarefas.show', $task, absolute: false) }}"
                        aria-haspopup="dialog"
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
                <li class="flex flex-wrap items-center justify-between gap-2 px-2 pb-2 text-xs">
                    <span class="text-slate-400">{{ $task->start_time ? substr($task->start_time, 0, 5).($task->end_time ? ' — '.substr($task->end_time, 0, 5) : '') : 'Dia todo' }}{{ $task->category ? ' · '.$task->category->name : '' }}</span>
                    <button type="button" data-task-details-url="{{ route('tarefas.show', $task, absolute: false) }}" aria-haspopup="dialog" class="rounded-lg border border-indigo-400/20 px-3 py-2 font-medium text-indigo-200 hover:bg-indigo-500/10">Ver detalhes e anexos</button>
                </li>
            @endforeach
        </ul>
    @endif

    <template data-task-create-template>
    <form data-add-tarefa-form action="{{ route('tarefas.store', absolute: false) }}" method="POST" class="mt-5 rounded-xl border border-indigo-400/20 bg-indigo-500/5 p-4">
        <h3 class="mb-1 text-sm font-semibold text-white">Adicionar tarefa</h3>
        <p class="mb-4 text-xs text-slate-400">Dê um título e preencha apenas as opções que precisar.</p>
        @csrf

        <label class="mb-1.5 block text-xs font-medium text-slate-300" for="task-create-title">Título da tarefa</label>
        <div class="flex items-center gap-2">
            <input
                id="task-create-title"
                type="text"
                name="title"
                required
                maxlength="255"
                placeholder="Nova tarefa para o dia"
                class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white placeholder-slate-500 outline-none focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
            >
        </div>
        <div class="mt-4 grid grid-cols-2 gap-3">
            <label class="grid gap-1.5 text-xs text-slate-300">Data de início<input type="date" name="date" required value="{{ $date->toDateString() }}" class="w-full min-w-0 rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white scheme-dark"></label>
            <label class="grid gap-1.5 text-xs text-slate-300">Data de fim (opcional)<input type="date" name="end_date" class="w-full min-w-0 rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white scheme-dark"></label>
        </div>
        <div data-new-task-schedule class="mt-4">@include('partials.tarefa-schedule-fields', ['task' => null])</div>
        <label class="mt-4 grid gap-1.5 text-xs text-slate-300">Observações (opcional)<textarea name="notes" rows="2" maxlength="10000" placeholder="O que você precisa lembrar?" class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white outline-none focus:border-indigo-400"></textarea></label>

        <section class="mt-4 border-t border-white/10 pt-4" data-repeat>
            <h4 class="text-xs font-semibold text-slate-200">Repetição (opcional)</h4><p class="mt-1 text-[11px] text-slate-500">Selecione os dias para repetir ou deixe sem seleção para uma tarefa única.</p>

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
        </section>
        <div class="mt-5 flex flex-wrap gap-2">
            <button type="submit" class="rounded-lg bg-indigo-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-400 disabled:opacity-50">Adicionar tarefa</button>
            <button type="submit" data-task-create-details class="rounded-lg border border-indigo-400/30 px-4 py-2.5 text-sm font-semibold text-indigo-200 hover:bg-indigo-500/10 disabled:opacity-50">Adicionar com anexos</button>
        </div>
    </form>
    </template>
</div>
