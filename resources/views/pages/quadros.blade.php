<x-layout title="Planner" align="start">
    <div data-quadros-index data-url="{{ route('cursos', absolute: false) }}" class="app-expand-with-sidebar w-full min-w-0 max-w-7xl">
        <header class="mb-8 flex flex-wrap items-center justify-between gap-4">
            <x-page-heading eyebrow="Seu planejamento, do seu jeito" title="Planner" description="Crie quadros, organize suas listas e avance um cartão por vez." />
            <button type="button" data-quadro-create class="quadro-primary">+ Criar quadro</button>
        </header>
        <div class="mb-7 grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-[2fr_1fr_1fr_1fr_auto]">
            <label class="grid gap-2 text-xs text-slate-400">Buscar quadro<input type="search" data-quadro-index-search placeholder="Buscar quadro pelo nome..." class="w-full"></label>
            <label class="grid gap-2 text-xs text-slate-400">Categoria<select data-quadro-index-category><option value="">Todas as categorias</option>@foreach ($quadros->pluck('category')->filter()->unique()->sort() as $category)<option value="{{ $category }}">{{ $category }}</option>@endforeach</select></label>
            <label class="grid gap-2 text-xs text-slate-400">Criado de<input type="date" data-quadro-index-from class="w-full"></label><label class="grid gap-2 text-xs text-slate-400">Até<input type="date" data-quadro-index-until class="w-full"></label>
            <div class="flex rounded-xl border border-white/10 bg-slate-900 p-1"><button type="button" data-quadro-group="all" aria-pressed="true" class="rounded-lg px-3 py-2 text-xs text-slate-300 aria-pressed:bg-indigo-500/25">Todos</button><button type="button" data-quadro-group="category" aria-pressed="false" class="rounded-lg px-3 py-2 text-xs text-slate-300 aria-pressed:bg-indigo-500/25">Por categoria</button></div>
        </div>
        <div data-quadro-index-grid class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($quadros as $board)
                <div data-quadro-tile data-title="{{ $board->title }}" data-category="{{ $board->category }}" data-date="{{ $board->created_at->toDateString() }}" class="group relative overflow-hidden rounded-2xl border border-white/10 bg-slate-900/80 shadow-lg transition hover:border-cyan-400 hover:ring-1 hover:ring-cyan-400/60 focus-within:border-cyan-400 focus-within:ring-1 focus-within:ring-cyan-400/60">
                    <a href="{{ route('quadros.show', $board, absolute: false) }}" aria-label="Abrir quadro {{ $board->title }}" class="block h-full outline-none">
                    <div @class(['quadro-cover relative h-36 overflow-hidden', 'bg-slate-900' => $board->cover_path, 'quadro-cover--'.$board->cover => ! $board->cover_path])>
                        @if ($board->cover_path)
                            <img src="{{ route('quadros.cover', $board, absolute: false) }}" alt="" loading="lazy" class="h-full w-full object-cover">
                        @endif
                        <span class="absolute left-3 top-3 rounded-full border border-white/20 bg-slate-950 px-3 py-1.5 text-xs font-semibold tabular-nums text-white shadow-md">{{ $board->created_at->format('d/m/Y') }}</span>
                    </div>
                    <div class="p-5"><div class="flex items-start justify-between gap-3"><h2 class="min-w-0 wrap-break-word text-base font-semibold text-white group-hover:text-indigo-200">{{ $board->title }}</h2><span class="shrink-0 text-xs text-slate-500">{{ $board->cartoes_count }} cartões</span></div><p class="mt-2 text-xs text-slate-400">{{ $board->category ?: 'Sem categoria' }}</p><p class="mt-4 flex items-center gap-2 text-xs text-indigo-300"><span class="quadro-avatar">{{ mb_strtoupper(mb_substr($board->user->name, 0, 2)) }}</span>{{ $board->user_id === auth()->id() ? 'Seu quadro' : 'Participando · de '.$board->user->name }}<span class="ml-auto text-slate-500">{{ $board->membros_count + 1 }} membros</span></p></div>
                    </a>
                    @if ($board->canManage(auth()->user()))
                        <div data-quadro-tile-actions class="absolute right-3 top-3 flex gap-2 opacity-100 transition sm:opacity-0 sm:group-hover:opacity-100 sm:group-focus-within:opacity-100">
                            <button type="button" data-quadro-tile-edit data-url="{{ route('quadros.edit', $board, absolute: false) }}" aria-label="Editar quadro {{ $board->title }}" title="Editar quadro" class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-black/40 text-white backdrop-blur-sm transition hover:bg-black/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"><svg aria-hidden="true" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="m16 3 5 5-12 12-6 1 1-6zM14 5l5 5" /></svg></button>
                            @if ($board->user_id === auth()->id())
                                <button type="button" data-quadro-tile-delete data-url="{{ route('quadros.destroy', $board, absolute: false) }}" aria-label="Excluir quadro {{ $board->title }}" title="Excluir quadro" class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-black/40 text-white backdrop-blur-sm transition hover:bg-black/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"><svg aria-hidden="true" class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7" /></svg></button>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
            <button type="button" data-quadro-create data-quadro-create-tile class="flex min-h-64 items-center justify-center gap-2 rounded-2xl border border-dashed border-indigo-400/25 bg-indigo-500/5 text-sm font-semibold text-indigo-200 transition hover:border-indigo-400 hover:bg-indigo-500/10"><span class="text-2xl font-normal">+</span>Criar quadro</button>
        </div>
        <p data-quadro-index-empty hidden class="mt-6 text-center text-sm text-slate-500">Nenhum quadro encontrado com esses filtros.</p><p data-quadro-page-error hidden class="mt-4 text-sm text-rose-300" role="alert"></p>
    </div>
    <x-slot:modals><x-quadro-board-form :categories="$quadros->pluck('category')->filter()->unique()->sort()" /></x-slot:modals>
</x-layout>
