@php
    $totalAportado = $investimento->totalAportado();
    $rendimento = $investimento->rendimentoEstimado();
    $patrimonio = $totalAportado + $rendimento;
    $aportadoPct = $patrimonio > 0 ? (int) round($totalAportado / $patrimonio * 100) : 100;
    $rendimentoPct = 100 - $aportadoPct;
    $rateDisplay = rtrim(rtrim(number_format((float) $investimento->rate, 2, ',', '.'), '0'), ',');
@endphp

<div class="rounded-2xl border border-white/10 bg-slate-900/70 p-4 shadow-lg shadow-black/20 sm:p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="wrap-break-word text-base font-semibold text-white sm:text-lg">{{ $investimento->name }}</h2>
            <p class="mt-1 text-xs text-slate-400">
                {{ $investimento->typeLabel() }}
                @if ($investimento->institution)
                    · {{ $investimento->institution }}
                @endif
                · {{ $rateDisplay }}% {{ $investimento->rate_period === 'anual' ? 'a.a.' : 'a.m.' }}
                @if ($investimento->rate_reference)
                    ({{ $investimento->rate_reference }})
                @endif
            </p>
        </div>

        <div class="flex shrink-0 items-center gap-3">
            <button
                type="button"
                data-investimento-edit-trigger
                data-investimento-edit-url="{{ route('financeiro.investimentos.update', $investimento, absolute: false) }}"
                data-name="{{ $investimento->name }}"
                data-institution="{{ $investimento->institution }}"
                data-type="{{ $investimento->type }}"
                data-rate="{{ $investimento->rate }}"
                data-rate-reference="{{ $investimento->rate_reference }}"
                data-rate-period="{{ $investimento->rate_period }}"
                data-liquidity="{{ $investimento->liquidity }}"
                data-maturity-date="{{ $investimento->maturity_date?->toDateString() }}"
                data-notes="{{ $investimento->notes }}"
                class="text-slate-500 transition hover:text-indigo-300"
                aria-label="Editar investimento"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                    <path d="m5.433 13.917 1.262-3.155A4 4 0 0 1 7.58 9.42l6.92-6.918a2.121 2.121 0 0 1 3 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 0 1-.65-.65Z" />
                    <path d="M3.5 5.75c0-.69.56-1.25 1.25-1.25H10A.75.75 0 0 0 10 3H4.75A2.75 2.75 0 0 0 2 5.75v9.5A2.75 2.75 0 0 0 4.75 18h9.5A2.75 2.75 0 0 0 17 15.25V10a.75.75 0 0 0-1.5 0v5.25c0 .69-.56 1.25-1.25 1.25h-9.5c-.69 0-1.25-.56-1.25-1.25v-9.5Z" />
                </svg>
            </button>
            <form
                action="{{ route('financeiro.investimentos.destroy', $investimento, absolute: false) }}"
                method="POST"
                onsubmit="return confirm('Excluir este investimento e todos os seus aportes?');"
            >
                @csrf
                @method('DELETE')
                <button type="submit" class="text-slate-500 transition hover:text-rose-400" aria-label="Excluir investimento">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" clip-rule="evenodd" />
                    </svg>
                </button>
            </form>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-3 gap-3 text-center">
        <div>
            <p class="text-xs text-slate-400">Aportado</p>
            <p class="mt-1 text-sm font-semibold text-white">@money($totalAportado)</p>
        </div>
        <div>
            <p class="text-xs text-slate-400">Rendimento (est.)</p>
            <p class="mt-1 text-sm font-semibold text-emerald-300">@money($rendimento)</p>
        </div>
        <div>
            <p class="text-xs text-slate-400">Patrimônio</p>
            <p class="mt-1 text-sm font-semibold text-white">@money($patrimonio)</p>
        </div>
    </div>

    <div class="mt-3 flex h-1.5 w-full overflow-hidden rounded-full bg-white/5">
        <div class="h-full bg-sky-400/80" style="width: {{ $aportadoPct }}%"></div>
        <div class="h-full bg-emerald-400/80" style="width: {{ $rendimentoPct }}%"></div>
    </div>
    <div class="mt-1.5 flex items-center gap-3 text-[11px] text-slate-400">
        <span class="inline-flex items-center gap-1">
            <span class="h-1.5 w-1.5 rounded-full bg-sky-400"></span>
            Aportado
        </span>
        <span class="inline-flex items-center gap-1">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
            Rendimento (estimativa)
        </span>
    </div>

    <details class="group mt-4">
        <summary class="cursor-pointer list-none text-xs font-medium text-indigo-300 transition hover:text-indigo-200">
            <span class="group-open:hidden">Ver aportes ({{ $investimento->aportes->count() }})</span>
            <span class="hidden group-open:inline">Ocultar aportes</span>
        </summary>

        <div class="mt-3 space-y-2">
            @forelse ($investimento->aportes->sortByDesc('date') as $aporte)
                <div class="flex items-center justify-between gap-2 rounded-lg border border-white/10 bg-slate-950/40 px-3 py-2 text-sm">
                    <span class="text-slate-300">{{ $aporte->date->format('d/m/Y') }}</span>
                    <span class="text-white">@money($aporte->amount)</span>
                    <form
                        action="{{ route('financeiro.investimentos.aportes.destroy', [$investimento, $aporte], absolute: false) }}"
                        method="POST"
                        onsubmit="return confirm('Remover este aporte?');"
                    >
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-slate-500 transition hover:text-rose-400" aria-label="Remover aporte">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </form>
                </div>
            @empty
                <p class="text-xs text-slate-500">Nenhum aporte registrado ainda.</p>
            @endforelse

            <form
                action="{{ route('financeiro.investimentos.aportes.store', $investimento, absolute: false) }}"
                method="POST"
                class="mt-3 flex flex-wrap items-end gap-2"
            >
                @csrf
                <div>
                    <label for="aporte-amount-{{ $investimento->id }}" class="mb-1 block text-xs text-slate-400">Novo aporte</label>
                    <input
                        id="aporte-amount-{{ $investimento->id }}"
                        type="number"
                        step="0.01"
                        min="0.01"
                        name="amount"
                        required
                        placeholder="Valor"
                        class="w-28 rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                    >
                </div>
                <input
                    type="date"
                    name="date"
                    required
                    value="{{ now()->toDateString() }}"
                    class="rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20 scheme-dark"
                >
                <button type="submit" class="rounded-lg bg-indigo-500/90 px-3 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500">
                    Adicionar
                </button>
            </form>
        </div>
    </details>
</div>
