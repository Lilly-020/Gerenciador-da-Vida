<x-layout title="Notas" align="start">
    <div id="notas-page" data-index-url="{{ route('notas', absolute: false) }}" class="w-full min-w-0 max-w-6xl">
        <div class="mb-7 flex flex-wrap items-start justify-between gap-4">
            <div><p class="mb-2 text-[10px] font-semibold uppercase tracking-[0.2em] text-indigo-300">Ideias, lembretes e listas</p><h1 class="text-3xl font-semibold tracking-tight text-white">Notas</h1><p class="mt-2 text-sm text-slate-400">Um lugar para lembrar, planejar e tirar as ideias do papel.</p></div>
            <div class="flex gap-2"><button type="button" data-note-create="text" class="rounded-xl bg-indigo-500 px-4 py-3 text-xs font-semibold text-white hover:bg-indigo-400">+ Nova nota</button><button type="button" data-note-create="list" class="rounded-xl border border-indigo-400/30 bg-indigo-500/10 px-4 py-3 text-xs font-semibold text-indigo-200 hover:bg-indigo-500/20">☑ Nova lista</button></div>
        </div>
        <form action="{{ route('notas', absolute: false) }}" method="GET" class="mb-5 flex flex-wrap gap-2" role="search">
            <label class="sr-only" for="notas-search">Pesquisar notas e itens</label><input id="notas-search" name="q" maxlength="100" value="{{ $term }}" placeholder="Pesquisar nas notas e nas listas…" class="min-w-48 flex-1 rounded-xl border border-white/10 bg-slate-900/70 px-4 py-3 text-sm text-white outline-none focus:border-indigo-400">
            <input type="hidden" name="filter" value="{{ $filter }}"><button type="submit" class="rounded-xl border border-white/10 px-5 py-3 text-xs font-semibold text-slate-300 hover:bg-white/5">Buscar</button>
        </form>
        <nav aria-label="Filtrar notas" class="mb-6 flex flex-wrap gap-2">
            @foreach (['all' => 'Todas', 'text' => 'Anotações', 'lists' => 'Listas', 'archived' => 'Arquivadas'] as $key => $label)
                <a href="{{ route('notas', ['filter' => $key, 'q' => $term], absolute: false) }}" @if ($filter === $key) aria-current="page" @endif @class(['rounded-full border px-4 py-2 text-xs font-semibold transition', 'border-indigo-400/30 bg-indigo-500/20 text-indigo-100' => $filter === $key, 'border-white/10 text-slate-400 hover:bg-white/5' => $filter !== $key])>{{ $label }}</a>
            @endforeach
        </nav>
        <div data-notas-results>@include('partials.nota-grid')</div>
    </div>
</x-layout>
