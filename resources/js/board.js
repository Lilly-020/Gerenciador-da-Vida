/**
 * Shared "board" behavior for pages like Sonhos and Projetos: a grid of
 * cards, a "+ Adicionar" modal with just a form, inline task adding inside
 * a card, and instant checkbox toggling — all via fetch, with the server
 * rendering the updated card + stats HTML so the Blade partials stay the
 * single source of truth (no duplicated logic in JS).
 */

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
    function applyUpdate({ card, stats } = {}) {
        if (stats) {
            document.getElementById(statsId)?.replaceWith(htmlToElement(stats));
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
            alert(error.message);
        }
    });

    grid.addEventListener('click', (event) => {
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
            alert(error.message);
        } finally {
            submitButton.disabled = false;
        }
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

        const titleField = form.querySelector('[name="title"]');

        if (!titleField.value.trim()) {
            titleField.focus();

            return;
        }

        const submitButton = form.querySelector('button[type="submit"]');
        submitButton.disabled = true;

        try {
            const data = await postJson(form.action, 'POST', Object.fromEntries(new FormData(form)));
            applyUpdate(data);
            close();
        } catch (error) {
            alert(error.message);
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
