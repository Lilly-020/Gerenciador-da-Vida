@php
    $catalog = collect($quadro->label_catalog ?? []);
    foreach ($cartao->labels ?? [] as $name) {
        if (!$catalog->contains('name', $name)) {
            $catalog->push(['id' => '', 'name' => $name, 'color' => 'violet']);
        }
    }
@endphp
<div data-quadro-label-browser>
<header class="mb-5 flex items-center justify-between gap-3"><h3 class="flex-1 text-center text-base font-semibold">Etiquetas</h3><button type="button" data-quadro-card-panel-close aria-label="Fechar etiquetas" class="rounded-lg p-1 text-slate-400 hover:bg-white/10"><x-card-icon name="close" /></button></header>
<input data-quadro-label-search type="search" placeholder="Buscar etiquetas…" aria-label="Buscar etiquetas" class="mb-5 w-full">
<h4 class="mb-3 text-xs font-medium text-slate-400">Etiquetas</h4>
<div class="grid gap-3" data-quadro-label-list>
@foreach ($catalog as $label)
    <div data-quadro-label-row data-id="{{ $label['id'] }}" data-name="{{ $label['name'] }}" data-color="{{ \App\Models\Quadro::labelColorValue($label['color']) }}" class="flex items-center gap-2">
        <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-3"><x-checkbox name="labels[]" value="{{ $label['name'] }}" :checked="in_array($label['name'], $cartao->labels ?? [])" /><span class="quadro-label-choice" style="{{ $quadro->labelStyle($label['name']) }}">{{ $label['name'] }}</span></label>
        @if ($label['id'])<button type="button" data-quadro-label-edit class="p-1.5 text-slate-400" aria-label="Editar etiqueta {{ $label['name'] }}"><x-card-icon name="pencil" /></button><button type="button" data-quadro-label-delete class="p-1.5 text-rose-300" aria-label="Excluir etiqueta {{ $label['name'] }}"><x-card-icon name="trash" /></button>@endif
    </div>
@endforeach
</div>
<button type="button" data-quadro-label-new class="quadro-tool mt-4 w-full">+ Criar uma nova etiqueta</button>
<button type="submit" class="quadro-primary mt-4 w-full">Aplicar etiquetas</button>
</div>
<div data-quadro-label-editor hidden>
    <header class="quadro-label-editor-header"><button type="button" data-quadro-label-cancel aria-label="Voltar para etiquetas"><x-card-icon name="back" /></button><h3 data-quadro-label-editor-heading>Criar etiqueta</h3><button type="button" data-quadro-card-panel-close aria-label="Fechar criação de etiqueta"><x-card-icon name="close" /></button></header>
    <div class="quadro-label-preview"><span data-quadro-label-preview></span></div>
    <label class="mt-4 grid gap-2 text-sm text-slate-400">Título<input data-quadro-label-name maxlength="40" class="w-full" autocomplete="off"></label>
    <fieldset class="mt-4"><legend class="mb-3 text-sm text-slate-400">Selecionar uma cor</legend><div class="quadro-label-color-grid">@foreach (\App\Models\Quadro::LABEL_COLORS as $color)<button type="button" data-quadro-label-color="{{ $color }}" title="{{ $color }}" aria-label="Usar cor {{ $color }}" aria-pressed="false" style="background-color:{{ $color }}"></button>@endforeach</div></fieldset>
    <label class="mt-4 flex cursor-pointer items-center gap-3 text-sm text-slate-200"><input type="color" data-quadro-label-custom-color value="#4bce97" class="h-8! w-9! cursor-pointer border-0! bg-transparent! p-0!">Cor personalizada</label>
    <button type="button" data-quadro-label-save class="quadro-primary mt-4">Criar</button>
</div>
<p data-quadro-label-error hidden class="mt-3 text-xs text-rose-300" role="alert"></p>
