<script setup>
import { onMounted, ref, watch } from 'vue';

defineProps({ label: String, placeholder: String });
const model = defineModel({ type: Object, required: true });
const editor = ref(null);
const emojiOpen = ref(false);
const formatting = [
    { command: 'bold', label: 'Negrito', sample: 'B', class: 'font-bold' },
    { command: 'italic', label: 'Itálico', sample: 'I', class: 'italic' },
    { command: 'underline', label: 'Sublinhado', sample: 'U', class: 'underline' },
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
    if (selection?.rangeCount && editor.value.contains(selection.getRangeAt(0).commonAncestorContainer)) {
        selectionRange = selection.getRangeAt(0).cloneRange();
    }
}

function emitContent() {
    const content = editor.value.innerText.trim();
    model.value = { content, content_html: content ? editor.value.innerHTML : '' };
    rememberSelection();
}

function format(command, value = null) {
    focus();
    if (selectionRange && editor.value.contains(selectionRange.commonAncestorContainer)) {
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(selectionRange);
    }
    document.execCommand('styleWithCSS', false, false);
    const applied = document.execCommand(command, false, value);
    if (!applied && command === 'hiliteColor') document.execCommand('backColor', false, value);
    emitContent();
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

onMounted(() => syncEditor(model.value));
watch(model, syncEditor);
</script>

<template>
    <div class="social-rich-editor">
        <div class="social-editor-toolbar social-actions" role="group" aria-label="Formatação da publicação">
            <button v-for="item in formatting" :key="item.command" type="button" :aria-label="item.label" :title="item.label" @pointerdown.prevent @click="format(item.command)">
                <span :class="item.class" aria-hidden="true">{{ item.sample }}</span>
            </button>
            <button type="button" aria-label="Destacar texto" title="Destacar texto" @pointerdown.prevent @click="format('hiliteColor', '#fef08a')">
                <span class="social-marker-sample" aria-hidden="true">A</span>
            </button>
            <button type="button" aria-label="Título" title="Título" @pointerdown.prevent @click="format('formatBlock', 'h2')">H2</button>
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
        <div v-if="emojiOpen" class="social-editor-emojis social-actions" role="group" aria-label="Escolher emoji">
            <button v-for="[emoji, name] in emojis" :key="emoji" type="button" :aria-label="name" :title="name" @pointerdown.prevent @click="insertEmoji(emoji)">{{ emoji }}</button>
        </div>
        <div
            ref="editor"
            contenteditable="true"
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
