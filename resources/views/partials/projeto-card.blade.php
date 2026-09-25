@php
    $meta = $projeto->statusMeta();
    $visibleTasksCount = 3;
@endphp

<div id="projeto-card-{{ $projeto->id }}" data-board-card class="rounded-2xl border border-white/10 bg-slate-900/70 p-5 shadow-lg shadow-black/20 sm:p-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <h2 class="wrap-break-word text-base font-semibold text-white sm:text-lg">{{ $projeto->title }}</h2>

        <div class="flex shrink-0 items-center gap-4">
            <span class="inline-flex items-center gap-1.5 text-xs font-medium {{ $meta['text'] }}">
                <span class="h-1.5 w-1.5 rounded-full {{ $meta['dot'] }}"></span>
                {{ $meta['label'] }}
            </span>
            <span class="text-xl font-bold text-white">{{ $projeto->progress }}%</span>
        </div>
    </div>

    <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-white/10">
        <div class="h-full rounded-full bg-indigo-400/80 transition-[width]" style="width: {{ $projeto->progress }}%"></div>
    </div>

    @if ($projeto->starts_at || $projeto->due_at)
        <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-1 text-xs text-slate-400">
            @if ($projeto->starts_at)
                <span class="inline-flex items-center gap-1.5">
                    Início
                    <span class="h-1 w-1 rounded-full bg-slate-500"></span>
                    {{ $projeto->starts_at->format('d-m-Y') }}
                </span>
            @endif
            @if ($projeto->due_at)
                <span class="inline-flex items-center gap-1.5">
                    Prazo
                    <span class="h-1 w-1 rounded-full bg-slate-500"></span>
                    {{ $projeto->due_at->format('d-m-Y') }}
                </span>
            @endif
        </div>
    @endif

    @if ($projeto->description)
        <div class="mt-4">
            <p class="mb-1.5 text-xs font-medium tracking-wide text-slate-400 uppercase">Objetivo</p>
            <div class="wrap-break-word rounded-lg border border-indigo-400/20 bg-slate-950/40 px-3 py-2 text-sm text-slate-300">
                {{ $projeto->description }}
            </div>
        </div>
    @endif

    <div class="mt-4">
        <p class="mb-1.5 text-xs font-medium tracking-wide text-slate-400 uppercase">Tarefas</p>

        @if ($projeto->tasks->isNotEmpty())
            <div class="space-y-2">
                @foreach ($projeto->tasks as $index => $task)
                    <label
                        @class(['flex cursor-pointer items-center gap-2.5 text-sm text-slate-300', 'hidden' => $index >= $visibleTasksCount])
                        @if ($index >= $visibleTasksCount) data-task-extra @endif
                    >
                        <input
                            type="checkbox"
                            data-task-toggle-url="{{ route('projetos.tarefas.update', [$projeto, $task], absolute: false) }}"
                            @checked($task->completed)
                            class="h-4 w-4 shrink-0 rounded border-white/20 bg-slate-950/60 text-indigo-500 focus:ring-indigo-400/40"
                        >
                        <span class="wrap-break-word">{{ $task->title }}</span>
                    </label>
                @endforeach
            </div>

            @if ($projeto->tasks->count() > $visibleTasksCount)
                <button
                    type="button"
                    data-tasks-toggle
                    aria-expanded="false"
                    class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-indigo-300 transition hover:text-indigo-200"
                >
                    <span data-tasks-toggle-label>Ver mais</span>
                    <svg data-tasks-toggle-icon xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5 transition-transform">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                    </svg>
                </button>
            @endif
        @endif
    </div>

    <div class="mt-4">
        <button
            type="button"
            data-add-task-trigger
            class="w-full rounded-lg border border-dashed border-white/15 px-4 py-2 text-center text-sm text-slate-400 transition hover:border-indigo-400/40 hover:text-indigo-300"
        >
            + Adicionar nova tarefa
        </button>

        <form
            data-add-task-form
            action="{{ route('projetos.tarefas.store', $projeto, absolute: false) }}"
            method="POST"
            class="hidden"
        >
            @csrf
            <div class="flex items-center gap-2">
                <input
                    type="text"
                    name="title"
                    data-add-task-input
                    maxlength="255"
                    required
                    placeholder="Nome da tarefa"
                    class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white placeholder-slate-500 outline-none focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                >
                <button type="submit" class="shrink-0 rounded-lg bg-indigo-500/90 px-3 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500">
                    Adicionar
                </button>
                <button type="button" data-add-task-cancel class="shrink-0 rounded-lg border border-white/10 px-3 py-2 text-sm text-slate-400 transition hover:text-white">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>
