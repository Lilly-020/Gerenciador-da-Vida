@php
    $meta = $sonho->statusMeta();
@endphp

<div
    id="sonho-card-{{ $sonho->id }}"
    data-status="{{ $sonho->archived ? 'archived' : $sonho->status }}"
    data-target-date="{{ $sonho->target_date?->format('Y-m-d') }}"
    data-financial-goal="{{ $sonho->financial_goal }}"
    data-saved-amount="{{ $sonho->saved_amount }}"
    data-priority="{{ $sonho->priority }}"
    data-notes="{{ $sonho->notes }}"
    data-image-url="{{ $sonho->image_url }}"
    data-update-url="{{ route('sonhos.update', $sonho, absolute: false) }}"
    data-board-card
    data-sonho-id="{{ $sonho->id }}"
    data-title="{{ $sonho->title }}"
    data-filter-item
    data-filter-title="{{ $sonho->title }}"
    data-filter-start="{{ $sonho->created_at?->format('Y-m-d') }}"
    class="rounded-2xl border border-white/10 bg-slate-900/70 p-4 shadow-lg shadow-black/20 sm:p-5"
>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="flex min-w-0 items-center gap-2">
            <h2 class="wrap-break-word text-base font-semibold text-white sm:text-lg">{{ $sonho->title }}</h2>

        </div>

        <div class="flex shrink-0 items-center gap-4">
            <span class="inline-flex items-center gap-1.5 text-xs font-medium {{ $meta['text'] }}">
                <span class="h-1.5 w-1.5 rounded-full {{ $meta['dot'] }}"></span>
                {{ $meta['label'] }}
            </span>
            <span class="text-xl font-bold text-white">{{ $sonho->progress }}%</span>
            <details class="relative">
                <summary aria-label="Ações do sonho" class="cursor-pointer list-none rounded-lg px-2 py-1 text-lg text-slate-300 hover:bg-white/10">⋯</summary>
                <div class="absolute right-0 z-10 grid w-44 gap-1 rounded-xl border border-white/10 bg-slate-900 p-2 text-sm shadow-xl">
                    <button type="button" data-edit-trigger class="rounded-lg px-3 py-2 text-left text-slate-300 hover:bg-white/10">Editar</button>
                    <form action="{{ route('sonhos.update', $sonho, absolute: false) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="title" value="{{ $sonho->title }}">
                        <input type="hidden" name="archived" value="{{ $sonho->archived ? 0 : 1 }}">
                        <button class="w-full rounded-lg px-3 py-2 text-left text-slate-300 hover:bg-white/10">{{ $sonho->archived ? 'Restaurar' : 'Arquivar' }}</button>
                    </form>
                    <button type="button" data-delete-card-url="{{ route('sonhos.destroy', $sonho, absolute: false) }}" data-confirm-message="Excluir este sonho e todas as suas tarefas? Essa ação não pode ser desfeita." class="rounded-lg px-3 py-2 text-left text-rose-400 hover:bg-white/10">Excluir</button>
                </div>
            </details>
        </div>
    </div>

    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-400">
        <span>{{ $sonho->tasks->where('completed', true)->count() }} de {{ $sonho->tasks->count() }} tarefas concluídas</span>
        <span>Prioridade {{ ['low' => 'baixa', 'medium' => 'média', 'high' => 'alta'][$sonho->priority] }} @if ($sonho->target_date) · Até {{ $sonho->target_date->format('d/m/Y') }} @endif</span>
    </div>
    <div role="progressbar" aria-label="Progresso das tarefas" aria-valuenow="{{ $sonho->progress }}" aria-valuemin="0" aria-valuemax="100" class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/10">
        <div class="h-full rounded-full bg-indigo-400" style="width: {{ $sonho->progress }}%"></div>
    </div>
    @if ($sonho->financial_goal)
        <div class="mt-4 rounded-xl border border-emerald-400/15 bg-emerald-400/5 p-3">
            <div class="flex justify-between gap-2 text-sm text-emerald-300"><span>Meta financeira</span><strong>{{ $sonho->financial_progress }}%</strong></div>
            <p class="mt-1 text-sm text-slate-300">R$ {{ number_format($sonho->saved_amount ?? 0, 2, ',', '.') }} / R$ {{ number_format($sonho->financial_goal, 2, ',', '.') }}</p>
            <div role="progressbar" aria-label="Progresso financeiro" aria-valuenow="{{ $sonho->financial_progress }}" aria-valuemin="0" aria-valuemax="100" class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/10"><div class="h-full bg-emerald-400" style="width: {{ $sonho->financial_progress }}%"></div></div>
            <p class="mt-2 text-xs text-slate-400">Faltam R$ {{ number_format(max(0, $sonho->financial_goal - ($sonho->saved_amount ?? 0)), 2, ',', '.') }} para alcançar sua meta.</p>
            <button type="button" data-edit-trigger class="mt-2 text-xs text-emerald-300 underline">Atualizar valor economizado</button>
        </div>
    @endif
    <details data-sonho-details class="mt-3">
        <summary class="cursor-pointer text-sm text-indigo-300">Ver tarefas e detalhes</summary>
        @if ($sonho->image_url)
            <img src="{{ $sonho->image_url }}" alt="Imagem de {{ $sonho->title }}" loading="lazy" referrerpolicy="no-referrer" class="mt-3 max-h-48 w-full rounded-lg object-cover">
        @endif
        @if ($sonho->notes)
            <p class="mt-3 whitespace-pre-wrap wrap-break-word text-sm text-slate-300">{{ $sonho->notes }}</p>
        @endif
    @if ($sonho->tasks->isNotEmpty())
        <div class="mt-4 grid grid-cols-1 gap-x-8 gap-y-2.5 sm:grid-cols-2">
            @foreach ($sonho->tasks as $task)
                <div class="group flex items-center gap-2" data-task-row>
                    <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-2.5 text-sm text-slate-300">
                        <span class="relative inline-flex h-5 w-5 shrink-0 items-center justify-center">
                            <input
                                type="checkbox"
                                data-task-toggle-url="{{ route('sonhos.tarefas.update', [$sonho, $task], absolute: false) }}"
                                @checked($task->completed)
                                class="peer h-5 w-5 cursor-pointer appearance-none rounded-md border border-slate-600 bg-slate-950/60 shadow-inner transition duration-150 checked:border-indigo-400 checked:bg-indigo-500 hover:border-indigo-400 focus-visible:outline-2 focus-visible:outline-offset-3 focus-visible:outline-indigo-400 disabled:cursor-wait disabled:opacity-60 motion-reduce:transition-none"
                            >
                            <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" class="pointer-events-none absolute h-3.5 w-3.5 scale-75 text-white opacity-0 transition duration-150 peer-checked:scale-100 peer-checked:opacity-100 motion-reduce:transition-none">
                                <path d="m4 10 4 4 8-8" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <span class="wrap-break-word" data-task-title>{{ $task->title }}</span>
                    </label>
                    <button
                        type="button"
                        data-task-edit-trigger
                        data-task-edit-url="{{ route('sonhos.tarefas.update', [$sonho, $task], absolute: false) }}"
                        class="shrink-0 text-slate-500 transition hover:text-indigo-300 sm:opacity-0 sm:group-hover:opacity-100 sm:group-focus-within:opacity-100"
                        aria-label="Editar tarefa"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                            <path d="m5.433 13.917 1.262-3.155A4 4 0 0 1 7.58 9.42l6.92-6.918a2.121 2.121 0 0 1 3 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 0 1-.65-.65Z" />
                            <path d="M3.5 5.75c0-.69.56-1.25 1.25-1.25H10A.75.75 0 0 0 10 3H4.75A2.75 2.75 0 0 0 2 5.75v9.5A2.75 2.75 0 0 0 4.75 18h9.5A2.75 2.75 0 0 0 17 15.25V10a.75.75 0 0 0-1.5 0v5.25c0 .69-.56 1.25-1.25 1.25h-9.5c-.69 0-1.25-.56-1.25-1.25v-9.5Z" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        data-task-delete-url="{{ route('sonhos.tarefas.destroy', [$sonho, $task], absolute: false) }}"
                        class="shrink-0 text-slate-500 transition hover:text-rose-400 sm:opacity-0 sm:group-hover:opacity-100 sm:group-focus-within:opacity-100"
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

    </details>
</div>
