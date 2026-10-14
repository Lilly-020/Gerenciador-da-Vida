@php
    $isEntrada = $type === 'entrada';
@endphp

<div id="lancamentos-list" class="space-y-2">
    @forelse ($lancamentos as $lancamento)
        @php
            // A lançamento from someone else's carteira, here because part
            // of it is the logged-in user's: shown read-only, at that part.
            $isForeign = (int) $lancamento->user_id !== (int) \App\Support\Carteira::ownerId();
        @endphp
        <div
            data-filter-item
            data-filter-title="{{ $lancamento->description }}"
            data-filter-start="{{ $lancamento->date->format('Y-m-d') }}"
            data-filter-category="{{ $lancamento->category }}"
            data-filter-amount="{{ $isForeign ? $lancamento->saidaFor(auth()->user()) : $lancamento->amount }}"
            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-white/10 bg-slate-950/40 px-4 py-3"
        >
            <div class="flex min-w-0 flex-1 items-center gap-3">
                @unless ($isForeign)
                <input
                    type="checkbox"
                    data-lancamento-toggle-url="{{ route('financeiro.lancamentos.update', ['lancamento' => $lancamento, 'mes' => $mes], absolute: false) }}"
                    @checked($lancamento->status === 'realizado')
                    title="Marcar como {{ $lancamento->status === 'previsto' ? 'realizado' : 'previsto' }}"
                    class="h-4 w-4 shrink-0 rounded border-white/20 bg-slate-950/60 text-indigo-500 focus:ring-indigo-400/40"
                >
                @endunless

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
                            <span class="text-indigo-300">
                                Recorrente
                                @if ($lancamento->recurrence_period)
                                    · {{ $lancamento->recurrence_period }}@if ($lancamento->recurrence_day), dia {{ $lancamento->recurrence_day }}@endif
                                @endif
                            </span>
                        @elseif ($lancamento->parent_recurring_id)
                            <span class="h-1 w-1 rounded-full bg-slate-600"></span>
                            <span class="text-indigo-300">Gerado por recorrência</span>
                        @endif
                        @if ($lancamento->custo_fixo_id)
                            <span class="h-1 w-1 rounded-full bg-slate-600"></span>
                            <span class="text-indigo-300">Custo fixo</span>
                        @endif
                        <span class="h-1 w-1 rounded-full bg-slate-600"></span>
                        @if ($lancamento->status === 'previsto')
                            <span class="inline-flex items-center gap-1 text-amber-300">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                                Previsto
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-emerald-300">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                {{ $isEntrada ? 'Recebido' : 'Pago' }}
                            </span>
                        @endif
                    </p>
                    @if ($isForeign)
                        <p class="wrap-break-word mt-1.5 text-xs text-indigo-300">
                            @if ($lancamento->type === 'entrada')
                                Pago a {{ $lancamento->user->handle() }} · parte de uma entrada de @money($lancamento->amount) na carteira dele
                            @else
                                Sua parte de @money($lancamento->amount) · carteira de {{ $lancamento->user->handle() }}
                            @endif
                        </p>
                    @else
                        @include('partials.financeiro.partes-resumo', [
                            'partes' => $lancamento->partes->pluck('amount', 'user_id'), 'total' => $lancamento->amount,
                            'isEntrada' => $isEntrada, 'membros' => $membros, 'contaDono' => $lancamento->partesSaemDoDono(),
                        ])
                    @endif
                    @if ($lancamento->notes)
                        <p class="wrap-break-word mt-1.5 text-xs whitespace-pre-line text-slate-500">{{ $lancamento->notes }}</p>
                    @endif
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-3">
                <span class="text-sm font-semibold {{ $isEntrada ? 'text-emerald-300' : 'text-rose-300' }}">
                    {{ $isEntrada ? '+' : '−' }} @money($isForeign ? $lancamento->saidaFor(auth()->user()) : $lancamento->amount)
                </span>
                @unless ($isForeign)
                <button
                    type="button"
                    data-lancamento-edit-trigger
                    data-lancamento-edit-url="{{ route('financeiro.lancamentos.update', ['lancamento' => $lancamento, 'mes' => $mes], absolute: false) }}"
                    data-description="{{ $lancamento->description }}"
                    data-amount="{{ $lancamento->amount }}"
                    data-date="{{ $lancamento->date->toDateString() }}"
                    data-category="{{ $lancamento->category }}"
                    data-subcategory="{{ $lancamento->subcategory }}"
                    data-payment-method="{{ $lancamento->payment_method }}"
                    data-notes="{{ $lancamento->notes }}"
                    data-partes-conta="{{ $lancamento->partes_conta ?: 'participante' }}"
                    @if ($lancamento->isDivided())
                        @foreach ($membros as $membro)
                            data-parte-u{{ $membro->id }}="{{ $lancamento->parteOf($membro) ?: '' }}"
                        @endforeach
                    @endif
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
                    data-lancamento-delete-url="{{ route('financeiro.lancamentos.destroy', ['lancamento' => $lancamento, 'mes' => $mes], absolute: false) }}"
                    class="text-slate-500 transition hover:text-rose-400"
                    aria-label="Excluir"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" clip-rule="evenodd" />
                    </svg>
                </button>
                @endunless
            </div>
        </div>
    @empty
        <div class="rounded-2xl border border-dashed border-white/10 bg-white/5 p-10 text-center text-slate-400">
            @if ($month)
                Nenhum lançamento em {{ \App\Http\Controllers\LancamentoController::monthTitle($month) }}.
            @else
                Nenhum lançamento ainda. Clique em "Adicionar {{ $isEntrada ? 'entrada' : 'saída' }}" para começar.
            @endif
        </div>
    @endforelse
</div>
