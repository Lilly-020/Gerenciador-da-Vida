<x-layout title="Projetos" align="start">
    <div class="w-full max-w-5xl">
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

        <div id="projetos-grid" class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            @forelse ($projetos as $projeto)
                @include('partials.projeto-card', ['projeto' => $projeto])
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-white/10 bg-white/5 p-10 text-center text-slate-400">
                    Você ainda não adicionou nenhum projeto. Clique em "Adicionar projeto" para começar.
                </div>
            @endforelse
        </div>
    </div>

    <x-slot:modals>
        <x-projeto-create-modal />
    </x-slot:modals>
</x-layout>
