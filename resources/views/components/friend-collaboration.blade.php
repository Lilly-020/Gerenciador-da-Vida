@props(['item', 'type'])
<details class="mt-4 rounded-xl border border-white/10 p-3 text-sm" data-friend-collaboration>
    <summary class="cursor-pointer font-medium text-indigo-200">Amigos neste {{ ['projeto' => 'projeto', 'sonho' => 'sonho', 'tarefa' => 'tarefa'][$type] }}</summary>
    <div class="mt-3 grid gap-3">
        <p class="text-xs text-slate-400">Cada amigo vê somente este item. A exclusão fica com quem criou.</p>
        @foreach ($item->participacoes->where('status', 'aceito') as $entry)
            <div class="flex flex-wrap items-center justify-between gap-2"><span>{{ $entry->participant->name }} · {{ $entry->participant->handle() }}</span>@if ($item->user_id === auth()->id() || $entry->participant_id === auth()->id())<form action="{{ route('participacoes.destroy', $entry, absolute: false) }}" method="POST">@csrf @method('DELETE')<button class="text-xs text-rose-300">{{ $entry->participant_id === auth()->id() ? 'Sair' : 'Remover' }}</button></form>@endif</div>
        @endforeach
        @if ($item->user_id === auth()->id())
            <form method="POST" action="{{ route('participacoes.store', absolute: false) }}" class="flex flex-wrap gap-3">@csrf<input type="hidden" name="tipo" value="{{ $type }}"><input type="hidden" name="item_id" value="{{ $item->id }}"><select name="friend_id" required aria-label="Selecionar amigo" class="min-w-0 flex-1"><option value="">Selecionar amigo</option>@foreach ($friends->whereNotIn('id', $item->participacoes->where('status', 'aceito')->pluck('participant_id')) as $friend)<option value="{{ $friend->id }}">{{ $friend->name }} · {{ $friend->handle() }}</option>@endforeach</select><button class="quadro-primary">Adicionar amigo</button></form>
            <a href="{{ route('perfil', ['aba' => 'amigos'], absolute: false) }}" class="text-xs text-indigo-300">Adicionar uma amizade pelo apelido</a>
        @endif
    </div>
</details>
