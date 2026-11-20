export const underlineTag = 'u';
export const markerTags = 'mark, span[style*="background-color: rgb(254, 240, 138)"], span[style*="background-color: #fef08a"]';

function textBefore(editor, node, offset) {
    const prefix = document.createRange();
    prefix.selectNodeContents(editor);
    prefix.setEnd(node, offset);
    return prefix.toString().length;
}

function textNodes(editor) {
    const walker = document.createTreeWalker(editor, NodeFilter.SHOW_TEXT);
    const nodes = [];
    let node;
    while ((node = walker.nextNode())) {
        if (node.length) nodes.push(node);
    }
    return nodes;
}

function selectedText(editor, range, selector) {
    const start = textBefore(editor, range.startContainer, range.startOffset);
    const end = textBefore(editor, range.endContainer, range.endOffset);
    let offset = 0;
    const parts = textNodes(editor).map(node => {
        const from = Math.max(0, start - offset);
        const to = Math.min(node.length, end - offset);
        offset += node.length;
        return { node, from, to, wrapper: node.parentElement.closest(selector) };
    });
    return { start, end, parts };
}

function setSelection(editor, start, end) {
    const range = document.createRange();
    let offset = 0;
    for (const node of textNodes(editor)) {
        const next = offset + node.length;
        if (start >= offset && start < next) range.setStart(node, start - offset);
        if (end > offset && end <= next) {
            range.setEnd(node, end - offset);
            break;
        }
        offset = next;
    }
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
}

function appendText(fragment, value, tag, color) {
    if (!value) return;
    if (color === null) {
        fragment.append(value);
        return;
    }
    const wrapper = document.createElement(tag);
    if (color) {
        if (tag === 'mark') wrapper.dataset.markerColor = color;
        else wrapper.dataset.underlineColor = color;
    }
    wrapper.textContent = value;
    fragment.append(wrapper);
}

export function selectionHasFormat(editor, range, selector) {
    const selected = selectedText(editor, range, selector).parts.filter(part => part.from < part.to);
    return selected.length > 0 && selected.every(part => part.wrapper);
}

export function formatSelection(editor, range, { tag, selector = tag, color = '', toggle = false }) {
    const { start, end, parts } = selectedText(editor, range, selector);
    const selected = parts.filter(part => part.from < part.to);
    if (!selected.length) return;

    const chosenColor = toggle && selected.every(part => part.wrapper) ? null : color;
    const affected = new Set();
    const originalColors = new Map();
    for (const part of selected) {
        for (let ancestor = part.wrapper; ancestor && editor.contains(ancestor); ancestor = ancestor.parentElement.closest(selector)) {
            affected.add(ancestor);
        }
    }
    for (const wrapper of affected) {
        originalColors.set(wrapper, (tag === 'mark' ? wrapper.dataset.markerColor : wrapper.dataset.underlineColor) ?? '');
        wrapper.replaceWith(...wrapper.childNodes);
    }

    for (const { node, from, to, wrapper } of parts) {
        if (from >= to && !affected.has(wrapper)) continue;
        const original = originalColors.get(wrapper) ?? null;
        const fragment = document.createDocumentFragment();
        if (from < to) {
            appendText(fragment, node.data.slice(0, from), tag, original);
            appendText(fragment, node.data.slice(from, to), tag, chosenColor);
            appendText(fragment, node.data.slice(to), tag, original);
        } else {
            appendText(fragment, node.data, tag, original);
        }
        node.replaceWith(fragment);
    }
    setSelection(editor, start, end);
}
