/**
 * Custom confirm/alert dialogs that match the app's existing modal style
 * (same .board-modal / .modal-panel classes and open/close animation as
 * every other modal), used in place of the browser's native confirm()/
 * alert() — which look jarring next to the rest of the UI and block the
 * whole tab while open.
 *
 * A single dialog root is created lazily and reused for every call, since
 * only one confirm/alert can reasonably be on screen at a time.
 */

let root = null;
let settle = null;

function ensureRoot() {
    if (root) {
        return root;
    }

    root = document.createElement('div');
    root.id = 'app-dialog-root';
    root.className = 'board-modal fixed inset-0 z-[100] hidden bg-black/60 backdrop-blur-sm';
    root.setAttribute('aria-hidden', 'true');
    root.setAttribute('role', 'alertdialog');
    root.innerHTML = `
        <div class="flex min-h-full items-center justify-center px-4 py-8">
            <div class="modal-panel w-full max-w-sm rounded-2xl border border-white/10 bg-slate-900/95 p-6 shadow-2xl shadow-black/40">
                <p data-dialog-message class="wrap-break-word text-sm leading-relaxed text-slate-200"></p>
                <div data-dialog-actions class="mt-5 flex justify-end gap-2"></div>
            </div>
        </div>
    `;
    document.body.appendChild(root);

    root.addEventListener('click', (event) => {
        if (event.target === root) {
            resolveWith(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && root.classList.contains('is-open')) {
            resolveWith(false);
        }
    });

    return root;
}

function resolveWith(value) {
    if (!settle) {
        return;
    }

    const currentSettle = settle;
    settle = null;

    root.classList.remove('is-open');
    root.setAttribute('aria-hidden', 'true');
    setTimeout(() => root.classList.add('hidden'), 150);

    currentSettle(value);
}

function button(label, className) {
    const el = document.createElement('button');
    el.type = 'button';
    el.textContent = label;
    el.className = className;

    return el;
}

const SECONDARY_BUTTON = 'rounded-full border border-white/10 px-4 py-2 text-sm text-slate-300 transition hover:text-white';
const PRIMARY_BUTTON = 'rounded-full bg-indigo-500/90 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-indigo-900/40 transition hover:bg-indigo-500';
const DANGER_BUTTON = 'rounded-full bg-rose-500/90 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-rose-900/40 transition hover:bg-rose-500';

function open(message, buttons) {
    const dialogRoot = ensureRoot();

    // If a dialog is already open (shouldn't normally happen), resolve it
    // as "cancelled" before replacing it with the new one.
    resolveWith(false);

    dialogRoot.querySelector('[data-dialog-message]').textContent = message;

    const actions = dialogRoot.querySelector('[data-dialog-actions]');
    actions.replaceChildren(...buttons);

    dialogRoot.classList.remove('hidden');
    dialogRoot.setAttribute('aria-hidden', 'false');
    requestAnimationFrame(() => dialogRoot.classList.add('is-open'));

    return dialogRoot;
}

/**
 * Replaces `window.confirm(message)`. Resolves to true/false.
 *
 * @param {string} message
 * @param {{confirmLabel?: string, cancelLabel?: string, danger?: boolean}} [options]
 */
export function confirmDialog(message, { confirmLabel = 'Confirmar', cancelLabel = 'Cancelar', danger = false } = {}) {
    return new Promise((resolve) => {
        const cancelButton = button(cancelLabel, SECONDARY_BUTTON);
        const confirmButton = button(confirmLabel, danger ? DANGER_BUTTON : PRIMARY_BUTTON);

        cancelButton.addEventListener('click', () => resolveWith(false));
        confirmButton.addEventListener('click', () => resolveWith(true));

        settle = resolve;
        open(message, [cancelButton, confirmButton]);
        confirmButton.focus();
    });
}

/**
 * Replaces `window.alert(message)`. Resolves once dismissed.
 *
 * @param {string} message
 * @param {{okLabel?: string}} [options]
 */
export function alertDialog(message, { okLabel = 'OK' } = {}) {
    return new Promise((resolve) => {
        const okButton = button(okLabel, PRIMARY_BUTTON);

        okButton.addEventListener('click', () => resolveWith(true));

        settle = resolve;
        open(message, [okButton]);
        okButton.focus();
    });
}
