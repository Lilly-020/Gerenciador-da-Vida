<div data-project-comment-history>
    <h3 class="text-sm font-semibold text-slate-200">Histórico de atividades</h3>
    <p class="mt-3 text-xs text-slate-500">Últimas 50 atividades</p>
    <ol class="mt-2 grid gap-3 border-l border-indigo-400/20 pl-3">
        @forelse ($projeto->activities as $activity)
            <li class="text-xs text-slate-400"><span class="text-slate-200">{{ $activity->user?->handle() ?? 'Usuário removido' }}</span> · {{ $activity->description }}<time class="mt-1 block text-slate-500">{{ $activity->created_at->format('d/m/Y H:i') }}</time></li>
        @empty
            <li class="text-xs text-slate-500">As próximas alterações aparecerão aqui.</li>
        @endforelse
    </ol>
</div>
