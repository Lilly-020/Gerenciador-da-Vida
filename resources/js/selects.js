import { postJson } from './board';
import { confirmDialog } from './dialog';

const enhanced = new WeakMap();
let active = null;
let sequence = 0;
const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

export function toast(message, error = false) {
    let stack = document.getElementById('app-toasts');
    if (!stack) {
        stack = document.createElement('div');
        stack.id = 'app-toasts';
        stack.className = 'app-toasts';
        stack.setAttribute('aria-live', 'polite');
        document.body.append(stack);
    }
    const notice = document.createElement('div');
    notice.className = `app-toast ${error ? 'app-toast-error' : ''}`;
    notice.setAttribute('role', error ? 'alert' : 'status');
    const text = document.createElement('span');
    text.textContent = message;
    const close = document.createElement('button');
    close.type = 'button';
    close.textContent = '×';
    close.setAttribute('aria-label', 'Fechar aviso');
    close.addEventListener('click', () => notice.remove());
    notice.append(text, close);
    stack.append(notice);
    setTimeout(() => notice.remove(), error ? 9000 : 5000);
}

export function setupSelects() {
    const scan = () => document.querySelectorAll('select').forEach((select) => {
        if (!enhanced.has(select)) enhanced.set(select, enhance(select));
    });
    scan();
    new MutationObserver((records) => {
        if (records.some((record) => record.addedNodes.length)) scan();
    }).observe(document.body, { childList: true, subtree: true });
    document.addEventListener('pointerdown', (event) => {
        if (active && !active.panel.contains(event.target) && !active.button.contains(event.target)) active.close();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && active) {
            event.preventDefault();
            event.stopPropagation();
            const { button } = active;
            active.close();
            button.focus();
        }
    }, true);
    window.addEventListener('resize', () => active?.position());
    window.addEventListener('scroll', () => active?.position(), true);
}

function enhance(select) {
    const categoryType = select.dataset.categoryType;
    const categoryUrl = select.dataset.categoryUrl;
    const isCategory = select.hasAttribute('data-category-picker');
    const wrapper = document.createElement('span');
    wrapper.className = 'app-select';
    select.before(wrapper);
    wrapper.append(select);
    select.classList.add('app-select-native');
    select.tabIndex = -1;
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'app-select-trigger';
    button.setAttribute('aria-haspopup', 'listbox');
    button.setAttribute('aria-expanded', 'false');
    const label = document.createElement('span');
    const chevron = document.createElement('span');
    chevron.textContent = '⌄';
    chevron.setAttribute('aria-hidden', 'true');
    button.append(label, chevron);
    wrapper.append(button);
    if (select.id) {
        const associated = [...document.querySelectorAll('label[for]')].find((node) => node.htmlFor === select.id);
        if (associated) {
            if (!associated.id) associated.id = `select-label-${++sequence}`;
            button.setAttribute('aria-labelledby', associated.id);
            associated.addEventListener('click', (event) => { event.preventDefault(); button.focus(); });
        }
    }
    if (!button.hasAttribute('aria-labelledby')) {
        button.setAttribute('aria-label', select.getAttribute('aria-label') || select.closest('label')?.childNodes[0]?.textContent.trim() || 'Selecionar opção');
    }

    let panel = null;
    let list = null;
    let search = null;
    let metadata = new Map();
    let canManage = false;
    let loading = false;
    const sync = () => {
        const selected = [...select.selectedOptions].map((option) => option.textContent);
        const text = select.multiple ? (selected.length ? `${selected.length} selecionado(s)` : 'Selecionar opções') : selected[0] || 'Selecione uma opção';
        if (label.textContent !== text) label.textContent = text;
        button.disabled = select.disabled;
        button.setAttribute('aria-invalid', select.getAttribute('aria-invalid') || 'false');
    };
    const descriptor = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
    Object.defineProperty(select, 'value', {
        get: () => descriptor.get.call(select),
        set: (value) => { descriptor.set.call(select, value); sync(); },
    });
    const close = () => {
        panel?.remove();
        panel = null;
        button.setAttribute('aria-expanded', 'false');
        if (active?.button === button) active = null;
    };
    const position = () => {
        if (!panel) return;
        if (!button.isConnected || button.getClientRects().length === 0) { close(); return; }
        const rect = button.getBoundingClientRect();
        const width = Math.min(Math.max(rect.width, 260), window.innerWidth - 24);
        panel.style.width = `${width}px`;
        panel.style.left = `${Math.max(12, Math.min(rect.left, window.innerWidth - width - 12))}px`;
        const height = Math.min(panel.scrollHeight, 380, window.innerHeight - 24);
        panel.style.maxHeight = `${height}px`;
        panel.style.top = `${Math.max(12, rect.bottom + height + 8 <= window.innerHeight ? rect.bottom + 6 : rect.top - height - 6)}px`;
    };
    const applyCategories = (data) => {
        canManage = data.canManage;
        metadata = new Map(data.categories.map((entry) => [entry.name, entry]));
        const value = select.value;
        select.replaceChildren(new Option('Selecione uma categoria', ''), ...data.categories.map((entry) => new Option(entry.name, entry.name)));
        select.value = metadata.has(value) ? value : '';
        sync();
    };
    const load = async () => {
        if (!isCategory || loading) return;
        loading = true;
        try {
            const response = await fetch(`${categoryUrl}?type=${encodeURIComponent(categoryType)}`, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Não foi possível carregar as categorias.');
            applyCategories(await response.json());
            if (panel) render();
        } catch (error) {
            toast(error.message, true);
        } finally {
            loading = false;
        }
    };
    const render = () => {
        if (!panel) return;
        const term = normalize(search.value.trim());
        list.replaceChildren();
        const options = [...select.options].filter((option) => !option.hidden && normalize(option.textContent).includes(term));
        options.forEach((option) => {
            const row = document.createElement('div');
            row.className = 'app-select-row';
            const choice = document.createElement('button');
            choice.type = 'button';
            choice.className = 'app-select-option';
            choice.disabled = option.disabled;
            choice.setAttribute('role', 'option');
            choice.setAttribute('aria-selected', String(option.selected));
            const name = document.createElement('span');
            name.textContent = option.textContent;
            choice.append(name);
            if (isCategory && option.value && metadata.has(option.value)) {
                const count = document.createElement('small');
                count.textContent = `${metadata.get(option.value).links} vínculo(s)`;
                choice.append(count);
            }
            choice.addEventListener('click', () => {
                if (select.multiple) option.selected = !option.selected;
                else select.value = option.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                sync();
                if (select.multiple) render();
                else { close(); button.focus(); }
            });
            row.append(choice);
            if (isCategory && canManage && option.value) {
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'app-select-delete';
                remove.textContent = '×';
                remove.setAttribute('aria-label', `Excluir categoria ${option.value}`);
                remove.addEventListener('click', async () => {
                    if (!(metadata.get(option.value)?.links > 0) && !await confirmDialog(`Excluir a categoria “${option.value}”?`, { confirmLabel: 'Excluir', danger: true })) return;
                    remove.disabled = true;
                    try {
                        const data = await postJson(categoryUrl, 'DELETE', { type: categoryType, name: option.value });
                        toast(data.message);
                        document.dispatchEvent(new CustomEvent('categories:changed', { detail: { type: categoryType } }));
                    } catch (error) {
                        toast(error.message, true);
                    } finally { remove.disabled = false; }
                });
                row.append(remove);
            }
            list.append(row);
        });
        if (!options.length) {
            const empty = document.createElement('p');
            empty.className = 'app-select-empty';
            empty.textContent = 'Nenhum resultado encontrado.';
            list.append(empty);
        }
        if (isCategory && canManage && search.value.trim() && ![...metadata.keys()].some((name) => normalize(name) === normalize(search.value.trim()))) {
            const create = document.createElement('button');
            create.type = 'button';
            create.className = 'app-select-create';
            create.textContent = `+ Criar “${search.value.trim()}”`;
            create.addEventListener('click', async () => {
                create.disabled = true;
                const name = search.value.trim();
                try {
                    const data = await postJson(categoryUrl, 'POST', { type: categoryType, name });
                    applyCategories(data);
                    select.value = name;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    toast('Categoria criada.');
                    close();
                    button.focus();
                    document.dispatchEvent(new CustomEvent('categories:changed', { detail: { type: categoryType } }));
                } catch (error) { toast(error.message, true); create.disabled = false; }
            });
            list.append(create);
        }
        position();
    };
    const open = () => {
        if (select.disabled) return;
        active?.close();
        panel = document.createElement('div');
        panel.className = 'app-select-panel';
        panel.id = `select-panel-${++sequence}`;
        button.setAttribute('aria-controls', `${panel.id}-list`);
        search = document.createElement('input');
        search.type = 'search';
        search.placeholder = isCategory ? 'Pesquisar ou criar categoria…' : 'Pesquisar opções…';
        search.setAttribute('aria-label', 'Pesquisar opções');
        search.maxLength = isCategory ? 255 : 1000;
        list = document.createElement('div');
        list.className = 'app-select-results';
        list.id = `${panel.id}-list`;
        list.setAttribute('role', 'listbox');
        if (select.multiple) list.setAttribute('aria-multiselectable', 'true');
        panel.append(search, list);
        (select.closest('dialog[open]') || document.body).append(panel);
        active = { panel, button, close, position };
        button.setAttribute('aria-expanded', 'true');
        search.addEventListener('input', render);
        panel.addEventListener('keydown', (event) => {
            const choices = [...panel.querySelectorAll('button:not(:disabled)')];
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                const index = choices.indexOf(document.activeElement);
                const next = event.key === 'ArrowDown' ? (index + 1) % choices.length : (index - 1 + choices.length) % choices.length;
                choices[next]?.focus();
            } else if (event.key === 'Enter' && event.target === search) {
                event.preventDefault();
                choices[0]?.click();
            } else if (event.key === 'Tab') {
                close();
                button.focus();
            }
        });
        render();
        search.focus();
        load();
    };
    button.addEventListener('click', () => panel ? close() : open());
    button.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') { event.preventDefault(); open(); }
    });
    select.addEventListener('change', sync);
    select.addEventListener('invalid', (event) => { event.preventDefault(); open(); });
    select.form?.addEventListener('reset', () => setTimeout(sync));
    new MutationObserver(() => { sync(); if (panel) render(); }).observe(select, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled', 'selected', 'aria-invalid'] });
    if (isCategory) document.addEventListener('categories:changed', (event) => { if (event.detail.type === categoryType) load(); });
    sync();
    return { close };
}

export function setupDashboardPeriod() {
    const form = document.querySelector('[data-dashboard-period]');
    if (!form) return;
    const period = form.elements.namedItem('period');
    const start = form.elements.namedItem('start');
    const end = form.elements.namedItem('end');
    const format = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    period.addEventListener('change', () => {
        if (period.value === 'personalizado') return;
        const now = new Date();
        const year = now.getFullYear();
        const month = now.getMonth();
        const ranges = {
            mes_atual: [new Date(year, month, 1), new Date(year, month + 1, 0)],
            mes_anterior: [new Date(year, month - 1, 1), new Date(year, month, 0)],
            ultimos_3_meses: [new Date(year, month - 2, 1), new Date(year, month + 1, 0)],
            ultimos_6_meses: [new Date(year, month - 5, 1), new Date(year, month + 1, 0)],
            ano_atual: [new Date(year, 0, 1), new Date(year, 11, 31)],
            ano_anterior: [new Date(year - 1, 0, 1), new Date(year - 1, 11, 31)],
        };
        const range = ranges[period.value];
        if (range) { start.value = format(range[0]); end.value = format(range[1]); end.min = start.value; }
    });
    [start, end].forEach((input) => input.addEventListener('change', () => { period.value = 'personalizado'; end.min = start.value; }));
    end.min = start.value;
}
