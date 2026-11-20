<x-layout title="Projetos" align="start">
    <div class="app-expand-with-sidebar w-full min-w-0 max-w-7xl">
        <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-white sm:text-3xl">Projetos</h1>
                <p class="mt-1 text-sm text-slate-400">Acompanhe o progresso dos seus projetos.</p>
            </div>
            <button
                type="button"
                id="open-new-projeto-modal"
                class="inline-flex items-center gap-2 rounded-full bg-indigo-500/90 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-900/40 transition hover:bg-indigo-500"
            >
                + Adicionar projeto
            </button>
        </div>

        <div class="mb-8">
            @include('partials.projeto-stats', ['stats' => $stats])
        </div>

        <x-filtros alvo="projetos-grid" data-label="Prazo" :projetos="true" :server-url="route('projetos', absolute: false)" stats-id="projetos-stats" />

        <div id="projetos-grid" data-paginated class="grid grid-cols-1 items-start gap-4 lg:grid-cols-3">
            @include('partials.projeto-list')
        </div>
        {{ $projetos->onEachSide(1)->links('partials.projeto-pagination') }}

    </div>


    <x-slot:modals>
        <x-projeto-create-modal />
        <x-projeto-edit-modal />
        <x-projeto-etiqueta-modal />
    </x-slot:modals>
</x-layout>
