@props(['html' => null, 'text' => '', 'uploadUrl', 'draftKey' => null])
<div data-rich-editor data-upload-url="{{ $uploadUrl }}" @if ($draftKey) data-draft-key="{{ $draftKey }}" @endif class="quadro-rich-editor">
    <div data-rich-toolbar class="quadro-rich-toolbar" role="toolbar" aria-label="Formatar comentário">
        <select data-rich-block aria-label="Estilo do texto" data-native-select><option value="p">Normal</option><option value="h2">Título</option><option value="h3">Subtítulo</option><option value="blockquote">Citação</option><option value="pre">Código</option></select>
        @foreach (['bold' => ['bold', 'Negrito'], 'italic' => ['italic', 'Itálico'], 'underline' => ['underline', 'Sublinhado'], 'strikeThrough' => ['strike', 'Tachado'], 'insertUnorderedList' => ['list', 'Lista com marcadores'], 'insertOrderedList' => ['list-ordered', 'Lista numerada']] as $command => [$icon, $label])
            <button type="button" data-rich-command="{{ $command }}" aria-label="{{ $label }}" title="{{ $label }}"><x-card-icon :name="$icon" /></button>
        @endforeach
        <button type="button" data-rich-link aria-label="Inserir link" title="Inserir link"><x-card-icon name="link" /></button>
        <button type="button" data-rich-command="formatBlock" data-value="pre" aria-label="Código" title="Código"><x-card-icon name="code" /></button>
        <button type="button" data-rich-image aria-label="Inserir imagem" title="Inserir imagem"><x-card-icon name="image" /></button>
        <button type="button" data-rich-attach aria-label="Anexar arquivo" title="Anexar arquivo"><x-card-icon name="attachment" /></button>
    </div>
    <div data-rich-mentions popover="manual" class="quadro-mention-options app-scroll" role="listbox" aria-label="Mencionar participante"></div>
    <div data-rich-reply hidden class="quadro-rich-reply" contenteditable="false" aria-label="Comentário respondido">
        <div class="min-w-0 flex-1"><strong data-rich-reply-author></strong><p data-rich-reply-text></p></div>
        <button type="button" data-rich-reply-cancel aria-label="Cancelar resposta" title="Cancelar resposta"><x-card-icon name="close" /></button>
    </div>
    <div data-rich-content contenteditable="true" role="textbox" aria-multiline="true" aria-label="Comentário" data-placeholder="Escrever um comentário…" spellcheck="true" class="quadro-rich-content app-scroll">@if ($html){!! $html !!}@elseif ($text){{ $text }}@endif</div>
    <input type="hidden" name="body" value="{{ $text }}">
    <input data-rich-file type="file" hidden>
    <span data-rich-progress hidden class="block border-t border-white/10 px-3 py-2 text-xs text-indigo-300" role="status">Enviando arquivo…</span>
    @if ($draftKey)<div data-rich-draft-bar hidden class="quadro-rich-draft-bar"><span data-rich-draft-status role="status"></span><button type="button" data-rich-discard aria-label="Apagar rascunho" title="Apagar rascunho"><x-card-icon name="trash" /></button></div>@endif
</div>
