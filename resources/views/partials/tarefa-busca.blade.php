<div class="rounded-2xl border border-white/10 bg-slate-900/70 p-4 shadow-lg shadow-black/20 sm:p-5">
    @forelse ($tarefas as $tarefa)
        <a
            href="{{ route('tarefas', ['date' => $tarefa->date->toDateString()], absolute: false) }}"
            class="flex items-center justify-between gap-3 border-t border-white/10 py-2 text-sm transition first:border-t-0 first:pt-0 last:pb-0 hover:text-indigo-200"
        >
            <span class="wrap-break-word min-w-0 {{ $tarefa->completed ? 'text-slate-500 line-through' : 'text-slate-200' }}">{{ $tarefa->title }}</span>
            <span class="shrink-0 text-xs text-slate-400">{{ $tarefa->date->format('d/m/Y') }}</span>
        </a>
    @empty
        <p class="text-sm text-slate-400">Nenhuma tarefa encontrada.</p>
    @endforelse

    @if ($hasMore)
        <p class="mt-2 text-xs text-slate-500">Mostrando as {{ $tarefas->count() }} mais recentes — refine a busca para ver outras.</p>
    @endif
</div>
