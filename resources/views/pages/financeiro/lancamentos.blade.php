@php
    $isEntrada = $type === 'entrada';
    $title = $isEntrada ? 'Entradas' : 'Saídas';
    $routeName = $isEntrada ? 'financeiro.entradas' : 'financeiro.saidas';
    $arrowClasses = 'inline-flex h-10 w-10 items-center justify-center rounded-lg border border-white/10 bg-slate-950/60 text-slate-300 transition hover:border-indigo-400/50 hover:text-white';
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

        <form method="GET" action="{{ route($routeName, absolute: false) }}" class="mb-4 flex flex-wrap items-center gap-2">
            @if ($month)
                <a
                    href="{{ route($routeName, ['mes' => $month->copy()->subMonthNoOverflow()->format('Y-m')], absolute: false) }}"
                    class="{{ $arrowClasses }}"
                    aria-label="Mês anterior"
                >‹</a>
            @endif

            <label for="mes" class="sr-only">Mês</label>
            <select
                id="mes"
                name="mes"
                onchange="this.form.submit()"
                class="rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
            >
                <option value="todos" @selected(! $month)>Todos os meses</option>
                @foreach ($meses as $key => $label)
                    <option value="{{ $key }}" @selected($mes === $key)>{{ $label }}</option>
                @endforeach
            </select>

            @if ($month)
                <a
                    href="{{ route($routeName, ['mes' => $month->copy()->addMonthNoOverflow()->format('Y-m')], absolute: false) }}"
                    class="{{ $arrowClasses }}"
                    aria-label="Próximo mês"
                >›</a>
            @endif
        </form>

        @include('partials.financeiro.lancamentos-totals', ['totalRealizado' => $totalRealizado, 'totalPrevisto' => $totalPrevisto])

        @include('partials.financeiro.lancamentos-list', ['type' => $type, 'lancamentos' => $lancamentos, 'month' => $month, 'mes' => $mes, 'membros' => $membros])
    </div>

    <x-slot:modals>
        <x-lancamento-create-modal :type="$type" :categories="$categories" :mes="$mes" :membros="$membros" />
        <x-lancamento-edit-modal :type="$type" :categories="$categories" :membros="$membros" />
    </x-slot:modals>
</x-layout>
