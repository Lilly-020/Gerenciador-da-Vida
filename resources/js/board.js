/**
 * Shared "board" behavior for pages like Sonhos and Projetos: a grid of
 * cards, a "+ Adicionar" modal with just a form, inline task adding inside
 * a card, and instant checkbox toggling — all via fetch, with the server
 * rendering the updated card + stats HTML so the Blade partials stay the
 * single source of truth (no duplicated logic in JS).
 */

import { alertDialog, confirmDialog } from './dialog';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

export async function postJson(url, method, body) {
    const response = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: body !== undefined ? JSON.stringify(body) : undefined,
    });

    if (!response.ok) {
        const data = await response.json().catch(() => null);

        throw new Error(data?.message ?? 'Não foi possível concluir a ação. Tente novamente.');
    }

    return response.json();
}

/**
 * Like postJson, but sends a multipart/form-data body (for file uploads).
 * The browser sets the correct Content-Type + boundary automatically as
 * long as we don't set it ourselves.
 */
export async function postForm(url, formData) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: formData,
    });

    if (!response.ok) {
        const data = await response.json().catch(() => null);

        throw new Error(data?.message ?? 'Não foi possível concluir a ação. Tente novamente.');
    }

    return response.json();
}

export function htmlToElement(html) {
    const template = document.createElement('template');
    template.innerHTML = html.trim();

    return template.content.firstElementChild;
}

/**
 * @param {object} options
 * @param {string} options.gridId - id of the element cards get prepended/replaced in.
 * @param {string} options.statsId - id of the analytics tiles element.
 * @param {string} options.modalId - id of the "create" modal.
 * @param {string} options.openButtonId - id of the button that opens the modal.
 * @param {string} options.formId - id of the create form inside the modal.
 */
export function setupBoard({ gridId, statsId, modalId, openButtonId, formId }) {
    function applyUpdate({ card, stats, removeCardId } = {}) {
        if (stats) {
            document.getElementById(statsId)?.replaceWith(htmlToElement(stats));
        }

        if (removeCardId) {
            document.getElementById(removeCardId)?.remove();
        }

        if (card) {
            const newCard = htmlToElement(card);
            const existing = document.getElementById(newCard.id);

            if (existing) {
                existing.replaceWith(newCard);
            } else {
                document.getElementById(gridId)?.prepend(newCard);
            }
        }
    }

    setupGrid(gridId, applyUpdate);
    setupCreateModal({ modalId, openButtonId, formId, applyUpdate });

    return { applyUpdate };
}

function setupGrid(gridId, applyUpdate) {
    const grid = document.getElementById(gridId);

    if (!grid) {
        return;
    }

    grid.addEventListener('change', async (event) => {
        const checkbox = event.target.closest('[data-task-toggle-url]');

        if (!checkbox) {
            return;
        }

        checkbox.disabled = true;

        try {
            const data = await postJson(checkbox.dataset.taskToggleUrl, 'PATCH', { completed: checkbox.checked });
            applyUpdate(data);
        } catch (error) {
            checkbox.checked = !checkbox.checked;
            checkbox.disabled = false;
            alertDialog(error.message);
        }
    });

    grid.addEventListener('click', async (event) => {
        const trigger = event.target.closest('[data-add-task-trigger]');

        if (trigger) {
            const card = trigger.closest('[data-board-card]');
            trigger.classList.add('hidden');
            card?.querySelector('[data-add-task-form]')?.classList.remove('hidden');
            card?.querySelector('[data-add-task-input]')?.focus();

            return;
        }

        const cancel = event.target.closest('[data-add-task-cancel]');

        if (cancel) {
            const card = cancel.closest('[data-board-card]');
            card?.querySelector('[data-add-task-form]')?.classList.add('hidden');
            card?.querySelector('[data-add-task-trigger]')?.classList.remove('hidden');

            return;
        }

        const deleteTask = event.target.closest('[data-task-delete-url]');

        if (deleteTask) {
            const confirmed = await confirmDialog(deleteTask.dataset.confirmMessage || 'Excluir esta tarefa?', {
                confirmLabel: 'Excluir',
                danger: true,
            });

            if (!confirmed) {
                return;
            }

            deleteTask.disabled = true;

            try {
                const data = await postJson(deleteTask.dataset.taskDeleteUrl, 'DELETE');
                applyUpdate(data);
            } catch (error) {
                deleteTask.disabled = false;
                alertDialog(error.message);
            }

            return;
        }

        const deleteCard = event.target.closest('[data-delete-card-url]');

        if (deleteCard) {
            const confirmed = await confirmDialog(
                deleteCard.dataset.confirmMessage || 'Excluir? Essa ação não pode ser desfeita.',
                { confirmLabel: 'Excluir', danger: true },
            );

            if (!confirmed) {
                return;
            }

            deleteCard.disabled = true;

            try {
                const data = await postJson(deleteCard.dataset.deleteCardUrl, 'DELETE');
                applyUpdate(data);
            } catch (error) {
                deleteCard.disabled = false;
                alertDialog(error.message);
            }
        }
    });

    grid.addEventListener('submit', async (event) => {
        const form = event.target.closest('[data-add-task-form]');

        if (!form) {
            return;
        }

        event.preventDefault();

        const input = form.querySelector('[data-add-task-input]');
        const title = input.value.trim();

        if (!title) {
            return;
        }

        const submitButton = form.querySelector('button[type="submit"]');
        submitButton.disabled = true;

        try {
            const data = await postJson(form.action, 'POST', { title });
            applyUpdate(data);
        } catch (error) {
            alertDialog(error.message);
        } finally {
            submitButton.disabled = false;
        }
    });

    setupInlineTaskEdit(grid, applyUpdate);
}

/**
 * Inline "click pencil, edit text, Enter/blur to save" editing for a task
 * row's title — shared by the Sonhos/Projetos board and the Tarefas day
 * list. Expects each row to be marked [data-task-row], wrapping a
 * [data-task-title] element and an edit-trigger button carrying
 * [data-task-edit-url] (the same PATCH endpoint the checkbox toggles).
 *
 * @param {HTMLElement | null} container
 * @param {(data: object) => void} applyUpdate
 */
export function setupInlineTaskEdit(container, applyUpdate) {
    if (!container) {
        return;
    }

    const startEdit = (trigger) => {
        const row = trigger.closest('[data-task-row]');
        const titleEl = row?.querySelector('[data-task-title]');

        if (!row || !titleEl) {
            return;
        }

        const original = titleEl.textContent.trim();
        const input = document.createElement('input');
        input.type = 'text';
        input.value = original;
        input.maxLength = 255;
        input.dataset.taskEditInput = '';
        input.dataset.taskEditUrl = trigger.dataset.taskEditUrl;
        input.dataset.originalTitle = original;
        input.className =
            'min-w-0 flex-1 rounded-md border border-indigo-400/50 bg-slate-950/80 px-2 py-1 text-sm text-white outline-none focus:ring-2 focus:ring-indigo-400/20';

        titleEl.replaceWith(input);
        input.focus();
        input.select();
    };

    const finishEdit = async (input, { save }) => {
        const original = input.dataset.originalTitle ?? '';
        const value = input.value.trim();

        const span = document.createElement('span');
        span.dataset.taskTitle = '';
        span.className = 'wrap-break-word';

        if (!save || !value || value === original) {
            span.textContent = original;
            input.replaceWith(span);

            return;
        }

        span.textContent = value;
        input.replaceWith(span);

        try {
            const data = await postJson(input.dataset.taskEditUrl, 'PATCH', { title: value });
            applyUpdate(data);
        } catch (error) {
            span.textContent = original;
            alertDialog(error.message);
        }
    };

    container.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-task-edit-trigger]');

        if (trigger) {
            startEdit(trigger);
        }
    });

    container.addEventListener('keydown', (event) => {
        const input = event.target.closest('[data-task-edit-input]');

        if (!input) {
            return;
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            input.blur();
        } else if (event.key === 'Escape') {
            event.preventDefault();
            input.dataset.cancelled = '1';
            input.blur();
        }
    });

    container.addEventListener('focusout', (event) => {
        const input = event.target.closest('[data-task-edit-input]');

        if (!input) {
            return;
        }

        finishEdit(input, { save: input.dataset.cancelled !== '1' });
    });
}

export function setupCreateModal({ modalId, openButtonId, formId, applyUpdate }) {
    const modal = document.getElementById(modalId);
    const openButton = document.getElementById(openButtonId);
    const form = document.getElementById(formId);

    if (!modal || !openButton || !form) {
        return;
    }

    const firstField = form.querySelector('input, textarea');

    const open = () => {
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        requestAnimationFrame(() => modal.classList.add('is-open'));
        firstField?.focus();
    };

    const close = () => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        setTimeout(() => modal.classList.add('hidden'), 150);
        form.reset();
    };

    openButton.addEventListener('click', open);

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

        // Let the browser's native validation (required, min, type=date, ...)
        // handle whatever fields this particular form has — this used to
        // hardcode a check for a [name="title"] field, which crashed with a
        // TypeError (and silently did nothing) on any form that doesn't have
        // one, like the Financeiro "nova entrada/saída" and "novo custo
        // fixo" forms.
        if (!form.reportValidity()) {
            return;
        }

        const submitButton = form.querySelector('button[type="submit"]');
        submitButton.disabled = true;

        try {
            const data = await postJson(form.action, 'POST', Object.fromEntries(new FormData(form)));
            applyUpdate(data);
            close();
        } catch (error) {
            alertDialog(error.message);
        } finally {
            submitButton.disabled = false;
        }
    });
}

/**
 * Collapses a card's task list beyond a threshold behind a "Ver mais" toggle.
 * Purely client-side (no request), scoped by event delegation on the grid so
 * it keeps working after a card is replaced with fresh server-rendered HTML.
 */
export function setupTaskListToggle(gridId) {
    const grid = document.getElementById(gridId);

    if (!grid) {
        return;
    }

    grid.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-tasks-toggle]');

        if (!toggle) {
            return;
        }

        const card = toggle.closest('[data-board-card]');
        const isExpanded = toggle.getAttribute('aria-expanded') === 'true';

        card?.querySelectorAll('[data-task-extra]').forEach((el) => {
            el.classList.toggle('hidden', isExpanded);
        });

        toggle.setAttribute('aria-expanded', String(!isExpanded));
        toggle.querySelector('[data-tasks-toggle-label]').textContent = isExpanded ? 'Ver mais' : 'Ver menos';
        toggle.querySelector('[data-tasks-toggle-icon]')?.classList.toggle('rotate-180', !isExpanded);
    });
}
