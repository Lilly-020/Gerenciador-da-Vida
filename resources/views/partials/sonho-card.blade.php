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
                <div class="flex items-center gap-2">
                    <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-2.5 text-sm text-slate-300">
                        <input
                            type="checkbox"
                            data-task-toggle-url="{{ route('sonhos.tarefas.update', [$sonho, $task], absolute: false) }}"
                            @checked($task->completed)
                            class="h-4 w-4 shrink-0 rounded border-white/20 bg-slate-950/60 text-indigo-500 focus:ring-indigo-400/40"
                        >
                        <span class="wrap-break-word">{{ $task->title }}</span>
                    </label>
                    <button
                        type="button"
                        data-task-delete-url="{{ route('sonhos.tarefas.destroy', [$sonho, $task], absolute: false) }}"
                        class="shrink-0 text-slate-500 transition hover:text-rose-400"
                        aria-label="Excluir tarefa"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
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

    <div class="mt-4 flex items-center justify-end border-t border-white/10 pt-3">
        <button
            type="button"
            data-delete-card-url="{{ route('sonhos.destroy', $sonho, absolute: false) }}"
            data-confirm-message="Excluir este sonho e todas as suas tarefas? Essa ação não pode ser desfeita."
            class="text-xs font-medium text-slate-400 transition hover:text-rose-400"
        >
            Excluir sonho
        </button>
    </div>
</div>
