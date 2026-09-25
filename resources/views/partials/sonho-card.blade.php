@php
    $meta = $sonho->statusMeta();
@endphp

<div id="sonho-card-{{ $sonho->id }}" data-board-card class="rounded-2xl border border-white/10 bg-slate-900/70 p-5 shadow-lg shadow-black/20 sm:p-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <h2 class="wrap-break-word text-base font-semibold text-white sm:text-lg">{{ $sonho->title }}</h2>

        <div class="flex shrink-0 items-center gap-4">
            <span class="inline-flex items-center gap-1.5 text-xs font-medium {{ $meta['text'] }}">
                <span class="h-1.5 w-1.5 rounded-full {{ $meta['dot'] }}"></span>
                {{ $meta['label'] }}
            </span>
            <span class="text-xl font-bold text-white">{{ $sonho->progress }}%</span>
        </div>
    </div>

    @if ($sonho->tasks->isNotEmpty())
        <div class="mt-4 grid grid-cols-1 gap-x-8 gap-y-2.5 sm:grid-cols-2">
            @foreach ($sonho->tasks as $task)
                <label class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-300">
                    <input
                        type="checkbox"
                        data-task-toggle-url="{{ route('sonhos.tarefas.update', [$sonho, $task], absolute: false) }}"
                        @checked($task->completed)
                        class="h-4 w-4 shrink-0 rounded border-white/20 bg-slate-950/60 text-indigo-500 focus:ring-indigo-400/40"
                    >
                    <span class="wrap-break-word">{{ $task->title }}</span>
                </label>
            @endforeach
        </div>
    @endif

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
            action="{{ route('sonhos.tarefas.store', $sonho, absolute: false) }}"
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
