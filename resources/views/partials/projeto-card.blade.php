@php
    $meta = $projeto->statusMeta();
    $isOwner = $projeto->isOwnedBy(auth()->user());
    $canManage = $projeto->canBeManagedBy(auth()->user());
    $canEdit = $projeto->canBeEditedBy(auth()->user());
    $accepted = $projeto->participacoes->where('status', 'aceito');
    $pending = $projeto->participacoes->where('status', 'pendente');
    $financialTasks = $projeto->tasks->where('goal_type', 'financial');
@endphp
<div id="projeto-card-{{ $projeto->id }}" data-board-card data-projeto-id="{{ $projeto->id }}"
    data-title="{{ $projeto->title }}" data-description="{{ $projeto->description }}" data-details="{{ $projeto->details }}" data-priority="{{ $projeto->priority }}"
    data-starts-at="{{ $projeto->starts_at?->format('Y-m-d') }}" data-due-at="{{ $projeto->due_at?->format('Y-m-d') }}"
    data-filter-item data-filter-title="{{ $projeto->title }}" data-filter-start="{{ $projeto->due_at?->format('Y-m-d') }}"
    data-status="{{ $projeto->archived ? 'archived' : $projeto->status }}" data-progress="{{ $projeto->progress }}"
    data-overdue="{{ $projeto->overdue ? 'true' : 'false' }}" data-soon="{{ ! $projeto->archived && $projeto->status !== 'completed' && $projeto->due_at && $projeto->due_at->betweenIncluded(today(), today()->addDays(7)) ? 'true' : 'false' }}"
    class="rounded-2xl border border-white/10 bg-slate-900/70 p-4 shadow-lg shadow-black/20 sm:p-5">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 class="wrap-break-word text-base font-semibold text-white sm:text-lg">{{ $projeto->title }}</h2>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                <span class="inline-flex items-center gap-1.5 {{ $meta['text'] }}"><span class="h-1.5 w-1.5 rounded-full {{ $meta['dot'] }}"></span>{{ $meta['label'] }}</span>
                @if ($projeto->overdue)<span class="rounded-full border border-rose-400/20 bg-rose-400/10 px-2 py-0.5 text-rose-300">Atrasado</span>@endif
                @if ($projeto->archived)<span class="text-slate-400">Arquivado</span>@endif
                <span class="{{ $projeto->priority === 'high' ? 'text-orange-300' : 'text-slate-400' }}">Prioridade {{ ['low' => 'baixa', 'medium' => 'média', 'high' => 'alta'][$projeto->priority] }}</span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <strong aria-label="Progresso de execução" class="text-xl text-white">{{ $projeto->progress }}%</strong>
            @if ($canManage)
                <details class="relative">
                    <summary aria-label="Ações do projeto" class="cursor-pointer list-none rounded-lg px-2 text-lg text-slate-300 hover:bg-white/10">⋯</summary>
                    <div class="absolute right-0 z-10 grid w-44 gap-1 rounded-xl border border-white/10 bg-slate-900 p-2 text-sm shadow-xl">
                        <button type="button" data-edit-trigger aria-label="Editar projeto" class="rounded-lg px-3 py-2 text-left text-slate-300 hover:bg-white/10">Editar projeto</button>
                        <form method="POST" action="{{ route('projetos.update', $projeto, absolute: false) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="title" value="{{ $projeto->title }}"><input type="hidden" name="archived" value="{{ $projeto->archived ? 0 : 1 }}">
                            <button class="w-full rounded-lg px-3 py-2 text-left text-slate-300 hover:bg-white/10">{{ $projeto->archived ? 'Restaurar' : 'Arquivar' }}</button>
                        </form>
                        <button type="button" data-delete-card-url="{{ route('projetos.destroy', $projeto, absolute: false) }}" data-confirm-message="Excluir este projeto e todas as suas tarefas? Essa ação não pode ser desfeita." class="rounded-lg px-3 py-2 text-left text-rose-400 hover:bg-white/10">Excluir projeto</button>
                    </div>
                </details>
            @endif
        </div>
    </div>
    <div role="progressbar" aria-label="Progresso de execução" aria-valuenow="{{ $projeto->progress }}" aria-valuemin="0" aria-valuemax="100" class="mt-3 h-1.5 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-indigo-400" style="width: {{ $projeto->progress }}%"></div></div>
    <div class="mt-3 flex flex-wrap justify-between gap-2 text-xs text-slate-400">
        <span>{{ $projeto->tasks->filter(fn ($task) => $task->is_finished)->count() }} de {{ $projeto->tasks->count() }} tarefas concluídas</span>
        @if ($projeto->due_at)<span>Prazo · {{ $projeto->due_at->format('d-m-Y') }}</span>@endif
    </div>
    @if ($financialTasks->isNotEmpty())
        <div class="mt-3 rounded-lg border border-emerald-400/15 bg-emerald-400/5 p-3 text-xs text-emerald-300">
            <div class="flex justify-between gap-2"><span>Progresso financeiro</span><strong>{{ $projeto->financial_progress }}%</strong></div>
            <p class="mt-1">R$ {{ number_format($projeto->financial_saved, 2, ',', '.') }} / R$ {{ number_format($financialTasks->sum('target_value'), 2, ',', '.') }}</p>
            <p class="mt-1 text-slate-400">Acompanhado separadamente da execução.</p>
        </div>
    @endif
    <details data-details-key="participants" class="mt-4">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-2 text-xs text-indigo-300">
            <span class="flex items-center gap-2">
                <span class="flex -space-x-1.5">
                    <span title="{{ $projeto->user->handle() }} · Administrador" class="inline-flex h-7 w-7 items-center justify-center rounded-full border-2 border-slate-900 bg-indigo-500/30 text-[10px] font-semibold text-indigo-200">{{ mb_strtoupper(mb_substr($projeto->user->name, 0, 2)) }}</span>
                    @foreach ($accepted->take(3) as $participacao)
                        <span title="{{ $participacao->participant->handle() }}" class="inline-flex h-7 w-7 items-center justify-center rounded-full border-2 border-slate-900 bg-slate-700 text-[10px] font-semibold text-slate-200">{{ mb_strtoupper(mb_substr($participacao->participant->name, 0, 2)) }}</span>
                    @endforeach
                </span>
                {{ $accepted->count() + 1 }} participantes
            </span>
            <span>{{ $canManage ? 'Gerenciar' : 'Visualizar' }} @if ($pending->isNotEmpty()) · {{ $pending->count() }} pendentes @endif</span>
        </summary>
        @include('partials.projeto-participants')
    </details>
    <details data-details-key="execution" class="mt-4 border-t border-white/10 pt-3">
        <summary class="cursor-pointer text-sm text-indigo-300">Visualizar tarefas e detalhes</summary>
        @if ($projeto->starts_at)<p class="mt-3 text-xs text-slate-400">Início · {{ $projeto->starts_at->format('d-m-Y') }}</p>@endif
        @foreach (['description' => 'Objetivo', 'details' => 'Descrição'] as $field => $label)
            @if ($projeto->$field)
                <div class="mt-4"><h3 class="mb-2 text-xs font-medium text-slate-400">{{ $label }}</h3><p class="whitespace-pre-wrap wrap-break-word text-sm text-slate-300">{{ $projeto->$field }}</p></div>
            @endif
        @endforeach
        <div class="mt-4 grid gap-2">
            @foreach ($projeto->tasks->whereNull('parent_id') as $rootTask)
                @include('partials.projeto-task', ['task' => $rootTask])
                @foreach ($projeto->tasks->where('parent_id', $rootTask->id) as $childTask)
                    @include('partials.projeto-task', ['task' => $childTask])
                @endforeach
            @endforeach
        </div>
        @if ($canEdit)
            <details data-details-key="new-task" class="mt-3 rounded-xl border border-dashed border-white/15 p-3">
                <summary class="cursor-pointer text-center text-sm text-indigo-300">+ Adicionar nova tarefa</summary>
                <form method="POST" action="{{ route('projetos.tarefas.store', $projeto, absolute: false) }}" data-project-task-form class="mt-4 grid gap-3">
                    @csrf
                    @include('partials.projeto-task-fields', ['task' => null])
                    <button type="submit" class="justify-self-start rounded-lg bg-indigo-500 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-400">Adicionar tarefa</button>
                </form>
            </details>
        @endif
        @include('partials.projeto-collaboration')
    </details>
</div>
