import { postJson } from './board';

export function setupProjectLabels(grid) {
    const dialog = document.getElementById('project-label-dialog');
    if (!grid || !dialog) return;
    const list = dialog.querySelector('[data-project-label-list]');
    const search = dialog.querySelector('[data-project-label-search]');
    const error = dialog.querySelector('[data-project-label-error]');
    const form = dialog.querySelector('[data-project-label-form]');
    let projectUrl = '';
    let cardId = '';
    let editingUrl = '';
    let generation = 0;
    let busy = false;

    const filter = () => {
        const normalize = (text) => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        const term = normalize(search.value.trim());
        const rows = [...list.querySelectorAll('[data-project-label-row]')];
        rows.forEach((row) => { row.hidden = !normalize(row.dataset.name).includes(term); });
        dialog.querySelector('[data-project-label-no-results]').hidden = !rows.length || rows.some((row) => !row.hidden);
    };
    const apply = (data) => {
        list.innerHTML = data.list;
        dialog.querySelector('[data-project-label-editor]').hidden = !data.canEdit;
        document.getElementById(cardId)?.querySelectorAll('[data-project-label-badges]').forEach((badges) => { badges.innerHTML = data.badges; });
        filter();
    };
    const showError = (message) => { error.textContent = message; error.hidden = false; };
    const reset = () => {
        form.reset();
        form.hidden = true;
        editingUrl = '';
        dialog.querySelector('[data-project-label-form-title]').textContent = 'Nova etiqueta';
    };
    const save = async (url, method, values) => {
        if (busy) return false;
        busy = true;
        error.hidden = true;
        const current = generation;
        const controls = [...dialog.querySelectorAll('button:not([data-project-label-close]), input')];
        const disabled = controls.map((control) => control.disabled);
        controls.forEach((control) => { control.disabled = true; });
        try {
            const data = await postJson(url, method, values);
            if (current !== generation) return false;
            apply(data);
            return true;
        } catch (failure) {
            if (current === generation) showError(failure.message);
            return false;
        } finally {
            if (current === generation) {
                busy = false;
                controls.forEach((control, index) => { if (control.isConnected) control.disabled = disabled[index]; });
            }
        }
    };
    grid.addEventListener('click', async (event) => {
        const trigger = event.target.closest('[data-project-labels-open]');
        if (!trigger) return;
        projectUrl = trigger.dataset.url;
        cardId = trigger.closest('[data-board-card]').id;
        reset();
        search.value = '';
        error.hidden = true;
        list.textContent = 'Carregando etiquetas…';
        dialog.querySelector('[data-project-label-editor]').hidden = true;
        const current = ++generation;
        dialog.showModal();
        try {
            const response = await fetch(projectUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!response.ok) throw new Error('Não foi possível carregar as etiquetas.');
            const data = await response.json();
            if (current === generation) apply(data);
        } catch (failure) {
            if (current === generation) { list.textContent = ''; showError(failure.message); }
        }
    });
    dialog.querySelector('[data-project-label-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
        generation++;
        busy = false;
        dialog.querySelectorAll('button, input').forEach((control) => { control.disabled = false; });
    });
    search.addEventListener('input', filter);
    dialog.querySelector('[data-project-label-new]').addEventListener('click', () => {
        reset();
        form.hidden = false;
        form.elements.name.focus();
    });
    dialog.querySelector('[data-project-label-cancel]').addEventListener('click', reset);
    list.addEventListener('change', async (event) => {
        const toggle = event.target.closest('[data-project-label-toggle]');
        if (!toggle) return;
        const checked = toggle.checked;
        if (!await save(toggle.closest('[data-project-label-row]').dataset.url, 'PATCH', { selected: checked })) toggle.checked = !checked;
    });
    list.addEventListener('click', async (event) => {
        const row = event.target.closest('[data-project-label-row]');
        if (!row) return;
        if (event.target.closest('[data-project-label-edit]')) {
            editingUrl = row.dataset.url;
            form.hidden = false;
            form.elements.name.value = row.dataset.name;
            form.elements.color.value = row.dataset.color;
            dialog.querySelector('[data-project-label-form-title]').textContent = 'Editar etiqueta';
            form.elements.name.focus();
        }
        const remove = event.target.closest('[data-project-label-delete]');
        if (remove) {
            if (remove.dataset.confirmed) {
                if (await save(row.dataset.url, 'DELETE') && editingUrl === row.dataset.url) reset();
                return;
            }
            const original = remove.innerHTML;
            remove.dataset.confirmed = 'true';
            remove.textContent = 'Excluir?';
            const cancel = document.createElement('button');
            cancel.type = 'button';
            cancel.className = 'text-xs text-slate-400';
            cancel.textContent = 'Cancelar';
            cancel.addEventListener('click', () => { delete remove.dataset.confirmed; remove.innerHTML = original; cancel.remove(); });
            remove.after(cancel);
        }
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!form.reportValidity()) return;
        const values = Object.fromEntries(new FormData(form));
        if (await save(editingUrl || projectUrl, editingUrl ? 'PATCH' : 'POST', values)) reset();
    });
}
