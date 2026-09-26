import { setupBoard, setupCardEditModal, setupTaskListToggle } from './board';

export function setupProjetos() {
    const { applyUpdate } = setupBoard({
        gridId: 'projetos-grid',
        statsId: 'projetos-stats',
        modalId: 'new-projeto-modal',
        openButtonId: 'open-new-projeto-modal',
        formId: 'new-projeto-form',
    });

    setupTaskListToggle('projetos-grid');

    setupCardEditModal({
        gridId: 'projetos-grid',
        modalId: 'edit-projeto-modal',
        formId: 'edit-projeto-form',
        urlFor: (card) => `/projetos/${card.dataset.projetoId}`,
        applyUpdate,
    });
}
