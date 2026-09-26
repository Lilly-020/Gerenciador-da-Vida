@php
    $today = \Carbon\Carbon::today();
@endphp

<div id="custos-fixos-list" class="space-y-2">
    @forelse ($custoFixos as $custoFixo)
        @php
            $isDueThisMonth = $custoFixo->isDueIn($today);
            $isPaid = $custoFixo->isPaidFor($today);
            $notStartedYet = $custoFixo->starts_on->gt($today);
            $hasEnded = $custoFixo->ends_on !== null && $custoFixo->ends_on->lt($today);
            $notDueThisPeriod = ! $notStartedYet && ! $hasEnded && ! $isDueThisMonth;
            $nextDue = $notDueThisPeriod ? $custoFixo->nextDueMonth($today->copy()->addMonthNoOverflow()) : null;
        @endphp
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-white/10 bg-slate-950/40 px-4 py-3">
            <div class="min-w-0 flex-1">
                <p class="wrap-break-word text-sm font-medium text-white">{{ $custoFixo->name }}</p>
                <p class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-slate-400">
                    <span>{{ $custoFixo->category }}</span>
                    <span class="h-1 w-1 rounded-full bg-slate-600"></span>
                    <span>Vence dia {{ $custoFixo->due_day }}</span>
                    <span class="h-1 w-1 rounded-full bg-slate-600"></span>
                    <span class="capitalize">{{ $custoFixo->periodicity }}</span>
                    <span class="h-1 w-1 rounded-full bg-slate-600"></span>
                    @if ($notStartedYet)
                        <span class="inline-flex items-center gap-1 text-slate-400">
                            <span class="h-1.5 w-1.5 rounded-full bg-slate-500"></span>
                            Começa em {{ $custoFixo->starts_on->format('d-m-Y') }}
                        </span>
                    @elseif ($hasEnded)
                        <span class="inline-flex items-center gap-1 text-slate-400">
                            <span class="h-1.5 w-1.5 rounded-full bg-slate-500"></span>
                            Encerrado em {{ $custoFixo->ends_on->format('d-m-Y') }}
                        </span>
                    @elseif ($notDueThisPeriod)
                        <span class="inline-flex items-center gap-1 text-slate-400">
                            <span class="h-1.5 w-1.5 rounded-full bg-slate-500"></span>
                            Não vence este mês
                            @if ($nextDue)
                                · próximo em {{ $nextDue->format('m/Y') }}
                            @endif
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 {{ $isPaid ? 'text-emerald-300' : 'text-amber-300' }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $isPaid ? 'bg-emerald-400' : 'bg-amber-400' }}"></span>
                            {{ $isPaid ? 'Pago este mês' : 'Pendente' }}
                        </span>
                    @endif
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-3">
                <span class="text-sm font-semibold text-white">@money($custoFixo->amount)</span>
                @if ($isDueThisMonth && ! $isPaid)
                    <button
                        type="button"
                        data-custo-fixo-pay-url="{{ route('financeiro.custos-fixos.pagar', $custoFixo, absolute: false) }}"
                        class="rounded-full bg-indigo-500/90 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-indigo-500"
                    >
                        Marcar como pago
                    </button>
                @endif
                <button
                    type="button"
                    data-custo-fixo-edit-trigger
                    data-custo-fixo-edit-url="{{ route('financeiro.custos-fixos.update', $custoFixo, absolute: false) }}"
                    data-name="{{ $custoFixo->name }}"
                    data-category="{{ $custoFixo->category }}"
                    data-amount="{{ $custoFixo->amount }}"
                    data-due-day="{{ $custoFixo->due_day }}"
                    data-periodicity="{{ $custoFixo->periodicity }}"
                    data-starts-on="{{ $custoFixo->starts_on->toDateString() }}"
                    data-ends-on="{{ $custoFixo->ends_on?->toDateString() }}"
                    data-notes="{{ $custoFixo->notes }}"
                    data-status="{{ $custoFixo->status }}"
                    class="text-slate-500 transition hover:text-indigo-300"
                    aria-label="Editar"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                        <path d="m5.433 13.917 1.262-3.155A4 4 0 0 1 7.58 9.42l6.92-6.918a2.121 2.121 0 0 1 3 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 0 1-.65-.65Z" />
                        <path d="M3.5 5.75c0-.69.56-1.25 1.25-1.25H10A.75.75 0 0 0 10 3H4.75A2.75 2.75 0 0 0 2 5.75v9.5A2.75 2.75 0 0 0 4.75 18h9.5A2.75 2.75 0 0 0 17 15.25V10a.75.75 0 0 0-1.5 0v5.25c0 .69-.56 1.25-1.25 1.25h-9.5c-.69 0-1.25-.56-1.25-1.25v-9.5Z" />
                    </svg>
                </button>
                <button
                    type="button"
                    data-custo-fixo-delete-url="{{ route('financeiro.custos-fixos.destroy', $custoFixo, absolute: false) }}"
                    class="text-slate-500 transition hover:text-rose-400"
                    aria-label="Excluir"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
        </div>
    @empty
        <div class="rounded-2xl border border-dashed border-white/10 bg-white/5 p-10 text-center text-slate-400">
            Nenhum custo fixo cadastrado ainda.
        </div>
    @endforelse
</div>
