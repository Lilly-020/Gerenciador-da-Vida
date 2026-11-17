import { htmlToElement, postJson } from './board';

const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

// "Aluguél" matches "aluguel": compare without case or accents.
const normalize = (text) =>
    (text ?? '')
        .toString()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

/**
 * The search bars rendered by <x-filtros>: filter a list's cards by title,
 * date range and category, entirely in the browser.
 *
 * Each card describes itself: data-filter-item, data-filter-title,
 * data-filter-start / data-filter-end (ISO dates; a card matches a range it
 * overlaps), data-filter-category and data-filter-amount. Cards get
 * re-rendered by fetch all the time, so the container is looked up by id on
 * every pass and a MutationObserver re-applies the filter after each change.
 */
export function setupFiltros() {
    document.querySelectorAll('[data-filtros]').forEach(setupBar);
}

function setupBar(bar) {
    if (bar.dataset.filtrosServer) { setupServerBar(bar); return; }
    const titulo = bar.querySelector('[data-filtro-titulo]');
    const de = bar.querySelector('[data-filtro-de]');
    const ate = bar.querySelector('[data-filtro-ate]');
    const categoria = bar.querySelector('[data-filtro-categoria]');
    const plataforma = bar.querySelector('[data-filtro-plataforma]');
    const cursoStatus = bar.querySelector('[data-filtro-curso-status]');
    const status = bar.querySelector('[data-filtro-status]');
    const prazo = bar.querySelector('[data-filtro-prazo]');
    const ordem = bar.querySelector('[data-filtro-ordem]');
    const limpar = bar.querySelector('[data-filtro-limpar]');
    const resumo = bar.querySelector('[data-filtro-resumo]');

    const items = () =>
        Array.from(document.getElementById(bar.dataset.filtrosAlvo)?.querySelectorAll('[data-filter-item]') ?? []);

    const syncCategorias = (cards) => {
        if (!categoria) {
            return;
        }

        const wanted = [...new Set(cards.map((card) => card.dataset.filterCategory).filter(Boolean))].sort((a, b) =>
            a.localeCompare(b, 'pt-BR'),
        );
        const current = Array.from(categoria.options)
            .slice(1)
            .map((option) => option.value);

        if (wanted.join('|') === current.join('|')) {
            return;
        }

        const selected = categoria.value;

        categoria.replaceChildren(categoria.options[0], ...wanted.map((value) => new Option(value, value)));
        categoria.value = wanted.includes(selected) ? selected : '';
    };

    const apply = () => {
        const cards = items();

        syncCategorias(cards);
        if (plataforma) {
            const wanted = [...new Set(cards.map((card) => card.dataset.platform).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'pt-BR'));
            const current = [...plataforma.options].slice(1).map((option) => option.value);
            if (wanted.join('|') !== current.join('|')) {
                const selected = plataforma.value;
                plataforma.replaceChildren(plataforma.options[0], ...wanted.map((value) => new Option(value, value)));
                plataforma.value = wanted.includes(selected) ? selected : '';
            }
        }

        const termo = normalize(titulo?.value);
        const inicio = de?.value || '';
        const fim = ate?.value || '';
        const categoriaEscolhida = categoria?.value || '';
        const ativo = Boolean(termo || inicio || fim || categoriaEscolhida || plataforma?.value || cursoStatus?.value || status?.value || prazo?.value);
        if (ordem) {
            const sorted = [...cards].sort((a, b) => {
                switch (ordem.value) {
                    case 'due_at': return (a.dataset.dueAt || '9999').localeCompare(b.dataset.dueAt || '9999');
                    case 'progress': return Number(b.dataset.progress) - Number(a.dataset.progress);
                    case 'name': return a.dataset.filterTitle.localeCompare(b.dataset.filterTitle, 'pt-BR');
                    case 'priority': {
                        const rank = { high: 0, medium: 1, low: 2 };
                        return rank[a.dataset.priority] - rank[b.dataset.priority];
                    }
                    default: return Number(b.dataset.projetoId) - Number(a.dataset.projetoId);
                }
            });
            if (sorted.some((card, index) => card !== cards[index])) sorted.forEach((card) => card.parentElement.append(card));
        }

        let visiveis = 0;
        let soma = 0;
        let temValor = false;

        cards.forEach((card) => {
            const start = (status ? card.dataset.dueAt : card.dataset.filterStart) || '';
            const end = status ? start : card.dataset.filterEnd || start;
            const semData = !start && !end;

            const matches =
                (!plataforma?.value || card.dataset.platform === plataforma.value) &&
                (!cursoStatus?.value || card.dataset.status === cursoStatus.value) &&
                (!status || (status.value ? card.dataset.status === status.value : card.dataset.status !== 'archived')) &&
                (!prazo?.value || (prazo.value === 'none' ? !card.dataset.dueAt : card.dataset[prazo.value] === 'true')) &&
                (!termo || normalize(card.dataset.filterTitle).includes(termo)) &&
                (!categoriaEscolhida || card.dataset.filterCategory === categoriaEscolhida) &&
                (!inicio || (!semData && (end || start) >= inicio)) &&
                (!fim || (!semData && (start || end) <= fim));

            card.style.display = matches ? '' : 'none';

            if (matches) {
                visiveis += 1;

                if (card.dataset.filterAmount) {
                    temValor = true;
                    soma += parseFloat(card.dataset.filterAmount) || 0;
                }
            }
        });

        document.getElementById(bar.dataset.filtrosAlvo)?.querySelector('[data-board-empty]')?.classList.toggle('hidden', visiveis > 0 || ativo);
        document.getElementById(bar.dataset.filtrosAlvo)?.dispatchEvent(new CustomEvent('filters:updated', { detail: { visible: visiveis } }));
        limpar?.classList.toggle('hidden', !ativo);

        if (resumo) {
            const texto = !ativo
                ? ''
                : visiveis === 0
                  ? 'Nenhum resultado para esse filtro.'
                  : `Mostrando ${visiveis} de ${cards.length}${temValor ? ` · soma ${currency.format(soma)}` : ''}`;

            // Only touch the DOM when it changes, or the observer below
            // would keep re-triggering itself.
            if (resumo.textContent !== texto) {
                resumo.textContent = texto;
            }

            resumo.classList.toggle('hidden', !ativo);
        }
    };

    [titulo, de, ate, categoria, status, prazo, ordem, plataforma, cursoStatus].forEach((field) => {
        field?.addEventListener('input', apply);
        field?.addEventListener('change', apply);
    });

    limpar?.addEventListener('click', () => {
        [titulo, de, ate, categoria, status, prazo, ordem, plataforma, cursoStatus].forEach((field) => {
            if (field) {
                field.value = '';
            }
        });

        apply();
    });

    new MutationObserver(apply).observe(bar.parentElement, { childList: true, subtree: true });

    apply();
}


function setupServerBar(bar) {
    const grid = document.getElementById(bar.dataset.filtrosAlvo);
    if (!grid) return;
    const fields = {
        q: bar.querySelector('[data-filtro-titulo]'),
        de: bar.querySelector('[data-filtro-de]'),
        ate: bar.querySelector('[data-filtro-ate]'),
        status: bar.querySelector('[data-filtro-status]'),
        prazo: bar.querySelector('[data-filtro-prazo]'),
        sort: bar.querySelector('[data-filtro-ordem]'),
    };
    const clear = bar.querySelector('[data-filtro-limpar]');
    const summary = bar.querySelector('[data-filtro-resumo]');
    summary.setAttribute('role', 'status');
    let version = 0;
    let timer;
    const syncFields = () => {
        const address = new URL(window.location.href);
        Object.entries(fields).forEach(([name, field]) => { if (field) field.value = address.searchParams.get(name) || ''; });
        clear.classList.toggle('hidden', !Object.values(fields).some((field) => field?.value));
    };
    const filterAddress = (resetPage) => {
        const address = new URL(window.location.href);
        address.pathname = new URL(bar.dataset.filtrosServer, window.location.origin).pathname;
        Object.entries(fields).forEach(([name, field]) => {
            if (field?.value) address.searchParams.set(name, field.value); else address.searchParams.delete(name);
        });
        if (resetPage) address.searchParams.delete('page');
        return address;
    };
    const load = async (address, history = true) => {
        const request = ++version;
        grid.setAttribute('aria-busy', 'true');
        clear.classList.toggle('hidden', !Object.values(fields).some((field) => field?.value));
        try {
            const data = await postJson(address.href, 'GET');
            if (request !== version) return;
            const expanded = [...grid.querySelectorAll('details[data-details-key][open]')].map((details) => [details.closest('[data-board-card]').id, details.dataset.detailsKey]);
            grid.innerHTML = data.cards;
            expanded.forEach(([id, key]) => {
                document.getElementById(id)?.querySelectorAll('details[data-details-key]').forEach((details) => { if (details.dataset.detailsKey === key) details.open = true; });
            });
            document.getElementById('projetos-pagination').replaceWith(htmlToElement(data.pagination));
            if (data.stats && bar.dataset.filtrosStats) document.getElementById(bar.dataset.filtrosStats)?.replaceWith(htmlToElement(data.stats));
            if (data.page > 1) address.searchParams.set('page', data.page); else address.searchParams.delete('page');
            if (history && address.href !== window.location.href) window.history.pushState({}, '', address);
            summary.textContent = '';
            summary.classList.add('hidden');
        } catch (error) {
            if (request === version) { summary.textContent = error.message; summary.classList.remove('hidden'); }
        } finally {
            if (request === version) grid.removeAttribute('aria-busy');
        }
    };
    Object.values(fields).forEach((field) => {
        field?.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => load(filterAddress(true)), 300); });
        field?.addEventListener('change', () => { clearTimeout(timer); load(filterAddress(true)); });
    });
    clear.addEventListener('click', () => {
        clearTimeout(timer);
        Object.values(fields).forEach((field) => { if (field) field.value = ''; });
        load(filterAddress(true));
    });
    bar.parentElement.addEventListener('click', (event) => {
        const link = event.target.closest('#projetos-pagination a');
        if (!link || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        clearTimeout(timer);
        load(new URL(link.href));
    });
    grid.addEventListener('board:updated', (event) => {
        clearTimeout(timer);
        load(filterAddress(event.detail.created));
    });
    window.addEventListener('popstate', () => { syncFields(); load(new URL(window.location.href), false); });
    syncFields();
}
