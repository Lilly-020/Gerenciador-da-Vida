@php
    $isEntrada = $type === 'entrada';
    $title = $isEntrada ? 'Entradas' : 'Saídas';
@endphp

<x-layout :title="$title" align="start">
    <div id="lancamentos-page" data-type="{{ $type }}" class="w-full max-w-4xl">
        <x-financeiro-subnav :active="$isEntrada ? 'financeiro.entradas' : 'financeiro.saidas'" />

        <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-white sm:text-3xl">{{ $title }}</h1>
                <p class="mt-1 text-sm text-slate-400">
                    {{ $isEntrada ? 'Registre o que você recebeu.' : 'Registre o que você gastou.' }}
                </p>
            </div>
            <button
                type="button"
                id="open-new-lancamento-modal"
                class="inline-flex items-center gap-2 rounded-full bg-indigo-500/90 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-900/40 transition hover:bg-indigo-500"
            >
                + Adicionar {{ $isEntrada ? 'entrada' : 'saída' }}
            </button>
        </div>

        @include('partials.financeiro.lancamentos-totals', ['totalRealizado' => $totalRealizado, 'totalPrevisto' => $totalPrevisto])

        @include('partials.financeiro.lancamentos-list', ['type' => $type, 'lancamentos' => $lancamentos])
    </div>

    <x-slot:modals>
        <x-lancamento-create-modal :type="$type" :categories="$categories" />
    </x-slot:modals>
</x-layout>
