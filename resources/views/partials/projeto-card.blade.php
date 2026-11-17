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
    class="project-card min-w-0 rounded-2xl border border-white/10 bg-slate-900/70 shadow-lg shadow-black/20">
    <button type="button" data-project-open aria-haspopup="dialog" aria-controls="project-detail-{{ $projeto->id }}" class="project-card-preview w-full p-5 text-left focus-visible:outline-2 focus-visible:outline-indigo-400">
        <div class="mb-4 flex items-center justify-between gap-3 text-xs">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/5 px-2.5 py-1 {{ $meta['text'] }}"><span class="h-1.5 w-1.5 rounded-full {{ $meta['dot'] }}"></span>{{ $meta['label'] }}</span>
            <span class="rounded-md bg-indigo-400/10 px-2 py-1 text-indigo-200">{{ ['low' => 'Baixa', 'medium' => 'Média', 'high' => 'Alta'][$projeto->priority] }} prioridade</span>
        </div>
        <h2 class="line-clamp-2 min-h-14 wrap-break-word text-lg font-semibold text-white">{{ $projeto->title }}</h2>
        <p class="mt-2 line-clamp-2 min-h-10 text-sm text-slate-400">{{ $projeto->description ?: 'Organize as tarefas e acompanhe cada etapa.' }}</p>
        <div data-project-label-badges class="mt-3 flex min-h-6 flex-wrap gap-1.5">@include('partials.projeto-etiqueta-badges')</div>
        <div class="mt-6 flex justify-between gap-2 text-xs text-slate-400"><span>{{ $projeto->tasks->filter(fn ($task) => $task->is_finished)->count() }}/{{ $projeto->tasks->count() }} tarefas</span><strong class="text-indigo-200">{{ $projeto->progress }}%</strong></div>
        <div role="progressbar" aria-label="Progresso de execução" aria-valuenow="{{ $projeto->progress }}" aria-valuemin="0" aria-valuemax="100" class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-indigo-400" style="width: {{ $projeto->progress }}%"></div></div>
        <div class="mt-5 flex items-center justify-between gap-2 border-t border-white/10 pt-4 text-xs text-slate-400">
            <span class="flex min-w-0 items-center gap-2"><span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-500/25 text-indigo-200">{{ mb_strtoupper(mb_substr($projeto->user->name, 0, 2)) }}</span><span class="truncate">{{ $projeto->user->name }}</span>@if ($accepted->isNotEmpty())<span>+{{ $accepted->count() }}</span>@endif</span>
            @if ($projeto->due_at)<span class="shrink-0 {{ $projeto->overdue ? 'text-rose-300' : '' }}">{{ $projeto->due_at->format('d-m-Y') }}</span>@endif
            @if ($projeto->archived)<span>Arquivado</span>@endif
        </div>
    </button>
    @include('partials.projeto-detail')
</div>
