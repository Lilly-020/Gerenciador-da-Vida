@props(['active'])

@php
    use App\Support\Carteira;

    // In someone else's carteira, only the sections they shared show up.
    $items = collect([
        ['route' => 'financeiro', 'label' => 'Dashboard', 'section' => 'dashboard'],
        ['route' => 'financeiro.entradas', 'label' => 'Entradas', 'section' => 'entradas'],
        ['route' => 'financeiro.saidas', 'label' => 'Saídas', 'section' => 'saidas'],
        ['route' => 'financeiro.investimentos', 'label' => 'Investimentos', 'section' => 'investimentos'],
    ])->filter(fn (array $item): bool => Carteira::canSee($item['section']));

    if (Carteira::canSee('entradas') || Carteira::canSee('saidas')) {
        $items->push(['route' => 'financeiro.contas', 'label' => 'Contas e categorias']);
    }


@endphp

<nav class="mb-8 flex w-fit max-w-full flex-wrap items-center justify-center gap-1 rounded-2xl border border-white/10 bg-slate-900/70 p-1.5 shadow-xl shadow-black/30 backdrop-blur sm:rounded-full">
    @foreach ($items as $item)
        @php $isActive = $active === $item['route']; @endphp
        <a
            href="{{ route($item['route'], absolute: false) }}"
            class="max-w-full rounded-full px-3 py-2 text-center text-sm sm:px-4 font-semibold transition {{ $isActive ? 'bg-indigo-500/25 text-indigo-100' : 'text-slate-300 hover:text-white' }}"
        >
            {{ $item['label'] }}
        </a>
    @endforeach
</nav>
