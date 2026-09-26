<div id="investimento-simulador" class="mb-8 rounded-2xl border border-white/10 bg-slate-900/70 p-4 shadow-lg shadow-black/20 sm:p-5">
    <details class="group" open>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-white sm:text-lg">Simulador</h2>
                <p class="mt-0.5 text-xs text-slate-400">
                    Quanto você teria se investisse um valor a uma taxa, sem afetar seus investimentos reais.
                </p>
            </div>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 shrink-0 text-slate-400 transition-transform group-open:rotate-180">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
            </svg>
        </summary>

        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[1fr_auto_1fr]">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="sim-valor-inicial" class="mb-1.5 block text-xs font-medium text-slate-300">Valor inicial</label>
                    <input
                        id="sim-valor-inicial"
                        type="number"
                        step="0.01"
                        min="0"
                        data-sim-input
                        data-sim-field="valorInicial"
                        value="1000"
                        class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                    >
                </div>
                <div>
                    <label for="sim-aporte-mensal" class="mb-1.5 block text-xs font-medium text-slate-300">Aporte mensal</label>
                    <input
                        id="sim-aporte-mensal"
                        type="number"
                        step="0.01"
                        min="0"
                        data-sim-input
                        data-sim-field="aporteMensal"
                        value="0"
                        placeholder="Opcional"
                        class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white placeholder-slate-500 outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                    >
                </div>
                <div>
                    <label for="sim-taxa" class="mb-1.5 block text-xs font-medium text-slate-300">Taxa (%)</label>
                    <input
                        id="sim-taxa"
                        type="number"
                        step="0.01"
                        min="0"
                        data-sim-input
                        data-sim-field="taxa"
                        value="12"
                        class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                    >
                </div>
                <div>
                    <label for="sim-periodo" class="mb-1.5 block text-xs font-medium text-slate-300">Período da taxa</label>
                    <select
                        id="sim-periodo"
                        data-sim-input
                        data-sim-field="periodo"
                        class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                    >
                        <option value="anual">Ao ano</option>
                        <option value="mensal">Ao mês</option>
                    </select>
                </div>
                <div class="col-span-2">
                    <label for="sim-prazo" class="mb-1.5 block text-xs font-medium text-slate-300">Prazo</label>
                    <div class="flex gap-2">
                        <input
                            id="sim-prazo"
                            type="number"
                            step="1"
                            min="1"
                            data-sim-input
                            data-sim-field="prazo"
                            value="12"
                            class="w-full rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                        >
                        <select
                            id="sim-prazo-unidade"
                            data-sim-input
                            data-sim-field="prazoUnidade"
                            class="w-32 shrink-0 rounded-lg border border-white/10 bg-slate-950/60 px-3 py-2 text-sm text-white outline-none transition focus:border-indigo-400/50 focus:ring-2 focus:ring-indigo-400/20"
                        >
                            <option value="meses">Meses</option>
                            <option value="anos">Anos</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="hidden w-px bg-white/10 lg:block"></div>

            <div>
                <div class="grid grid-cols-3 gap-2 text-center sm:gap-3">
                    <div>
                        <p class="text-[11px] text-slate-400">Investido</p>
                        <p data-sim-output="investido" class="mt-1 text-sm font-semibold text-white">—</p>
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-400">Rendimento (est.)</p>
                        <p data-sim-output="rendimento" class="mt-1 text-sm font-semibold text-emerald-300">—</p>
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-400">Valor final</p>
                        <p data-sim-output="final" class="mt-1 text-sm font-semibold text-white">—</p>
                    </div>
                </div>

                <div class="mt-3 flex h-1.5 w-full overflow-hidden rounded-full bg-white/5">
                    <div data-sim-output="bar-investido" class="h-full bg-sky-400/80" style="width: 100%"></div>
                    <div data-sim-output="bar-rendimento" class="h-full bg-emerald-400/80" style="width: 0%"></div>
                </div>

                <p class="mt-3 text-xs text-slate-500">
                    Estimativa com juros compostos, taxa constante durante todo o período — não é um valor garantido, nem os seus investimentos reais.
                </p>
            </div>
        </div>
    </details>
</div>
