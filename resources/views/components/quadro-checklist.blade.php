@props(['checklist'])
<section data-quadro-checklist-section data-id="{{ $checklist['id'] }}" class="quadro-card-section quadro-checklist">
    <div class="quadro-checklist-heading">
        <x-card-icon name="checklist" />
        <input data-quadro-checklist-title value="{{ $checklist['title'] }}" required maxlength="100" aria-label="Nome do checklist" class="min-w-0 flex-1">
        <span data-quadro-checklist-count class="shrink-0 text-xs text-slate-400"></span>
        <button type="button" data-quadro-checklist-delete class="quadro-tool" aria-label="Excluir checklist"><x-card-icon name="trash" /></button>
    </div>
    <div class="quadro-checklist-progress-row">
        <span data-quadro-checklist-percent class="text-xs text-slate-400">0%</span>
        <div class="h-2 flex-1 overflow-hidden rounded-full bg-white/5"><div data-quadro-checklist-progress class="h-full bg-indigo-400"></div></div>
    </div>
    <div data-quadro-checklist-items class="quadro-checklist-items">@foreach ($checklist['items'] as $item)<x-quadro-checklist-item :item="$item" />@endforeach</div>
    <div class="quadro-checklist-add-row">
        <input data-quadro-checklist-new maxlength="2000" aria-label="Novo item do checklist" placeholder="Adicionar item... (use ; para adicionar vários)" class="min-w-0 flex-1">
        <button type="button" data-quadro-checklist-add class="quadro-primary"><span aria-hidden="true">+</span>Adicionar</button>
    </div>
</section>
