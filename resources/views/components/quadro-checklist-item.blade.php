@props(['item' => ['text' => '', 'done' => false]])
<div data-quadro-checklist-item class="quadro-checklist-item">
    <button type="button" draggable="true" data-quadro-checklist-drag="item" class="quadro-drag-handle shrink-0" aria-label="Reordenar item com as setas para cima e para baixo" title="Arrastar item"><x-card-icon name="grip" /></button>
    <x-checkbox data-quadro-checklist-done :checked="$item['done']" aria-label="Concluir item" />
    <span data-quadro-checklist-label data-quadro-checklist-drag="item" draggable="true" @class(['min-w-0 flex-1 cursor-grab wrap-break-word text-sm active:cursor-grabbing', 'line-through text-slate-500' => $item['done']])>{{ $item['text'] }}</span>
    <input hidden data-quadro-checklist-text value="{{ $item['text'] }}" readonly required maxlength="500" aria-label="Item do checklist" @class(['min-w-0 flex-1', 'line-through text-slate-500' => $item['done']])>
    <div class="flex shrink-0 gap-2">
        <button type="button" data-quadro-checklist-edit class="quadro-tool" aria-label="Editar item"><x-card-icon name="pencil" /></button>
        <button type="button" data-quadro-checklist-remove class="quadro-tool" aria-label="Excluir item"><x-card-icon name="trash" /></button>
    </div>
</div>
