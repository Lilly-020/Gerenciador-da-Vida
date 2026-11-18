import { htmlToElement, postJson, postForm } from './board';
import { confirmDialog } from './dialog';

const normalize = (value) => (value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
const get = (url) => postJson(url, 'GET');
const fields = (form) => {
    const data = Object.fromEntries(new FormData(form));
    if (form.matches('[data-quadro-card-form]')) {
        data.assignee_ids = [...form.querySelectorAll('[name="assignee_ids[]"]:checked')].map((input) => Number(input.value));
        delete data['assignee_ids[]'];
        if (form.querySelector('[data-quadro-checklist-items]')) {
            data.checklist = [...form.querySelectorAll('[data-quadro-checklist-item]')].map((item) => ({ text: item.querySelector('[data-quadro-checklist-text]').value, done: item.querySelector('[data-quadro-checklist-done]').checked }));
        }
    }
    return data;
};

export function setupQuadros() {
    const index = document.querySelector('[data-quadros-index]');
    const page = document.querySelector('[data-quadro-page]');
    if (!index && !page) return;
    const boardDialog = document.getElementById('quadro-board-dialog');
    const listDialog = document.getElementById('quadro-list-dialog');
    const cardDialog = document.getElementById('quadro-card-dialog');
    const membersDialog = document.getElementById('quadro-members-dialog');
    const filterDialog = document.getElementById('quadro-filter-dialog');
    const busy = new WeakSet();
    const armed = new WeakMap();
    const listFilters = new Map();
    let activeList = null;
    let insertBeforeListId = null;
    let listComposerTrigger = null;
    let filterListId = null;
    let filterAnchor = null;
    let listAnchor = null;
    let filterSort = 'manual';
    let cardPanel = null;
    let cardPanelAnchor = null;
    let membersTab = 'accepted';
    let view = 'kanban';
    let reloadVersion = 0;
    let cardVersion = 0;
    let commentRevision = '';
    let commentSequence = 0;
    let commentLoading = false;
    let commentSaving = false;
    let drag = null;
    let lastDrop = 0;
    let coverObjectUrl = null;
    const boardUrl = page?.dataset.url;
    try { view = localStorage.getItem(`quadro-view:${boardUrl}`) || 'kanban'; } catch {}
    const error = (container, message) => {
        const output = container?.querySelector('[data-quadro-error]') || document.querySelector('[data-quadro-page-error]');
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
    };
    const filterCards = () => {
        if (!page) return;
        const term = normalize(page.querySelector('[data-quadro-search]').value);
        const status = page.querySelector('[data-quadro-status]').value;
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
    };
    const reload = async () => {
        if (!page) return;
        const version = ++reloadVersion;
        const scroll = page.querySelector('[data-quadro-kanban]')?.scrollLeft || 0;
        const listScroll = new Map([...page.querySelectorAll('[data-quadro-list]')].map((list) => [list.dataset.quadroList, list.querySelector('.quadro-column-scroll').scrollTop]));
        const data = await get(boardUrl);
        if (version !== reloadVersion) return;
        page.querySelector('[data-quadro-content]').innerHTML = data.html;
        setView(); filterCards();
        page.querySelector('[data-quadro-kanban]').scrollLeft = scroll;
        page.querySelectorAll('[data-quadro-list]').forEach((list) => { list.querySelector('.quadro-column-scroll').scrollTop = listScroll.get(list.dataset.quadroList) || 0; });
    };
    const updateChecklist = () => {
        const section = cardDialog.querySelector('[data-quadro-checklist-section]');
        if (!section) return;
        const items = [...section.querySelectorAll('[data-quadro-checklist-item]')];
        const done = items.filter((item) => item.querySelector('[data-quadro-checklist-done]').checked).length;
        section.querySelector('[data-quadro-checklist-count]').textContent = `${done}/${items.length} itens`;
        section.querySelector('[data-quadro-checklist-progress]').style.width = `${items.length ? done / items.length * 100 : 0}%`;
    };
    const addChecklistItem = () => {
        const container = cardDialog.querySelector('[data-quadro-checklist-items]');
        if (container.children.length >= 200) { error(cardDialog, 'O checklist pode ter até 200 itens.'); return; }
        const item = htmlToElement('<div data-quadro-checklist-item class="flex items-center gap-2"><span class="relative inline-flex h-5 w-5 shrink-0 items-center justify-center"><input data-quadro-checklist-done type="checkbox" aria-label="Concluir item" class="peer h-5 w-5 cursor-pointer appearance-none rounded-md border border-slate-500 bg-slate-950/60 checked:border-indigo-400 checked:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-indigo-400"><svg aria-hidden="true" viewBox="0 0 20 20" fill="none" class="pointer-events-none absolute h-3.5 w-3.5 text-white opacity-0 peer-checked:opacity-100"><path d="m4 10 4 4 8-8" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" /></svg></span><input data-quadro-checklist-text required maxlength="500" aria-label="Item do checklist" placeholder="Novo item" class="min-w-0 flex-1"><button type="button" data-quadro-checklist-remove class="quadro-tool px-2!" aria-label="Excluir item"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7" /></svg></button></div>');
        container.append(item);
        item.querySelector('input[type="text"], [data-quadro-checklist-text]').focus();
        updateChecklist();
    };
    const openCard = async (url) => {
        const version = ++cardVersion;
        commentRevision = '';
        cardDialog.querySelector('[data-quadro-card-body]').innerHTML = '<p class="p-6 text-sm text-slate-400">Carregando cartão…</p>';
        show(cardDialog);
        try {
            const data = await get(url);
            if (version !== cardVersion || !cardDialog.open) return;
            cardDialog.querySelector('[data-quadro-card-body]').innerHTML = data.html;
            updateChecklist();
        } catch (failure) { if (version === cardVersion && cardDialog.open) cardDialog.querySelector('[data-quadro-card-body]').textContent = failure.message; }
    };
    const refreshCard = async () => {
        const detail = cardDialog.querySelector('[data-quadro-card-detail]');
        if (!detail || !cardDialog.open) return;
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
    };
    const applyComments = (data) => {
        const container = cardDialog?.querySelector('[data-quadro-comments]');
        if (!container) return;
        commentRevision = data.revision;
        const count = cardDialog.querySelector('[data-quadro-comments-count]');
        if (count && data.count !== undefined) count.textContent = data.count;
        if (data.history) cardDialog.querySelector('[data-quadro-history]').innerHTML = data.history;
        if (!data.html) return;
        const next = htmlToElement(data.html);
        const focused = container.contains(document.activeElement) ? document.activeElement : null;
        const selection = focused?.tagName === 'TEXTAREA' ? [focused.selectionStart, focused.selectionEnd] : null;
        container.querySelectorAll('[data-quadro-comment]').forEach((comment) => {
            if (!comment.querySelector('[data-quadro-comment-edit-form]:not([hidden])')) return;
            const replacement = next.querySelector(`[data-quadro-comment="${comment.dataset.quadroComment}"]`);
            if (replacement) replacement.replaceWith(comment); else next.append(comment);
        });
        const scroll = container.scrollTop;
        container.replaceChildren(next);
        container.scrollTop = scroll;
        if (focused?.isConnected) { focused.focus({ preventScroll: true }); if (selection) focused.setSelectionRange(...selection); }
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

    document.addEventListener('click', async (event) => {
        const target = event.target;
        if (target.closest('[data-quadro-close]')) { target.closest('dialog')?.close(); return; }
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
            const detail = target.closest('[data-quadro-card-detail]');
            const cardTab = target.closest('[data-quadro-card-tab]');
            if (cardTab) {
                const history = cardTab.dataset.quadroCardTab === 'history';
                detail.querySelectorAll('[data-quadro-card-tab]').forEach((tab) => tab.setAttribute('aria-selected', String(tab === cardTab)));
                detail.querySelector('[data-quadro-comments]').hidden = history;
                detail.querySelector('[data-quadro-history]').hidden = !history;
                const composer = detail.querySelector('[data-quadro-comment-create]');
                if (composer) composer.hidden = history;
                return;
            }
            const panelButton = target.closest('[data-quadro-card-panel]');
            if (panelButton) {
                if (panelButton.dataset.quadroCardPanel === 'checklist') {
                    const section = detail.querySelector('[data-quadro-checklist-section]');
                    section.hidden = false;
                    if (!section.querySelector('[data-quadro-checklist-item]')) addChecklistItem();
                    section.scrollIntoView({ block: 'nearest' }); return;
                }
                const panel = detail.querySelector(`[data-quadro-card-editor="${panelButton.dataset.quadroCardPanel}"]`);
                if (panel.matches(':popover-open')) { panel.hidePopover(); return; }
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
            if (target.closest('[data-quadro-checklist-add]')) { addChecklistItem(); return; }
            const removeItem = target.closest('[data-quadro-checklist-remove]');
            if (removeItem) { removeItem.closest('[data-quadro-checklist-item]').remove(); updateChecklist(); return; }
            if (target.closest('[data-quadro-card-cover-clear]')) {
                const form = detail.querySelector('[data-quadro-card-form]');
                form.elements.cover_color.value = ''; form.elements.cover_file_id.value = ''; form.requestSubmit(); return;
            }
            if (target.closest('[data-quadro-card-attach]')) { detail.querySelector('[data-quadro-card-file]').click(); return; }
            const deleteFile = target.closest('[data-quadro-card-file-delete]');
            if (deleteFile && confirm(deleteFile)) { await postJson(deleteFile.dataset.url, 'DELETE'); await refreshCard(); return; }
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
            if (editComment) { const form = editComment.closest('[data-quadro-comment]').querySelector('form'); form.hidden = false; form.elements.body.focus(); return; }
            if (target.closest('[data-quadro-comment-cancel]')) { target.closest('form').hidden = true; commentRevision = ''; await refreshComments(); return; }
            const deleteComment = target.closest('[data-quadro-comment-delete]');
            if (deleteComment && confirm(deleteComment)) { commentSaving = true; commentSequence++; try { applyComments(await postJson(deleteComment.dataset.url, 'DELETE')); await reload(); } finally { commentSaving = false; } }
        } catch (failure) { error(target.closest('form') || target.closest('dialog') || page || index, failure.message); target.disabled = false; }
    });
    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!form.matches('[data-quadro-board-form], [data-quadro-inline-card], [data-quadro-inline-list], [data-quadro-list-form], [data-quadro-card-form], [data-quadro-member-form], [data-quadro-comment-create], [data-quadro-comment-edit-form]')) return;
        event.preventDefault();
        if (!form.reportValidity() || busy.has(form)) return;
        busy.add(form); error(form, '');
        const button = event.submitter || form.querySelector('[type="submit"]'); button.disabled = true;
        const commentForm = form.matches('[data-quadro-comment-create], [data-quadro-comment-edit-form]');
        if (commentForm) { commentSaving = true; commentSequence++; }
        try {
            if (form.matches('[data-quadro-board-form]')) { const data = await postForm(form.action, new FormData(form)); location.assign(form.dataset.quadroReturnIndex ? index.dataset.url : data.url); }
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
                await postJson(form.action, form.dataset.method, fields(form));
                if (form.dataset.method === 'PATCH') await refreshCard(); else cardDialog.close();
                await reload();
            }
            else if (form.matches('[data-quadro-member-form]')) { applyMembers(await postJson(form.action, 'POST', fields(form))); }
            else {
                const body = form.elements.body.value;
                const data = await postJson(form.action, form.matches('[data-quadro-comment-edit-form]') ? 'PATCH' : 'POST', { body });
                if (form.matches('[data-quadro-comment-edit-form]')) form.hidden = true;
                else if (form.elements.body.value === body) form.reset();
                applyComments(data); await reload();
            }
        } catch (failure) { error(form, failure.message); }
        finally { busy.delete(form); button.disabled = false; if (commentForm) commentSaving = false; }
    });
    document.addEventListener('change', async (event) => {
        const target = event.target;
        const detail = target.closest('[data-quadro-card-detail]');
        if (target.matches('[data-quadro-checklist-done]')) { updateChecklist(); return; }
        if (target.matches('[data-quadro-card-complete]')) {
            const form = detail.querySelector('[data-quadro-card-form]');
            form.elements.status.value = target.checked ? 'done' : 'todo';
            form.requestSubmit(); return;
        }
        if (target.matches('[data-quadro-card-cover-color]')) { detail.querySelector('[name="cover_color"]').value = target.value; return; }
        if (target.matches('[data-quadro-card-file]')) {
            const file = target.files[0];
            if (!file) return;
            if (file.size > 10 * 1024 * 1024) { error(detail, 'O arquivo deve ter até 10 MB.'); target.value = ''; return; }
            const data = new FormData(); data.append('file', file);
            target.disabled = true;
            try { await postForm(target.dataset.url, data); await refreshCard(); }
            catch (failure) { error(detail, failure.message); }
            finally { target.disabled = false; target.value = ''; }
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
    cardDialog.addEventListener('close', () => { cardVersion++; commentRevision = ''; });
    page.addEventListener('keydown', (event) => {
        const cardComposer = event.target.closest('[data-quadro-inline-card]');
        if (event.key === 'Escape' && cardComposer) {
            event.preventDefault(); closeCardComposer(cardComposer); return;
        }
        if (event.key === 'Escape' && event.target.closest('[data-quadro-inline-list]')) {
            event.preventDefault(); closeListComposer();
        }
    });
    page.addEventListener('dragstart', (event) => {
        if (page.dataset.editable !== '1') return;
        const handle = event.target.closest('[data-quadro-list-handle]');
        const card = event.target.closest('[data-quadro-card]');
        if (!handle && !card) return;
        drag = handle ? { type: 'list', element: handle.closest('[data-quadro-list]') } : { type: 'card', element: card };
        event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', drag.type); drag.element.setAttribute('aria-grabbed', 'true');
    });
    page.addEventListener('dragover', (event) => {
        const list = event.target.closest('[data-quadro-list]');
        if (!drag || !list) return;
        event.preventDefault(); event.dataTransfer.dropEffect = 'move';
        page.querySelectorAll('[data-drop-over]').forEach((element) => element.removeAttribute('data-drop-over')); list.setAttribute('data-drop-over', '');
        const scroller = page.querySelector('[data-quadro-kanban]'); const box = scroller.getBoundingClientRect();
        if (event.clientX < box.left + 55) scroller.scrollLeft -= 18; else if (event.clientX > box.right - 55) scroller.scrollLeft += 18;
        const vertical = list.querySelector('.quadro-column-scroll'); const rect = vertical.getBoundingClientRect();
        if (event.clientY < rect.top + 45) vertical.scrollTop -= 16; else if (event.clientY > rect.bottom - 45) vertical.scrollTop += 16;
    });
    page.addEventListener('drop', async (event) => {
        const list = event.target.closest('[data-quadro-list]');
        if (!drag || !list) return;
        event.preventDefault(); lastDrop = Date.now(); const moving = drag; drag = null;
        page.querySelectorAll('[data-drop-over]').forEach((element) => element.removeAttribute('data-drop-over')); moving.element.removeAttribute('aria-grabbed');
        try {
            if (moving.type === 'list') {
                if (moving.element === list) return;
                const before = event.clientX < list.getBoundingClientRect().left + list.offsetWidth / 2;
                list.parentElement.insertBefore(moving.element, before ? list : list.nextElementSibling);
                await postJson(`${boardUrl}/listas/ordem`, 'PUT', { ids: [...page.querySelectorAll('[data-quadro-list]')].map((element) => Number(element.dataset.quadroList)) });
            } else {
                const target = event.target.closest('[data-quadro-card]');
                if (target === moving.element) return;
                let before = target;
                if (target && event.clientY > target.getBoundingClientRect().top + target.offsetHeight / 2) before = target.nextElementSibling;
                if (before === moving.element) before = moving.element.nextElementSibling;
                listFilters.delete(list.dataset.quadroList);
                await postJson(moving.element.dataset.moveUrl, 'PATCH', { lista_id: Number(list.dataset.quadroList), before_id: before ? Number(before.dataset.quadroCard) : null });
            }
            await reload();
        } catch (failure) { error(page, failure.message); await reload().catch(() => {}); }
    });
    page.addEventListener('dragend', () => { drag?.element.removeAttribute('aria-grabbed'); drag = null; page.querySelectorAll('[data-drop-over]').forEach((element) => element.removeAttribute('data-drop-over')); });
    setView(); filterCards(); setInterval(refreshComments, 2000);
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
