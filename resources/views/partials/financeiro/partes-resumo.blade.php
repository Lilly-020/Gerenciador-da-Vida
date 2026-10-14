{{--
    A card's participant shares. On an expense each share comes in (the
    participant pays it); on an income it goes out (the participant takes
    it). What is left is the carteira owner's.

    $partes: amount per user id · $total · $isEntrada · $membros
--}}
@php
    $ownerId = $membros->first()?->id;
    $shares = collect($partes)
        ->map(fn ($amount, $userId): array => ['user' => $membros->firstWhere('id', $userId), 'amount' => (float) $amount])
        ->filter(fn (array $share): bool => $share['user'] !== null && $share['user']->id !== $ownerId && $share['amount'] > 0);
@endphp

@if ($shares->isNotEmpty())
    <p class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs">
        @foreach ($shares as $share)
            <span class="{{ $isEntrada ? 'text-rose-300' : 'text-emerald-300' }}">
                {{ $isEntrada ? '−' : '+' }} @money($share['amount'])
                <span class="text-slate-400">{{ $isEntrada ? 'para' : 'de' }} {{ $share['user']->handle() }}</span>
            </span>
        @endforeach
        <span class="text-slate-400">
            Fica para {{ $membros->first()->handle() }}:
            <span class="text-slate-200">@money((float) $total - $shares->sum('amount'))</span>
        </span>
    </p>
@endif
