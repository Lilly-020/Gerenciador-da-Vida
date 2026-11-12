import { htmlToElement, postJson } from './board';
import { confirmDialog } from './dialog';
import { toast } from './selects';

export function setupNotas() {
    const tools = document.getElementById('nota-tools');
    if (!tools) return;
    const page = document.getElementById('notas-page');
    const editor = document.getElementById('note-editor-modal');
    const form = editor.querySelector('form');
    const rows = editor.querySelector('[data-note-editor-items]');
    const editorError = editor.querySelector('[data-note-editor-error]');
    const linkModal = document.getElementById('note-link-modal');
    const existingForm = linkModal.querySelector('[data-note-existing-form]');
    const scheduleForm = linkModal.querySelector('[data-note-schedule-form]');
    const linkError = linkModal.querySelector('[data-note-link-error]');
    const picker = document.getElementById('note-picker-modal');
    const pickerSearch = picker.querySelector('[data-note-picker-search]');
    const pickerOptions = picker.querySelector('[data-note-picker-options]');
    const pickerError = picker.querySelector('[data-note-picker-error]');
    let currentNote = null;
    let editorTask = null;
    let linkedNote = null;
    let pickerTask = null;
    let baseline = '';
    let editorBusy = false;
    let linkBusy = false;
    let pickerBusy = false;
    let editorVersion = 0;
    let linkVersion = 0;
    let tasksVersion = 0;
    let pickerVersion = 0;
    let gridVersion = 0;
    let taskTimer;
    let pickerTimer;

    const errorMessage = (element, message = '') => {
        element.textContent = message;
        element.hidden = !message;
    };
    const setDisabled = (container, disabled) => {
        container.querySelectorAll('button, input, textarea, select').forEach((control) => { control.disabled = disabled; });
    };
    const payload = () => {
        const data = {
            title: form.elements.namedItem('title').value.trim(),
            content: form.elements.namedItem('content').value,
            color: form.elements.namedItem('color').value,
            pinned: form.elements.namedItem('pinned').checked,
            items: [...rows.querySelectorAll('[data-note-editor-row]')].map((row) => ({
                ...(row.dataset.itemId ? { id: Number(row.dataset.itemId) } : {}),
                text: row.querySelector('[data-note-row-text]').value.trim(),
                completed: row.querySelector('[data-note-row-completed]').checked,
            })),
        };
        if (!currentNote && editorTask) data.tarefa_id = editorTask;
        return data;
    };
    const dirty = () => JSON.stringify(payload()) !== baseline;
    const countItems = () => {
        const count = rows.children.length;
        editor.querySelector('[data-note-editor-count]').textContent = count ? `${count} / 200 itens` : '';
        editor.querySelector('[data-note-add-item]').disabled = editorBusy || count >= 200;
    };
    const addItem = (item = null, after = null, focus = true) => {
        if (rows.children.length >= 200) { toast('Uma lista pode ter até 200 itens.', true); return; }
        const row = tools.querySelector('[data-note-item-template]').content.firstElementChild.cloneNode(true);
        if (item?.id) row.dataset.itemId = item.id;
        row.querySelector('[data-note-row-text]').value = item?.text ?? '';
        row.querySelector('[data-note-row-completed]').checked = item?.completed ?? false;
        if (after) after.after(row); else rows.append(row);
        countItems();
        if (focus) row.querySelector('[data-note-row-text]').focus();
    };
    const updateEditorTask = () => {
        const label = editor.querySelector('[data-note-editor-task]');
        label.hidden = !currentNote?.tarefa && !editorTask;
        label.textContent = currentNote?.tarefa ? `Vinculada a: ${currentNote.tarefa.title}` : 'Esta nota será adicionada à tarefa aberta.';
    };
    const refreshGrid = async () => {
        if (!page) return;
        const version = ++gridVersion;
        const address = new URL(window.location.href);
        address.pathname = new URL(page.dataset.indexUrl, window.location.origin).pathname;
        const data = await postJson(address.href, 'GET');
        if (version === gridVersion) page.querySelector('[data-notas-results]').innerHTML = data.html;
    };
    const changed = (data = null, { refresh = true, calendarChanged = false } = {}) => {
        if (data?.nota && currentNote?.id === data.nota.id) {
            currentNote = data.nota;
            updateEditorTask();
        }
        document.dispatchEvent(new CustomEvent('notas:changed', { detail: { calendarChanged } }));
        if (calendarChanged) {
            try { localStorage.setItem('tarefas:last-change', `${Date.now()}-${Math.random()}`); } catch {}
        }
        if (!page) return;
        if (!refresh && data?.card) {
            const card = page.querySelector(`[data-note-card="${data.nota.id}"]`);
            if (card) card.replaceWith(htmlToElement(data.card));
            return;
        }
        refreshGrid().catch(() => toast('Alteração salva. Atualize a página para recarregar as notas.', true));
    };
    const closeEditor = async () => {
        if (editorBusy) return false;
        if (dirty()) {
            editor.querySelector('[data-note-discard-warning]').hidden = false;
            editor.querySelector('[data-note-discard-cancel]').focus();
            return false;
        }
        editor.close();
        return true;
    };
    const openEditor = async ({ url = null, type = 'text', task = null } = {}) => {
        if (editorBusy || (editor.open && !await closeEditor())) return;
        const version = ++editorVersion;
        try {
            const note = url ? (await postJson(url, 'GET')).nota : null;
            if (version !== editorVersion) return;
            currentNote = note;
            editorTask = task ? Number(task) : null;
            form.reset();
            rows.replaceChildren();
            form.elements.namedItem('title').value = note?.title ?? '';
            form.elements.namedItem('content').value = note?.content ?? '';
            form.elements.namedItem('color').value = note?.color ?? 'slate';
            form.elements.namedItem('pinned').checked = note?.pinned ?? false;
            (note?.items ?? []).forEach((item) => addItem(item, null, false));
            if (!note && type === 'list') addItem(null, null, false);
            countItems();
            updateEditorTask();
            editor.querySelector('h2').textContent = note ? 'Editar nota' : type === 'list' ? 'Nova lista' : 'Nova nota';
            editor.querySelector('[data-note-editor-link]').hidden = !note;
            errorMessage(editorError);
            editor.querySelector('[data-note-discard-warning]').hidden = true;
            baseline = JSON.stringify(payload());
            editor.showModal();
            form.elements.namedItem('title').focus();
        } catch (error) { toast(error.message, true); }
    };
    const selectLinkTab = (tab) => {
        existingForm.hidden = tab !== 'existing';
        scheduleForm.hidden = tab !== 'schedule';
        linkModal.querySelectorAll('[data-note-link-tab]').forEach((button) => {
            const selected = button.dataset.noteLinkTab === tab;
            button.setAttribute('aria-selected', String(selected));
            button.classList.toggle('bg-indigo-500/20', selected);
            button.classList.toggle('text-indigo-200', selected);
        });
    };
    const loadTasks = async (query = '', selected = null) => {
        const version = ++tasksVersion;
        const select = existingForm.elements.namedItem('tarefa_id');
        const status = linkModal.querySelector('[data-note-task-options-status]');
        select.disabled = true;
        status.textContent = 'Buscando tarefas…';
        try {
            const address = new URL(tools.dataset.tasksUrl, window.location.origin);
            address.searchParams.set('q', query);
            const data = await postJson(address.href, 'GET');
            if (version !== tasksVersion || !linkModal.open) return;
            select.replaceChildren(new Option('Selecione uma tarefa', ''));
            data.options.forEach((option) => select.add(new Option(option.label, option.id)));
            if (selected && !data.options.some((option) => option.id === selected) && linkedNote?.tarefa) select.add(new Option(`${linkedNote.tarefa.title} · ${linkedNote.tarefa.date}`, selected));
            select.value = selected ? String(selected) : '';
            status.textContent = data.options.length ? 'Até 50 resultados. Use a busca para encontrar outra tarefa.' : 'Nenhuma tarefa encontrada. Você também pode agendar uma nova.';
        } catch (error) {
            if (version === tasksVersion) errorMessage(linkError, error.message);
        } finally {
            if (version === tasksVersion) select.disabled = linkBusy;
        }
    };
    const openLink = async (url, tab = 'existing') => {
        if (linkBusy) return;
        const version = ++linkVersion;
        try {
            const data = await postJson(url, 'GET');
            if (version !== linkVersion) return;
            linkedNote = data.nota;
            const task = linkedNote.tarefa;
            linkModal.querySelector('[data-note-link-title]').textContent = linkedNote.title;
            const current = linkModal.querySelector('[data-note-current-task]');
            current.hidden = !task;
            current.textContent = task ? `Vinculada a “${task.title}”. Agendar atualiza esta tarefa; desvincular mantém a nota e a tarefa.` : '';
            linkModal.querySelector('[data-note-link-detach]').hidden = !task;
            const calendar = linkModal.querySelector('[data-note-task-calendar]');
            calendar.hidden = !task;
            calendar.href = task?.url ?? '#';
            existingForm.reset();
            existingForm.elements.namedItem('tarefa_id').replaceChildren(new Option('Carregando tarefas…', ''));
            scheduleForm.reset();
            scheduleForm.elements.namedItem('title').value = task?.title ?? linkedNote.title;
            scheduleForm.elements.namedItem('date').value = task?.date ?? tools.dataset.today;
            ['start_time', 'end_time', 'end_date'].forEach((field) => { scheduleForm.elements.namedItem(field).value = task?.[field] ?? ''; });
            scheduleForm.elements.namedItem('end_date').min = scheduleForm.elements.namedItem('date').value;
            errorMessage(linkError);
            selectLinkTab(tab);
            if (!linkModal.open) linkModal.showModal();
            loadTasks('', task?.id);
        } catch (error) { toast(error.message, true); }
    };
    const loadNotes = async () => {
        const version = ++pickerVersion;
        pickerOptions.textContent = 'Buscando notas…';
        errorMessage(pickerError);
        try {
            const address = new URL(tools.dataset.optionsUrl, window.location.origin);
            address.searchParams.set('q', pickerSearch.value);
            address.searchParams.set('tarefa_id', pickerTask);
            const data = await postJson(address.href, 'GET');
            if (version !== pickerVersion || !picker.open) return;
            pickerOptions.replaceChildren();
            data.options.forEach((option) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.dataset.notePickUrl = option.url;
                button.className = 'rounded-xl border border-white/10 p-3 text-left text-sm text-indigo-200 hover:bg-indigo-500/10';
                button.textContent = option.label;
                pickerOptions.append(button);
            });
            if (!data.options.length) pickerOptions.textContent = 'Nenhuma nota disponível. Crie uma nota ou uma lista diretamente na tarefa.';
        } catch (error) { if (version === pickerVersion) errorMessage(pickerError, error.message); }
    };

    document.addEventListener('click', async (event) => {
        const menu = event.target.closest('[data-note-menu]');
        document.querySelectorAll('[data-note-menu][open]').forEach((item) => { if (item !== menu) item.open = false; });
        const edit = event.target.closest('[data-note-edit]');
        if (edit) { menu?.removeAttribute('open'); await openEditor({ url: edit.dataset.noteEdit }); return; }
        const create = event.target.closest('[data-note-create]');
        if (create) { await openEditor({ type: create.dataset.noteCreate, task: create.dataset.noteTask }); return; }
        const link = event.target.closest('[data-note-link], [data-note-schedule]');
        if (link) { menu?.removeAttribute('open'); await openLink(link.dataset.noteLink || link.dataset.noteSchedule, link.hasAttribute('data-note-schedule') ? 'schedule' : 'existing'); return; }
        const pick = event.target.closest('[data-note-picker]');
        if (pick) {
            pickerTask = Number(pick.dataset.notePicker);
            pickerSearch.value = '';
            picker.showModal();
            loadNotes();
            pickerSearch.focus();
            return;
        }
        const action = event.target.closest('[data-note-action]');
        if (action) {
            menu?.removeAttribute('open');
            action.disabled = true;
            try {
                const data = await postJson(action.dataset.noteUrl, 'PATCH', { [action.dataset.noteAction === 'pin' ? 'pinned' : 'archived']: action.dataset.value === '1' });
                changed(data);
            } catch (error) { toast(error.message, true); }
            finally { action.disabled = false; }
        }
        const remove = event.target.closest('[data-note-delete]');
        if (remove) {
            menu?.removeAttribute('open');
            if (!await confirmDialog('Excluir esta nota e todos os seus itens? A tarefa vinculada será mantida.', { confirmLabel: 'Excluir nota', danger: true })) return;
            remove.disabled = true;
            try { await postJson(remove.dataset.noteDelete, 'DELETE'); changed(); toast('Nota excluída.'); }
            catch (error) { toast(error.message, true); }
            finally { remove.disabled = false; }
        }
        const detach = event.target.closest('[data-note-detach]');
        if (detach) {
            detach.disabled = true;
            try { changed(await postJson(detach.dataset.noteDetach, 'PUT', { tarefa_id: null })); toast('Nota desvinculada.'); }
            catch (error) { toast(error.message, true); }
            finally { detach.disabled = false; }
        }
    });
    document.addEventListener('change', async (event) => {
        const checkbox = event.target.closest('[data-note-item-toggle]');
        if (!checkbox) return;
        checkbox.disabled = true;
        try { changed(await postJson(checkbox.dataset.noteItemToggle, 'PATCH', { completed: checkbox.checked }), { refresh: false }); }
        catch (error) { checkbox.checked = !checkbox.checked; toast(error.message, true); }
        finally { checkbox.disabled = false; }
    });
    editor.addEventListener('cancel', (event) => { event.preventDefault(); closeEditor(); });
    editor.addEventListener('close', () => { ++editorVersion; });
    editor.addEventListener('click', async (event) => {
        if (editorBusy) return;
        if (event.target.closest('[data-note-discard-confirm]')) { editor.close(); return; }
        if (event.target.closest('[data-note-discard-cancel]')) {
            editor.querySelector('[data-note-discard-warning]').hidden = true;
            form.elements.namedItem('title').focus();
            return;
        }
        if (event.target.closest('[data-note-editor-close]')) { await closeEditor(); return; }
        if (event.target.closest('[data-note-editor-link]')) {
            if (dirty()) { errorMessage(editorError, 'Salve a nota antes de vincular ou agendar.'); return; }
            await openLink(currentNote.url);
            return;
        }
        if (event.target.closest('[data-note-add-item]')) { addItem(); return; }
        const row = event.target.closest('[data-note-editor-row]');
        if (!row) return;
        if (event.target.closest('[data-note-row-remove]')) {
            const next = row.nextElementSibling ?? row.previousElementSibling;
            row.remove();
            countItems();
            (next?.querySelector('[data-note-row-text]') ?? editor.querySelector('[data-note-add-item]')).focus();
        }
        if (event.target.closest('[data-note-row-up]') && row.previousElementSibling) row.previousElementSibling.before(row);
        if (event.target.closest('[data-note-row-down]') && row.nextElementSibling) row.nextElementSibling.after(row);
    });
    rows.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && event.target.matches('[data-note-row-text]') && !event.isComposing) {
            event.preventDefault();
            if (!editorBusy) addItem(null, event.target.closest('[data-note-editor-row]'));
        }
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (editorBusy || !form.reportValidity()) return;
        const data = payload();
        if (data.items.some((item) => !item.text)) { errorMessage(editorError, 'Preencha ou remova os itens vazios.'); return; }
        editorBusy = true;
        setDisabled(form, true);
        errorMessage(editorError);
        try {
            const response = await postJson(currentNote?.url ?? tools.dataset.storeUrl, currentNote ? 'PATCH' : 'POST', data);
            editor.close();
            changed(response);
            toast('Nota salva.');
        } catch (error) { errorMessage(editorError, error.message); }
        finally { editorBusy = false; setDisabled(form, false); countItems(); }
    });
    linkModal.addEventListener('cancel', (event) => { if (linkBusy) event.preventDefault(); });
    linkModal.addEventListener('close', () => { ++linkVersion; ++tasksVersion; clearTimeout(taskTimer); });
    linkModal.addEventListener('click', async (event) => {
        if (linkBusy) return;
        if (event.target.closest('[data-note-link-close]')) { linkModal.close(); return; }
        const tab = event.target.closest('[data-note-link-tab]');
        if (tab) selectLinkTab(tab.dataset.noteLinkTab);
        if (event.target.closest('[data-note-link-detach]')) {
            linkBusy = true;
            setDisabled(linkModal, true);
            try { const data = await postJson(linkedNote.link_url, 'PUT', { tarefa_id: null }); linkModal.close(); changed(data); toast('Nota e tarefa mantidas, vínculo removido.'); }
            catch (error) { errorMessage(linkError, error.message); }
            finally { linkBusy = false; setDisabled(linkModal, false); }
        }
    });
    existingForm.querySelector('[data-note-task-search]').addEventListener('input', (event) => {
        clearTimeout(taskTimer);
        ++tasksVersion;
        const query = event.target.value;
        taskTimer = setTimeout(() => loadTasks(query), 250);
    });
    scheduleForm.elements.namedItem('date').addEventListener('input', () => { scheduleForm.elements.namedItem('end_date').min = scheduleForm.elements.namedItem('date').value; });
    linkModal.addEventListener('submit', async (event) => {
        event.preventDefault();
        const target = event.target;
        if (linkBusy || !target.reportValidity()) return;
        const scheduling = target === scheduleForm;
        const values = scheduling ? Object.fromEntries(new FormData(scheduleForm)) : { tarefa_id: Number(existingForm.elements.namedItem('tarefa_id').value) };
        linkBusy = true;
        errorMessage(linkError);
        setDisabled(linkModal, true);
        try {
            const data = await postJson(scheduling ? linkedNote.schedule_url : linkedNote.link_url, scheduling ? 'POST' : 'PUT', values);
            linkModal.close();
            changed(data, { calendarChanged: scheduling });
            toast(scheduling ? 'Tarefa agendada com a nota e seus itens.' : 'Nota vinculada à tarefa.');
            if (scheduling && data.calendar_url) window.location.assign(data.calendar_url);
        } catch (error) { errorMessage(linkError, error.message); }
        finally { linkBusy = false; setDisabled(linkModal, false); }
    });
    picker.addEventListener('cancel', (event) => { if (pickerBusy) event.preventDefault(); });
    picker.addEventListener('close', () => { ++pickerVersion; clearTimeout(pickerTimer); });
    pickerSearch.addEventListener('input', () => { clearTimeout(pickerTimer); ++pickerVersion; pickerTimer = setTimeout(loadNotes, 250); });
    picker.addEventListener('click', async (event) => {
        if (pickerBusy) return;
        if (event.target.closest('[data-note-picker-close]')) { picker.close(); return; }
        const button = event.target.closest('[data-note-pick-url]');
        if (!button) return;
        pickerBusy = true;
        setDisabled(picker, true);
        try { const data = await postJson(button.dataset.notePickUrl, 'PUT', { tarefa_id: pickerTask }); picker.close(); changed(data); toast('Nota vinculada à tarefa.'); }
        catch (error) { errorMessage(pickerError, error.message); }
        finally { pickerBusy = false; setDisabled(picker, false); }
    });
    window.addEventListener('beforeunload', (event) => {
        if (editor.open && dirty()) { event.preventDefault(); event.returnValue = ''; }
    });
}
