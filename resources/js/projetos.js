import { setupBoard, setupTaskListToggle } from './board';

export function setupProjetos() {
    setupBoard({
        gridId: 'projetos-grid',
        statsId: 'projetos-stats',
        modalId: 'new-projeto-modal',
        openButtonId: 'open-new-projeto-modal',
        formId: 'new-projeto-form',
    });

    setupTaskListToggle('projetos-grid');
}
