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
    const titulo = bar.querySelector('[data-filtro-titulo]');
    const de = bar.querySelector('[data-filtro-de]');
    const ate = bar.querySelector('[data-filtro-ate]');
    const categoria = bar.querySelector('[data-filtro-categoria]');
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

        const termo = normalize(titulo?.value);
        const inicio = de?.value || '';
        const fim = ate?.value || '';
        const categoriaEscolhida = categoria?.value || '';
        const ativo = Boolean(termo || inicio || fim || categoriaEscolhida);

        let visiveis = 0;
        let soma = 0;
        let temValor = false;

        cards.forEach((card) => {
            const start = card.dataset.filterStart || '';
            const end = card.dataset.filterEnd || start;
            const semData = !start && !end;

            const matches =
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

    [titulo, de, ate, categoria].forEach((field) => {
        field?.addEventListener('input', apply);
        field?.addEventListener('change', apply);
    });

    limpar?.addEventListener('click', () => {
        [titulo, de, ate, categoria].forEach((field) => {
            if (field) {
                field.value = '';
            }
        });

        apply();
    });

    new MutationObserver(apply).observe(bar.parentElement, { childList: true, subtree: true });

    apply();
}
