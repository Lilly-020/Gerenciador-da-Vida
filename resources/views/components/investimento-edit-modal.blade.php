<div id="edit-investimento-modal" class="board-modal fixed inset-0 z-50 hidden bg-black/60 backdrop-blur-sm" aria-hidden="true">
    <div class="flex min-h-full items-center justify-center px-4 py-8">
        <div class="modal-panel w-full max-w-md rounded-2xl border border-white/10 bg-slate-900/95 p-6 shadow-2xl shadow-black/40">
            <div class="mb-5 flex items-start justify-between gap-4">
                <h2 class="text-lg font-semibold text-white">Editar investimento</h2>
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

            <form id="edit-investimento-form" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="edit-investimento-name" class="mb-1.5 block text-sm font-medium text-slate-300">Nome</label>
                    <input
                        id="edit-investimento-name"
                        type="text"
                        name="name"
                        required
                        maxlength="255"
                        class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-4 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                    >
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="edit-investimento-type" class="mb-1.5 block text-sm font-medium text-slate-300">Tipo</label>
                        <select
                            id="edit-investimento-type"
                            name="type"
                            required
                            class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                        >
                            @foreach (\App\Models\Investimento::TYPES as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="edit-investimento-institution" class="mb-1.5 block text-sm font-medium text-slate-300">Instituição</label>
                        <input
                            id="edit-investimento-institution"
                            type="text"
                            name="institution"
                            maxlength="255"
                            placeholder="Opcional"
                            class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-4 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                        >
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label for="edit-investimento-rate" class="mb-1.5 block text-sm font-medium text-slate-300">Taxa (%)</label>
                        <input
                            id="edit-investimento-rate"
                            type="number"
                            step="0.01"
                            min="0"
                            name="rate"
                            required
                            class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-4 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                        >
                    </div>
                    <div>
                        <label for="edit-investimento-rate-period" class="mb-1.5 block text-sm font-medium text-slate-300">Período</label>
                        <select
                            id="edit-investimento-rate-period"
                            name="rate_period"
                            required
                            class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                        >
                            <option value="anual">Ao ano</option>
                            <option value="mensal">Ao mês</option>
                        </select>
                    </div>
                    <div>
                        <label for="edit-investimento-rate-reference" class="mb-1.5 block text-sm font-medium text-slate-300">Referência</label>
                        <input
                            id="edit-investimento-rate-reference"
                            type="text"
                            name="rate_reference"
                            maxlength="255"
                            placeholder="Ex: CDI"
                            class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-4 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                        >
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="edit-investimento-liquidity" class="mb-1.5 block text-sm font-medium text-slate-300">Liquidez</label>
                        <input
                            id="edit-investimento-liquidity"
                            type="text"
                            name="liquidity"
                            maxlength="255"
                            placeholder="Ex: Diária"
                            class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-4 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                        >
                    </div>
                    <div>
                        <label for="edit-investimento-maturity" class="mb-1.5 block text-sm font-medium text-slate-300">Vencimento</label>
                        <input
                            id="edit-investimento-maturity"
                            type="date"
                            name="maturity_date"
                            class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20 scheme-dark"
                        >
                    </div>
                </div>

                <div>
                    <label for="edit-investimento-notes" class="mb-1.5 block text-sm font-medium text-slate-300">Observação</label>
                    <textarea
                        id="edit-investimento-notes"
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
                        Salvar alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
