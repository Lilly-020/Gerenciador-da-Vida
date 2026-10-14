@props(['active'])

@php
    use App\Support\Carteira;

    // In someone else's carteira, only the sections they shared show up.
    $items = collect([
        ['route' => 'financeiro', 'label' => 'Dashboard', 'section' => 'dashboard'],
        ['route' => 'financeiro.entradas', 'label' => 'Entradas', 'section' => 'entradas'],
        ['route' => 'financeiro.saidas', 'label' => 'Saídas', 'section' => 'saidas'],
        ['route' => 'financeiro.custos-fixos', 'label' => 'Custos Fixos', 'section' => 'custos_fixos'],
        ['route' => 'financeiro.investimentos', 'label' => 'Investimentos', 'section' => 'investimentos'],
    ])->filter(fn (array $item): bool => Carteira::canSee($item['section']));

    if (Carteira::isOwn()) {
        $items->push(['route' => 'financeiro.participantes', 'label' => 'Participantes']);
    }

    $carteiras = Carteira::available();
@endphp

@if ($carteiras->isNotEmpty())
    <form method="POST" action="{{ route('financeiro.carteira', absolute: false) }}" class="mb-3 flex flex-wrap items-center gap-2 text-sm text-slate-400">
        @csrf
        <label for="carteira-owner">Carteira</label>
        <select
            id="carteira-owner"
            name="owner_id"
            onchange="this.form.submit()"
            class="rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
        >
            <option value="">Minha carteira</option>
            @foreach ($carteiras as $carteira)
                <option value="{{ $carteira->owner_id }}" @selected(Carteira::ownerId() === $carteira->owner_id)>
                    Carteira de {{ $carteira->owner->handle() }}
                </option>
            @endforeach
        </select>
        @unless (Carteira::canEdit())
            <span class="rounded-full border border-amber-400/30 bg-amber-500/10 px-2.5 py-1 text-xs text-amber-200">Somente leitura</span>
        @endunless
    </form>
@endif

<nav class="mb-8 flex max-w-full items-center gap-1 overflow-x-auto rounded-full border border-white/10 bg-slate-900/70 p-1.5 shadow-xl shadow-black/30 backdrop-blur scrollbar-none">
    @foreach ($items as $item)
        @php $isActive = $active === $item['route']; @endphp
        <a
            href="{{ route($item['route'], absolute: false) }}"
            class="shrink-0 rounded-full px-4 py-2 text-sm font-semibold whitespace-nowrap transition {{ $isActive ? 'bg-indigo-500/25 text-indigo-100' : 'text-slate-300 hover:text-white' }}"
        >
            {{ $item['label'] }}
        </a>
    @endforeach
</nav>
