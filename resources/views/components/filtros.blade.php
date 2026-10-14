@props([
    'alvo',                 // id of the element holding the [data-filter-item] cards
    'datas' => true,        // offer the "de / até" date range
    'categorias' => false,  // offer the category select (options come from the cards)
    'dataLabel' => 'Data',
])

{{--
    Search bar for a page's list: by title, date range and (Financeiro)
    category. Filtering happens in the browser — see resources/js/filtros.js
    — over cards that describe themselves with data-filter-* attributes, so
    it keeps working when the list is re-rendered after an edit.
--}}
<div data-filtros data-filtros-alvo="{{ $alvo }}" class="mb-4 flex flex-wrap items-end gap-2">
    <div class="min-w-48 flex-1">
        <label for="filtro-titulo-{{ $alvo }}" class="sr-only">Buscar por título</label>
        <input
            id="filtro-titulo-{{ $alvo }}"
            type="search"
            data-filtro-titulo
            placeholder="Buscar por título"
            autocomplete="off"
            class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
        >
    </div>

    @if ($datas)
        <div>
            <label for="filtro-de-{{ $alvo }}" class="mb-1 block text-xs text-slate-400">{{ $dataLabel }} de</label>
            <input id="filtro-de-{{ $alvo }}" type="date" data-filtro-de class="rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20 scheme-dark">
        </div>
        <div>
            <label for="filtro-ate-{{ $alvo }}" class="mb-1 block text-xs text-slate-400">até</label>
            <input id="filtro-ate-{{ $alvo }}" type="date" data-filtro-ate class="rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20 scheme-dark">
        </div>
    @endif

    @if ($categorias)
        <div>
            <label for="filtro-categoria-{{ $alvo }}" class="sr-only">Categoria</label>
            <select id="filtro-categoria-{{ $alvo }}" data-filtro-categoria class="rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20">
                <option value="">Todas as categorias</option>
            </select>
        </div>
    @endif

    <button
        type="button"
        data-filtro-limpar
        class="hidden rounded-lg border border-white/10 px-3 py-2.5 text-sm text-slate-300 transition hover:text-white"
    >
        Limpar
    </button>

    <p data-filtro-resumo class="hidden w-full text-xs text-slate-400"></p>
</div>
