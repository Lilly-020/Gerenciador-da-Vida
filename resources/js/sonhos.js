import { setupBoard, setupCardEditModal } from './board';

export function setupSonhos() {
    const { applyUpdate } = setupBoard({
        gridId: 'sonhos-grid',
        statsId: 'sonhos-stats',
        modalId: 'new-sonho-modal',
        openButtonId: 'open-new-sonho-modal',
        formId: 'new-sonho-form',
    });

    setupCardEditModal({
        gridId: 'sonhos-grid',
        modalId: 'edit-sonho-modal',
        formId: 'edit-sonho-form',
        urlFor: (card) => `/sonhos/${card.dataset.sonhoId}`,
        applyUpdate,
    });
}
