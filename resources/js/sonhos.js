import { setupBoard } from './board';

export function setupSonhos() {
    setupBoard({
        gridId: 'sonhos-grid',
        statsId: 'sonhos-stats',
        modalId: 'new-sonho-modal',
        openButtonId: 'open-new-sonho-modal',
        formId: 'new-sonho-form',
    });
}
