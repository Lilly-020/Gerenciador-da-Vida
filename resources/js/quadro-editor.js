import { postForm } from './board';

const states = new WeakMap();
const stateFor = (wrapper) => {
    if (!states.has(wrapper)) states.set(wrapper, { range: null, uploads: 0, restored: false, reply: null, lastSavedReply: 'null', originalHtml: wrapper.querySelector('[data-rich-content]').innerHTML, lastSavedHtml: wrapper.querySelector('[data-rich-content]').innerHTML });
    return states.get(wrapper);
};
const contentFor = (wrapper) => wrapper.querySelector('[data-rich-content]');
const remember = (wrapper) => {
    const selection = window.getSelection();
    if (selection.rangeCount && contentFor(wrapper).contains(selection.anchorNode)) stateFor(wrapper).range = selection.getRangeAt(0).cloneRange();
};
const restore = (wrapper) => {
    const content = contentFor(wrapper);
    content.focus({ preventScroll: true });
    const selection = window.getSelection();
    const saved = stateFor(wrapper).range;
    const range = saved && content.contains(saved.commonAncestorContainer) ? saved : document.createRange();
    if (range !== saved) { range.selectNodeContents(content); range.collapse(false); }
    selection.removeAllRanges(); selection.addRange(range);
};
const hideMentions = (wrapper) => {
    const panel = wrapper.querySelector('[data-rich-mentions]');
    if (panel?.matches(':popover-open')) panel.hidePopover();
    stateFor(wrapper).mention = null;
    contentFor(wrapper).removeAttribute('aria-expanded');
};
const selectMention = (wrapper, id) => {
    const mention = stateFor(wrapper).mention;
    const person = mention?.people.find((person) => person.id === id);
    if (!person) return;
    const token = document.createElement('span'); token.dataset.mentionId = person.id;
    token.className = 'quadro-mention'; token.contentEditable = 'false'; token.textContent = `@${person.name}`;
    const space = document.createTextNode(' ');
    const fragment = document.createDocumentFragment(); fragment.append(token, space);
    mention.range.deleteContents(); mention.range.insertNode(fragment);
    const range = document.createRange(); range.setStartAfter(space); range.collapse(true);
    const selection = window.getSelection(); selection.removeAllRanges(); selection.addRange(range);
    stateFor(wrapper).range = range.cloneRange(); hideMentions(wrapper); sync(wrapper);
};
const suggestMentions = (wrapper) => {
    const selection = window.getSelection();
    const node = selection.anchorNode;
    if (!selection.isCollapsed || node?.nodeType !== Node.TEXT_NODE || !contentFor(wrapper).contains(node)) { hideMentions(wrapper); return; }
    const prefix = node.textContent.slice(0, selection.anchorOffset);
    const match = prefix.match(/(?:^|\s)@([^@\s]{0,50})$/u);
    if (!match || node.parentElement.closest('[data-mention-id]')) { hideMentions(wrapper); return; }
    let people;
    const normalize = (text) => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    try { people = JSON.parse(wrapper.closest('[data-quadro-card-detail]')?.dataset.mentionPeople || '[]').filter((person) => normalize(person.name).includes(normalize(match[1]))).slice(0, 12); }
    catch { people = []; }
    const panel = wrapper.querySelector('[data-rich-mentions]');
    if (!panel || !people.length) { hideMentions(wrapper); return; }
    const range = selection.getRangeAt(0).cloneRange(); range.setStart(node, prefix.length - match[1].length - 1);
    stateFor(wrapper).mention = { people, range, index: 0 };
    panel.replaceChildren();
    people.forEach((person, index) => {
        const button = document.createElement('button'); button.type = 'button'; button.dataset.richMention = person.id;
        button.setAttribute('role', 'option'); button.setAttribute('aria-selected', String(index === 0));
        const avatar = document.createElement('span'); avatar.className = 'quadro-avatar'; avatar.textContent = person.name.slice(0, 2).toUpperCase();
        const name = document.createElement('span'); name.textContent = person.name;
        button.append(avatar, name); panel.append(button);
    });
    if (!panel.matches(':popover-open')) panel.showPopover();
    const rect = selection.getRangeAt(0).getBoundingClientRect();
    const fallback = contentFor(wrapper).getBoundingClientRect();
    const top = rect.height ? rect.top : fallback.top;
    const left = rect.height ? rect.left : fallback.left;
    panel.style.left = `${Math.max(8, Math.min(left, innerWidth - panel.offsetWidth - 8))}px`;
    panel.style.top = `${Math.max(8, Math.min(top - panel.offsetHeight - 8, innerHeight - panel.offsetHeight - 8))}px`;
    contentFor(wrapper).setAttribute('aria-expanded', 'true');
};
const sync = (wrapper) => {
    wrapper.querySelector('[name="body"]').value = contentFor(wrapper).innerText;
    remember(wrapper);
    saveDraft(wrapper, true);
};
const insert = (wrapper, html) => {
    restore(wrapper);
    document.execCommand('insertHTML', false, html);
    sync(wrapper);
};
const error = (wrapper, message) => {
    const output = wrapper.closest('form').querySelector('[data-quadro-error]');
    output.textContent = message; output.hidden = !message;
};
const cleanPaste = (html, attachmentUrl = null) => {
    const template = document.createElement('template'); template.innerHTML = html;
    const allowed = new Set(['P', 'DIV', 'B', 'STRONG', 'I', 'EM', 'U', 'S', 'STRIKE', 'UL', 'OL', 'LI', 'BLOCKQUOTE', 'PRE', 'CODE', 'H2', 'H3', 'BR', 'A']);
    const walk = (parent) => [...parent.children].forEach((element) => {
        const fileId = element.getAttribute('data-file-id');
        const attachment = attachmentUrl && /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(fileId || '') ? fileId : null;
        if (element.tagName === 'SPAN' && /^\d+$/.test(element.getAttribute('data-mention-id') || '')) {
            const id = element.getAttribute('data-mention-id'); const text = element.textContent;
            [...element.attributes].forEach((attribute) => element.removeAttribute(attribute.name));
            element.dataset.mentionId = id; element.className = 'quadro-mention'; element.contentEditable = 'false'; element.textContent = text; return;
        }
        if (element.tagName === 'IMG' && attachment) {
            const alt = element.getAttribute('alt') || '';
            [...element.attributes].forEach((attribute) => element.removeAttribute(attribute.name));
            element.dataset.fileId = attachment; element.src = `${attachmentUrl}/${attachment}?preview=1`; element.alt = alt;
            return;
        }
        if (['SCRIPT', 'STYLE', 'IFRAME', 'SVG', 'MATH', 'OBJECT', 'IMG'].includes(element.tagName)) { element.remove(); return; }
        walk(element);
        if (!allowed.has(element.tagName)) { element.replaceWith(...element.childNodes); return; }
        const href = element.tagName === 'A' ? element.getAttribute('href') : null;
        [...element.attributes].forEach((attribute) => element.removeAttribute(attribute.name));
        if (element.tagName === 'A' && attachment) { element.dataset.fileId = attachment; element.href = `${attachmentUrl}/${attachment}`; }
        else if (href && /^(https?:\/\/|mailto:)[^\s]+$/i.test(href)) element.setAttribute('href', href);
    });
    walk(template.content);
    return template.innerHTML;
};
const showDraftStatus = (wrapper, message) => {
    const bar = wrapper.querySelector('[data-rich-draft-bar]');
    if (!bar) return;
    const output = bar.querySelector('[data-rich-draft-status]');
    if (output.textContent !== message) output.textContent = message;
    bar.hidden = !message;
};
const renderReply = (wrapper) => {
    const preview = wrapper.querySelector('[data-rich-reply]');
    if (!preview) return;
    const reply = stateFor(wrapper).reply;
    preview.hidden = !reply;
    preview.querySelector('[data-rich-reply-author]').textContent = reply?.author || '';
    preview.querySelector('[data-rich-reply-text]').textContent = reply?.text || '';
};
const replyHtml = (reply) => {
    if (!reply) return '';
    const quote = document.createElement('blockquote');
    const name = document.createElement('strong'); name.textContent = reply.author;
    quote.append(name, document.createElement('br'), document.createTextNode(reply.text));
    return quote.outerHTML;
};
const draftSnapshot = (wrapper) => JSON.stringify({ html: contentFor(wrapper).innerHTML, reply: stateFor(wrapper).reply });
export function richCommentMatches(form, sent) {
    const wrapper = form.querySelector('[data-rich-editor]');
    return wrapper ? draftSnapshot(wrapper) === sent.draftSnapshot : form.elements.body.value === sent.body;
}
const hasDraftContent = (content) => Boolean(content.textContent.trim() || content.querySelector('[data-file-id]'));
const saveDraft = (wrapper, force = false) => {
    const key = wrapper.dataset.draftKey;
    if (!key) return;
    const content = contentFor(wrapper);
    const state = stateFor(wrapper);
    if (!force && state.lastSavedHtml === content.innerHTML && state.lastSavedReply === JSON.stringify(state.reply)) return;
    try {
        if (hasDraftContent(content) || state.reply) {
            localStorage.setItem(key, JSON.stringify({ version: 1, html: content.innerHTML, reply: state.reply, updatedAt: new Date().toISOString() }));
            showDraftStatus(wrapper, 'Rascunho salvo neste navegador');
        } else { localStorage.removeItem(key); showDraftStatus(wrapper, ''); }
        state.lastSavedHtml = content.innerHTML; state.lastSavedReply = JSON.stringify(state.reply);
    } catch { showDraftStatus(wrapper, 'Não foi possível salvar o rascunho neste navegador.'); }
};
export function restoreRichDrafts(root) {
    root.querySelectorAll('[data-rich-editor][data-draft-key]').forEach((wrapper) => {
        const state = stateFor(wrapper);
        if (state.restored) return;
        state.restored = true;
        try {
            const draft = JSON.parse(localStorage.getItem(wrapper.dataset.draftKey) || 'null');
            if (draft?.version !== 1 || typeof draft.html !== 'string') return;
            contentFor(wrapper).innerHTML = cleanPaste(draft.html, wrapper.dataset.uploadUrl);
            if (draft.reply && typeof draft.reply.author === 'string' && typeof draft.reply.text === 'string') {
                state.reply = { author: draft.reply.author.slice(0, 255), text: draft.reply.text.slice(0, 5000) };
            } else {
                const quote = contentFor(wrapper).firstElementChild;
                if (quote?.tagName === 'BLOCKQUOTE' && quote.firstElementChild?.tagName === 'STRONG' && quote.children[1]?.tagName === 'BR') {
                    const name = quote.firstElementChild;
                    state.reply = { author: name.textContent, text: quote.textContent.slice(name.textContent.length) };
                    quote.remove();
                }
            }
            renderReply(wrapper);
            wrapper.querySelector('[name="body"]').value = contentFor(wrapper).innerText;
            state.lastSavedHtml = contentFor(wrapper).innerHTML; state.lastSavedReply = JSON.stringify(state.reply);
            showDraftStatus(wrapper, (hasDraftContent(contentFor(wrapper)) || state.reply) ? 'Rascunho recuperado deste navegador' : '');
        } catch { /* The editor remains available when browser storage is unavailable. */ }
    });
}
export function saveRichDrafts(root = document) {
    root.querySelectorAll('[data-rich-editor][data-draft-key]').forEach((wrapper) => saveDraft(wrapper));
}
const upload = async (wrapper, files) => {
    const state = stateFor(wrapper);
    state.uploads++;
    wrapper.querySelector('[data-rich-progress]').hidden = false;
    error(wrapper, '');
    try {
        for (const file of files) {
            if (file.size > 10 * 1024 * 1024) throw new Error('Cada arquivo deve ter até 10 MB.');
            const data = new FormData(); data.append('file', file); data.append('source', 'comment');
            const result = await postForm(wrapper.dataset.uploadUrl, data);
            if (!wrapper.isConnected) return;
            const attachment = result.file;
            let element;
            if (attachment.mime.startsWith('image/')) {
                element = document.createElement('img'); element.src = `${attachment.url}?preview=1`; element.alt = attachment.name;
            } else {
                element = document.createElement('a'); element.href = attachment.url; element.textContent = attachment.name;
            }
            element.dataset.fileId = attachment.id;
            insert(wrapper, `${element.outerHTML}<p><br></p>`);
        }
    } catch (failure) { if (wrapper.isConnected) error(wrapper, failure.message); }
    finally { state.uploads--; wrapper.querySelector('[data-rich-progress]').hidden = !state.uploads; }
};
export function readRichComment(form) {
    const wrapper = form.querySelector('[data-rich-editor]');
    if (!wrapper) return { body: form.elements.body.value };
    if (stateFor(wrapper).uploads) throw new Error('Aguarde o envio das imagens e arquivos.');
    const content = contentFor(wrapper);
    const body = content.innerText.trim();
    if (!body && !content.querySelector('[data-file-id]')) throw new Error('Escreva um comentário ou adicione uma imagem.');
    if (body.length > 5000) throw new Error('O comentário pode ter até 5.000 caracteres.');
    saveDraft(wrapper, true);
    const reply = stateFor(wrapper).reply;
    const payload = { body: reply ? `${reply.author}\n${reply.text}\n${body}` : body, rich_body: replyHtml(reply) + content.innerHTML };
    if (payload.body.length > 5000) throw new Error('O comentário com a resposta pode ter até 5.000 caracteres.');
    Object.defineProperty(payload, 'draftSnapshot', { value: draftSnapshot(wrapper) });
    return payload;
}
export function resetRichComment(form, sent = null) {
    const wrapper = form.querySelector('[data-rich-editor]');
    if (!wrapper) { form.reset(); return; }
    const key = wrapper.dataset.draftKey;
    if (key) {
        try {
            const draft = JSON.parse(localStorage.getItem(key) || 'null');
            if (!sent || JSON.stringify({ html: draft?.html, reply: draft?.reply ?? null }) === sent.draftSnapshot) localStorage.removeItem(key);
        } catch { /* Clearing the visible editor does not depend on browser storage. */ }
    }
    const editors = new Set([wrapper]);
    if (key) document.querySelectorAll('[data-rich-editor][data-draft-key]').forEach((editor) => { if (editor.dataset.draftKey === key) editors.add(editor); });
    editors.forEach((editor) => {
        if (sent && draftSnapshot(editor) !== sent.draftSnapshot) return;
        contentFor(editor).replaceChildren(); editor.querySelector('[name="body"]').value = '';
        hideMentions(editor);
        stateFor(editor).range = null; stateFor(editor).lastSavedHtml = ''; stateFor(editor).reply = null; stateFor(editor).lastSavedReply = 'null'; renderReply(editor);
        showDraftStatus(editor, '');
    });
}
export function replyToComment(form, author, text) {
    const wrapper = form.querySelector('[data-rich-editor]');
    stateFor(wrapper).reply = { author, text };
    renderReply(wrapper); saveDraft(wrapper, true); restore(wrapper);
}
export function focusRichComment(form) { form.querySelector('[data-rich-content], textarea')?.focus(); }
export function setupRichComments() {
    window.addEventListener('pagehide', () => saveRichDrafts());
    window.addEventListener('beforeunload', () => saveRichDrafts());
    document.addEventListener('visibilitychange', () => { if (document.hidden) saveRichDrafts(); });
    document.addEventListener('selectionchange', () => {
        const wrapper = document.activeElement?.closest('[data-rich-editor]');
        if (!wrapper) return;
        remember(wrapper);
        wrapper.querySelectorAll('[data-rich-command]').forEach((button) => {
            const command = button.dataset.richCommand;
            if (command !== 'formatBlock') button.setAttribute('aria-pressed', String(document.queryCommandState(command)));
        });
    });
    document.addEventListener('input', (event) => {
        const content = event.target.closest('[data-rich-content]');
        if (content) { const wrapper = content.closest('[data-rich-editor]'); sync(wrapper); if (!event.isComposing) suggestMentions(wrapper); }
    });
    document.addEventListener('pointerdown', (event) => {
        if (event.target.closest('[data-rich-toolbar] button, [data-rich-mention]')) event.preventDefault();
        document.querySelectorAll('[data-rich-editor]').forEach((wrapper) => { if (!wrapper.contains(event.target)) hideMentions(wrapper); });
    });
    document.addEventListener('click', (event) => {
        const wrapper = event.target.closest('[data-rich-editor]');
        if (!wrapper) return;
        const mention = event.target.closest('[data-rich-mention]');
        if (mention) { selectMention(wrapper, Number(mention.dataset.richMention)); return; }
        if (event.target.closest('[data-rich-discard]')) {
            const form = wrapper.closest('form');
            const original = stateFor(wrapper).originalHtml;
            resetRichComment(form);
            if (form.matches('[data-quadro-comment-edit-form]')) {
                contentFor(wrapper).innerHTML = original; wrapper.querySelector('[name="body"]').value = contentFor(wrapper).innerText;
                stateFor(wrapper).lastSavedHtml = original; form.hidden = true;
            } else contentFor(wrapper).focus();
            return;
        }
        if (event.target.closest('[data-rich-reply-cancel]')) {
            stateFor(wrapper).reply = null; renderReply(wrapper); saveDraft(wrapper, true); restore(wrapper); return;
        }
        if (event.target.closest('[data-rich-toolbar]')) hideMentions(wrapper);
        const command = event.target.closest('[data-rich-command]');
        if (command) { restore(wrapper); document.execCommand(command.dataset.richCommand, false, command.dataset.value || null); sync(wrapper); return; }
        if (event.target.closest('[data-rich-link]')) {
            const url = window.prompt('Endereço do link (https://…)');
            if (url && /^https?:\/\/[^\s]+$/i.test(url)) { restore(wrapper); document.execCommand('createLink', false, url); sync(wrapper); }
            return;
        }
        const image = event.target.closest('[data-rich-image]');
        if (image || event.target.closest('[data-rich-attach]')) {
            remember(wrapper);
            const input = wrapper.querySelector('[data-rich-file]');
            input.accept = image ? 'image/jpeg,image/png,image/webp,image/gif' : 'image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.zip';
            input.multiple = true; input.click();
        }
    });
    document.addEventListener('keydown', (event) => {
        const wrapper = event.target.closest('[data-rich-editor]');
        if (!wrapper || event.isComposing) return;
        const mention = stateFor(wrapper).mention;
        if (!mention) return;
        if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
            event.preventDefault(); mention.index = (mention.index + (event.key === 'ArrowDown' ? 1 : -1) + mention.people.length) % mention.people.length;
            wrapper.querySelectorAll('[data-rich-mention]').forEach((button, index) => { button.setAttribute('aria-selected', String(index === mention.index)); if (index === mention.index) button.scrollIntoView({ block: 'nearest' }); });
        } else if (['Enter', 'Tab'].includes(event.key)) { event.preventDefault(); selectMention(wrapper, mention.people[mention.index].id); }
        else if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); hideMentions(wrapper); }
    });
    document.addEventListener('change', (event) => {
        const wrapper = event.target.closest('[data-rich-editor]');
        if (!wrapper) return;
        if (event.target.matches('[data-rich-block]')) { restore(wrapper); document.execCommand('formatBlock', false, event.target.value); sync(wrapper); }
        if (event.target.matches('[data-rich-file]') && event.target.files.length) { upload(wrapper, [...event.target.files]); event.target.value = ''; }
    });
    document.addEventListener('paste', (event) => {
        const content = event.target.closest('[data-rich-content]');
        if (!content) return;
        event.preventDefault();
        const wrapper = content.closest('[data-rich-editor]'); remember(wrapper);
        const images = [...event.clipboardData.items].filter((item) => item.kind === 'file' && item.type.startsWith('image/')).map((item) => item.getAsFile()).filter(Boolean);
        if (images.length) { upload(wrapper, images); return; }
        const html = event.clipboardData.getData('text/html');
        if (html) insert(wrapper, cleanPaste(html));
        else { restore(wrapper); document.execCommand('insertText', false, event.clipboardData.getData('text/plain')); sync(wrapper); }
    });
    document.addEventListener('drop', (event) => {
        const content = event.target.closest('[data-rich-content]');
        if (!content) return;
        event.preventDefault();
        if (event.dataTransfer.files.length) { const wrapper = content.closest('[data-rich-editor]'); remember(wrapper); upload(wrapper, [...event.dataTransfer.files]); }
    });
}
