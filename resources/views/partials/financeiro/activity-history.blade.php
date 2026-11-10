<details class="mt-4 rounded-xl border border-white/10 p-4">
    <summary class="cursor-pointer text-sm text-indigo-300">Histórico de alterações</summary>
    <p class="mt-3 text-xs text-slate-500">Últimas 50 atividades</p>
    <ol class="mt-3 grid gap-3">@forelse ($activities as $activity)<li class="text-xs text-slate-400"><span class="text-slate-200">{{ $activity->actor?->handle() ?? 'Sistema' }}</span> · {{ $activity->description }}<time class="mt-1 block text-slate-500">{{ $activity->created_at->format('d/m/Y H:i') }}</time></li>@empty<li class="text-xs text-slate-500">As próximas alterações aparecerão aqui.</li>@endforelse</ol>
</details>
