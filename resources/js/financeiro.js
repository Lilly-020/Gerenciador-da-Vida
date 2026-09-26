import { htmlToElement, postJson, setupCreateModal, setupRowEditModal, toCamelCase } from './board';
import { alertDialog, confirmDialog } from './dialog';

/**
 * Entradas / Saídas page: create via modal, toggle previsto↔realizado,
 * delete — all through fetch, swapping in the server-rendered list +
 * totals. Also wires the "lançamento recorrente" checkbox that reveals
 * the periodicity/day fields.
 */
export function setupLancamentos() {
    const page = document.getElementById('lancamentos-page');

    if (!page) {
        return;
    }

    const applyUpdate = ({ list, totals } = {}) => {
        if (list) {
            document.getElementById('lancamentos-list')?.replaceWith(htmlToElement(list));
        }

        if (totals) {
            document.getElementById('lancamentos-totals')?.replaceWith(htmlToElement(totals));
        }
    };

    page.addEventListener('change', async (event) => {
        const checkbox = event.target.closest('[data-lancamento-toggle-url]');

        if (!checkbox) {
            return;
        }

        checkbox.disabled = true;

        try {
            const status = checkbox.checked ? 'realizado' : 'previsto';
            const data = await postJson(checkbox.dataset.lancamentoToggleUrl, 'PATCH', { status });
            applyUpdate(data);
        } catch (error) {
            checkbox.checked = !checkbox.checked;
            checkbox.disabled = false;
            alertDialog(error.message);
        }
    });

    page.addEventListener('click', async (event) => {
        const deleteButton = event.target.closest('[data-lancamento-delete-url]');

        if (!deleteButton) {
            return;
        }

        const confirmed = await confirmDialog('Excluir este lançamento?', { confirmLabel: 'Excluir', danger: true });

        if (!confirmed) {
            return;
        }

        try {
            const data = await postJson(deleteButton.dataset.lancamentoDeleteUrl, 'DELETE');
            applyUpdate(data);
        } catch (error) {
            alertDialog(error.message);
        }
    });

    setupCreateModal({
        modalId: 'new-lancamento-modal',
        openButtonId: 'open-new-lancamento-modal',
        formId: 'new-lancamento-form',
        applyUpdate,
    });

    setupRecurringToggle();

    setupRowEditModal({
        containerId: 'lancamentos-page',
        triggerSelector: '[data-lancamento-edit-trigger]',
        modalId: 'edit-lancamento-modal',
        formId: 'edit-lancamento-form',
        urlDataKey: 'lancamentoEditUrl',
        method: 'PATCH',
        applyUpdate,
    });
}

function setupRecurringToggle() {
    const checkbox = document.querySelector('[data-recurring-toggle]');
    const fields = document.querySelector('[data-recurring-fields]');

    if (!checkbox || !fields) {
        return;
    }

    checkbox.addEventListener('change', () => {
        fields.classList.toggle('hidden', !checkbox.checked);
        fields.classList.toggle('grid', checkbox.checked);
    });
}

/**
 * Custos Fixos page: create via modal, mark this month as paid, delete —
 * same fetch + swap pattern.
 */
export function setupCustosFixos() {
    const page = document.getElementById('custos-fixos-page');

    if (!page) {
        return;
    }

    const applyUpdate = ({ list, summary } = {}) => {
        if (list) {
            document.getElementById('custos-fixos-list')?.replaceWith(htmlToElement(list));
        }

        if (summary) {
            document.getElementById('custos-fixos-summary')?.replaceWith(htmlToElement(summary));
        }
    };

    page.addEventListener('click', async (event) => {
        const payButton = event.target.closest('[data-custo-fixo-pay-url]');

        if (payButton) {
            payButton.disabled = true;

            try {
                const data = await postJson(payButton.dataset.custoFixoPayUrl, 'POST');
                applyUpdate(data);
            } catch (error) {
                payButton.disabled = false;
                alertDialog(error.message);
            }

            return;
        }

        const deleteButton = event.target.closest('[data-custo-fixo-delete-url]');

        if (deleteButton) {
            const confirmed = await confirmDialog(
                'Excluir este custo fixo? Os pagamentos já registrados continuam no histórico de saídas.',
                { confirmLabel: 'Excluir', danger: true },
            );

            if (!confirmed) {
                return;
            }

            try {
                const data = await postJson(deleteButton.dataset.custoFixoDeleteUrl, 'DELETE');
                applyUpdate(data);
            } catch (error) {
                alertDialog(error.message);
            }
        }
    });

    setupCreateModal({
        modalId: 'new-custo-fixo-modal',
        openButtonId: 'open-new-custo-fixo-modal',
        formId: 'new-custo-fixo-form',
        applyUpdate,
    });

    setupRowEditModal({
        containerId: 'custos-fixos-page',
        triggerSelector: '[data-custo-fixo-edit-trigger]',
        modalId: 'edit-custo-fixo-modal',
        formId: 'edit-custo-fixo-form',
        urlDataKey: 'custoFixoEditUrl',
        method: 'PUT',
        applyUpdate,
    });
}

/**
 * Investimentos page: the "novo investimento" modal is just an open/close
 * toggle — its form submits normally (full page reload), since adding an
 * investment or a contribution isn't a rapid, repeated action the way
 * checking off tasks is elsewhere in the app.
 */
export function setupInvestimentos() {
    const modal = document.getElementById('new-investimento-modal');
    const openButton = document.getElementById('open-new-investimento-modal');

    if (!modal || !openButton) {
        return;
    }

    const open = () => {
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        requestAnimationFrame(() => modal.classList.add('is-open'));
    };

    const close = () => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        setTimeout(() => modal.classList.add('hidden'), 150);
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

    setupInvestimentoEditModal();
}

/**
 * Investimento edit modal: like the create modal, it's a plain form that
 * submits normally (full page reload via method-spoofed PUT) rather than
 * fetch — consistent with the rest of this page. The trigger button on
 * each card carries the submit url and every prefill value directly on its
 * own dataset, so opening it is just copying those into the shared form.
 */
function setupInvestimentoEditModal() {
    const modal = document.getElementById('edit-investimento-modal');
    const form = document.getElementById('edit-investimento-form');

    if (!modal || !form) {
        return;
    }

    // Excludes the @csrf/@method hidden inputs (name="_token"/"_method")
    // this form needs for a real, method-spoofed POST — unlike the other
    // Financeiro edit modals, which submit via fetch and have no such
    // fields to accidentally blank out.
    const fields = Array.from(form.elements).filter((el) => el.name && !el.name.startsWith('_'));

    const open = (trigger) => {
        form.action = trigger.dataset.investimentoEditUrl;

        fields.forEach((field) => {
            field.value = trigger.dataset[toCamelCase(field.name)] ?? '';
        });

        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        requestAnimationFrame(() => modal.classList.add('is-open'));
    };

    const close = () => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        setTimeout(() => modal.classList.add('hidden'), 150);
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-investimento-edit-trigger]');

        if (trigger) {
            open(trigger);
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
}
