<dialog id="project-label-dialog" aria-labelledby="project-label-heading" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl border border-white/15 bg-slate-900 p-0 text-white shadow-2xl backdrop:bg-slate-950/75 backdrop:backdrop-blur-sm">
    <div class="flex max-h-[85dvh] flex-col">
        <header class="flex shrink-0 items-center justify-between gap-3 border-b border-white/10 px-5 py-4"><h2 id="project-label-heading" class="text-lg font-semibold">Etiquetas</h2><button type="button" data-project-label-close aria-label="Fechar etiquetas" class="rounded-lg px-2 py-1 text-slate-400 hover:text-white">✕</button></header>
        <div class="app-scroll grid min-h-0 gap-4 overflow-y-auto p-5">
            <label class="grid gap-2 text-xs text-slate-400">Buscar etiquetas<input type="search" data-project-label-search placeholder="Buscar etiquetas..." class="w-full" autocomplete="off"></label>
            <p data-project-label-error hidden role="alert" class="text-sm text-rose-300"></p>
            <div data-project-label-list class="grid gap-3"></div>
            <p data-project-label-no-results hidden class="text-sm text-slate-500">Nenhuma etiqueta encontrada.</p>
            <div data-project-label-editor>
                <button type="button" data-project-label-new class="w-full rounded-xl border border-white/15 px-4 py-3 text-left text-sm font-semibold text-indigo-200 hover:bg-white/5">+ Criar uma nova etiqueta</button>
                <form data-project-label-form hidden class="mt-3 grid gap-4 rounded-xl border border-indigo-400/20 bg-slate-950/40 p-4">
                    <h3 data-project-label-form-title class="text-sm font-semibold">Nova etiqueta</h3>
                    <label class="grid gap-2 text-xs text-slate-400">Nome<input name="name" required maxlength="60" class="w-full" placeholder="Ex.: Importante"></label>
                    <fieldset><legend class="mb-2 text-xs text-slate-400">Cor</legend><div class="flex flex-wrap gap-2">
                        @foreach (\App\Models\ProjetoEtiqueta::COLORS as $color => $meta)
                            <label class="cursor-pointer"><input type="radio" name="color" value="{{ $color }}" @checked($color === 'violet') class="peer sr-only"><span class="block h-8 w-8 rounded-full border-2 border-transparent peer-checked:border-white peer-focus-visible:outline-2 peer-focus-visible:outline-indigo-300 {{ $meta['class'] }}" title="{{ $meta['label'] }}"></span><span class="sr-only">{{ $meta['label'] }}</span></label>
                        @endforeach
                    </div></fieldset>
                    <div class="flex items-center gap-3"><button type="submit" class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold hover:bg-indigo-400">Salvar etiqueta</button><button type="button" data-project-label-cancel class="text-sm text-slate-400">Cancelar</button></div>
                </form>
            </div>
        </div>
    </div>
</dialog>
