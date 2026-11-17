@forelse ($projetos as $projeto)
    @include('partials.projeto-card', ['projeto' => $projeto])
@empty
    <div data-board-empty class="col-span-full rounded-2xl border border-dashed border-white/10 bg-white/5 p-10 text-center text-slate-400">
        Nenhum projeto nesta situação. Ajuste os filtros ou clique em “Adicionar projeto”.
    </div>
@endforelse
