@props(['active'])

@php
    $items = [
        ['route' => 'financeiro', 'label' => 'Dashboard'],
        ['route' => 'financeiro.entradas', 'label' => 'Entradas'],
        ['route' => 'financeiro.saidas', 'label' => 'Saídas'],
        ['route' => 'financeiro.custos-fixos', 'label' => 'Custos Fixos'],
        ['route' => 'financeiro.investimentos', 'label' => 'Investimentos'],
    ];
@endphp

<nav class="mb-8 inline-flex flex-wrap items-center gap-1 rounded-full border border-white/10 bg-slate-900/70 p-1.5 shadow-xl shadow-black/30 backdrop-blur">
    @foreach ($items as $item)
        @php $isActive = $active === $item['route']; @endphp
        <a
            href="{{ route($item['route'], absolute: false) }}"
            class="rounded-full px-4 py-2 text-sm font-semibold whitespace-nowrap transition {{ $isActive ? 'bg-indigo-500/25 text-indigo-100' : 'text-slate-300 hover:text-white' }}"
        >
            {{ $item['label'] }}
        </a>
    @endforeach
</nav>
