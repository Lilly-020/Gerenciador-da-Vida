import { alertDialog } from './dialog';
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
    grid?.addEventListener('submit', async (event) => {
        const form = event.target.closest('[data-project-task-form]');
        if (!form) return;
        event.preventDefault();
        if (!form.reportValidity()) return;
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        try {
            const values = Object.fromEntries(new FormData(form));
            const data = await postJson(form.action, values._method || 'POST', values);
            applyUpdate(data);
        } catch (error) {
            alertDialog(error.message);
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
    grid?.addEventListener('toggle', () => {
        const expanded = [...grid.querySelectorAll('details[data-details-key][open]')].map((details) => `${details.closest('[data-board-card]').id}:${details.dataset.detailsKey}`);
        try { sessionStorage.setItem(storageKey, JSON.stringify(expanded)); } catch {}
    }, true);

    setupCardEditModal({
        gridId: 'projetos-grid',
        modalId: 'edit-projeto-modal',
        formId: 'edit-projeto-form',
        urlFor: (card) => `/projetos/${card.dataset.projetoId}`,
        applyUpdate,
    });
}
