import { setupProjectFiles } from './project-files';
import { setupProjectLabels } from './project-labels';
import { setupProjectComments } from './project-comments';
import { alertDialog } from './dialog';
import { toast } from './selects';
import { setupBoard, setupCardEditModal, postJson } from './board';

export function setupProjetos() {
    const { applyUpdate } = setupBoard({
        gridId: 'projetos-grid',
        statsId: 'projetos-stats',
        modalId: 'new-projeto-modal',
        openButtonId: 'open-new-projeto-modal',
        formId: 'new-projeto-form',
    });

    const grid = document.getElementById('projetos-grid');
    setupProjectDetails(grid);
    setupProjectGoalFields(grid);
    setupProjectComments(grid);
    setupProjectLabels(grid);
    setupProjectFiles(grid);
    grid?.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-project-calendar-toggle]');
        if (!toggle) return;
        const panel = document.getElementById(toggle.getAttribute('aria-controls'));
        if (!panel) return;
        panel.hidden = !panel.hidden;
        toggle.setAttribute('aria-expanded', String(!panel.hidden));
    });
    grid?.addEventListener('submit', async (event) => {
        const form = event.target.closest('[data-project-task-form]');
        if (!form) return;
        event.preventDefault();
        if (!form.reportValidity()) return;
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        const calendarError = form.querySelector('[data-project-calendar-error]');
        if (calendarError) calendarError.hidden = true;
        try {
            const values = Object.fromEntries(new FormData(form));
            const data = await postJson(form.action, values._method || 'POST', values);
            const createTaskDetails = form.closest('details[data-details-key="new-task"]');
            if (createTaskDetails) {
                createTaskDetails.open = false;
                persistOpenDetails();
            }
            applyUpdate(data);
            if (form.hasAttribute('data-project-task-calendar')) {
                toast('Tarefa salva no calendário.');
                try { localStorage.setItem('tarefas:last-change', `${Date.now()}-${Math.random()}`); } catch {}
            }
        } catch (error) {
            if (calendarError) { calendarError.textContent = error.message; calendarError.hidden = false; }
            else alertDialog(error.message);
        } finally {
            button.disabled = false;
        }
    });

    const storageKey = 'project-open-details';
    let openDetails = [];
    try { openDetails = JSON.parse(sessionStorage.getItem(storageKey) || '[]'); } catch {}
    if (Array.isArray(openDetails)) {
        openDetails.forEach((id) => {
            const [cardId, key] = id.split(':');
            document.getElementById(cardId)?.querySelectorAll('details[data-details-key]').forEach((details) => {
                if (details.dataset.detailsKey === key) details.open = true;
            });
        });
    }
    const persistOpenDetails = () => {
        const expanded = [...grid.querySelectorAll('details[data-details-key][open]')].map((details) => `${details.closest('[data-board-card]').id}:${details.dataset.detailsKey}`);
        try { sessionStorage.setItem(storageKey, JSON.stringify(expanded)); } catch {}
    };
    grid?.addEventListener('toggle', persistOpenDetails, true);

    setupCardEditModal({
        gridId: 'projetos-grid',
        modalId: 'edit-projeto-modal',
        formId: 'edit-projeto-form',
        urlFor: (card) => `/projetos/${card.dataset.projetoId}`,
        applyUpdate,
    });
}

function setupProjectDetails(grid) {
    if (!grid) return;

    const storageKey = 'project-active-detail';
    let activeId = null;
    let activeTab = 'comments';
    let mobileConversation = false;
    let lastTrigger = null;
    const scrollPositions = new Map();
    try { activeId = sessionStorage.getItem(storageKey); } catch {}

    const activePanel = () => activeId ? document.getElementById(activeId)?.querySelector('[data-project-detail]') : null;
    const anotherModalOpen = () => document.querySelector('.board-modal.is-open, dialog[open]');
    const selectTab = (panel, tab) => {
        activeTab = tab;
        panel.querySelectorAll('[data-project-tab]').forEach((button) => {
            const selected = button.dataset.projectTab === tab;
            button.setAttribute('aria-selected', String(selected));
            button.tabIndex = selected ? 0 : -1;
        });
        panel.querySelectorAll('[data-project-tab-panel]').forEach((section) => {
            section.hidden = section.dataset.projectTabPanel !== tab;
        });
    };
    const close = () => {
        const panel = activePanel();
        if (panel) panel.hidden = true;
        activeId = null;
        scrollPositions.clear();
        document.documentElement.classList.remove('project-detail-open');
        try { sessionStorage.removeItem(storageKey); } catch {}
        if (lastTrigger?.isConnected) lastTrigger.focus();
    };
    const reveal = (focus = false) => {
        const panel = activePanel();
        if (!panel) { close(); return; }
        panel.hidden = false;
        panel.querySelector('.project-detail-body').toggleAttribute('data-mobile-conversation', mobileConversation);
        selectTab(panel, activeTab);
        document.documentElement.classList.add('project-detail-open');
        panel.querySelectorAll('[data-project-scroll]').forEach((section) => {
            section.scrollTop = scrollPositions.get(section.dataset.projectScroll) || 0;
        });
        if (focus) panel.querySelector('[data-project-close]')?.focus();
    };

    grid.addEventListener('click', (event) => {
        const openButton = event.target.closest('[data-project-open]');
        if (openButton) {
            activeId = openButton.closest('[data-board-card]').id;
            activeTab = 'comments';
            mobileConversation = false;
            scrollPositions.clear();
            lastTrigger = openButton;
            try { sessionStorage.setItem(storageKey, activeId); } catch {}
            reveal(true);
            return;
        }
        const panel = event.target.closest('[data-project-detail]');
        if (!panel) return;
        if (event.target.closest('[data-project-mobile-comments], [data-project-mobile-back]')) {
            mobileConversation = Boolean(event.target.closest('[data-project-mobile-comments]'));
            panel.querySelector('.project-detail-body').toggleAttribute('data-mobile-conversation', mobileConversation);
            const target = mobileConversation ? '[data-project-tab="comments"]' : '[data-project-mobile-comments]';
            panel.querySelector(target)?.focus();
        }
        if (event.target === panel || event.target.closest('[data-project-close]')) { close(); return; }
        const tab = event.target.closest('[data-project-tab]');
        if (tab) selectTab(panel, tab.dataset.projectTab);
        const jump = event.target.closest('[data-project-jump]');
        if (jump) {
            const section = panel.querySelector(`[data-project-section="${jump.dataset.projectJump}"]`);
            if (section?.tagName === 'DETAILS') section.open = true;
            if (jump.dataset.projectJump === 'participants') section?.querySelector('details')?.setAttribute('open', '');
            section?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
    grid.addEventListener('scroll', (event) => {
        if (event.target.matches('[data-project-scroll]')) scrollPositions.set(event.target.dataset.projectScroll, event.target.scrollTop);
    }, true);
    document.addEventListener('keydown', (event) => {
        const panel = activePanel();
        if (!panel || panel.hidden || anotherModalOpen()) return;
        if (event.key === 'Escape') { event.preventDefault(); close(); return; }
        const tab = event.target.closest('[data-project-tab]');
        if (tab && ['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
            event.preventDefault();
            const next = event.key === 'Home' ? 'comments' : event.key === 'End' ? 'history' : activeTab === 'comments' ? 'history' : 'comments';
            selectTab(panel, next);
            panel.querySelector(`[data-project-tab="${next}"]`)?.focus();
        }
        if (event.key !== 'Tab' || !panel.contains(event.target)) return;
        const focusable = [...panel.querySelectorAll('button, a[href], input, textarea, select, summary, [tabindex="0"]')]
            .filter((element) => !element.disabled && element.tabIndex >= 0 && element.getClientRects().length);
        const first = focusable[0];
        const last = focusable.at(-1);
        if (event.shiftKey && event.target === first) { event.preventDefault(); last?.focus(); }
        else if (!event.shiftKey && event.target === last) { event.preventDefault(); first?.focus(); }
    });
    new MutationObserver(() => { if (activeId) reveal(); }).observe(grid, { childList: true });
    if (activeId) reveal(true);
}

function setupProjectGoalFields(grid) {
    if (!grid) return;
    const initialized = new WeakSet();
    const sync = (fields) => {
        const type = fields.querySelector('[name="goal_type"]').value;
        fields.querySelectorAll('[data-project-goal-field]').forEach((field) => {
            const kind = field.dataset.projectGoalField;
            const visible = type !== 'checklist' && (kind !== 'unit' || type === 'numeric');
            field.hidden = !visible;
            field.querySelectorAll('input').forEach((input) => {
                input.disabled = !visible;
                input.required = visible && kind !== 'unit' && !input.hidden && input.type !== 'hidden';
            });
            const label = field.querySelector('[data-project-goal-label]');
            if (label) label.textContent = kind === 'target'
                ? (type === 'financial' ? 'Meta financeira (R$)' : 'Meta a atingir')
                : (type === 'financial' ? 'Valor acumulado (R$)' : 'Quantidade atual');
        });
    };
    const scan = () => grid.querySelectorAll('[data-project-goal-fields]').forEach((fields) => {
        if (initialized.has(fields)) return;
        initialized.add(fields);
        sync(fields);
    });
    grid.addEventListener('change', (event) => {
        if (event.target.matches('[name="goal_type"]')) sync(event.target.closest('[data-project-goal-fields]'));
    });
    scan();
    new MutationObserver(scan).observe(grid, { childList: true, subtree: true });
}
