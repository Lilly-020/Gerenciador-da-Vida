import { htmlToElement, postJson } from './board';
import { toast } from './selects';

export function setupLifeDashboard() {
    const dashboard = document.getElementById('life-dashboard');
    if (!dashboard) return;

    const grid = dashboard.querySelector('[data-dashboard-grid]');
    const settingsForm = dashboard.querySelector('[data-dashboard-settings]');
    const taskView = dashboard.querySelector('[data-dashboard-task-view]');
    const settingsError = settingsForm.querySelector('[data-dashboard-settings-error]');
    const status = dashboard.querySelector('[data-dashboard-save-status]');
    const createModal = document.getElementById('dashboard-create');
    const createForm = createModal.querySelector('form');
    const createError = createModal.querySelector('[data-dashboard-create-error]');
    const taskPage = document.getElementById('tarefas-page');
    const defaults = JSON.parse(dashboard.querySelector('[data-dashboard-defaults]').textContent);
    let saved = JSON.parse(dashboard.querySelector('[data-dashboard-preferences]').textContent);
    let draft = structuredClone(saved);
    let editing = false;
    let saving = false;
    let dragged = null;
    let refreshVersion = 0;
    let refreshTimer;
    let creating = false;

    const showError = (element, message = '') => {
        element.textContent = message;
        element.hidden = !message;
    };
    const previewTaskView = (view) => {
        taskPage.dispatchEvent(new CustomEvent('tarefas:view', { detail: { view } }));
        dashboard.querySelector('[data-dashboard-task-view-label]').textContent = [...taskView.options].find((option) => option.value === view).textContent;
    };
    const render = () => {
        draft.widgets.forEach((widget) => {
            const block = grid.querySelector(`[data-dashboard-widget="${widget.id}"]`);
            block.hidden = !widget.visible;
            block.dataset.width = widget.width;
            block.classList.toggle('xl:col-span-2', widget.width === 'wide');
            grid.append(block);
            block.querySelector('[data-widget-width]').value = widget.width;
            const toggle = settingsForm.querySelector(`[data-setting-visibility="${widget.id}"]`);
            toggle.setAttribute('aria-pressed', String(widget.visible));
            toggle.classList.toggle('bg-indigo-500/20', widget.visible);
            toggle.classList.toggle('text-slate-500', !widget.visible);
        });
        const visible = draft.widgets.filter((widget) => widget.visible);
        visible.forEach((widget, index) => {
            const block = grid.querySelector(`[data-dashboard-widget="${widget.id}"]`);
            block.querySelector('[data-widget-move="-1"]').disabled = index === 0;
            block.querySelector('[data-widget-move="1"]').disabled = index === visible.length - 1;
        });
        taskView.value = draft.task_view;
        if (editing && taskPage.dataset.view !== draft.task_view) {
            previewTaskView(draft.task_view);
        }
        dashboard.querySelector('[data-dashboard-empty]').hidden = visible.length > 0;
        dashboard.querySelector('[data-dashboard-edit-banner]').hidden = !editing;
        dashboard.querySelectorAll('[data-dashboard-edit-controls]').forEach((controls) => { controls.hidden = !editing; });
        dashboard.classList.toggle('dashboard-editing', editing);
        dashboard.querySelectorAll('[data-dashboard-customize]').forEach((button) => button.setAttribute('aria-expanded', String(editing)));
        status.textContent = editing ? 'Personalizando · alterações ainda não salvas' : 'Personalização salva na sua conta';
        window.dispatchEvent(new Event('resize'));
    };
    const cancel = () => {
        if (saving) return;
        draft = structuredClone(saved);
        previewTaskView(saved.task_view);
        editing = false;
        render();
        showError(settingsError);
    };
    const customize = () => {
        if (saving) return;
        if (!editing) {
            draft = structuredClone(saved);
            editing = true;
        }
        render();
        showError(settingsError);
        settingsForm.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'nearest' });
    };
    const move = (id, direction, onlyVisible = false) => {
        const candidates = draft.widgets.filter((widget) => !onlyVisible || widget.visible);
        const current = candidates.findIndex((widget) => widget.id === id);
        const neighbor = candidates[current + direction];
        if (!neighbor) return;
        const from = draft.widgets.findIndex((widget) => widget.id === id);
        const to = draft.widgets.findIndex((widget) => widget.id === neighbor.id);
        [draft.widgets[from], draft.widgets[to]] = [draft.widgets[to], draft.widgets[from]];
        render();
        status.textContent = `${grid.querySelector(`[data-dashboard-widget="${id}"] h2`).textContent} reposicionado. Salve para manter.`;
    };
    const refresh = async () => {
        const version = ++refreshVersion;
        const data = await postJson(dashboard.dataset.refreshUrl, 'GET');
        if (version !== refreshVersion) return;
        document.getElementById('dashboard-summary').replaceWith(htmlToElement(data.summary));
        Object.entries(data.widgets).forEach(([id, content]) => {
            dashboard.querySelector(`[data-dashboard-content="${id}"]`).innerHTML = content;
        });
    };

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-dashboard-customize]')) customize();
    });
    settingsForm.addEventListener('click', (event) => {
        if (saving) return;
        if (event.target.closest('[data-dashboard-cancel]')) { cancel(); return; }
        if (event.target.closest('[data-dashboard-reset]')) { draft = structuredClone(defaults); render(); return; }
        const toggle = event.target.closest('[data-setting-visibility]');
        if (toggle) {
            const widget = draft.widgets.find((item) => item.id === toggle.dataset.settingVisibility);
            widget.visible = !widget.visible;
            render();
        }
    });
    dashboard.addEventListener('change', (event) => {
        if (!editing || saving) return;
        if (event.target === taskView) draft.task_view = taskView.value;
        const width = event.target.closest('[data-widget-width]');
        if (width) {
            const id = width.closest('[data-dashboard-widget]').dataset.dashboardWidget;
            draft.widgets.find((widget) => widget.id === id).width = width.value;
        }
        if (event.target === taskView || width) {
            render();
            event.target.focus();
        }
    });
    settingsForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (saving) return;
        saving = true;
        showError(settingsError);
        const controls = [...settingsForm.querySelectorAll('button'), ...dashboard.querySelectorAll('[data-dashboard-edit-controls] button, [data-dashboard-edit-controls] select')];
        controls.forEach((control) => { control.disabled = true; });
        try {
            const data = await postJson(dashboard.dataset.preferencesUrl, 'PUT', draft);
            saved = data.preferences;
            draft = structuredClone(saved);
            editing = false;
            render();
            toast('Dashboard personalizada. Tudo salvo na sua conta.');
            const address = new URL(window.location.href);
            address.searchParams.delete('view');
            window.history.replaceState({}, '', address);
        } catch (error) {
            showError(settingsError, error.message);
        } finally {
            saving = false;
            controls.forEach((control) => { control.disabled = false; });
            render();
        }
    });

    dashboard.addEventListener('click', async (event) => {
        if (event.target.closest('[data-dashboard-new-task]')) {
            taskPage.dispatchEvent(new CustomEvent('tarefas:create'));
            return;
        }
        const refreshButton = event.target.closest('[data-dashboard-refresh]');
        if (refreshButton) {
            refreshButton.disabled = true;
            try {
                await refresh();
                taskPage.dispatchEvent(new CustomEvent('tarefas:refresh'));
                toast('Dashboard atualizada.');
            } catch (error) { toast(error.message, true); }
            finally { refreshButton.disabled = false; }
            return;
        }
        const createButton = event.target.closest('[data-dashboard-create]');
        if (createButton) {
            const titles = { sonhos: 'Novo sonho', projetos: 'Novo projeto', cursos: 'Novo curso' };
            createForm.dataset.kind = createButton.dataset.dashboardCreate;
            createForm.action = createButton.dataset.dashboardStoreUrl;
            createModal.querySelector('h2').textContent = titles[createForm.dataset.kind];
            createForm.reset();
            showError(createError);
            createModal.showModal();
            createForm.elements.namedItem('title').focus();
            return;
        }
        if (!editing || saving) return;
        const block = event.target.closest('[data-dashboard-widget]');
        if (!block) return;
        const moveButton = event.target.closest('[data-widget-move]');
        if (moveButton) {
            move(block.dataset.dashboardWidget, Number(moveButton.dataset.widgetMove), true);
            moveButton.focus();
        }
        if (event.target.closest('[data-widget-hide]')) {
            draft.widgets.find((widget) => widget.id === block.dataset.dashboardWidget).visible = false;
            render();
            dashboard.querySelector('[data-dashboard-customize]').focus();
        }
    });
    grid.addEventListener('dragstart', (event) => {
        const handle = event.target.closest('[data-widget-drag]');
        if (!handle || !editing || saving) return;
        dragged = handle.closest('[data-dashboard-widget]').dataset.dashboardWidget;
        event.dataTransfer.setData('text/plain', dragged);
        event.dataTransfer.effectAllowed = 'move';
        handle.closest('[data-dashboard-widget]').classList.add('dashboard-dragging');
    });
    grid.addEventListener('dragover', (event) => {
        if (!dragged) return;
        const target = event.target.closest('[data-dashboard-widget]');
        if (!target || target.dataset.dashboardWidget === dragged) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        grid.querySelectorAll('.dashboard-drop-target').forEach((block) => block.classList.remove('dashboard-drop-target'));
        target.classList.add('dashboard-drop-target');
    });
    grid.addEventListener('drop', (event) => {
        if (!dragged || !editing || saving) return;
        event.preventDefault();
        const target = event.target.closest('[data-dashboard-widget]');
        if (!target || target.dataset.dashboardWidget === dragged) return;
        const from = draft.widgets.findIndex((widget) => widget.id === dragged);
        const to = draft.widgets.findIndex((widget) => widget.id === target.dataset.dashboardWidget);
        const [widget] = draft.widgets.splice(from, 1);
        draft.widgets.splice(to, 0, widget);
        render();
    });
    grid.addEventListener('dragend', () => {
        dragged = null;
        grid.querySelectorAll('.dashboard-dragging, .dashboard-drop-target').forEach((block) => block.classList.remove('dashboard-dragging', 'dashboard-drop-target'));
    });
    window.addEventListener('beforeunload', (event) => {
        if (editing && JSON.stringify(draft) !== JSON.stringify(saved)) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    document.addEventListener('tarefas:updated', () => {
        clearTimeout(refreshTimer);
        refreshTimer = setTimeout(() => refresh().catch((error) => toast(`Não foi possível atualizar os resumos: ${error.message}`, true)), 250);
    });
    createModal.addEventListener('cancel', (event) => { if (creating) event.preventDefault(); });
    createModal.addEventListener('click', (event) => {
        if (!creating && event.target.closest('[data-dashboard-create-close]')) createModal.close();
    });
    createForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (creating || !createForm.reportValidity()) return;
        creating = true;
        const controls = [...createForm.querySelectorAll('button')];
        controls.forEach((button) => { button.disabled = true; });
        showError(createError);
        try {
            await postJson(createForm.action, 'POST', { title: createForm.elements.namedItem('title').value });
            createModal.close();
            toast('Criado. Mais um passo no seu caminho!');
            await refresh().catch(() => toast('Cadastro salvo. Use Atualizar para recarregar os resumos.', true));
        } catch (error) { showError(createError, error.message); }
        finally { creating = false; controls.forEach((button) => { button.disabled = false; }); }
    });
    render();
}
