@php
    $today = \Carbon\Carbon::today();
@endphp

<div id="custos-fixos-list" class="space-y-2">
    @forelse ($custoFixos as $custoFixo)
        @php $isPaid = $custoFixo->isPaidFor($today); @endphp
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
                    <span class="inline-flex items-center gap-1 {{ $isPaid ? 'text-emerald-300' : 'text-amber-300' }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $isPaid ? 'bg-emerald-400' : 'bg-amber-400' }}"></span>
                        {{ $isPaid ? 'Pago este mês' : 'Pendente' }}
                    </span>
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-3">
                <span class="text-sm font-semibold text-white">@money($custoFixo->amount)</span>
                @unless ($isPaid)
                    <button
                        type="button"
                        data-custo-fixo-pay-url="{{ route('financeiro.custos-fixos.pagar', $custoFixo, absolute: false) }}"
                        class="rounded-full bg-indigo-500/90 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-indigo-500"
                    >
                        Marcar como pago
                    </button>
                @endunless
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
