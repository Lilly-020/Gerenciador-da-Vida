import { htmlToElement, postJson, postForm } from './board';
import { confirmDialog } from './dialog';
import { setupRichComments, readRichComment, richCommentMatches, resetRichComment, focusRichComment, replyToComment, restoreRichDrafts, saveRichDrafts } from './quadro-editor';

const normalize = (value) => (value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
const get = (url) => postJson(url, 'GET');
const fields = (form) => {
    const data = Object.fromEntries(new FormData(form));
    if (form.matches('[data-quadro-card-form]')) {
        data.assignee_ids = [...form.querySelectorAll('[name="assignee_ids[]"]:checked')].map((input) => Number(input.value));
        delete data['assignee_ids[]'];
        if (form.querySelector('[data-quadro-checklists]')) {
            data.checklists = [...form.querySelectorAll('[data-quadro-checklist-section]')].map((section) => ({ id: section.dataset.id, title: section.querySelector('[data-quadro-checklist-title]').value, items: [...section.querySelectorAll('[data-quadro-checklist-item]')].map((item) => ({ text: item.querySelector('[data-quadro-checklist-text]').value, done: item.querySelector('[data-quadro-checklist-done]').checked })) }));
        }
        if (form.querySelector('[data-quadro-label-list]')) { data.labels = [...form.querySelectorAll('[name="labels[]"]:checked')].map((input) => input.value); delete data['labels[]']; }
        if (form.querySelector('[data-quadro-due-enabled]')) {
            const dueEnabled = form.querySelector('[data-quadro-due-enabled]').checked;
            const date = form.querySelector('[data-quadro-due-date]').value;
            const time = form.querySelector('[data-quadro-due-time]').value;
            data.due_at = dueEnabled && date && time ? `${date}T${time}` : null;
            data.starts_on = form.querySelector('[data-quadro-start-enabled]').checked ? form.querySelector('[data-quadro-start-date]').value : null;
            const reminder = form.querySelector('[data-quadro-reminder]').value;
            data.reminder_minutes = dueEnabled && reminder !== '' ? Number(reminder) : null;
        }
    }
    return data;
};

export function setupQuadros() {
    const index = document.querySelector('[data-quadros-index]');
    const page = document.querySelector('[data-quadro-page]');
    if (!index && !page) return;
    if (page) setupRichComments();
    let filePanel = null, fileAnchor = null;
    const filePreview = document.getElementById('quadro-file-preview');
    const boardDialog = document.getElementById('quadro-board-dialog');
    const listDialog = document.getElementById('quadro-list-dialog');
    const cardDialog = document.getElementById('quadro-card-dialog');
    const membersDialog = document.getElementById('quadro-members-dialog');
    const filterDialog = document.getElementById('quadro-filter-dialog');
    const contextMenu = document.getElementById('quadro-card-menu');
    const busy = new WeakSet();
    const armed = new WeakMap();
    const listFilters = new Map();
    const expandedComments = new Set();
    let activeList = null;
    let insertBeforeListId = null;
    let listComposerTrigger = null;
    let filterListId = null;
    let filterAnchor = null;
    let listAnchor = null;
    let filterSort = 'manual';
    let cardPanel = null;
    let cardPanelAnchor = null;
    let contextCard = null;
    let contextAnchor = null;
    let copiedCard = null;
    try { copiedCard = JSON.parse(localStorage.getItem('quadro-copied-card') || 'null'); } catch {}
    let membersTab = 'accepted';
    let view = 'kanban';
    let reloadVersion = 0;
    let cardVersion = 0;
    let commentRevision = '';
    let commentSequence = 0;
    let commentLoading = false;
    let commentSaving = false;
    let drag = null;
    let touchPending = null;
    let touchDrag = null;
    let lastDrop = 0;
    let coverObjectUrl = null;
    const boardUrl = page?.dataset.url;
    let tableSort = { key: null, direction: 'asc' };
    try {
        const saved = JSON.parse(localStorage.getItem(`quadro-table-sort:${boardUrl}`) || 'null');
        if (saved && ['title', 'list', 'status', 'labels', 'assignees', 'due', 'response'].includes(saved.key)) tableSort = { key: saved.key, direction: saved.direction === 'desc' ? 'desc' : 'asc' };
    } catch {}
    const syncViewport = () => {
        const viewport = window.visualViewport;
        const height = viewport?.height || window.innerHeight;
        document.documentElement.style.setProperty('--quadro-viewport-height', `${height}px`);
        document.documentElement.style.setProperty('--quadro-viewport-top', `${viewport?.offsetTop || 0}px`);
        document.documentElement.toggleAttribute('data-quadro-small-viewport', height < 500);
    };
    syncViewport();
    window.addEventListener('resize', syncViewport);
    window.visualViewport?.addEventListener('resize', syncViewport);
    window.visualViewport?.addEventListener('scroll', syncViewport);
    const prepareTouchSources = () => {
        if (!page) return;
        const touch = window.matchMedia('(pointer: coarse)').matches;
        page.querySelectorAll('[data-quadro-card], [data-quadro-list-handle]').forEach((element) => { element.draggable = !touch && page.dataset.editable === '1'; });
    };
    try { view = localStorage.getItem(`quadro-view:${boardUrl}`) || 'kanban'; } catch {}
    const error = (container, message) => {
        const activePanel = container?.querySelector('[data-quadro-card-editor]:popover-open');
        const output = activePanel?.querySelector('[data-quadro-error]') || container?.querySelector(':scope > [data-quadro-error]') || container?.querySelector('[data-quadro-error]') || document.querySelector('[data-quadro-page-error]');
        if (output) { output.textContent = message; output.hidden = !message; }
    };
    const confirm = (button) => {
        if (armed.has(button)) return true;
        const original = button.innerHTML;
        armed.set(button, original);
        button.textContent = 'Confirmar exclusão';
        setTimeout(() => { if (armed.has(button)) { button.innerHTML = original; armed.delete(button); } }, 5000);
        return false;
    };
    const show = (dialog) => { if (dialog && !dialog.open) dialog.showModal(); };
    const setView = () => {
        if (!page) return;
        page.querySelector('[data-quadro-kanban]').hidden = view !== 'kanban';
        page.querySelector('[data-quadro-table]').hidden = view !== 'table';
        page.querySelectorAll('[data-quadro-view]').forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.quadroView === view)));
    };
    const updateSortOptions = () => {
        filterDialog.querySelectorAll('[data-quadro-list-sort]').forEach((option) => {
            option.setAttribute('aria-pressed', String(option.dataset.quadroListSort === filterSort));
        });
    };
    const positionPopover = (panel, trigger) => {
        if (!panel?.matches(':popover-open')) return;
        if (!trigger?.isConnected) { panel.hidePopover(); return; }
        const anchor = trigger.getBoundingClientRect();
        const viewport = window.visualViewport;
        const left = viewport?.offsetLeft || 0;
        const top = viewport?.offsetTop || 0;
        const width = viewport?.width || window.innerWidth;
        const height = viewport?.height || window.innerHeight;
        panel.style.maxHeight = `${height - 16}px`;
        panel.style.left = `${Math.max(left + 8, Math.min(anchor.right - panel.offsetWidth, left + width - panel.offsetWidth - 8))}px`;
        panel.style.top = `${Math.max(top + 8, Math.min(anchor.bottom + 10, top + height - panel.offsetHeight - 8))}px`;
    };
    const positionFilter = () => {
        positionPopover(filterDialog, filterAnchor);
        positionPopover(listDialog, listAnchor);
        positionPopover(cardPanel, cardPanelAnchor);
        positionPopover(contextMenu, contextAnchor);
        positionPopover(filePanel, fileAnchor);
    };
    const sortTable = () => {
        if (!page) return;
        page.querySelectorAll('[data-quadro-table-sort]').forEach((button) => {
            const direction = button.dataset.quadroTableSort === tableSort.key ? tableSort.direction : null;
            button.closest('th').setAttribute('aria-sort', direction === 'asc' ? 'ascending' : direction === 'desc' ? 'descending' : 'none');
        });
        if (!tableSort.key) return;
        const tbody = page.querySelector('[data-quadro-table] tbody');
        const rows = [...tbody.querySelectorAll('[data-quadro-table-row]')];
        const numeric = ['status', 'due', 'response'].includes(tableSort.key);
        rows.sort((a, b) => {
            const first = a.getAttribute(`data-sort-${tableSort.key}`) || '';
            const second = b.getAttribute(`data-sort-${tableSort.key}`) || '';
            if (!first && second) return 1;
            if (first && !second) return -1;
            const comparison = numeric ? Number(first) - Number(second) : first.localeCompare(second, 'pt-BR', { sensitivity: 'base', numeric: true });
            return comparison ? comparison * (tableSort.direction === 'desc' ? -1 : 1) : Number(a.dataset.quadroRowId) - Number(b.dataset.quadroRowId);
        });
        rows.forEach((row) => tbody.append(row));
    };
    const filterCards = () => {
        if (!page) return;
        const term = normalize(page.querySelector('[data-quadro-search]').value);
        const status = page.querySelector('[data-quadro-status]').value;
        const statusControl = page.querySelector('.quadro-status-control');
        if (statusControl) {
            statusControl.dataset.active = String(Boolean(status));
            statusControl.title = status ? `Status: ${page.querySelector('[data-quadro-status]').selectedOptions[0].textContent}` : 'Filtrar por status';
        }
        page.querySelectorAll('[data-quadro-list]').forEach((list) => {
            const pref = listFilters.get(list.dataset.quadroList) || { term: '', sort: 'manual' };
            const cards = [...list.querySelectorAll('[data-quadro-card]')];
            cards.forEach((card) => { card.hidden = !normalize(card.dataset.title).includes(term) || !normalize(card.dataset.title).includes(normalize(pref.term)) || Boolean(status && card.dataset.status !== status); });
            const sorted = cards.sort((a, b) => {
                switch (pref.sort) {
                    case 'title': return a.dataset.title.localeCompare(b.dataset.title, 'pt-BR');
                    case 'newest': return b.dataset.created.localeCompare(a.dataset.created);
                    case 'oldest': return a.dataset.created.localeCompare(b.dataset.created);
                    case 'due_newest': return (b.dataset.due || '').localeCompare(a.dataset.due || '');
                    case 'due_oldest': return (a.dataset.due || '9999').localeCompare(b.dataset.due || '9999');
                    default: return Number(a.dataset.position) - Number(b.dataset.position) || Number(a.dataset.quadroCard) - Number(b.dataset.quadroCard);
                }
            });
            sorted.forEach((card) => list.querySelector('[data-quadro-list-cards]').append(card));
            list.querySelector('[data-quadro-list-count]').textContent = cards.filter((card) => !card.hidden).length;
        });
        page.querySelectorAll('[data-quadro-table-row]').forEach((row) => { row.hidden = !normalize(row.dataset.title).includes(term) || Boolean(status && row.dataset.status !== status); });
        sortTable();
    };
    const reload = async () => {
        if (!page) return;
        const version = ++reloadVersion;
        const scroll = page.querySelector('[data-quadro-kanban]')?.scrollLeft || 0;
        const scrollTop = page.querySelector('[data-quadro-kanban]')?.scrollTop || 0;
        const listScroll = new Map([...page.querySelectorAll('[data-quadro-list]')].map((list) => [list.dataset.quadroList, list.querySelector('.quadro-column-scroll').scrollTop]));
        const data = await get(boardUrl);
        if (version !== reloadVersion) return;
        page.querySelector('[data-quadro-content]').innerHTML = data.html;
        prepareTouchSources();
        setView(); filterCards();
        page.querySelector('[data-quadro-kanban]').scrollLeft = scroll;
        page.querySelector('[data-quadro-kanban]').scrollTop = scrollTop;
        page.querySelectorAll('[data-quadro-list]').forEach((list) => { list.querySelector('.quadro-column-scroll').scrollTop = listScroll.get(list.dataset.quadroList) || 0; });
    };
    const openContextMenu = (card, trigger, point = null) => {
        contextCard = card;
        contextAnchor = point ? { isConnected: true, getBoundingClientRect: () => ({ right: point.x, bottom: point.y }) } : trigger;
        contextMenu.querySelector('[data-quadro-context-title]').textContent = card.dataset.title;
        contextMenu.querySelector('[data-quadro-context-actions]').hidden = false;
        const moveForm = contextMenu.querySelector('[data-quadro-context-move]');
        if (moveForm) {
            moveForm.hidden = true;
            const lists = [...page.querySelectorAll('[data-quadro-list]')];
            moveForm.elements.lista_id.replaceChildren(...lists.map((list) => new Option(list.dataset.title, list.dataset.quadroList)));
            moveForm.elements.lista_id.value = card.dataset.list;
        }
        try { copiedCard = JSON.parse(localStorage.getItem('quadro-copied-card') || 'null'); } catch {}
        const paste = contextMenu.querySelector('[data-quadro-context-action="paste"]');
        if (paste) paste.disabled = !copiedCard || copiedCard.board !== boardUrl || !Number.isInteger(copiedCard.id);
        error(contextMenu, ''); contextMenu.showPopover(); positionPopover(contextMenu, contextAnchor);
    };
    const syncCardDates = () => {
        const detail = cardDialog.querySelector('[data-quadro-card-detail]');
        if (!detail?.querySelector('[data-quadro-due-enabled]')) return;
        const startEnabled = detail.querySelector('[data-quadro-start-enabled]').checked;
        const dueEnabled = detail.querySelector('[data-quadro-due-enabled]').checked;
        const start = detail.querySelector('[data-quadro-start-date]');
        start.disabled = !startEnabled; start.required = startEnabled;
        detail.querySelectorAll('[data-quadro-due-date], [data-quadro-due-time]').forEach((input) => { input.disabled = !dueEnabled; input.required = dueEnabled; });
        detail.querySelector('[data-quadro-reminder]').disabled = !dueEnabled;
        detail.querySelector('[data-quadro-due-date]').min = startEnabled ? start.value : '';
    };
    const checklistSaves = new WeakMap();
    const checklistData = (form) => fields(form).checklists;
    const saveChecklists = async (form) => {
        if (!form) return true;
        let state = checklistSaves.get(form);
        if (!state) { state = { saved: null, timer: null, pending: null }; checklistSaves.set(form, state); }
        clearTimeout(state.timer);
        if (state.pending) { await state.pending; return saveChecklists(form); }
        const checklists = checklistData(form);
        const snapshot = JSON.stringify(checklists);
        if (snapshot === state.saved) return true;
        const status = form.querySelector('[data-quadro-checklist-status]');
        const display = (message) => { if (status) { status.hidden = false; status.textContent = message; } };
        if (checklists.some((list) => !list.title.trim() || list.items.some((item) => !item.text.trim()))) {
            display('Preencha o título e o texto dos itens para salvar.'); return false;
        }
        display('Salvando checklist…');
        state.pending = (async () => {
            try {
                await postJson(form.action, 'PATCH', { checklists });
                state.saved = snapshot;
                display('Checklist salvo automaticamente');
                return true;
            } catch (failure) { display(`Não foi possível salvar. ${failure.message}`); return false; }
        })();
        const saved = await state.pending;
        state.pending = null;
        if (saved && JSON.stringify(checklistData(form)) !== snapshot) return saveChecklists(form);
        if (saved) reload().catch(() => {});
        return saved;
    };
    const scheduleChecklistSave = (form) => {
        let state = checklistSaves.get(form);
        if (!state) { state = { saved: null, timer: null, pending: null }; checklistSaves.set(form, state); }
        clearTimeout(state.timer);
        state.timer = setTimeout(() => saveChecklists(form), 400);
        const status = form.querySelector('[data-quadro-checklist-status]');
        if (status) { status.hidden = false; status.textContent = 'Alterações pendentes…'; }
    };
    const updateChecklist = () => {
        cardDialog.querySelectorAll('[data-quadro-checklist-section]').forEach((section) => {
            const items = [...section.querySelectorAll('[data-quadro-checklist-item]')];
            const done = items.filter((item) => item.querySelector('[data-quadro-checklist-done]').checked).length;
            const percent = items.length ? Math.round(done / items.length * 100) : 0;
            section.querySelector('[data-quadro-checklist-count]').textContent = `${done}/${items.length}`;
            section.querySelector('[data-quadro-checklist-percent]').textContent = `${percent}%`;
            section.querySelector('[data-quadro-checklist-progress]').style.width = `${percent}%`;
            items.forEach((item) => {
                const checked = item.querySelector('[data-quadro-checklist-done]').checked;
                item.querySelector('[data-quadro-checklist-text]').classList.toggle('line-through', checked);
                item.querySelector('[data-quadro-checklist-text]').classList.toggle('text-slate-500', checked);
                const label = item.querySelector('[data-quadro-checklist-label]');
                label.textContent = item.querySelector('[data-quadro-checklist-text]').value;
                label.classList.toggle('line-through', checked); label.classList.toggle('text-slate-500', checked);
            });
        });
    };
    const addChecklistItem = (section) => {
        const container = section.querySelector('[data-quadro-checklist-items]');
        const input = section.querySelector('[data-quadro-checklist-new]');
        const texts = input.value.split(';').map((text) => text.trim()).filter(Boolean);
        if (!texts.length) { input.focus(); return; }
        if (container.children.length + texts.length > 200) { error(cardDialog, 'O checklist pode ter até 200 itens.'); return; }
        if (texts.some((text) => text.length > 500)) { error(cardDialog, 'Cada item pode ter até 500 caracteres.'); return; }
        const form = section.closest('form');
        texts.forEach((text) => {
            const item = form.querySelector('[data-quadro-checklist-item-template]').content.firstElementChild.cloneNode(true);
            item.querySelector('[data-quadro-checklist-text]').value = text;
            item.querySelector('[data-quadro-checklist-label]').textContent = text;
            container.append(item);
        });
        input.value = ''; input.focus(); updateChecklist(); saveChecklists(form);
    };
    const updateLabelEditor = (editor) => {
        const color = editor.dataset.color || '#4bce97';
        const preview = editor.querySelector('[data-quadro-label-preview]');
        const brightness = (parseInt(color.slice(1, 3), 16) * 299 + parseInt(color.slice(3, 5), 16) * 587 + parseInt(color.slice(5, 7), 16) * 114) / 1000;
        preview.style.backgroundColor = color;
        preview.style.color = brightness > 160 ? '#0f172a' : '#ffffff';
        preview.textContent = editor.querySelector('[data-quadro-label-name]').value;
        editor.querySelector('[data-quadro-label-custom-color]').value = color;
        editor.querySelectorAll('[data-quadro-label-color]').forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.quadroLabelColor === color)));
    };
    const updateCommentPreviews = () => {
        cardDialog.querySelectorAll('[data-quadro-comment]').forEach((comment) => {
            const content = comment.querySelector('[data-quadro-comment-content]');
            const expanded = expandedComments.has(comment.dataset.quadroComment);
            content.classList.toggle('quadro-comment-collapsed', !expanded);
            const button = comment.querySelector('[data-quadro-comment-expand]');
            button.textContent = expanded ? 'Ler menos' : 'Ler mais';
            button.hidden = !expanded && content.scrollHeight <= content.clientHeight + 2;
            button.setAttribute('aria-expanded', String(expanded));
            content.classList.toggle('quadro-comment-overflow', !button.hidden && !expanded);
        });
    };
    const openCard = async (url) => {
        saveRichDrafts(cardDialog);
        const previousForm = cardDialog.querySelector('[data-quadro-card-form]');
        if (previousForm && checklistSaves.has(previousForm) && !await saveChecklists(previousForm)) return;
        const version = ++cardVersion;
        commentRevision = '';
        cardDialog.querySelector('[data-quadro-card-body]').innerHTML = '<p class="p-6 text-sm text-slate-400">Carregando cartão…</p>';
        show(cardDialog);
        try {
            const data = await get(url);
            if (version !== cardVersion || !cardDialog.open) return;
            cardDialog.querySelector('[data-quadro-card-body]').innerHTML = data.html;
            restoreRichDrafts(cardDialog);
            updateChecklist();
            syncCardDates();
            requestAnimationFrame(updateCommentPreviews);
        } catch (failure) { if (version === cardVersion && cardDialog.open) cardDialog.querySelector('[data-quadro-card-body]').textContent = failure.message; }
    };
    const refreshCard = async () => {
        const detail = cardDialog.querySelector('[data-quadro-card-detail]');
        if (!detail || !cardDialog.open) return;
        const form = detail.querySelector('[data-quadro-card-form]');
        if (form && checklistSaves.has(form) && !await saveChecklists(form)) return;
        const version = cardVersion;
        const body = cardDialog.querySelector('[data-quadro-card-body]');
        const scroll = body.querySelector('.quadro-card-fields').scrollTop;
        const data = await get(detail.dataset.url);
        if (version !== cardVersion || !cardDialog.open) return;
        cardPanel?.hidePopover();
        cardPanel = null;
        const next = htmlToElement(data.html);
        body.querySelector('.quadro-card-main').replaceWith(next.querySelector('.quadro-card-main'));
        body.querySelector('[data-quadro-history]').innerHTML = next.querySelector('[data-quadro-history]').innerHTML;
        body.querySelector('.quadro-card-fields').scrollTop = scroll;
        updateChecklist();
        syncCardDates();
    };
    const updateNotificationCount = (count) => document.dispatchEvent(new CustomEvent('app-notification-count', { detail: count }));
    const applyComments = (data) => {
        const container = cardDialog?.querySelector('[data-quadro-comments]');
        if (!container) return;
        commentRevision = data.revision;
        if (data.unread_count !== undefined) updateNotificationCount(data.unread_count);
        const count = cardDialog.querySelector('[data-quadro-comments-count]');
        if (count && data.count !== undefined) count.textContent = data.count;
        if (data.history) cardDialog.querySelector('[data-quadro-history]').innerHTML = data.history;
        if (data.pinned !== undefined) {
            const pinned = cardDialog.querySelector('[data-quadro-pinned]');
            pinned.innerHTML = data.pinned; pinned.hidden = !data.pinned.trim();
        }
        if (data.files !== undefined) {
            const files = cardDialog.querySelector('[data-quadro-files]');
            if (files) { files.innerHTML = data.files; files.hidden = !data.files.trim(); }
        }
        if (!data.html) return;
        const next = htmlToElement(data.html);
        const focused = container.contains(document.activeElement) ? document.activeElement : null;
        const selection = focused?.tagName === 'TEXTAREA' ? [focused.selectionStart, focused.selectionEnd] : null;
        const browserSelection = window.getSelection();
        const range = focused?.isContentEditable && browserSelection.rangeCount ? browserSelection.getRangeAt(0) : null;
        const richSelection = range ? { start: range.startContainer, startOffset: range.startOffset, end: range.endContainer, endOffset: range.endOffset } : null;
        container.querySelectorAll('[data-quadro-comment]').forEach((comment) => {
            if (!comment.querySelector('[data-quadro-comment-edit-form]:not([hidden])')) return;
            const replacement = next.querySelector(`[data-quadro-comment="${comment.dataset.quadroComment}"]`);
            if (replacement) replacement.replaceWith(comment); else next.append(comment);
        });
        const scroll = container.scrollTop;
        container.replaceChildren(next);
        restoreRichDrafts(container);
        container.scrollTop = scroll;
        if (focused?.isConnected) { focused.focus({ preventScroll: true }); if (selection) focused.setSelectionRange(...selection); }
        if (richSelection?.start.isConnected && richSelection.end.isConnected) {
            const restored = document.createRange();
            restored.setStart(richSelection.start, richSelection.startOffset); restored.setEnd(richSelection.end, richSelection.endOffset);
            browserSelection.removeAllRanges(); browserSelection.addRange(restored);
        }
        requestAnimationFrame(updateCommentPreviews);
    };
    const refreshComments = async () => {
        const detail = cardDialog?.querySelector('[data-quadro-card-detail]');
        if (!cardDialog?.open || !detail || document.hidden || commentLoading || commentSaving || !navigator.onLine) return;
        commentLoading = true;
        const version = cardVersion;
        const sequence = ++commentSequence;
        try {
            const data = await get(`${detail.dataset.commentsUrl}?revision=${encodeURIComponent(commentRevision)}`);
            if (version === cardVersion && sequence === commentSequence && !commentSaving) applyComments(data);
        } catch {} finally { commentLoading = false; }
    };
    const memberPanels = () => {
        membersDialog.querySelectorAll('[data-quadro-member-tab]').forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.quadroMemberTab === membersTab)));
        membersDialog.querySelectorAll('[data-quadro-member-panel]').forEach((panel) => { panel.hidden = panel.dataset.quadroMemberPanel !== membersTab; });
    };
    const applyMembers = (data) => { membersDialog.querySelector('[data-quadro-members-body]').innerHTML = data.html; memberPanels(); };
    const closeCardComposer = (form) => {
        if (busy.has(form)) return;
        form.hidden = true;
        form.reset();
        const trigger = form.closest('[data-quadro-list]').querySelector('[data-quadro-new-card]');
        trigger.hidden = false;
        trigger.focus({ preventScroll: true });
    };
    const openListComposer = (trigger, next = null) => {
        const form = page.querySelector('[data-quadro-inline-list]');
        if (busy.has(form)) return;
        listComposerTrigger = trigger;
        insertBeforeListId = next ? Number(next.dataset.quadroList) : null;
        const addButton = page.querySelector('.quadro-add-list');
        form.parentElement.insertBefore(form, next || addButton);
        form.hidden = false;
        addButton.hidden = !next;
        error(form, '');
        form.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        form.elements.title.focus({ preventScroll: true });
    };
    const closeListComposer = () => {
        const form = page.querySelector('[data-quadro-inline-list]');
        if (busy.has(form)) return;
        form.hidden = true;
        form.reset();
        page.querySelector('.quadro-add-list').hidden = false;
        listComposerTrigger?.focus({ preventScroll: true });
    };
    const openList = (list, trigger) => {
        if (listAnchor === trigger && listDialog.matches(':popover-open')) { listDialog.hidePopover(); return; }
        listAnchor?.setAttribute('aria-expanded', 'false');
        listAnchor = trigger;
        activeList = list;
        const form = listDialog.querySelector('form');
        form.reset(); error(form, '');
        form.elements.title.value = list?.dataset.title || '';
        form.elements.color.value = list?.dataset.color || '#334155';
        listDialog.querySelectorAll('[data-quadro-list-color]').forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.quadroListColor === form.elements.color.value)));
        listDialog.querySelector('[data-quadro-list-delete]').hidden = !list;
        listDialog.querySelector('[data-quadro-list-order-controls]').hidden = !list;
        listDialog.querySelector('h2').textContent = list ? 'Configurar lista' : 'Nova lista';
        listDialog.showPopover();
        listAnchor.setAttribute('aria-expanded', 'true');
        positionPopover(listDialog, listAnchor);
        form.elements.title.focus({ preventScroll: true });
    };

    const openFileMenu = (button) => {
        const panel = button.closest('[data-quadro-file]').querySelector('[data-quadro-file-actions]');
        if (panel.matches(':popover-open')) { panel.hidePopover(); return; }
        filePanel = panel; fileAnchor = button;
        if (!panel.dataset.bound) {
            panel.dataset.bound = '1';
            panel.addEventListener('toggle', () => button.setAttribute('aria-expanded', String(panel.matches(':popover-open'))));
        }
        panel.showPopover(); positionPopover(panel, button);
    };
    const previewFile = (file) => {
        const image = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'].includes(file.dataset.mime);
        const video = ['video/mp4', 'video/webm', 'video/quicktime', 'application/mp4'].includes(file.dataset.mime);
        if (!image && !video) return;
        if (filePanel?.isConnected && filePanel.matches(':popover-open')) filePanel.hidePopover();
        filePreview.querySelector('h2').textContent = file.dataset.name;
        const media = document.createElement(video ? 'video' : 'img');
        if (video) { media.controls = true; media.preload = 'metadata'; media.playsInline = true; }
        else media.alt = file.dataset.name;
        media.src = `${file.dataset.url}?preview=1`;
        filePreview.querySelector('[data-quadro-file-preview-body]').replaceChildren(media);
        show(filePreview);
    };
    const clearFilePreview = () => {
        filePreview?.querySelector('video')?.pause();
        filePreview?.querySelector('[data-quadro-file-preview-body]').replaceChildren();
    };
    filePreview?.addEventListener('close', clearFilePreview);
    filePreview?.addEventListener('cancel', clearFilePreview);
    document.addEventListener('click', async (event) => {
        const target = event.target;
        const fileMenu = target.closest('[data-quadro-file-menu]');
        if (fileMenu) { openFileMenu(fileMenu); return; }
        const fileButton = target.closest('[data-quadro-file-preview]');
        if (fileButton) { previewFile(fileButton.closest('[data-quadro-file]')); return; }
        const commentFile = target.closest('[data-file-id]');
        if (commentFile && target.closest('[data-quadro-comment-content]')) {
            event.preventDefault();
            const file = cardDialog.querySelector(`[data-quadro-file="${CSS.escape(commentFile.dataset.fileId)}"]`);
            if (file) {
                if (file.querySelector('[data-quadro-file-preview]')) previewFile(file);
                else openFileMenu(file.querySelector('[data-quadro-file-menu]'));
            }
            return;
        }
        if (target.closest('[data-quadro-card-handle], [data-quadro-list-handle]')) return;
        if (target.closest('[data-quadro-close]')) {
            const dialog = target.closest('dialog');
            const form = dialog === cardDialog ? dialog.querySelector('[data-quadro-card-form]') : null;
            if (form && checklistSaves.has(form) && !await saveChecklists(form)) return;
            if (dialog === filePreview) clearFilePreview();
            dialog?.close(); return;
        }
        if (target.closest('[data-quadro-create], [data-quadro-config]')) { error(boardDialog, ''); show(boardDialog); return; }
        try {
            const editTile = target.closest('[data-quadro-tile-edit]');
            if (editTile) {
                editTile.disabled = true;
                try {
                    const data = await get(editTile.dataset.url);
                    document.getElementById('quadro-edit-dialog')?.remove();
                    const dialog = htmlToElement(data.html);
                    dialog.querySelector('form').dataset.quadroReturnIndex = '1';
                    document.body.append(dialog);
                    show(dialog);
                } finally { editTile.disabled = false; }
                return;
            }
            const deleteTile = target.closest('[data-quadro-tile-delete]');
            if (deleteTile) {
                if (!await confirmDialog('Excluir este quadro, suas listas e cartões?', { confirmLabel: 'Excluir quadro', danger: true })) return;
                deleteTile.disabled = true;
                const data = await postJson(deleteTile.dataset.url, 'DELETE');
                location.assign(data.url);
                return;
            }
            const invite = target.closest('[data-quadro-invite]');
            if (invite) { invite.disabled = true; const data = await postJson(invite.dataset.url, 'POST', { accept: invite.dataset.quadroInvite === '1' }); location.assign(data.url); return; }
            const removeBoard = target.closest('[data-quadro-delete]');
            if (removeBoard && confirm(removeBoard)) { const data = await postJson(removeBoard.dataset.url, 'DELETE'); location.assign(data.url); return; }
            if (!page) return;
            const sortButton = target.closest('[data-quadro-table-sort]');
            if (sortButton) {
                tableSort = { key: sortButton.dataset.quadroTableSort, direction: tableSort.key === sortButton.dataset.quadroTableSort && tableSort.direction === 'asc' ? 'desc' : 'asc' };
                try { localStorage.setItem(`quadro-table-sort:${boardUrl}`, JSON.stringify(tableSort)); } catch {}
                sortTable(); return;
            }
            const menuButton = target.closest('[data-quadro-card-menu]');
            if (menuButton) { openContextMenu(menuButton.closest('[data-quadro-card]'), menuButton); return; }
            const tileComplete = target.closest('[data-quadro-tile-complete]');
            if (tileComplete) {
                if (tileComplete.disabled) return;
                const tile = tileComplete.closest('[data-quadro-card]');
                tileComplete.disabled = true;
                try { await postJson(tile.dataset.url, 'PATCH', { status: tile.dataset.status === 'done' ? 'todo' : 'done' }); await reload(); }
                finally { tileComplete.disabled = false; }
                return;
            }
            if (target.closest('[data-quadro-context-close]')) { contextMenu.hidePopover(); return; }
            const contextAction = target.closest('[data-quadro-context-action]');
            if (contextAction && contextCard) {
                const action = contextAction.dataset.quadroContextAction;
                if (action === 'open') { contextMenu.hidePopover(); await openCard(contextCard.dataset.url); return; }
                if (action === 'copy') {
                    copiedCard = { board: boardUrl, id: Number(contextCard.dataset.quadroCard) };
                    try { localStorage.setItem('quadro-copied-card', JSON.stringify(copiedCard)); } catch {}
                    try { await navigator.clipboard?.writeText(`${location.origin}${location.pathname}#card=${copiedCard.id}`); } catch {}
                    contextMenu.hidePopover(); return;
                }
                if (action === 'move') { contextMenu.querySelector('[data-quadro-context-actions]').hidden = true; contextMenu.querySelector('[data-quadro-context-move]').hidden = false; positionPopover(contextMenu, contextAnchor); return; }
                if (action === 'duplicate' || action === 'paste') {
                    if (contextAction.disabled) return;
                    contextAction.disabled = true;
                    try {
                        await postJson(action === 'paste' ? `${boardUrl}/cartoes/${copiedCard.id}/copiar` : contextCard.dataset.copyUrl, 'POST', { lista_id: Number(contextCard.dataset.list) });
                        contextMenu.hidePopover(); await reload();
                    } finally { contextAction.disabled = false; }
                    return;
                }
                if (action === 'delete' && confirm(contextAction)) { await postJson(contextCard.dataset.deleteUrl, 'DELETE'); contextMenu.hidePopover(); await reload(); return; }
            }
            const detail = target.closest('[data-quadro-card-detail]');
            const mobileTab = target.closest('[data-quadro-mobile-tab]');
            if (mobileTab) {
                const pane = mobileTab.dataset.quadroMobileTab;
                detail.dataset.mobilePane = pane;
                detail.querySelectorAll('[data-quadro-mobile-tab]').forEach((tab) => tab.setAttribute('aria-selected', String(tab === mobileTab)));
                if (pane !== 'card') detail.querySelector(`[data-quadro-card-tab="${pane}"]`).click();
                return;
            }
            const cardTab = target.closest('[data-quadro-card-tab]');
            if (cardTab) {
                const history = cardTab.dataset.quadroCardTab === 'history';
                if (window.matchMedia('(max-width: 47.999rem)').matches) {
                    detail.dataset.mobilePane = history ? 'history' : 'comments';
                    detail.querySelectorAll('[data-quadro-mobile-tab]').forEach((tab) => tab.setAttribute('aria-selected', String(tab.dataset.quadroMobileTab === detail.dataset.mobilePane)));
                }
                detail.querySelectorAll('[data-quadro-card-tab]').forEach((tab) => tab.setAttribute('aria-selected', String(tab === cardTab)));
                detail.querySelector('[data-quadro-comments]').hidden = history;
                detail.querySelector('[data-quadro-history]').hidden = !history;
                const composer = detail.querySelector('[data-quadro-comment-create]');
                if (composer) composer.hidden = history;
                return;
            }
            const panelButton = target.closest('[data-quadro-card-panel]');
            if (target.closest('[data-quadro-card-panel-close]')) { target.closest('[popover]').hidePopover(); return; }
            if (panelButton) {
                const panel = detail.querySelector(`[data-quadro-card-editor="${panelButton.dataset.quadroCardPanel}"]`);
                if (panel.matches(':popover-open')) { panel.hidePopover(); return; }
                if (panelButton.dataset.quadroCardPanel === 'tag') {
                    panel.querySelector('[data-quadro-label-browser]').hidden = false;
                    panel.querySelector('[data-quadro-label-editor]').hidden = true;
                }
                cardPanel?.hidePopover();
                cardPanel = panel; cardPanelAnchor = panelButton;
                panel.showPopover(); positionPopover(panel, panelButton);
                panel.querySelector('input:not([type="hidden"]), select')?.focus({ preventScroll: true }); return;
            }
            if (target.closest('[data-quadro-description-edit]')) {
                detail.querySelector('[data-quadro-description-preview]').hidden = true;
                detail.querySelector('[data-quadro-description-editor]').hidden = false;
                detail.querySelector('[name="description"]').focus(); return;
            }
            if (target.closest('[data-quadro-card-dates-clear]')) {
                const form = detail.querySelector('[data-quadro-card-form]');
                form.querySelector('[data-quadro-start-enabled]').checked = false;
                form.querySelector('[data-quadro-due-enabled]').checked = false;
                form.querySelector('[data-quadro-reminder]').value = '';
                syncCardDates(); form.requestSubmit(); return;
            }
            if (target.closest('[data-quadro-checklist-create]')) {
                const title = detail.querySelector('[data-quadro-new-checklist-title]').value.trim();
                if (!title) return;
                const container = detail.querySelector('[data-quadro-checklists]');
                if (container.children.length >= 20) { error(detail, 'O cartão pode ter até 20 checklists.'); return; }
                const section = detail.querySelector('[data-quadro-checklist-template]').content.firstElementChild.cloneNode(true);
                section.dataset.id = crypto.randomUUID(); section.querySelector('[data-quadro-checklist-title]').value = title;
                container.append(section); updateChecklist();
                cardPanel?.hidePopover();
                await saveChecklists(detail.querySelector('[data-quadro-card-form]')); return;
            }
            if (target.closest('[data-quadro-checklist-add]')) { addChecklistItem(target.closest('[data-quadro-checklist-section]')); return; }
            if (target.closest('[data-quadro-checklist-delete]')) { const form = target.closest('form'); target.closest('[data-quadro-checklist-section]').remove(); await saveChecklists(form); return; }
            if (target.closest('[data-quadro-checklist-edit]')) { const input = target.closest('[data-quadro-checklist-item]').querySelector('[data-quadro-checklist-text]'); input.hidden = false; input.previousElementSibling.hidden = true; input.readOnly = false; input.focus(); input.select(); return; }
            const removeItem = target.closest('[data-quadro-checklist-remove]');
            if (removeItem) { const form = removeItem.closest('form'); removeItem.closest('[data-quadro-checklist-item]').remove(); updateChecklist(); await saveChecklists(form); return; }
            if (target.closest('[data-quadro-card-cover-clear]')) {
                const form = detail.querySelector('[data-quadro-card-form]');
                form.elements.cover_color.value = ''; form.elements.cover_file_id.value = ''; form.requestSubmit(); return;
            }
            const coverSwatch = target.closest('[data-quadro-cover-swatch]');
            const coverImage = target.closest('[data-quadro-cover-file]');
            if (coverSwatch || coverImage) {
                const form = detail.querySelector('[data-quadro-card-form]');
                if (busy.has(form)) return;
                form.elements.cover_color.value = coverSwatch?.dataset.quadroCoverSwatch || '';
                form.elements.cover_file_id.value = coverImage?.dataset.quadroCoverFile || '';
                form.requestSubmit(); return;
            }
            if (target.closest('[data-quadro-card-attach]')) { const file = detail.querySelector('[data-quadro-card-file]'); delete file.dataset.useCover; file.accept = 'image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.zip'; file.click(); return; }
            if (target.closest('[data-quadro-card-cover-upload]')) { const file = detail.querySelector('[data-quadro-card-file]'); file.dataset.useCover = '1'; file.accept = 'image/jpeg,image/png,image/webp,image/gif'; file.click(); return; }
            const deleteFile = target.closest('[data-quadro-card-file-delete]');
            if (deleteFile && confirm(deleteFile)) { if (filePanel?.isConnected && filePanel.matches(':popover-open')) filePanel.hidePopover(); await postJson(deleteFile.dataset.url, 'DELETE'); await refreshCard(); return; }
            const labelPanel = target.closest('[data-quadro-card-editor="tag"]');
            const newLabel = target.closest('[data-quadro-label-new]');
            const editLabel = target.closest('[data-quadro-label-edit]');
            if (newLabel || editLabel) {
                const row = editLabel?.closest('[data-quadro-label-row]');
                const editor = labelPanel.querySelector('[data-quadro-label-editor]');
                labelPanel.querySelector('[data-quadro-label-browser]').hidden = true;
                editor.hidden = false; editor.dataset.id = row?.dataset.id || ''; editor.dataset.color = row?.dataset.color || '#4bce97';
                editor.querySelector('[data-quadro-label-name]').value = row?.dataset.name || '';
                editor.querySelector('[data-quadro-label-editor-heading]').textContent = row ? 'Editar etiqueta' : 'Criar etiqueta';
                editor.querySelector('[data-quadro-label-save]').textContent = row ? 'Salvar' : 'Criar';
                labelPanel.querySelector('[data-quadro-label-error]').hidden = true;
                updateLabelEditor(editor); labelPanel.scrollTop = 0; positionPopover(labelPanel, cardPanelAnchor);
                editor.querySelector('[data-quadro-label-name]').focus({ preventScroll: true }); return;
            }
            const colorLabel = target.closest('[data-quadro-label-color]');
            if (colorLabel) { const editor = colorLabel.closest('[data-quadro-label-editor]'); editor.dataset.color = colorLabel.dataset.quadroLabelColor; updateLabelEditor(editor); return; }
            if (target.closest('[data-quadro-label-cancel]')) {
                labelPanel.querySelector('[data-quadro-label-editor]').hidden = true;
                labelPanel.querySelector('[data-quadro-label-browser]').hidden = false;
                labelPanel.querySelector('[data-quadro-label-error]').hidden = true;
                labelPanel.scrollTop = 0; positionPopover(labelPanel, cardPanelAnchor); return;
            }
            const saveLabel = target.closest('[data-quadro-label-save]');
            const deleteLabel = target.closest('[data-quadro-label-delete]');
            if (saveLabel || deleteLabel) {
                if (deleteLabel && !confirm(deleteLabel)) return;
                const editor = labelPanel.querySelector('[data-quadro-label-editor]');
                const id = deleteLabel?.closest('[data-quadro-label-row]').dataset.id || editor.dataset.id;
                const name = editor.querySelector('[data-quadro-label-name]').value.trim();
                if (saveLabel && !name) { editor.querySelector('[data-quadro-label-name]').focus(); return; }
                const output = labelPanel.querySelector('[data-quadro-label-error]'); output.hidden = true;
                const button = saveLabel || deleteLabel; button.disabled = true;
                try {
                    const form = detail.querySelector('[data-quadro-card-form]');
                    await postJson(form.action, 'PATCH', fields(form));
                    await postJson(`${boardUrl}/etiquetas${id ? `/${id}` : ''}`, deleteLabel ? 'DELETE' : (id ? 'PATCH' : 'POST'), saveLabel ? { name, color: editor.dataset.color } : {});
                    await refreshCard(); await reload();
                    cardDialog.querySelector('[data-quadro-card-panel="tag"]').click();
                } catch (failure) { output.textContent = failure.message; output.hidden = false; }
                finally { button.disabled = false; }
                return;
            }
            const viewButton = target.closest('[data-quadro-view]');
            if (viewButton) { view = viewButton.dataset.quadroView; try { localStorage.setItem(`quadro-view:${boardUrl}`, view); } catch {} setView(); return; }
            const card = target.closest('[data-quadro-card-open]');
            if (card && Date.now() - lastDrop > 300) { await openCard(card.dataset.url); return; }
            const newCard = target.closest('[data-quadro-new-card]');
            if (newCard) {
                const form = newCard.closest('[data-quadro-list]').querySelector('[data-quadro-inline-card]');
                newCard.hidden = true;
                form.hidden = false;
                error(form, '');
                form.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                form.elements.title.focus({ preventScroll: true });
                return;
            }
            const cancelCard = target.closest('[data-quadro-card-cancel]');
            if (cancelCard) { closeCardComposer(cancelCard.closest('form')); return; }
            const insert = target.closest('[data-quadro-insert-list]');
            if (insert) {
                const list = insert.closest('[data-quadro-list]');
                const lists = [...page.querySelectorAll('[data-quadro-list]')];
                const next = lists[lists.indexOf(list) + 1];
                openListComposer(insert, next);
                return;
            }
            if (target.closest('[data-quadro-list-cancel]')) { closeListComposer(); return; }
            const newList = target.closest('[data-quadro-new-list]');
            if (newList) {
                if (view !== 'kanban') { view = 'kanban'; setView(); }
                openListComposer(newList); return;
            }
            const config = target.closest('[data-quadro-list-config]');
            if (config) { openList(config.closest('[data-quadro-list]'), config); return; }
            if (target.closest('[data-quadro-list-close]')) { listDialog.hidePopover(); listAnchor?.focus({ preventScroll: true }); return; }
            const listFilter = target.closest('[data-quadro-list-filter]');
            if (listFilter) {
                if (filterAnchor === listFilter && filterDialog.matches(':popover-open')) { filterDialog.hidePopover(); return; }
                filterAnchor?.setAttribute('aria-expanded', 'false');
                filterAnchor = listFilter;
                filterListId = listFilter.closest('[data-quadro-list]').dataset.quadroList;
                const pref = listFilters.get(filterListId) || { term: '', sort: 'manual' };
                filterDialog.querySelector('[data-quadro-list-search]').value = pref.term;
                filterSort = pref.sort;
                updateSortOptions();
                filterDialog.showPopover();
                filterAnchor.setAttribute('aria-expanded', 'true');
                positionFilter();
                filterDialog.querySelector('[data-quadro-list-search]').focus({ preventScroll: true });
                return;
            }
            if (target.closest('[data-quadro-filter-close]')) { filterDialog.hidePopover(); filterAnchor?.focus({ preventScroll: true }); return; }
            const sortOption = target.closest('[data-quadro-list-sort]');
            if (sortOption) { filterSort = sortOption.dataset.quadroListSort; updateSortOptions(); saveListFilter(); return; }
            if (target.closest('[data-quadro-list-clear]')) { listFilters.delete(filterListId); filterDialog.querySelector('[data-quadro-list-search]').value = ''; filterSort = 'manual'; updateSortOptions(); filterCards(); return; }
            const swatch = target.closest('[data-quadro-list-color]');
            if (swatch) { listDialog.querySelector('[name="color"]').value = swatch.dataset.quadroListColor; listDialog.querySelectorAll('[data-quadro-list-color]').forEach((button) => button.setAttribute('aria-pressed', String(button === swatch))); return; }
            const shift = target.closest('[data-quadro-list-shift]');
            if (shift && activeList) {
                const ids = [...page.querySelectorAll('[data-quadro-list]')].map((list) => Number(list.dataset.quadroList));
                const from = ids.indexOf(Number(activeList.dataset.quadroList)); const to = from + Number(shift.dataset.quadroListShift);
                if (to < 0 || to >= ids.length) return;
                [ids[from], ids[to]] = [ids[to], ids[from]];
                await postJson(`${boardUrl}/listas/ordem`, 'PUT', { ids }); listDialog.hidePopover(); await reload(); return;
            }
            const removeList = target.closest('[data-quadro-list-delete]');
            if (removeList && activeList && confirm(removeList)) { await postJson(activeList.dataset.url, 'DELETE'); listDialog.hidePopover(); await reload(); return; }
            const removeCard = target.closest('[data-quadro-card-delete]');
            if (removeCard && confirm(removeCard)) { await postJson(removeCard.dataset.url, 'DELETE'); cardDialog.close(); await reload(); return; }
            const share = target.closest('[data-quadro-members]');
            if (share) { membersTab = 'accepted'; show(membersDialog); membersDialog.querySelector('[data-quadro-members-body]').innerHTML = '<p class="p-6 text-slate-400">Carregando membros…</p>'; applyMembers(await get(share.dataset.url)); return; }
            const tab = target.closest('[data-quadro-member-tab]');
            if (tab) { membersTab = tab.dataset.quadroMemberTab; memberPanels(); return; }
            const removeMember = target.closest('[data-quadro-member-delete]');
            if (removeMember && confirm(removeMember)) { applyMembers(await postJson(removeMember.dataset.url, 'DELETE')); return; }
            const editComment = target.closest('[data-quadro-comment-edit]');
            if (editComment) { const form = editComment.closest('[data-quadro-comment]').querySelector('form'); form.hidden = false; focusRichComment(form); return; }
            const expandComment = target.closest('[data-quadro-comment-expand]');
            if (expandComment) { const id = expandComment.closest('[data-quadro-comment]').dataset.quadroComment; if (expandedComments.has(id)) expandedComments.delete(id); else expandedComments.add(id); updateCommentPreviews(); return; }
            const pinComment = target.closest('[data-quadro-comment-pin]');
            const reactComment = target.closest('[data-quadro-comment-react]');
            if (pinComment || reactComment) {
                commentSaving = true; commentSequence++;
                try { const button = pinComment || reactComment; applyComments(await postJson(button.dataset.url, pinComment ? 'PATCH' : 'POST', pinComment ? { pinned: pinComment.dataset.pinned !== '1' } : {})); }
                finally { commentSaving = false; }
                return;
            }
            const reply = target.closest('[data-quadro-comment-reply]');
            if (reply) { const comment = reply.closest('[data-quadro-comment]'); replyToComment(detail.querySelector('[data-quadro-comment-create]'), comment.dataset.author, comment.dataset.text); return; }
            const jump = target.closest('[data-quadro-comment-jump]');
            if (jump) { detail.querySelector('[data-quadro-card-tab="comments"]').click(); const comment = detail.querySelector(`[data-quadro-comment="${jump.dataset.quadroCommentJump}"]`); comment?.scrollIntoView({ block: 'center' }); return; }
            if (target.closest('[data-quadro-comment-cancel]')) { target.closest('form').hidden = true; commentRevision = ''; await refreshComments(); return; }
            const deleteComment = target.closest('[data-quadro-comment-delete]');
            if (deleteComment && confirm(deleteComment)) { commentSaving = true; commentSequence++; try { applyComments(await postJson(deleteComment.dataset.url, 'DELETE')); await reload(); } finally { commentSaving = false; } }
        } catch (failure) { error(target.closest('[popover]') || target.closest('form') || target.closest('dialog') || page || index, failure.message); target.disabled = false; }
    });
    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!form.matches('[data-quadro-context-move], [data-quadro-board-form], [data-quadro-inline-card], [data-quadro-inline-list], [data-quadro-list-form], [data-quadro-card-form], [data-quadro-member-form], [data-quadro-comment-create], [data-quadro-comment-edit-form]')) return;
        event.preventDefault();
        if (!form.reportValidity() || busy.has(form)) return;
        busy.add(form); error(form, '');
        const button = event.submitter || form.querySelector('[type="submit"]'); button.disabled = true;
        const commentForm = form.matches('[data-quadro-comment-create], [data-quadro-comment-edit-form]');
        const commentTarget = commentForm ? form.closest('[data-quadro-card-detail]')?.dataset.url : null;
        if (commentForm) { commentSaving = true; commentSequence++; }
        try {
            if (form.matches('[data-quadro-context-move]')) { await postJson(contextCard.dataset.moveUrl, 'PATCH', { lista_id: Number(form.elements.lista_id.value) }); contextMenu.hidePopover(); await reload(); }
            else if (form.matches('[data-quadro-board-form]')) { const data = await postForm(form.action, new FormData(form)); location.assign(form.dataset.quadroReturnIndex ? index.dataset.url : data.url); }
            else if (form.matches('[data-quadro-inline-card]')) {
                await postJson(`${boardUrl}/cartoes`, 'POST', { title: form.elements.title.value, lista_id: Number(form.dataset.list) });
                form.reset();
                form.hidden = true;
                form.closest('[data-quadro-list]').querySelector('[data-quadro-new-card]').hidden = false;
                await reload();
            }
            else if (form.matches('[data-quadro-inline-list]')) {
                await postJson(`${boardUrl}/listas`, 'POST', { title: form.elements.title.value, color: '#334155', before_id: insertBeforeListId });
                form.hidden = true;
                page.querySelector('.quadro-add-list').hidden = false;
                await reload();
            }
            else if (form.matches('[data-quadro-list-form]')) { await postJson(activeList?.dataset.url || `${boardUrl}/listas`, activeList ? 'PATCH' : 'POST', { ...fields(form), ...(activeList ? {} : { before_id: insertBeforeListId }) }); listDialog.hidePopover(); await reload(); }
            else if (form.matches('[data-quadro-card-form]')) {
                if (checklistSaves.has(form) && !await saveChecklists(form)) return;
                await postJson(form.action, form.dataset.method, fields(form));
                if (form.dataset.method === 'PATCH') await refreshCard(); else cardDialog.close();
                await reload();
            }
            else if (form.matches('[data-quadro-member-form]')) { applyMembers(await postJson(form.action, 'POST', fields(form))); }
            else {
                const payload = readRichComment(form);
                const data = await postJson(form.action, form.matches('[data-quadro-comment-edit-form]') ? 'PATCH' : 'POST', payload);
                if (richCommentMatches(form, payload)) {
                    resetRichComment(form, payload);
                    if (form.matches('[data-quadro-comment-edit-form]')) form.hidden = true;
                }
                if (cardDialog.open && cardDialog.querySelector('[data-quadro-card-detail]')?.dataset.url === commentTarget) applyComments(data);
                await reload();
            }
        } catch (failure) { error(form, failure.message); }
        finally { busy.delete(form); button.disabled = false; if (commentForm) commentSaving = false; }
    });
    document.addEventListener('change', async (event) => {
        const target = event.target;
        const detail = target.closest('[data-quadro-card-detail]');
        if (target.matches('[data-quadro-label-custom-color]')) { const editor = target.closest('[data-quadro-label-editor]'); editor.dataset.color = target.value; updateLabelEditor(editor); return; }
        if (target.matches('[data-quadro-start-enabled], [data-quadro-due-enabled], [data-quadro-start-date]')) { syncCardDates(); return; }
        if (target.matches('[data-quadro-checklist-done]')) { updateChecklist(); await saveChecklists(target.closest('form')); return; }
        if (target.matches('[data-quadro-card-complete]')) {
            const form = detail.querySelector('[data-quadro-card-form]');
            form.elements.status.value = target.checked ? 'done' : 'todo';
            form.requestSubmit(); return;
        }
        if (target.matches('[data-quadro-card-cover-color]')) {
            const form = detail.querySelector('[data-quadro-card-form]');
            if (busy.has(form)) return;
            form.elements.cover_color.value = target.value; form.elements.cover_file_id.value = '';
            form.requestSubmit(); return;
        }
        if (target.matches('[data-quadro-card-file]')) {
            const file = target.files[0];
            if (!file) return;
            const isCover = target.dataset.useCover === '1';
            const coverError = detail.querySelector('[data-quadro-cover-error]');
            const uploadError = (message) => { if (isCover && coverError) { coverError.textContent = message; coverError.hidden = !message; } else error(detail, message); };
            const maxSize = isCover ? 8 : 10;
            if (file.size > maxSize * 1024 * 1024) { uploadError(`O arquivo deve ter até ${maxSize} MB.`); target.value = ''; return; }
            const data = new FormData(); data.append('file', file);
            if (isCover) data.append('cover', '1');
            uploadError('');
            target.disabled = true;
            try {
                await postForm(target.dataset.url, data);
                await refreshCard(); await reload();
            }
            catch (failure) { uploadError(failure.message); }
            finally { target.disabled = false; target.value = ''; delete target.dataset.useCover; }
            return;
        }
        if (target.matches('[data-quadro-member-role]')) {
            try { applyMembers(await postJson(target.dataset.url, 'PATCH', { role: target.value })); } catch (failure) { error(membersDialog, failure.message); }
        }
        if (target.matches('[name="cover"], [name="cover_image"]') && target.closest('[data-quadro-board-form]')) {
            const form = target.form; const preview = form.querySelector('[data-quadro-cover-preview]');
            if (target.name === 'cover') {
                [...preview.classList].filter((name) => name.startsWith('quadro-cover--')).forEach((name) => preview.classList.remove(name)); preview.classList.add(`quadro-cover--${target.value}`);
                preview.querySelector('img')?.remove(); form.elements.cover_image.value = ''; if (form.elements.remove_cover) form.elements.remove_cover.checked = true;
            } else if (target.files[0]) {
                if (target.files[0].size > 8 * 1024 * 1024) { error(form, 'A imagem deve ter no máximo 8 MB.'); target.value = ''; return; }
                if (coverObjectUrl) URL.revokeObjectURL(coverObjectUrl);
                coverObjectUrl = URL.createObjectURL(target.files[0]);
                let img = preview.querySelector('img'); if (!img) { img = document.createElement('img'); img.className = 'absolute inset-0 h-full w-full object-cover'; img.alt = ''; preview.prepend(img); } img.src = coverObjectUrl;
                if (form.elements.remove_cover) form.elements.remove_cover.checked = false;
            }
        }
    });
    if (index) setupIndex(index);
    if (!page) return;
    const readingMentions = new WeakSet();
    const readMention = async (target) => {
        const comment = target.closest('[data-quadro-comment]');
        if (!comment?.querySelector('[data-quadro-mention-unread]') || readingMentions.has(comment) || !comment.getClientRects().length || document.hidden) return;
        readingMentions.add(comment);
        try {
            const data = await postJson(comment.dataset.quadroMentionReadUrl, 'POST', {});
            comment.querySelector('[data-quadro-mention-unread]')?.remove(); updateNotificationCount(data.unread_count); commentRevision = '';
        } catch {} finally { readingMentions.delete(comment); }
    };
    cardDialog.addEventListener('pointerover', (event) => { if (event.pointerType !== 'touch') readMention(event.target); });
    cardDialog.addEventListener('click', (event) => readMention(event.target));
    page.addEventListener('contextmenu', (event) => {
        if (touchDrag) { event.preventDefault(); return; }
        const card = event.target.closest('[data-quadro-card]');
        if (!card) return;
        event.preventDefault(); openContextMenu(card, card, { x: event.clientX, y: event.clientY });
    });
    const setDragPreview = (event, element) => {
        const bounds = element.getBoundingClientRect();
        event.dataTransfer.setDragImage(element, event.clientX - bounds.left, event.clientY - bounds.top);
    };
    const checklistRow = (handle) => handle.closest(`[data-quadro-checklist-${handle.dataset.quadroChecklistDrag}]`);
    let checklistDrag = null;
    cardDialog.addEventListener('dragstart', (event) => {
        const handle = event.target.closest('[data-quadro-checklist-drag]');
        if (!handle) return;
        checklistDrag = { row: checklistRow(handle), type: handle.dataset.quadroChecklistDrag };
        const { row } = checklistDrag;
        row.classList.add('quadro-checklist-drag-preview');
        setDragPreview(event, row);
        requestAnimationFrame(() => row.classList.remove('quadro-checklist-drag-preview'));
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', checklistDrag.type);
    });
    const checklistDropTarget = (event) => {
        if (!checklistDrag) return null;
        const { row, type } = checklistDrag;
        const section = event.target.closest('[data-quadro-checklist-section]');
        if (!section || section.closest('form') !== row.closest('form')) return null;
        if (type === 'section') return section === row ? null : { target: section };

        const items = section.querySelector('[data-quadro-checklist-items]');
        if (items !== row.parentElement && items.children.length >= 200) return null;
        const item = event.target.closest('[data-quadro-checklist-item]');
        if (item === row) return null;
        return item ? { target: item } : { target: section, appendTo: items };
    };
    const clearChecklistDropMarker = () => cardDialog.querySelector('[data-checklist-drop-side]')?.removeAttribute('data-checklist-drop-side');
    const checklistDropSide = (event, target) => {
        const bounds = target.getBoundingClientRect();
        return event.clientY < bounds.top + bounds.height / 2 ? 'before' : 'after';
    };
    cardDialog.addEventListener('dragover', (event) => {
        if (!checklistDrag) return;
        const destination = checklistDropTarget(event);
        const target = destination?.target;
        const marked = cardDialog.querySelector('[data-checklist-drop-side]');
        if (marked !== target) marked?.removeAttribute('data-checklist-drop-side');
        if (!target) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        target.dataset.checklistDropSide = destination.appendTo ? 'append' : checklistDropSide(event, target);
    });
    cardDialog.addEventListener('drop', (event) => {
        const destination = checklistDropTarget(event);
        if (!destination) return;
        event.preventDefault();
        const { row } = checklistDrag;
        checklistDrag = null;
        clearChecklistDropMarker();
        const { target, appendTo } = destination;
        if (appendTo) {
            appendTo.append(row);
        } else {
            target[checklistDropSide(event, target)](row);
        }
        updateChecklist();
        saveChecklists(row.closest('form'));
    });
    cardDialog.addEventListener('dragend', () => {
        checklistDrag = null;
        clearChecklistDropMarker();
    });
    cardDialog.addEventListener('keydown', (event) => {
        if (event.isComposing) return;
        if (event.target.matches('[data-quadro-checklist-drag]') && ['ArrowUp', 'ArrowDown'].includes(event.key)) {
            event.preventDefault();
            const row = checklistRow(event.target);
            if (event.key === 'ArrowUp' && row.previousElementSibling) row.previousElementSibling.before(row);
            else if (event.key === 'ArrowDown' && row.nextElementSibling) row.nextElementSibling.after(row);
            else return;
            event.target.focus();
            saveChecklists(row.closest('form'));
            return;
        }
        if (event.key !== 'Enter') return;
        if (event.target.matches('[data-quadro-checklist-new]')) { event.preventDefault(); addChecklistItem(event.target.closest('[data-quadro-checklist-section]')); }
        else if (event.target.matches('[data-quadro-checklist-title], [data-quadro-checklist-text]')) { event.preventDefault(); event.target.blur(); }
    });
    cardDialog.addEventListener('focusout', (event) => {
        if (!event.target.matches('[data-quadro-checklist-title], [data-quadro-checklist-text]')) return;
        if (event.target.matches('[data-quadro-checklist-text]')) { event.target.readOnly = true; event.target.hidden = true; event.target.previousElementSibling.hidden = false; updateChecklist(); }
        if (checklistSaves.has(event.target.closest('form'))) saveChecklists(event.target.closest('form'));
    });
    cardDialog.addEventListener('input', (event) => {
        if (event.target.matches('[data-quadro-checklist-title], [data-quadro-checklist-text]')) scheduleChecklistSave(event.target.closest('form'));
        if (event.target.matches('[data-quadro-label-name]')) updateLabelEditor(event.target.closest('[data-quadro-label-editor]'));
        if (event.target.matches('[data-quadro-label-custom-color]')) { const editor = event.target.closest('[data-quadro-label-editor]'); editor.dataset.color = event.target.value; updateLabelEditor(editor); }
        if (event.target.matches('[data-quadro-label-search]')) {
            const term = normalize(event.target.value);
            event.target.closest('[data-quadro-card-editor]').querySelectorAll('[data-quadro-label-row]').forEach((row) => { row.hidden = !normalize(row.dataset.name).includes(term); });
        }
    });
    cardDialog.addEventListener('load', (event) => { if (event.target.tagName === 'IMG') updateCommentPreviews(); }, true);
    page.querySelector('[data-quadro-search]').addEventListener('input', filterCards);
    page.querySelector('[data-quadro-status]').addEventListener('change', filterCards);
    const saveListFilter = () => { if (filterListId) { listFilters.set(filterListId, { term: filterDialog.querySelector('[data-quadro-list-search]').value, sort: filterSort }); filterCards(); } };
    filterDialog.querySelector('[data-quadro-list-search]').addEventListener('input', saveListFilter);
    listDialog.addEventListener('toggle', (event) => {
        if (event.newState === 'closed') listAnchor?.setAttribute('aria-expanded', 'false');
    });
    filterDialog.addEventListener('toggle', (event) => {
        if (event.newState === 'closed') filterAnchor?.setAttribute('aria-expanded', 'false');
    });
    window.addEventListener('resize', positionFilter);
    window.visualViewport?.addEventListener('resize', positionFilter);
    window.visualViewport?.addEventListener('scroll', positionFilter);
    document.addEventListener('scroll', (event) => {
        if (!filterDialog.contains(event.target)) positionFilter();
    }, true);
    cardDialog.addEventListener('cancel', (event) => {
        const form = cardDialog.querySelector('[data-quadro-card-form]');
        if (!form || !checklistSaves.has(form)) return;
        event.preventDefault();
        saveChecklists(form).then((saved) => { if (saved && cardDialog.open && form.isConnected) cardDialog.close(); });
    });
    cardDialog.addEventListener('close', () => { const form = cardDialog.querySelector('[data-quadro-card-form]'); if (form && checklistSaves.has(form)) saveChecklists(form); saveRichDrafts(cardDialog); cardVersion++; commentRevision = ''; });
    page.addEventListener('keydown', (event) => {
        const cardComposer = event.target.closest('[data-quadro-inline-card]');
        if (event.key === 'Escape' && cardComposer) {
            event.preventDefault(); closeCardComposer(cardComposer); return;
        }
        if (event.key === 'Escape' && event.target.closest('[data-quadro-inline-list]')) {
            event.preventDefault(); closeListComposer();
        }
    });
    const clearDrag = () => {
        if (touchPending) clearTimeout(touchPending.timer);
        touchPending = null;
        if (touchDrag) { cancelAnimationFrame(touchDrag.frame); touchDrag.ghost.remove(); }
        touchDrag = null;
        page.classList.remove('quadro-touch-dragging');
        if (drag) {
            cancelAnimationFrame(drag.frame);
            drag.element.classList.remove('quadro-drag-source');
            drag.element.removeAttribute('aria-grabbed');
            drag.placeholder.remove();
        }
        drag = null;
        page.querySelectorAll('[data-drop-over]').forEach((element) => element.removeAttribute('data-drop-over'));
    };
    const placeDrag = (event, scrollStep = 18) => {
        const scroller = event.target.closest('[data-quadro-kanban]');
        if (!drag || !scroller) return false;
        const list = event.target.closest('[data-quadro-list]');
        if (drag.type === 'card' && !list) return false;
        const box = scroller.getBoundingClientRect();
        const stacked = window.getComputedStyle(scroller).flexDirection === 'column';
        if (stacked) {
            if (event.clientY < box.top + 55) scroller.scrollTop -= scrollStep;
            else if (event.clientY > box.bottom - 55) scroller.scrollTop += scrollStep;
        } else {
            if (event.clientX < box.left + 55) scroller.scrollLeft -= scrollStep;
            else if (event.clientX > box.right - 55) scroller.scrollLeft += scrollStep;
        }
        if (drag.type === 'card') {
            page.querySelectorAll('[data-drop-over]').forEach((element) => element.removeAttribute('data-drop-over'));
            list.setAttribute('data-drop-over', '');
            const vertical = list.querySelector('.quadro-column-scroll');
            const rect = vertical.getBoundingClientRect();
            if (event.clientY < rect.top + 45) vertical.scrollTop -= scrollStep;
            else if (event.clientY > rect.bottom - 45) vertical.scrollTop += scrollStep;
        }
        const placeholderBox = drag.placeholder.getBoundingClientRect();
        if (event.clientX >= placeholderBox.left && event.clientX <= placeholderBox.right && event.clientY >= placeholderBox.top && event.clientY <= placeholderBox.bottom) return true;
        if (drag.type === 'list') {
            const lists = [...scroller.querySelectorAll('[data-quadro-list]')].filter((element) => element !== drag.element);
            const before = lists.find((element) => {
                const rect = element.getBoundingClientRect();
                return stacked ? event.clientY < rect.top + rect.height / 2 : event.clientX < rect.left + rect.width / 2;
            });
            scroller.insertBefore(drag.placeholder, before || scroller.querySelector('.quadro-add-list'));
        } else {
            const cards = list.querySelector('[data-quadro-list-cards]');
            const before = [...cards.querySelectorAll('[data-quadro-card]')].filter((element) => element !== drag.element && !element.hidden)
                .find((element) => { const rect = element.getBoundingClientRect(); return event.clientY < rect.top + rect.height / 2; });
            cards.insertBefore(drag.placeholder, before || null);
        }
        return true;
    };
    const beginDrag = (element, type) => {
        clearDrag();
        const rect = element.getBoundingClientRect();
        const placeholder = document.createElement('div');
        placeholder.dataset.quadroDropPlaceholder = type;
        placeholder.setAttribute('aria-hidden', 'true');
        placeholder.className = `quadro-drop-placeholder quadro-drop-placeholder--${type}`;
        placeholder.style.height = `${rect.height}px`;
        if (type === 'list') placeholder.style.width = `${rect.width}px`;
        const nextCard = element.nextElementSibling?.dataset.quadroCard || null;
        drag = { type, element, placeholder, originalList: element.closest('[data-quadro-list]')?.dataset.quadroList, nextCard,
            originalOrder: [...page.querySelectorAll('[data-quadro-list]')].map((list) => list.dataset.quadroList).join(',') };
        element.before(placeholder);
        element.setAttribute('aria-grabbed', 'true');
        const current = drag;
        drag.frame = requestAnimationFrame(() => { if (drag === current) element.classList.add('quadro-drag-source'); });
    };
    page.addEventListener('dragstart', (event) => {
        if (touchDrag) { event.preventDefault(); return; }
        if (page.dataset.editable !== '1') return;
        const handle = event.target.closest('[data-quadro-list-handle]');
        const card = event.target.closest('[data-quadro-card]');
        if (!handle && !card) return;
        const element = handle ? handle.closest('[data-quadro-list]') : card;
        setDragPreview(event, element);
        beginDrag(element, handle ? 'list' : 'card');
        event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', drag.type);
    });
    page.addEventListener('dragover', (event) => {
        if (!placeDrag(event)) return;
        event.preventDefault(); event.dataTransfer.dropEffect = 'move';
    });
    const finishDrop = async () => {
        if (!drag) return;
        lastDrop = Date.now();
        const moving = drag;
        const destination = moving.type === 'card' ? moving.placeholder.closest('[data-quadro-list]') : null;
        moving.placeholder.replaceWith(moving.element);
        clearDrag();
        try {
            if (moving.type === 'list') {
                const ids = [...page.querySelectorAll('[data-quadro-list]')].map((element) => Number(element.dataset.quadroList));
                if (ids.join(',') === moving.originalOrder) return;
                await postJson(`${boardUrl}/listas/ordem`, 'PUT', { ids });
            } else {
                const before = moving.element.nextElementSibling?.dataset.quadroCard || null;
                if (destination.dataset.quadroList === moving.originalList && before === moving.nextCard) return;
                listFilters.delete(destination.dataset.quadroList);
                await postJson(moving.element.dataset.moveUrl, 'PATCH', { lista_id: Number(destination.dataset.quadroList), before_id: before ? Number(before) : null });
            }
            await reload();
        } catch (failure) { error(page, failure.message); await reload().catch(() => {}); }
    };
    page.addEventListener('drop', async (event) => {
        if (!placeDrag(event)) return;
        event.preventDefault(); await finishDrop();
    });
    const updateTouchPosition = () => {
        if (!touchDrag || !drag) return;
        const current = touchDrag;
        current.ghost.style.left = `${current.x - current.offsetX}px`;
        current.ghost.style.top = `${current.y - current.offsetY}px`;
        const target = document.elementFromPoint(current.x, current.y);
        current.valid = target ? placeDrag({ target, clientX: current.x, clientY: current.y }, 8) : false;
    };
    const touchFrame = () => {
        updateTouchPosition();
        if (touchDrag) touchDrag.frame = requestAnimationFrame(touchFrame);
    };
    const startTouchDrag = (pending) => {
        if (touchPending !== pending || !pending.element.isConnected) return;
        const rect = pending.element.getBoundingClientRect();
        const ghost = pending.element.cloneNode(true);
        [ghost, ...ghost.querySelectorAll('*')].forEach((element) => {
            [...element.attributes].filter((attribute) => attribute.name === 'id' || attribute.name.startsWith('data-')).forEach((attribute) => element.removeAttribute(attribute.name));
        });
        ghost.classList.add('quadro-touch-ghost'); ghost.setAttribute('aria-hidden', 'true'); ghost.inert = true;
        ghost.style.width = `${rect.width}px`; ghost.style.height = `${rect.height}px`;
        beginDrag(pending.element, pending.type);
        document.body.append(ghost);
        touchDrag = { ghost, pointerId: pending.pointerId, x: pending.x, y: pending.y, offsetX: pending.x - rect.left, offsetY: pending.y - rect.top, valid: true };
        if (page.setPointerCapture) page.setPointerCapture(pending.pointerId);
        page.classList.add('quadro-touch-dragging'); touchFrame();
    };
    page.addEventListener('pointerdown', (event) => {
        if (event.pointerType !== 'touch' && event.pointerType !== 'pen') return;
        if (!event.isPrimary) { clearDrag(); return; }
        if (page.dataset.editable !== '1' || touchDrag) return;
        const handle = event.target.closest('[data-quadro-list-handle]');
        const cardHandle = event.target.closest('[data-quadro-card-handle]');
        const card = event.target.closest('[data-quadro-card]');
        if (!handle && !card) return;
        const control = event.target.closest('button, input, textarea, select, a, [contenteditable]');
        if (control && !handle && !cardHandle && !control.hasAttribute('data-quadro-card-open')) return;
        clearDrag();
        const pending = { element: handle ? handle.closest('[data-quadro-list]') : card, type: handle ? 'list' : 'card', pointerId: event.pointerId, x: event.clientX, y: event.clientY, immediate: !!(handle || cardHandle) };
        touchPending = pending;
        if (pending.immediate) { event.preventDefault(); startTouchDrag(pending); }
        else pending.timer = setTimeout(() => startTouchDrag(pending), 350);
    });
    page.addEventListener('pointermove', (event) => {
        if (touchDrag) {
            if (event.pointerId !== touchDrag.pointerId) return;
            event.preventDefault(); touchDrag.x = event.clientX; touchDrag.y = event.clientY;
            updateTouchPosition(); return;
        }
        if (touchPending && event.pointerId === touchPending.pointerId && Math.hypot(event.clientX - touchPending.x, event.clientY - touchPending.y) > 8) {
            clearTimeout(touchPending.timer); touchPending = null;
        }
    });
    page.addEventListener('pointerup', async (event) => {
        if (!touchDrag) { if (touchPending && touchPending.pointerId === event.pointerId) { clearTimeout(touchPending.timer); touchPending = null; } return; }
        if (event.pointerId !== touchDrag.pointerId) return;
        event.preventDefault(); lastDrop = Date.now();
        const target = document.elementFromPoint(event.clientX, event.clientY);
        if (target && placeDrag({ target, clientX: event.clientX, clientY: event.clientY }, 0)) await finishDrop();
        else clearDrag();
    });
    page.addEventListener('pointercancel', (event) => {
        if (touchDrag?.pointerId === event.pointerId || touchPending?.pointerId === event.pointerId) clearDrag();
    });
    page.addEventListener('lostpointercapture', (event) => { if (touchDrag?.pointerId === event.pointerId) clearDrag(); });
    page.addEventListener('dragend', () => { if (drag) lastDrop = Date.now(); clearDrag(); });
    window.addEventListener('blur', clearDrag);
    prepareTouchSources(); setView(); filterCards(); setInterval(refreshComments, 1000);
    const deepLink = location.hash.match(/^#card=(\d+)(?:&comment=(\d+))?$/);
    if (deepLink) openCard(`${boardUrl}/cartoes/${Number(deepLink[1])}`).then(() => {
        if (!deepLink[2]) return;
        cardDialog.querySelector('[data-quadro-card-tab="comments"]')?.click();
        cardDialog.querySelector('[data-quadro-mobile-tab="comments"]')?.click();
        cardDialog.querySelector(`[data-quadro-comment="${Number(deepLink[2])}"]`)?.scrollIntoView({ block: 'center' });
    });
    window.addEventListener('focus', refreshComments);
    document.addEventListener('visibilitychange', refreshComments);
}

function setupIndex(index) {
    const grid = index.querySelector('[data-quadro-index-grid]');
    const tiles = [...grid.querySelectorAll('[data-quadro-tile]')];
    const create = grid.querySelector('[data-quadro-create-tile]');
    let grouped = false;
    const render = () => {
        const term = normalize(index.querySelector('[data-quadro-index-search]').value);
        const category = index.querySelector('[data-quadro-index-category]').value;
        const from = index.querySelector('[data-quadro-index-from]').value;
        const until = index.querySelector('[data-quadro-index-until]').value;
        grid.querySelectorAll('[data-quadro-category-heading]').forEach((element) => element.remove());
        const visible = tiles.filter((tile) => { const show = normalize(tile.dataset.title).includes(term) && (!category || tile.dataset.category === category) && (!from || tile.dataset.date >= from) && (!until || tile.dataset.date <= until); tile.hidden = !show; return show; });
        if (grouped) {
            const categories = [...new Set(visible.map((tile) => tile.dataset.category || 'Sem categoria'))].sort((a, b) => a.localeCompare(b, 'pt-BR'));
            categories.forEach((name) => { const heading = document.createElement('h2'); heading.dataset.quadroCategoryHeading = ''; heading.className = 'col-span-full mt-3 text-sm font-semibold text-slate-300'; heading.textContent = name; grid.append(heading); visible.filter((tile) => (tile.dataset.category || 'Sem categoria') === name).forEach((tile) => grid.append(tile)); });
        } else tiles.forEach((tile) => grid.append(tile));
        grid.append(create); index.querySelector('[data-quadro-index-empty]').hidden = visible.length > 0 || tiles.length === 0;
    };
    index.querySelectorAll('input, select').forEach((input) => { input.addEventListener('input', render); input.addEventListener('change', render); });
    index.querySelectorAll('[data-quadro-group]').forEach((button) => button.addEventListener('click', () => { grouped = button.dataset.quadroGroup === 'category'; index.querySelectorAll('[data-quadro-group]').forEach((item) => item.setAttribute('aria-pressed', String(item === button))); render(); }));
}
