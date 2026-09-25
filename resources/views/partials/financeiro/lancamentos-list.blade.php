@php
    $isEntrada = $type === 'entrada';
@endphp

<div id="lancamentos-list" class="space-y-2">
    @forelse ($lancamentos as $lancamento)
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-white/10 bg-slate-950/40 px-4 py-3">
            <div class="flex min-w-0 flex-1 items-center gap-3">
                <input
                    type="checkbox"
                    data-lancamento-toggle-url="{{ route('financeiro.lancamentos.update', $lancamento, absolute: false) }}"
                    @checked($lancamento->status === 'realizado')
                    title="Marcar como {{ $lancamento->status === 'previsto' ? 'realizado' : 'previsto' }}"
                    class="h-4 w-4 shrink-0 rounded border-white/20 bg-slate-950/60 text-indigo-500 focus:ring-indigo-400/40"
                >

                <div class="min-w-0">
                    <p class="wrap-break-word text-sm font-medium text-white">{{ $lancamento->description }}</p>
                    <p class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-slate-400">
                        <span>{{ $lancamento->category }}</span>
                        @if ($lancamento->subcategory)
                            <span class="h-1 w-1 rounded-full bg-slate-600"></span>
                            <span>{{ $lancamento->subcategory }}</span>
                        @endif
                        <span class="h-1 w-1 rounded-full bg-slate-600"></span>
                        <span>{{ $lancamento->date->format('d/m/Y') }}</span>
                        @if ($lancamento->payment_method)
                            <span class="h-1 w-1 rounded-full bg-slate-600"></span>
                            <span>{{ $lancamento->payment_method }}</span>
                        @endif
                        @if ($lancamento->is_recurring_template)
                            <span class="h-1 w-1 rounded-full bg-slate-600"></span>
                            <span class="text-indigo-300">Recorrente</span>
                        @endif
                        @if ($lancamento->status === 'previsto')
                            <span class="h-1 w-1 rounded-full bg-slate-600"></span>
                            <span class="text-amber-300">Previsto</span>
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-3">
                <span class="text-sm font-semibold {{ $isEntrada ? 'text-emerald-300' : 'text-rose-300' }}">
                    {{ $isEntrada ? '+' : '−' }} @money($lancamento->amount)
                </span>
                <button
                    type="button"
                    data-lancamento-delete-url="{{ route('financeiro.lancamentos.destroy', $lancamento, absolute: false) }}"
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
            Nenhum lançamento ainda. Clique em "Adicionar {{ $isEntrada ? 'entrada' : 'saída' }}" para começar.
        </div>
    @endforelse
</div>
