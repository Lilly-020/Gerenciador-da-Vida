@php($palette = \App\Models\Nota::COLORS[$nota->color])
<article data-note-card="{{ $nota->id }}" class="relative rounded-2xl border p-4 shadow-lg shadow-black/10 {{ $palette['class'] }}">
    <div class="mb-3 flex items-start gap-3">
        <button type="button" data-note-edit="{{ route('notas.show', $nota, absolute: false) }}" class="min-w-0 flex-1 text-left"><span class="text-[10px] font-medium uppercase tracking-wider text-slate-500">{{ $nota->items->isNotEmpty() ? 'Lista' : 'Anotação' }}{{ $nota->pinned ? ' · Fixada' : '' }}</span><h2 class="mt-1 wrap-anywhere text-base font-semibold text-slate-100">{{ $nota->title }}</h2></button>
        <details data-note-menu class="relative shrink-0"><summary aria-label="Opções de {{ $nota->title }}" class="flex h-9 w-9 cursor-pointer list-none items-center justify-center rounded-lg text-xl text-slate-400 hover:bg-white/10">⋯</summary><div class="absolute right-0 z-30 grid w-52 gap-1 rounded-xl border border-white/10 bg-slate-900 p-1.5 shadow-xl shadow-black/40">
            <button type="button" data-note-edit="{{ route('notas.show', $nota, absolute: false) }}" class="rounded-lg px-3 py-2 text-left text-xs text-slate-200 hover:bg-white/5">Editar nota</button>
            <button type="button" data-note-action="pin" data-note-url="{{ route('notas.update', $nota, absolute: false) }}" data-value="{{ $nota->pinned ? '0' : '1' }}" class="rounded-lg px-3 py-2 text-left text-xs text-slate-200 hover:bg-white/5">{{ $nota->pinned ? 'Desafixar' : 'Fixar no topo' }}</button>
            <button type="button" data-note-link="{{ route('notas.show', $nota, absolute: false) }}" class="rounded-lg px-3 py-2 text-left text-xs text-slate-200 hover:bg-white/5">{{ $nota->tarefa ? 'Gerenciar tarefa vinculada' : 'Vincular a uma tarefa' }}</button>
            <button type="button" data-note-schedule="{{ route('notas.show', $nota, absolute: false) }}" class="rounded-lg px-3 py-2 text-left text-xs text-indigo-200 hover:bg-indigo-500/10">Agendar no calendário</button>
            <button type="button" data-note-action="archive" data-note-url="{{ route('notas.update', $nota, absolute: false) }}" data-value="{{ $nota->archived ? '0' : '1' }}" class="rounded-lg px-3 py-2 text-left text-xs text-slate-200 hover:bg-white/5">{{ $nota->archived ? 'Desarquivar' : 'Arquivar' }}</button>
            <button type="button" data-note-delete="{{ route('notas.destroy', $nota, absolute: false) }}" class="rounded-lg px-3 py-2 text-left text-xs text-rose-300 hover:bg-rose-500/10">Excluir nota</button>
        </div></details>
    </div>
    <div class="app-scroll max-h-72 space-y-3 overflow-y-auto overscroll-contain">
        @if ($nota->content)<button type="button" data-note-edit="{{ route('notas.show', $nota, absolute: false) }}" class="block w-full whitespace-pre-wrap wrap-anywhere text-left text-sm leading-relaxed text-slate-300">{{ $nota->content }}</button>@endif
        @if ($nota->items->isNotEmpty())<div class="space-y-2">@foreach ($nota->items as $item)<label class="flex cursor-pointer items-start gap-2.5 text-sm"><x-checkbox data-note-item-toggle="{{ route('notas.itens.update', [$nota, $item], absolute: false) }}" :checked="$item->completed" /><span @class(['wrap-anywhere leading-relaxed', 'text-slate-500 line-through' => $item->completed, 'text-slate-300' => ! $item->completed])>{{ $item->text }}</span></label>@endforeach</div>@endif
    </div>
    @if ($nota->items->isNotEmpty())<p class="mt-3 text-[10px] text-slate-500">{{ $nota->items->where('completed', true)->count() }} de {{ $nota->items->count() }} itens concluídos</p>@endif
    <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-white/5 pt-3">
        @if ($nota->tarefa)<a href="{{ route('tarefas', ['date' => $nota->tarefa->date->toDateString(), 'view' => 'week', 'task_id' => $nota->tarefa->id], absolute: false) }}" class="max-w-full truncate rounded-md border border-indigo-400/20 bg-indigo-500/10 px-2 py-1 text-[10px] text-indigo-200" title="{{ $nota->tarefa->title }}">↗ {{ $nota->tarefa->title }} · {{ $nota->tarefa->date->format('d/m') }}</a>@else<span class="text-[10px] text-slate-500">Nota independente</span>@endif
        <time datetime="{{ $nota->updated_at->toIso8601String() }}" class="text-[10px] text-slate-500">{{ $nota->updated_at->format('d/m/Y') }}</time>
    </div>
</article>
