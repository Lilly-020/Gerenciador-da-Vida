import { htmlToElement, postForm, postJson } from './board';
import { confirmDialog } from './dialog';

export function setupProjectFiles(grid) {
    if (!grid) return;
    const busy = new Set();
    const message = (card, kind, text = '') => {
        const element = card.querySelector(`[data-project-file-${kind}]`);
        if (element) { element.textContent = text; element.hidden = !text; }
    };
    const apply = (cardId, data, scroll = false) => {
        const card = document.getElementById(cardId);
        card?.querySelector('[data-project-files]')?.replaceWith(htmlToElement(data.html));
        if (scroll) card?.querySelector('[data-project-section="files"]')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };
    grid.addEventListener('click', async (event) => {
        const attach = event.target.closest('[data-project-attach]');
        if (attach) {
            const card = attach.closest('[data-board-card]');
            if (!busy.has(card.id)) card.querySelector('[data-project-file-input]')?.click();
        }
        const remove = event.target.closest('[data-project-file-delete]');
        if (!remove) return;
        const card = remove.closest('[data-board-card]');
        if (busy.has(card.id) || !await confirmDialog('Remover este anexo do projeto?', { confirmLabel: 'Remover', danger: true })) return;
        busy.add(card.id);
        remove.disabled = true;
        message(card, 'error');
        try {
            apply(card.id, await postJson(remove.dataset.url, 'DELETE'));
        } catch (error) {
            message(card, 'error', error.message);
        } finally {
            busy.delete(card.id);
            remove.disabled = false;
        }
    });
    grid.addEventListener('change', async (event) => {
        const input = event.target.closest('[data-project-file-input]');
        if (!input?.files.length) return;
        const card = input.closest('[data-board-card]');
        if (busy.has(card.id)) return;
        message(card, 'error');
        if (input.files[0].size > 10 * 1024 * 1024) {
            message(card, 'error', 'O arquivo deve ter no máximo 10 MB.');
            input.value = '';
            return;
        }
        busy.add(card.id);
        const button = card.querySelector('[data-project-attach]');
        if (button) button.disabled = true;
        message(card, 'status', 'Enviando anexo…');
        try {
            apply(card.id, await postForm(input.form.action, new FormData(input.form)), true);
        } catch (error) {
            message(card, 'error', error.message);
        } finally {
            busy.delete(card.id);
            if (button) button.disabled = false;
            message(card, 'status');
            input.value = '';
        }
    });
}
