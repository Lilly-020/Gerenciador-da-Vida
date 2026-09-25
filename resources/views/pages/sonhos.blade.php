<x-layout title="Sonhos" align="start">
    <div class="w-full max-w-3xl">
        <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-white sm:text-3xl">Sonhos</h1>
                <p class="mt-1 text-sm text-slate-400">Acompanhe o progresso dos seus sonhos e objetivos.</p>
            </div>
            <button
                type="button"
                id="open-new-sonho-modal"
                class="inline-flex items-center gap-2 rounded-full bg-indigo-500/90 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-900/40 transition hover:bg-indigo-500"
            >
                + Adicionar sonho
            </button>
        </div>

        <div class="mb-8">
            @include('partials.sonho-stats', ['stats' => $stats])
        </div>

        <div id="sonhos-grid" class="grid grid-cols-1 gap-4">
            @forelse ($sonhos as $sonho)
                @include('partials.sonho-card', ['sonho' => $sonho])
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-white/10 bg-white/5 p-10 text-center text-slate-400">
                    Você ainda não adicionou nenhum sonho. Clique em "Adicionar sonho" para começar.
                </div>
            @endforelse
        </div>
    </div>

    <x-slot:modals>
        <x-sonho-create-modal />
    </x-slot:modals>
</x-layout>
