<div id="edit-custo-fixo-modal" class="board-modal fixed inset-0 z-50 hidden bg-black/60 backdrop-blur-sm" aria-hidden="true">
    <div class="flex min-h-full items-center justify-center px-4 py-8">
        <div class="modal-panel w-full max-w-md rounded-2xl border border-white/10 bg-slate-900/95 p-6 shadow-2xl shadow-black/40">
            <div class="mb-5 flex items-start justify-between gap-4">
                <h2 class="text-lg font-semibold text-white">Editar custo fixo</h2>
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

            <form id="edit-custo-fixo-form" class="space-y-4">
                <input type="hidden" name="status">

                <div>
                    <label for="edit-custo-fixo-name" class="mb-1.5 block text-sm font-medium text-slate-300">Nome</label>
                    <input
                        id="edit-custo-fixo-name"
                        type="text"
                        name="name"
                        required
                        maxlength="255"
                        class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-4 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                    >
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="edit-custo-fixo-category" class="mb-1.5 block text-sm font-medium text-slate-300">Categoria</label>
                        <select
                            id="edit-custo-fixo-category"
                            name="category"
                            required
                            class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                        >
                            @foreach (\App\Models\CustoFixo::CATEGORIES as $category)
                                <option value="{{ $category }}">{{ $category }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="edit-custo-fixo-amount" class="mb-1.5 block text-sm font-medium text-slate-300">Valor</label>
                        <input
                            id="edit-custo-fixo-amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            name="amount"
                            required
                            class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-4 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                        >
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="edit-custo-fixo-due-day" class="mb-1.5 block text-sm font-medium text-slate-300">Dia de vencimento</label>
                        <input
                            id="edit-custo-fixo-due-day"
                            type="number"
                            name="due_day"
                            min="1"
                            max="31"
                            required
                            class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-4 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                        >
                    </div>
                    <div>
                        <label for="edit-custo-fixo-periodicity" class="mb-1.5 block text-sm font-medium text-slate-300">Periodicidade</label>
                        <select
                            id="edit-custo-fixo-periodicity"
                            name="periodicity"
                            required
                            class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                        >
                            @foreach (\App\Models\CustoFixo::PERIODICITIES as $periodicity)
                                <option value="{{ $periodicity }}">{{ ucfirst($periodicity) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="edit-custo-fixo-starts-on" class="mb-1.5 block text-sm font-medium text-slate-300">Início</label>
                        <input
                            id="edit-custo-fixo-starts-on"
                            type="date"
                            name="starts_on"
                            required
                            class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20 scheme-dark"
                        >
                    </div>
                    <div>
                        <label for="edit-custo-fixo-ends-on" class="mb-1.5 block text-sm font-medium text-slate-300">Fim (opcional)</label>
                        <input
                            id="edit-custo-fixo-ends-on"
                            type="date"
                            name="ends_on"
                            class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20 scheme-dark"
                        >
                    </div>
                </div>

                <div>
                    <label for="edit-custo-fixo-notes" class="mb-1.5 block text-sm font-medium text-slate-300">Observação</label>
                    <textarea
                        id="edit-custo-fixo-notes"
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
