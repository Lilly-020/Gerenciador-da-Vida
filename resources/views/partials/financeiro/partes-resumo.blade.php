{{--
    A card's participants, and whose account each part touches.

    $partes: amount per participant user id · $total · $isEntrada ·
    $membros (owner first) · $contaDono: an expense's parts come out of
    the owner's account instead of each participant's
--}}
@php
    $dono = $membros->first();
    $shares = collect($partes)
        ->map(fn ($amount, $userId): array => ['user' => $membros->firstWhere('id', $userId), 'amount' => (float) $amount])
        ->filter(fn (array $share): bool => $share['user'] !== null && $share['user']->id !== $dono?->id && $share['amount'] > 0);
@endphp

@if ($shares->isNotEmpty())
    <p class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-slate-400">
        @foreach ($shares as $share)
            @if ($isEntrada)
                <span>
                    <span class="text-emerald-300">+ @money($share['amount'])</span>
                    recebido de {{ $share['user']->handle() }} (sai da conta dele)
                </span>
            @elseif ($contaDono ?? false)
                <span>Parte de {{ $share['user']->handle() }}: <span class="text-slate-200">@money($share['amount'])</span></span>
            @else
                <span>
                    <span class="text-rose-300">− @money($share['amount'])</span>
                    da conta de {{ $share['user']->handle() }}
                </span>
            @endif
        @endforeach

        @unless ($isEntrada)
            <span>
                <span class="text-rose-300">− @money(($contaDono ?? false) ? (float) $total : (float) $total - $shares->sum('amount'))</span>
                da conta de {{ $dono->handle() }}{{ ($contaDono ?? false) ? ' (paga tudo)' : '' }}
            </span>
        @endunless
    </p>
@endif
