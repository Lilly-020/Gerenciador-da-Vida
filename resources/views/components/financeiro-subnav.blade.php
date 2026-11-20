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

<nav aria-label="Seções do Financeiro" class="mb-8 grid w-full max-w-full grid-cols-2 gap-1 rounded-2xl border border-white/10 bg-slate-900/70 p-1.5 sm:inline-flex sm:w-auto">
    @foreach ($items as $item)
        @php $isActive = $active === $item['route']; @endphp
        <a
            href="{{ route($item['route'], absolute: false) }}"
            @if ($isActive) aria-current="page" @endif
            @class([
                'flex min-h-10 min-w-0 items-center justify-center rounded-xl px-2 py-2 text-center text-xs font-semibold transition focus-visible:outline-2 focus-visible:outline-indigo-400 sm:px-3 sm:text-sm',
                'col-span-2' => $loop->last && $loop->odd,
                'bg-indigo-500/25 text-indigo-100 ring-1 ring-inset ring-indigo-400/30' => $isActive,
                'text-slate-300 hover:bg-white/5 hover:text-white' => ! $isActive,
            ])
        >
            {{ $item['label'] }}
        </a>
    @endforeach
</nav>
