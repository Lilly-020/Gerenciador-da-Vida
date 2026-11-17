<nav id="projetos-pagination" aria-label="Paginação dos projetos" class="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-white/10 bg-slate-900/60 px-4 py-3">
    <p class="text-xs text-slate-400">Mostrando <span class="font-semibold text-slate-200">{{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }}</span> de <span class="font-semibold text-slate-200">{{ $paginator->total() }}</span> projetos</p>
    @if ($paginator->hasPages())
        <div class="flex flex-wrap items-center gap-1">
            @if ($paginator->onFirstPage())<span aria-disabled="true" class="rounded-lg px-3 py-2 text-xs text-slate-600">‹ Anterior</span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="rounded-lg px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-white/5">‹ Anterior</a>@endif
            @foreach ($elements as $element)
                @if (is_string($element))<span class="px-2 text-xs text-slate-500" aria-hidden="true">{{ $element }}</span>
                @else
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())<span aria-current="page" aria-label="Página {{ $page }}" class="flex h-9 min-w-9 items-center justify-center rounded-lg border border-indigo-400/30 bg-indigo-500/25 px-2 text-xs font-semibold text-indigo-100">{{ $page }}</span>@else<a href="{{ $url }}" aria-label="Ir para página {{ $page }}" class="flex h-9 min-w-9 items-center justify-center rounded-lg border border-transparent px-2 text-xs font-semibold text-slate-400 hover:bg-white/5 hover:text-white">{{ $page }}</a>@endif
                    @endforeach
                @endif
            @endforeach
            @if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next" class="rounded-lg px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-white/5">Próxima ›</a>@else<span aria-disabled="true" class="rounded-lg px-3 py-2 text-xs text-slate-600">Próxima ›</span>@endif
        </div>
    @endif
</nav>
