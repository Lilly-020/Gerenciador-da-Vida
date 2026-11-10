import { setupBoard, setupCardEditModal } from './board';

export function setupSonhos() {
    const grid = document.getElementById('sonhos-grid');
    if (!grid) return;
    const params = new URLSearchParams(window.location.search);
    const status = params.get('status') || '';
    const sort = params.get('sort');
    const syncCards = () => {
        const cards = [...grid.querySelectorAll('[data-board-card]')];
        cards.forEach((card) => {
            card.hidden = status ? card.dataset.status !== status : card.dataset.status === 'archived';
        });
        const ordered = [...cards].sort((a, b) => {
            if (sort === 'priority') {
                const ranks = { high: 0, medium: 1, low: 2 };
                return ranks[a.dataset.priority] - ranks[b.dataset.priority];
            }
            if (sort === 'target_date') return (a.dataset.targetDate || '9999').localeCompare(b.dataset.targetDate || '9999');
            return Number(b.dataset.sonhoId) - Number(a.dataset.sonhoId);
        });
        if (ordered.some((card, index) => card !== cards[index])) ordered.forEach((card) => grid.append(card));
        let empty = grid.querySelector('[data-sonhos-empty]');
        if (!empty) {
            empty = document.createElement('p');
            empty.dataset.sonhosEmpty = '';
            empty.className = 'p-6 text-center text-sm text-slate-400';
            empty.textContent = 'Nenhum sonho nesta situação.';
            grid.append(empty);
        }
        empty.hidden = cards.some((card) => !card.hidden);
    };
    new MutationObserver(syncCards).observe(grid, { childList: true });
    syncCards();
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
