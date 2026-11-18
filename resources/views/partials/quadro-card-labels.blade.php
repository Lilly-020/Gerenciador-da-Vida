<h3 class="mb-4 font-semibold">Etiquetas</h3>
<input data-quadro-label-search type="search" placeholder="Buscar etiquetas" aria-label="Buscar etiquetas" class="mb-4 w-full">
<div class="grid gap-3" data-quadro-label-list>
@foreach ($quadro->label_catalog ?? [] as $label)
    <div data-quadro-label-row data-id="{{ $label['id'] }}" data-name="{{ $label['name'] }}" data-color="{{ $label['color'] }}" class="flex items-center gap-2">
        <label class="flex min-w-0 flex-1 items-center gap-2"><x-checkbox name="labels[]" value="{{ $label['name'] }}" :checked="in_array($label['name'], $cartao->labels ?? [])" /><span class="min-w-0 flex-1 wrap-break-word rounded-lg px-3 py-2 text-xs font-semibold {{ $quadro->labelClass($label['name']) }}">{{ $label['name'] }}</span></label>
        <button type="button" data-quadro-label-edit class="p-1.5 text-slate-400" aria-label="Editar etiqueta {{ $label['name'] }}"><x-card-icon name="pencil" /></button><button type="button" data-quadro-label-delete class="p-1.5 text-rose-300" aria-label="Excluir etiqueta {{ $label['name'] }}"><x-card-icon name="trash" /></button>
    </div>
@endforeach
</div>
<button type="button" data-quadro-label-new class="quadro-tool mt-4 w-full">+ Criar uma nova etiqueta</button>
<div data-quadro-label-editor hidden class="mt-4 rounded-xl border border-indigo-400/20 p-3">
    <label class="grid gap-2 text-xs text-slate-400">Nome<input data-quadro-label-name maxlength="40" class="w-full" placeholder="Ex.: Importante"></label>
    <fieldset class="mt-4"><legend class="mb-2 text-xs text-slate-400">Cor</legend><div class="flex flex-wrap gap-2">@foreach (\App\Models\ProjetoEtiqueta::COLORS as $color => $info)<button type="button" data-quadro-label-color="{{ $color }}" aria-label="{{ $info['label'] }}" aria-pressed="false" class="h-7 w-7 rounded-full border-2 border-transparent aria-pressed:border-white {{ $info['class'] }}"></button>@endforeach</div></fieldset>
    <div class="mt-4 flex gap-2"><button type="button" data-quadro-label-save class="quadro-primary">Salvar etiqueta</button><button type="button" data-quadro-label-cancel class="text-xs text-slate-400">Cancelar</button></div>
</div>
<p data-quadro-label-error hidden class="mt-3 text-xs text-rose-300" role="alert"></p>
<button type="submit" class="quadro-primary mt-4 w-full">Aplicar etiquetas</button>
