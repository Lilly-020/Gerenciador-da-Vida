<section data-task-notes-section class="mt-5 border-t border-white/10 pt-4">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2"><h3 class="text-sm font-semibold text-white">Notas e listas <span class="text-slate-500">({{ $task->notas->count() }})</span></h3><a href="{{ route('notas', absolute: false) }}" class="text-[11px] text-indigo-300">Abrir Notas ↗</a></div>
    <div class="space-y-3">
        @forelse ($task->notas as $nota)
            <div class="rounded-xl border border-white/10 bg-slate-950/30 p-3">
                <div class="flex items-start justify-between gap-3"><button type="button" data-note-edit="{{ route('notas.show', $nota, absolute: false) }}" class="wrap-anywhere text-left text-sm font-medium text-indigo-200">{{ $nota->title }}</button><button type="button" data-note-detach="{{ route('notas.tarefa', $nota, absolute: false) }}" class="shrink-0 text-[10px] text-slate-500 hover:text-rose-300">Desvincular</button></div>
                @if ($nota->content)<p class="app-scroll mt-2 max-h-32 overflow-y-auto whitespace-pre-wrap wrap-anywhere text-xs leading-relaxed text-slate-400">{{ $nota->content }}</p>@endif
                @if ($nota->items->isNotEmpty())<div class="app-scroll mt-3 max-h-60 space-y-2 overflow-y-auto">@foreach ($nota->items as $item)<label class="flex cursor-pointer items-start gap-2 text-xs"><x-checkbox data-note-item-toggle="{{ route('notas.itens.update', [$nota, $item], absolute: false) }}" :checked="$item->completed" /><span @class(['wrap-anywhere leading-relaxed', 'line-through text-slate-500' => $item->completed, 'text-slate-300' => ! $item->completed])>{{ $item->text }}</span></label>@endforeach</div><p class="mt-2 text-[10px] text-slate-500">{{ $nota->items->where('completed', true)->count() }} / {{ $nota->items->count() }} itens · A conclusão da tarefa é independente.</p>@endif
            </div>
        @empty
            <p class="text-xs leading-relaxed text-slate-500">Adicione uma anotação ou uma lista, como os itens que você precisa comprar.</p>
        @endforelse
    </div>
    <div class="mt-3 flex flex-wrap gap-2"><button type="button" data-note-create="text" data-note-task="{{ $task->id }}" class="rounded-lg border border-indigo-400/20 px-3 py-2 text-xs text-indigo-200">+ Nota</button><button type="button" data-note-create="list" data-note-task="{{ $task->id }}" class="rounded-lg border border-indigo-400/20 px-3 py-2 text-xs text-indigo-200">+ Checklist</button><button type="button" data-note-picker="{{ $task->id }}" class="rounded-lg border border-white/10 px-3 py-2 text-xs text-slate-300">Vincular existente</button></div>
</section>
