<x-layout title="Investimentos" align="start">
    <div class="w-full max-w-5xl">
        <x-financeiro-subnav active="financeiro.investimentos" />

        <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-white sm:text-3xl">Investimentos</h1>
                <p class="mt-1 text-sm text-slate-400">Acompanhe seus aportes e o rendimento estimado.</p>
            </div>
            <button
                type="button"
                id="open-new-investimento-modal"
                class="inline-flex items-center gap-2 rounded-full bg-indigo-500/90 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-900/40 transition hover:bg-indigo-500"
            >
                + Adicionar investimento
            </button>
        </div>

        <div class="mb-8 grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-white/10 bg-gradient-to-b from-slate-900/70 to-slate-900/40 p-4">
                <p class="text-xs font-medium tracking-wide text-slate-400 uppercase">Total aportado</p>
                <p class="mt-2 text-2xl font-semibold text-white">@money($totalAportado)</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-gradient-to-b from-slate-900/70 to-slate-900/40 p-4">
                <p class="text-xs font-medium tracking-wide text-slate-400 uppercase">Rendimento estimado</p>
                <p class="mt-2 text-2xl font-semibold text-emerald-300">@money($totalRendimento)</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-gradient-to-b from-slate-900/70 to-slate-900/40 p-4">
                <p class="text-xs font-medium tracking-wide text-slate-400 uppercase">Patrimônio estimado</p>
                <p class="mt-2 text-2xl font-semibold text-white">@money($totalAportado + $totalRendimento)</p>
            </div>
        </div>

        @include('partials.financeiro.investimento-simulador')

        <div class="space-y-4">
            @forelse ($investimentos as $investimento)
                @include('partials.financeiro.investimento-card', ['investimento' => $investimento])
            @empty
                <div class="rounded-2xl border border-dashed border-white/10 bg-white/5 p-10 text-center text-slate-400">
                    Você ainda não cadastrou nenhum investimento. Clique em "Adicionar investimento" para começar.
                </div>
            @endforelse
        </div>
    </div>

    <x-slot:modals>
        <x-investimento-create-modal />
        <x-investimento-edit-modal />
    </x-slot:modals>
</x-layout>
