<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { formatSelection, markerTags, selectionHasFormat, underlineTag } from './social-inline-format';

defineProps({ label: String, placeholder: String });
const model = defineModel({ type: Object, required: true });
const editor = ref(null);
const emojiOpen = ref(false);
const markerPaletteOpen = ref(false);
const underlineActive = ref(false);
const markerActive = ref(false);
const markerColor = ref('amber');
const blockStyle = ref('p');
const headings = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
const markerColors = [
    ['amber', 'Amarelo'], ['sky', 'Azul'], ['emerald', 'Verde'],
    ['rose', 'Rosa'], ['indigo', 'Lilás'], ['orange', 'Laranja'],
];
const formatting = [
    { command: 'bold', label: 'Negrito', sample: 'B', class: 'font-bold' },
    { command: 'italic', label: 'Itálico', sample: 'I', class: 'italic' },
    { command: 'strikeThrough', label: 'Tachado', sample: 'S', class: 'line-through' },
];
const emojis = [
    ['😊', 'Sorriso'], ['😂', 'Risada'], ['❤️', 'Coração'], ['👏', 'Aplausos'],
    ['🎉', 'Comemoração'], ['✨', 'Brilho'], ['👍', 'Positivo'], ['🙌', 'Celebração'],
];
let selectionRange = null;

function focus() {
    editor.value?.focus({ preventScroll: true });
}

defineExpose({ focus });

function syncEditor(value) {
    if (!editor.value || (value.content_html && editor.value.innerHTML === value.content_html)) return;
    if (value.content_html) editor.value.innerHTML = value.content_html;
    else editor.value.textContent = value.content || '';
    selectionRange = null;
}

function rememberSelection() {
    const selection = window.getSelection();
    if (selection?.rangeCount && editor.value?.contains(selection.getRangeAt(0).commonAncestorContainer)) {
        selectionRange = selection.getRangeAt(0).cloneRange();
        const container = selectionRange.startContainer;
        const element = container.nodeType === Node.ELEMENT_NODE ? container : container.parentElement;
        blockStyle.value = element?.closest(headings.join(','))?.localName ?? 'p';
        underlineActive.value = selectionRange.collapsed
            ? document.queryCommandState('underline')
            : selectionHasFormat(editor.value, selectionRange, underlineTag);
        markerActive.value = selectionRange.collapsed
            ? Boolean(selection.focusNode?.parentElement?.closest(markerTags))
            : selectionHasFormat(editor.value, selectionRange, markerTags);
    }
}

function emitContent() {
    const content = editor.value.innerText.trim();
    model.value = { content, content_html: content ? editor.value.innerHTML : '' };
    rememberSelection();
}

function restoreSelection() {
    const range = selectionRange?.cloneRange();
    focus();
    const selection = window.getSelection();
    if (!range || !selection || !editor.value.contains(range.commonAncestorContainer)) return null;
    selection.removeAllRanges();
    selection.addRange(range);
    return range;
}

function format(command, value = null) {
    restoreSelection();
    document.execCommand('styleWithCSS', false, false);
    document.execCommand(command, false, value);
    emitContent();
}

function changeBlockStyle(event) {
    const style = event.target.value;
    queueMicrotask(() => {
        if (editor.value) format('formatBlock', style);
    });
}

function toggleUnderline() {
    const range = restoreSelection();
    if (range && !range.collapsed) {
        formatSelection(editor.value, range, { tag: underlineTag, toggle: true });
    } else {
        document.execCommand('styleWithCSS', false, false);
        document.execCommand('underline');
    }
    emitContent();
}

function applyMarker(toggle) {
    const range = restoreSelection();
    if (range?.collapsed) {
        const marked = window.getSelection()?.focusNode?.parentElement?.closest(markerTags);
        if (marked && editor.value.contains(marked)) range.selectNodeContents(marked);
    }
    if (!range || range.collapsed) return;
    formatSelection(editor.value, range, { tag: 'mark', selector: markerTags, color: markerColor.value, toggle });
    emitContent();
}

function selectMarkerColor(color) {
    markerColor.value = color;
    markerPaletteOpen.value = false;
    applyMarker(false);
}

function paste(event) {
    const text = event.clipboardData?.getData('text/plain');
    rememberSelection();
    if (text) format('insertText', text);
}

function insertEmoji(emoji) {
    emojiOpen.value = false;
    format('insertText', emoji);
}

onMounted(() => {
    syncEditor(model.value);
    document.addEventListener('selectionchange', rememberSelection);
});
onBeforeUnmount(() => document.removeEventListener('selectionchange', rememberSelection));
watch(model, syncEditor);
</script>

<template>
    <div class="social-rich-editor">
        <div class="social-editor-toolbar social-actions" role="group" aria-label="Formatação da publicação">
            <button v-for="item in formatting" :key="item.command" type="button" :aria-label="item.label" :title="item.label" @pointerdown.prevent @click="format(item.command)">
                <span :class="item.class" aria-hidden="true">{{ item.sample }}</span>
            </button>
            <button type="button" aria-label="Sublinhado" title="Aplicar ou remover sublinhado" :aria-pressed="underlineActive" @pointerdown.prevent @click="toggleUnderline">
                <span class="underline" aria-hidden="true">U</span>
            </button>
            <button type="button" aria-label="Marca-texto" title="Aplicar ou remover marca-texto" :aria-pressed="markerActive" @pointerdown.prevent @click="applyMarker(true)">
                <span class="social-marker-sample" :data-marker-color="markerColor" aria-hidden="true">A</span>
            </button>
            <button type="button" aria-label="Cor do marca-texto" title="Cor do marca-texto" :aria-expanded="markerPaletteOpen" @pointerdown.prevent @click="markerPaletteOpen = !markerPaletteOpen">
                <span class="social-marker-swatch" :data-marker-color="markerColor" aria-hidden="true"></span>
            </button>
            <span class="social-editor-block-style">
                <select :value="blockStyle" aria-label="Estilo do parágrafo" @change="changeBlockStyle">
                    <option value="p">Texto normal</option>
                    <option v-for="heading in headings" :key="heading" :value="heading">Título {{ heading.toUpperCase() }}</option>
                </select>
            </span>
            <button type="button" aria-label="Lista com marcadores" title="Lista com marcadores" @pointerdown.prevent @click="format('insertUnorderedList')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/></svg>
            </button>
            <button type="button" aria-label="Lista numerada" title="Lista numerada" @pointerdown.prevent @click="format('insertOrderedList')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M10 6h10M10 12h10M10 18h10M3 5l2-1v5M3 9h4M3 14a2 2 0 0 1 4 0c0 1.5-4 3-4 5h4"/></svg>
            </button>
            <button type="button" aria-label="Inserir emoji" title="Inserir emoji" :aria-expanded="emojiOpen" @pointerdown.prevent @click="emojiOpen = !emojiOpen">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 14a4.5 4.5 0 0 0 8 0M9 9h.01M15 9h.01"/></svg>
            </button>
        </div>
        <div v-if="markerPaletteOpen" class="social-editor-emojis social-actions" role="group" aria-label="Escolher cor do marca-texto">
            <button v-for="[color, label] in markerColors" :key="color" type="button" :aria-label="label" :title="label" :aria-pressed="markerColor === color" @pointerdown.prevent @click="selectMarkerColor(color)">
                <span class="social-marker-swatch" :data-marker-color="color" aria-hidden="true"></span>
            </button>
        </div>
        <div v-if="emojiOpen" class="social-editor-emojis social-actions" role="group" aria-label="Escolher emoji">
            <button v-for="[emoji, name] in emojis" :key="emoji" type="button" :aria-label="name" :title="name" @pointerdown.prevent @click="insertEmoji(emoji)">{{ emoji }}</button>
        </div>
        <div
            ref="editor"
            contenteditable="true"
            spellcheck="false"
            role="textbox"
            aria-multiline="true"
            :aria-label="label"
            :data-placeholder="placeholder"
            class="social-rich-content social-rich-input"
            @input="emitContent"
            @keyup="rememberSelection"
            @pointerup="rememberSelection"
            @blur="rememberSelection"
            @paste.prevent="paste"
            @drop.prevent
        ></div>
    </div>
</template>
