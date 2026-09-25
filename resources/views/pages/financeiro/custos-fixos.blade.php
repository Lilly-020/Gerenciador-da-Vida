<x-layout title="Custos Fixos" align="start">
    <div id="custos-fixos-page" class="w-full max-w-4xl">
        <x-financeiro-subnav active="financeiro.custos-fixos" />

        <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-white sm:text-3xl">Custos Fixos</h1>
                <p class="mt-1 text-sm text-slate-400">O que você precisa pagar todos os meses.</p>
            </div>
            <button
                type="button"
                id="open-new-custo-fixo-modal"
                class="inline-flex items-center gap-2 rounded-full bg-indigo-500/90 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-900/40 transition hover:bg-indigo-500"
            >
                + Adicionar custo fixo
            </button>
        </div>

        @include('partials.financeiro.custos-fixos-summary', ['total' => $total, 'totalPago' => $totalPago])

        @include('partials.financeiro.custos-fixos-list', ['custoFixos' => $custoFixos])
    </div>

    <x-slot:modals>
        <x-custo-fixo-create-modal />
    </x-slot:modals>
</x-layout>
