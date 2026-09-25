@props(['type', 'categories'])

@php
    $isEntrada = $type === 'entrada';
@endphp

<div id="new-lancamento-modal" class="board-modal fixed inset-0 z-50 hidden bg-black/60 backdrop-blur-sm" aria-hidden="true">
    <div class="flex min-h-full items-center justify-center px-4 py-8">
        <div class="modal-panel w-full max-w-md rounded-2xl border border-white/10 bg-slate-900/95 p-6 shadow-2xl shadow-black/40">
            <div class="mb-5 flex items-start justify-between gap-4">
                <h2 class="text-lg font-semibold text-white">Nova {{ $isEntrada ? 'entrada' : 'saída' }}</h2>
                <button
                    type="button"
                    data-modal-close
                    class="shrink-0 rounded-full p-1 text-slate-400 transition hover:text-white"
                    aria-label="Fechar"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>

            <form id="new-lancamento-form" action="{{ route('financeiro.lancamentos.store', absolute: false) }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="type" value="{{ $type }}">

                <div>
                    <label for="lancamento-description" class="mb-1.5 block text-sm font-medium text-slate-300">Descrição</label>
                    <input
                        id="lancamento-description"
                        type="text"
                        name="description"
                        required
                        maxlength="255"
                        placeholder="{{ $isEntrada ? 'Ex: Salário de setembro' : 'Ex: Supermercado' }}"
                        class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-4 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                    >
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="lancamento-amount" class="mb-1.5 block text-sm font-medium text-slate-300">Valor</label>
                        <input
                            id="lancamento-amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            name="amount"
                            required
                            placeholder="0,00"
                            class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-4 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                        >
                    </div>
                    <div>
                        <label for="lancamento-date" class="mb-1.5 block text-sm font-medium text-slate-300">Data</label>
                        <input
                            id="lancamento-date"
                            type="date"
                            name="date"
                            required
                            value="{{ now()->toDateString() }}"
                            class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20 scheme-dark"
                        >
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="lancamento-category" class="mb-1.5 block text-sm font-medium text-slate-300">Categoria</label>
                        <select
                            id="lancamento-category"
                            name="category"
                            required
                            class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                        >
                            @foreach ($categories as $category)
                                <option value="{{ $category }}">{{ $category }}</option>
                            @endforeach
                        </select>
                    </div>
                    @unless ($isEntrada)
                        <div>
                            <label for="lancamento-subcategory" class="mb-1.5 block text-sm font-medium text-slate-300">Subcategoria</label>
                            <input
                                id="lancamento-subcategory"
                                type="text"
                                name="subcategory"
                                maxlength="255"
                                placeholder="Opcional"
                                class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-4 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                            >
                        </div>
                    @endunless
                </div>

                @unless ($isEntrada)
                    <div>
                        <label for="lancamento-payment-method" class="mb-1.5 block text-sm font-medium text-slate-300">Forma de pagamento</label>
                        <input
                            id="lancamento-payment-method"
                            type="text"
                            name="payment_method"
                            maxlength="255"
                            placeholder="Ex: Cartão, Pix, Dinheiro"
                            class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-4 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                        >
                    </div>
                @endunless

                <div>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                        <input
                            type="checkbox"
                            name="is_recurring_template"
                            value="1"
                            data-recurring-toggle
                            class="h-4 w-4 rounded border-white/20 bg-slate-950/60 text-indigo-500 focus:ring-indigo-400/40"
                        >
                        Lançamento recorrente
                    </label>
                </div>

                <div data-recurring-fields class="hidden grid-cols-2 gap-3">
                    <div>
                        <label for="lancamento-recurrence-period" class="mb-1.5 block text-sm font-medium text-slate-300">Periodicidade</label>
                        <select
                            id="lancamento-recurrence-period"
                            name="recurrence_period"
                            class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                        >
                            <option value="mensal">Mensal</option>
                            <option value="semanal">Semanal</option>
                            <option value="anual">Anual</option>
                        </select>
                    </div>
                    <div>
                        <label for="lancamento-recurrence-day" class="mb-1.5 block text-sm font-medium text-slate-300">Dia esperado</label>
                        <input
                            id="lancamento-recurrence-day"
                            type="number"
                            name="recurrence_day"
                            min="1"
                            max="31"
                            placeholder="Ex: 5"
                            class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-4 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                        >
                    </div>
                </div>

                <div>
                    <label for="lancamento-notes" class="mb-1.5 block text-sm font-medium text-slate-300">Observação</label>
                    <textarea
                        id="lancamento-notes"
                        name="notes"
                        rows="2"
                        maxlength="2000"
                        class="w-full resize-none rounded-lg border border-white/10 bg-slate-950/60 px-4 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                    ></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button
                        type="button"
                        data-modal-close
                        class="rounded-full border border-white/10 px-4 py-2 text-sm text-slate-300 transition hover:text-white"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        class="rounded-full bg-indigo-500/90 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-indigo-900/40 transition hover:bg-indigo-500"
                    >
                        Adicionar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
