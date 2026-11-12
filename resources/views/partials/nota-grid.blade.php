<div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2 xl:grid-cols-3">
    @forelse ($notas as $nota)
        @include('partials.nota-card', ['nota' => $nota])
    @empty
        <div class="col-span-full rounded-2xl border border-dashed border-white/10 bg-slate-900/40 px-6 py-14 text-center"><p class="text-3xl text-indigo-300/60" aria-hidden="true">▤</p><h2 class="mt-4 text-base font-semibold text-slate-200">{{ $term !== '' ? 'Nenhuma nota encontrada' : ($filter === 'archived' ? 'Nenhuma nota arquivada' : 'Deixe espaço para suas ideias') }}</h2><p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-500">{{ $term !== '' ? 'Tente outro título, trecho ou item da lista.' : 'Crie uma anotação ou lista. Você pode usar sozinha e vincular a uma tarefa quando precisar.' }}</p>@if ($filter !== 'archived')<button type="button" data-note-create="text" class="mt-5 rounded-xl bg-indigo-500 px-5 py-3 text-xs font-semibold text-white">Criar minha primeira nota</button>@endif</div>
    @endforelse
</div>
<div class="mt-6">{{ $notas->links() }}</div>
