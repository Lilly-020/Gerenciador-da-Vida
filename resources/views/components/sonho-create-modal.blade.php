<div
    id="new-sonho-modal"
    class="board-modal fixed inset-0 z-50 hidden overflow-y-auto bg-black/60 backdrop-blur-sm"
    aria-hidden="true"
>
    <div class="flex min-h-full items-center justify-center px-4 py-8">
        <div class="modal-panel w-full max-w-md rounded-2xl border border-white/10 bg-slate-900/95 p-6 shadow-2xl shadow-black/40">
            <div class="mb-5 flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-white">Novo sonho</h2>
                    <p class="mt-1 text-sm text-slate-400">Dê um título para o seu sonho. As tarefas você adiciona depois, direto no card.</p>
                </div>
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

            <form id="new-sonho-form" action="{{ route('sonhos.store', absolute: false) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="sonho-title" class="mb-1.5 block text-sm font-medium text-slate-300">Título</label>
                    <input
                        id="sonho-title"
                        type="text"
                        name="title"
                        required
                        maxlength="255"
                        placeholder="Ex: Comprar uma casa"
                        class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-4 py-2.5 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                    >
                </div>

                <details class="rounded-lg border border-white/10 p-3 text-sm text-slate-300"><summary class="cursor-pointer">Planejamento e detalhes opcionais</summary><div class="mt-4 grid gap-4"><label class="grid gap-1.5">Data-alvo<input type="date" name="target_date"  class="w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm text-white"></label><label class="grid gap-1.5">Meta financeira (R$)<input type="number" name="financial_goal" min="0.01" step="0.01" max="999999999999.99" class="w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm text-white"></label><label class="grid gap-1.5">Valor economizado (R$)<input type="number" name="saved_amount" min="0" step="0.01" max="999999999999.99" class="w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm text-white"></label><label class="grid gap-1.5">Imagem do sonho (URL HTTPS)<input type="url" name="image_url" maxlength="2048" class="w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm text-white"></label><label class="grid gap-1.5">Prioridade<select name="priority" class="w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm text-white"><option value="low">Baixa</option><option value="medium" selected>Média</option><option value="high">Alta</option></select></label><label class="grid gap-1.5">Anotações<textarea name="notes" maxlength="10000" rows="3" class="w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm text-white"></textarea></label></div></details>
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
                        Criar sonho
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
