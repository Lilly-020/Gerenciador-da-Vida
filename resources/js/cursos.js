import { htmlToElement, postForm, postJson, setupCreateModal } from './board';
import { alertDialog, confirmDialog } from './dialog';

/**
 * Kanban board for Cursos: drag a card between columns, or use the status
 * select in the card (touch/keyboard fallback) — both call the same PATCH
 * endpoint and swap in the server-rendered card on success. Also handles
 * editing, deleting, attaching files, and the "Ler mais" objective toggle.
 */
export function setupCursos() {
    const board = document.getElementById('cursos-board');

    if (!board) {
        return;
    }

    const columnListFor = (status) => board.querySelector(`[data-column="${status}"] [data-column-cards]`);

    const updateColumnCounts = () => {
        board.querySelectorAll('[data-column]').forEach((column) => {
            const list = column.querySelector('[data-column-cards]');
            const countEl = column.querySelector('[data-column-count]');

            if (list && countEl) {
                countEl.textContent = String(list.children.length);
            }
        });
    };

    const replaceCard = (card, html) => {
        const newCard = htmlToElement(html);
        card.replaceWith(newCard);

        return newCard;
    };

    const moveCard = async (card, status) => {
        const previousParent = card.parentElement;
        const previousNextSibling = card.nextElementSibling;

        columnListFor(status)?.prepend(card);
        updateColumnCounts();

        try {
            const data = await postJson(`/cursos/${card.dataset.cursoId}`, 'PATCH', { status });
            replaceCard(card, data.card);
        } catch (error) {
            card.remove();

            if (previousNextSibling) {
                previousParent.insertBefore(card, previousNextSibling);
            } else {
                previousParent.appendChild(card);
            }

            alertDialog(error.message);
        } finally {
            updateColumnCounts();
        }
    };

    board.addEventListener('dragstart', (event) => {
        const card = event.target.closest('[data-board-card]');

        if (!card) {
            return;
        }

        event.dataTransfer.setData('text/plain', card.id);
        event.dataTransfer.effectAllowed = 'move';
        requestAnimationFrame(() => card.classList.add('opacity-40'));
    });

    board.addEventListener('dragend', (event) => {
        event.target.closest('[data-board-card]')?.classList.remove('opacity-40');
    });

    board.querySelectorAll('[data-column-cards]').forEach((list) => {
        const column = list.closest('[data-column]');

        list.addEventListener('dragover', (event) => {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            column?.classList.add('ring-1', 'ring-indigo-400/40');
        });

        list.addEventListener('dragleave', () => {
            column?.classList.remove('ring-1', 'ring-indigo-400/40');
        });

        list.addEventListener('drop', (event) => {
            event.preventDefault();
            column?.classList.remove('ring-1', 'ring-indigo-400/40');

            const card = document.getElementById(event.dataTransfer.getData('text/plain'));
            const status = column?.dataset.column;

            if (card && status && card.dataset.status !== status) {
                moveCard(card, status);
            }
        });
    });

    // Status select (drag-and-drop fallback for touch/keyboard).
    board.addEventListener('change', (event) => {
        const select = event.target.closest('[data-status-select]');
        const card = select?.closest('[data-board-card]');

        if (select && card && select.value !== card.dataset.status) {
            moveCard(card, select.value);
        }
    });

    // "Ler mais" / "Ler menos" toggle for the objective text.
    board.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-objective-toggle]');

        if (!toggle) {
            return;
        }

        const text = toggle.closest('[data-board-card]')?.querySelector('[data-objective-text]');
        const isExpanded = toggle.getAttribute('aria-expanded') === 'true';

        text?.classList.toggle('line-clamp-3', isExpanded);
        toggle.setAttribute('aria-expanded', String(!isExpanded));
        toggle.textContent = isExpanded ? 'Ler mais' : 'Ler menos';
    });

    // Attach a file (diploma/certificate) — uploads immediately on selection.
    board.addEventListener('change', async (event) => {
        const input = event.target.closest('[data-file-input]');

        if (!input) {
            return;
        }

        const file = input.files[0];

        if (!file) {
            return;
        }

        const card = input.closest('[data-board-card]');
        const formData = new FormData();
        formData.append('file', file);

        try {
            const data = await postForm(input.dataset.uploadUrl, formData);
            replaceCard(card, data.card);
        } catch (error) {
            alertDialog(error.message);
        } finally {
            input.value = '';
        }
    });

    // Remove an attached file.
    board.addEventListener('click', async (event) => {
        const removeButton = event.target.closest('[data-remove-file-url]');

        if (!removeButton) {
            return;
        }

        const confirmed = await confirmDialog('Remover este anexo?', { confirmLabel: 'Remover', danger: true });

        if (!confirmed) {
            return;
        }

        const card = removeButton.closest('[data-board-card]');

        try {
            const data = await postJson(removeButton.dataset.removeFileUrl, 'DELETE');
            replaceCard(card, data.card);
        } catch (error) {
            alertDialog(error.message);
        }
    });

    // Delete a course entirely.
    board.addEventListener('click', async (event) => {
        const deleteButton = event.target.closest('[data-delete-trigger]');

        if (!deleteButton) {
            return;
        }

        const confirmed = await confirmDialog('Excluir este curso? Essa ação não pode ser desfeita.', {
            confirmLabel: 'Excluir',
            danger: true,
        });

        if (!confirmed) {
            return;
        }

        const card = deleteButton.closest('[data-board-card]');

        try {
            await postJson(`/cursos/${card.dataset.cursoId}`, 'DELETE');
            card.remove();
            updateColumnCounts();
        } catch (error) {
            alertDialog(error.message);
        }
    });

    setupCreateModal({
        modalId: 'new-curso-modal',
        openButtonId: 'open-new-curso-modal',
        formId: 'new-curso-form',
        applyUpdate: ({ card } = {}) => {
            if (!card) {
                return;
            }

            const newCard = htmlToElement(card);
            columnListFor(newCard.dataset.status)?.prepend(newCard);
            updateColumnCounts();
        },
    });

    setupEditModal(board, replaceCard);
}

function setupEditModal(board, replaceCard) {
    const modal = document.getElementById('edit-curso-modal');
    const form = document.getElementById('edit-curso-form');

    if (!modal || !form) {
        return;
    }

    const fields = {
        title: form.querySelector('[name="title"]'),
        platform: form.querySelector('[name="platform"]'),
        link: form.querySelector('[name="link"]'),
        objective: form.querySelector('[name="objective"]'),
        starts_at: form.querySelector('[name="starts_at"]'),
        due_at: form.querySelector('[name="due_at"]'),
    };

    let activeCard = null;

    const open = (card) => {
        activeCard = card;
        fields.title.value = card.dataset.title ?? '';
        fields.platform.value = card.dataset.platform ?? '';
        fields.link.value = card.dataset.link ?? '';
        fields.objective.value = card.dataset.objective ?? '';
        fields.starts_at.value = card.dataset.startsAt ?? '';
        fields.due_at.value = card.dataset.dueAt ?? '';

        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        requestAnimationFrame(() => modal.classList.add('is-open'));
        fields.title.focus();
    };

    const close = () => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        setTimeout(() => modal.classList.add('hidden'), 150);
        activeCard = null;
    };

    board.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-edit-trigger]');
        const card = trigger?.closest('[data-board-card]');

        if (card) {
            open(card);
        }
    });

    modal.querySelectorAll('[data-modal-close]').forEach((button) => {
        button.addEventListener('click', close);
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            close();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            close();
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!activeCard || !fields.title.value.trim()) {
            fields.title.focus();

            return;
        }

        const submitButton = form.querySelector('button[type="submit"]');
        submitButton.disabled = true;

        try {
            const data = await postJson(
                `/cursos/${activeCard.dataset.cursoId}`,
                'PUT',
                Object.fromEntries(new FormData(form)),
            );

            replaceCard(activeCard, data.card);
            close();
        } catch (error) {
            alertDialog(error.message);
        } finally {
            submitButton.disabled = false;
        }
    });
}
