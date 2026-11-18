@php
    $isEntrada = $type === 'entrada';
    $title = $isEntrada ? 'Entradas' : 'Saídas';
    $routeName = $isEntrada ? 'financeiro.entradas' : 'financeiro.saidas';
@endphp

<x-layout :title="$title" align="start">
    <div id="lancamentos-page" data-type="{{ $type }}" class="w-full min-w-0 max-w-6xl">
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

        <x-financeiro-period-filter :route-name="$routeName" :filter="$periodFilter" />

        @if ($month)
            <p class="mb-2 text-sm text-slate-400">{{ \App\Http\Controllers\LancamentoController::monthTitle($month) }}</p>
        @endif

        <a href="{{ route('financeiro.relatorio', ['mes' => ($periodFilter?->start ?? $month)?->format('Y-m') ?? now()->format('Y-m'), 'tipo' => $type], absolute: false) }}" class="mb-4 inline-flex rounded-lg border border-white/10 px-4 py-2 text-sm font-semibold text-indigo-300 transition hover:text-white">Relatório mensal de {{ $isEntrada ? 'entradas' : 'saídas' }}</a>

        @include('partials.financeiro.lancamentos-totals')

        <x-filtros alvo="lancamentos-list" :categorias="true" />

        @include('partials.financeiro.lancamentos-list', ['type' => $type, 'lancamentos' => $lancamentos, 'month' => $month, 'mes' => $mes, 'membros' => $membros])
    </div>

    <x-slot:modals>
        <x-lancamento-create-modal :type="$type" :categories="$categories" :mes="$mes" :membros="$membros" :accounts="$accounts" />
        <x-lancamento-edit-modal :type="$type" :categories="$categories" :membros="$membros" :accounts="$accounts" />
    </x-slot:modals>
</x-layout>
