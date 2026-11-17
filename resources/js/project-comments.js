import { htmlToElement, postJson } from './board';
import { confirmDialog } from './dialog';

export function setupProjectComments(grid) {
    if (!grid) return;
    const states = new WeakMap();
    const drafts = new Map();
    const stateFor = (workspace) => {
        if (!states.has(workspace)) states.set(workspace, { revision: '', sequence: 0, loading: false, saving: false, failures: 0, retryAt: 0 });
        return states.get(workspace);
    };
    const status = (workspace, message) => {
        const label = workspace.querySelector('[data-project-comment-sync]');
        if (label.textContent !== message) label.textContent = message;
    };
    const apply = (workspace, data) => {
        if (!workspace.isConnected) return;
        stateFor(workspace).revision = data.revision;
        if (!data.feed) return;
        const feed = workspace.querySelector('[data-project-comment-feed]');
        const focused = feed.contains(document.activeElement) ? document.activeElement : null;
        const selection = focused?.tagName === 'TEXTAREA' ? [focused.selectionStart, focused.selectionEnd] : null;
        const next = htmlToElement(data.feed);
        feed.querySelectorAll('[data-project-comment]').forEach((comment) => {
            if (!comment.querySelector('[data-project-comment-form="edit"]:not([hidden])')) return;
            const replacement = next.querySelector(`[data-project-comment="${comment.dataset.projectComment}"]`);
            if (replacement) replacement.replaceWith(comment);
            else next.append(comment);
        });
        const scrollTop = feed.scrollTop;
        feed.replaceChildren(...next.childNodes);
        feed.scrollTop = scrollTop;
        if (focused?.isConnected) {
            focused.focus({ preventScroll: true });
            if (selection) focused.setSelectionRange(...selection);
        }
        const history = workspace.querySelector('[data-project-comment-history]');
        history?.replaceWith(htmlToElement(data.history));
        workspace.querySelector('[data-project-comment-count]').textContent = data.count;
    };
    const refresh = async () => {
        const workspace = grid.querySelector('[data-project-detail]:not([hidden]) [data-project-comments]');
        if (!workspace || document.hidden || !navigator.onLine) return;
        const state = stateFor(workspace);
        if (state.loading || state.saving || Date.now() < state.retryAt) return;
        const composer = workspace.querySelector('[data-project-comment-form="create"]');
        const draft = drafts.get(workspace.dataset.syncUrl);
        if (composer && draft && !composer.elements.body.value) {
            composer.elements.body.value = draft.body;
            composer.elements.projeto_task_id.value = draft.task;
        }
        state.loading = true;
        const sequence = ++state.sequence;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 10000);
        try {
            const url = new URL(workspace.dataset.syncUrl, location.origin);
            if (state.revision) url.searchParams.set('revision', state.revision);
            const response = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store', signal: controller.signal });
            if (!response.ok) throw new Error('Não foi possível atualizar os comentários.');
            const data = await response.json();
            if (sequence !== state.sequence || !workspace.isConnected) return;
            apply(workspace, data);
            state.failures = 0;
            state.retryAt = 0;
            status(workspace, 'Atualização automática ativa');
        } catch {
            if (sequence !== state.sequence) return;
            state.failures++;
            state.retryAt = Date.now() + Math.min(30000, 1000 * 2 ** state.failures);
            status(workspace, 'Conexão interrompida. Tentando reconectar…');
        } finally {
            clearTimeout(timeout);
            state.loading = false;
        }
    };
    const rememberDraft = (event) => {
        const form = event.target.closest('[data-project-comment-form="create"]');
        if (!form) return;
        const workspace = form.closest('[data-project-comments]');
        drafts.set(workspace.dataset.syncUrl, { body: form.elements.body.value, task: form.elements.projeto_task_id.value });
    };
    grid.addEventListener('input', rememberDraft);
    grid.addEventListener('change', rememberDraft);
    grid.addEventListener('click', (event) => {
        const comment = event.target.closest('[data-project-comment]');
        if (!comment) return;
        const form = comment.querySelector('[data-project-comment-form="edit"]');
        if (event.target.closest('[data-project-comment-edit]')) {
            form.hidden = false;
            comment.querySelector('[data-project-comment-body]').hidden = true;
            form.elements.body.focus();
        }
        if (event.target.closest('[data-project-comment-cancel]')) {
            form.hidden = true;
            comment.querySelector('[data-project-comment-body]').hidden = false;
            stateFor(comment.closest('[data-project-comments]')).revision = '';
            refresh();
        }
    });
    grid.addEventListener('submit', async (event) => {
        const form = event.target.closest('[data-project-comment-form]');
        if (!form) return;
        event.preventDefault();
        if (!form.reportValidity()) return;
        const workspace = form.closest('[data-project-comments]');
        const state = stateFor(workspace);
        if (state.saving) return;
        const action = form.dataset.projectCommentForm;
        if (action === 'delete' && !await confirmDialog('Excluir este comentário?', { confirmLabel: 'Excluir', danger: true })) return;
        state.saving = true;
        ++state.sequence;
        const button = form.querySelector('button[type="submit"], button:not([type])');
        button.disabled = true;
        const error = form.querySelector('[data-comment-error]');
        if (error) error.hidden = true;
        const editingInput = action === 'edit' ? form.elements.body : null;
        if (editingInput) editingInput.readOnly = true;
        try {
            const values = Object.fromEntries(new FormData(form));
            const data = await postJson(form.action, values._method || 'POST', values);
            if (action === 'create' && form.elements.body.value === values.body && form.elements.projeto_task_id.value === values.projeto_task_id) {
                form.reset();
                drafts.delete(workspace.dataset.syncUrl);
            }
            if (action === 'edit') form.hidden = true;
            apply(workspace, data);
            status(workspace, action === 'delete' ? 'Comentário excluído' : action === 'edit' ? 'Edição salva' : 'Comentário enviado');
        } catch (failure) {
            if (error) { error.textContent = failure.message; error.hidden = false; }
            else status(workspace, failure.message);
        } finally {
            state.saving = false;
            button.disabled = false;
            if (editingInput) editingInput.readOnly = false;
        }
    });
    setInterval(refresh, 1000);
    window.addEventListener('focus', refresh);
    document.addEventListener('visibilitychange', refresh);
    window.addEventListener('online', refresh);
    new MutationObserver(refresh).observe(grid, { childList: true });
    refresh();
}
