@php
    $arrowClasses = 'inline-flex h-10 w-10 items-center justify-center rounded-lg border border-white/10 bg-slate-950/60 text-slate-300 transition hover:border-indigo-400/50 hover:text-white';
@endphp

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

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <a
                href="{{ route('financeiro.custos-fixos', ['mes' => $month->copy()->subMonthNoOverflow()->format('Y-m')], absolute: false) }}"
                class="{{ $arrowClasses }}"
                aria-label="Mês anterior"
            >‹</a>
            <span class="min-w-40 rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2.5 text-center text-sm text-white">
                {{ $monthTitle }}
            </span>
            <a
                href="{{ route('financeiro.custos-fixos', ['mes' => $month->copy()->addMonthNoOverflow()->format('Y-m')], absolute: false) }}"
                class="{{ $arrowClasses }}"
                aria-label="Próximo mês"
            >›</a>
            @unless ($isCurrentMonth)
                <a href="{{ route('financeiro.custos-fixos', absolute: false) }}" class="px-2 text-sm text-indigo-300 transition hover:text-indigo-200">
                    Voltar para o mês atual
                </a>
            @endunless
        </div>

        @include('partials.financeiro.custos-fixos-summary')

        @include('partials.financeiro.custos-fixos-list')
    </div>

    <x-slot:modals>
        <x-custo-fixo-create-modal :mes="$mes" />
        <x-custo-fixo-edit-modal :month-title="$monthTitle" />
    </x-slot:modals>
</x-layout>
