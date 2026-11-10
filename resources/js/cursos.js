import { htmlToElement, postForm, postJson, setupCreateModal, setupCardEditModal } from './board';
import { alertDialog, confirmDialog } from './dialog';

export function setupCursos() {
    const board = document.getElementById('cursos-board');
    if (!board) return;

    const columnListFor = (status) => board.querySelector(`[data-column="${status}"] [data-column-cards]`);
    const updateColumnCounts = () => {
        board.querySelectorAll('[data-column]').forEach((column) => {
            const count = [...column.querySelectorAll('[data-board-card]')].filter((card) => card.style.display !== 'none').length;
            const badge = column.querySelector('[data-column-count]');
            if (badge && badge.textContent !== String(count)) badge.textContent = String(count);
        });
    };

    const applyUpdate = ({ card, stats } = {}) => {
        if (stats) document.getElementById('cursos-stats')?.replaceWith(htmlToElement(stats));
        if (card) {
            const next = htmlToElement(card);
            const previous = document.getElementById(next.id);
            previous?.querySelectorAll('[data-course-details][open]').forEach((details) => {
                next.querySelector(`[data-course-details="${details.dataset.courseDetails}"]`)?.setAttribute('open', '');
            });
            if (previous && previous.dataset.status === next.dataset.status) {
                previous.replaceWith(next);
            } else {
                previous?.remove();
                columnListFor(next.dataset.status)?.prepend(next);
            }
            next.draggable = !board.classList.contains('course-list');
        }
        updateColumnCounts();
    };

    const pending = new Set();
    const moveCard = async (card, status) => {
        if (pending.has(card.id)) return;
        pending.add(card.id);
        card.setAttribute('aria-busy', 'true');
        try {
            applyUpdate(await postJson(`/cursos/${card.dataset.cursoId}`, 'PATCH', { status }));
        } catch (error) {
            const select = card.querySelector('[data-status-select]');
            if (select) select.value = card.dataset.status;
            alertDialog(error.message);
        } finally {
            card.removeAttribute('aria-busy');
            pending.delete(card.id);
        }
    };

    board.addEventListener('dragstart', (event) => {
        const card = event.target.closest('[data-board-card]');
        if (!card || board.classList.contains('course-list') || event.target.closest('a, button, input, select, textarea, summary')) {
            event.preventDefault();
            return;
        }
        event.dataTransfer.setData('text/plain', card.id);
        event.dataTransfer.effectAllowed = 'move';
        requestAnimationFrame(() => card.classList.add('opacity-40'));
    });
    board.addEventListener('dragend', (event) => {
        event.target.closest('[data-board-card]')?.classList.remove('opacity-40');
    });
    board.querySelectorAll('[data-column]').forEach((column) => {
        column.addEventListener('dragover', (event) => {
            event.preventDefault();
            column.classList.add('ring-1', 'ring-indigo-400/40');
        });
        column.addEventListener('dragleave', (event) => {
            if (!column.contains(event.relatedTarget)) column.classList.remove('ring-1', 'ring-indigo-400/40');
        });
        column.addEventListener('drop', (event) => {
            event.preventDefault();
            column.classList.remove('ring-1', 'ring-indigo-400/40');
            const card = document.getElementById(event.dataTransfer.getData('text/plain'));
            if (card && board.contains(card) && card.dataset.status !== column.dataset.column) moveCard(card, column.dataset.column);
        });
    });

    board.addEventListener('change', async (event) => {
        const select = event.target.closest('[data-status-select]');
        if (select) {
            const card = select.closest('[data-board-card]');
            if (select.value !== card.dataset.status) moveCard(card, select.value);
            return;
        }
        const input = event.target.closest('[data-file-input]');
        if (!input?.files[0]) return;
        const formData = new FormData();
        formData.append('file', input.files[0]);
        formData.append('kind', input.dataset.fileKind);
        input.disabled = true;
        try {
            applyUpdate(await postForm(input.dataset.uploadUrl, formData));
        } catch (error) {
            alertDialog(error.message);
        } finally {
            input.value = '';
            input.disabled = false;
        }
    });

    board.addEventListener('click', async (event) => {
        const reclassify = event.target.closest('[data-file-kind-url]');
        if (reclassify) {
            reclassify.disabled = true;
            try {
                applyUpdate(await postJson(reclassify.dataset.fileKindUrl, 'PATCH', { kind: reclassify.dataset.nextKind }));
            } catch (error) {
                alertDialog(error.message);
            } finally {
                reclassify.disabled = false;
            }
            return;
        }
        const remove = event.target.closest('[data-remove-file-url]');
        const deleteButton = event.target.closest('[data-delete-trigger]');
        if (!remove && !deleteButton) return;
        const confirmed = await confirmDialog(remove ? 'Remover este anexo?' : 'Excluir este curso? Essa ação não pode ser desfeita.', { confirmLabel: remove ? 'Remover' : 'Excluir', danger: true });
        if (!confirmed) return;
        const button = remove || deleteButton;
        const card = button.closest('[data-board-card]');
        button.disabled = true;
        try {
            const data = await postJson(remove ? remove.dataset.removeFileUrl : `/cursos/${card.dataset.cursoId}`, 'DELETE');
            if (deleteButton) card.remove();
            applyUpdate(data);
        } catch (error) {
            alertDialog(error.message);
        } finally {
            button.disabled = false;
        }
    });

    const hint = document.getElementById('cursos-scroll-hint');
    const setView = (view) => {
        const isList = view === 'list';
        board.classList.toggle('course-list', isList);
        board.querySelectorAll('[data-board-card]').forEach((card) => { card.draggable = !isList; });
        document.querySelectorAll('[data-course-view]').forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.courseView === view)));
        hint?.classList.toggle('hidden', isList);
    };
    let savedView;
    try { savedView = localStorage.getItem('cursos-view'); } catch {}
    const compactScreen = window.matchMedia('(max-width: 639px)');
    setView(savedView === 'list' || savedView === 'kanban' ? savedView : compactScreen.matches ? 'list' : 'kanban');
    compactScreen.addEventListener('change', () => {
        if (!savedView) setView(compactScreen.matches ? 'list' : 'kanban');
    });
    document.querySelectorAll('[data-course-view]').forEach((button) => {
        button.addEventListener('click', () => {
            savedView = button.dataset.courseView;
            setView(savedView);
            try { localStorage.setItem('cursos-view', savedView); } catch {}
        });
    });
    board.addEventListener('filters:updated', (event) => {
        updateColumnCounts();
        document.getElementById('cursos-empty')?.classList.toggle('hidden', event.detail.visible > 0);
    });

    setupCreateModal({ modalId: 'new-curso-modal', openButtonId: 'open-new-curso-modal', formId: 'new-curso-form', applyUpdate });
    setupCardEditModal({ gridId: 'cursos-board', modalId: 'edit-curso-modal', formId: 'edit-curso-form', urlFor: (card) => `/cursos/${card.dataset.cursoId}`, applyUpdate });
}
