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
    setupInvestimentoSimulador();
}

/**
 * "Quanto eu teria se investisse X a Y%" — a purely client-side, live
 * calculator on the Investimentos page. Doesn't touch the server or any
 * real investment: it's scratch paper next to the real numbers. Reuses
 * the exact same daily-compound-interest formula as
 * InvestimentoAporte::estimatedYield() on the backend (see that class),
 * so a simulation and a real investment with the same numbers agree.
 */
function setupInvestimentoSimulador() {
    const container = document.getElementById('investimento-simulador');

    if (!container) {
        return;
    }

    const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

    const field = (name) => container.querySelector(`[data-sim-field="${name}"]`);
    const output = (name) => container.querySelector(`[data-sim-output="${name}"]`);

    const fields = {
        valorInicial: field('valorInicial'),
        aporteMensal: field('aporteMensal'),
        taxa: field('taxa'),
        periodo: field('periodo'),
        prazo: field('prazo'),
        prazoUnidade: field('prazoUnidade'),
    };

    const outputs = {
        investido: output('investido'),
        rendimento: output('rendimento'),
        final: output('final'),
        barInvestido: output('bar-investido'),
        barRendimento: output('bar-rendimento'),
    };

    if (Object.values(fields).some((el) => !el) || Object.values(outputs).some((el) => !el)) {
        return;
    }

    // Same conversion as the backend's dailyRate(): the configured
    // annual/monthly rate turned into an equivalent constant daily rate.
    const dailyRateFrom = (ratePercent, period) => {
        const periodDays = period === 'mensal' ? 30 : 365;

        return (1 + ratePercent / 100) ** (1 / periodDays) - 1;
    };

    const recalculate = () => {
        const valorInicial = Math.max(0, parseFloat(fields.valorInicial.value) || 0);
        const aporteMensal = Math.max(0, parseFloat(fields.aporteMensal.value) || 0);
        const taxa = Math.max(0, parseFloat(fields.taxa.value) || 0);
        const periodo = fields.periodo.value;
        const prazoValue = Math.max(1, parseInt(fields.prazo.value, 10) || 0);
        const durationMonths = Math.round(fields.prazoUnidade.value === 'anos' ? prazoValue * 12 : prazoValue);

        const dailyRate = dailyRateFrom(taxa, periodo);
        const fvInicial = valorInicial * (1 + dailyRate) ** (durationMonths * 30);

        // Each monthly contribution compounds on its own, from the month
        // it's made through the end of the simulated period — same model
        // as how real aportes are treated (see aportesWithParentLoaded()).
        let fvAportes = 0;
        let totalAportesMensais = 0;

        for (let mes = 1; mes <= durationMonths; mes++) {
            const diasInvestidos = (durationMonths - mes) * 30;
            fvAportes += aporteMensal * (1 + dailyRate) ** diasInvestidos;
            totalAportesMensais += aporteMensal;
        }

        const investido = valorInicial + totalAportesMensais;
        const valorFinal = fvInicial + fvAportes;
        const rendimento = valorFinal - investido;

        outputs.investido.textContent = currency.format(investido);
        outputs.rendimento.textContent = currency.format(rendimento);
        outputs.final.textContent = currency.format(valorFinal);

        const investidoPct = valorFinal > 0 ? Math.max(0, Math.min(100, (investido / valorFinal) * 100)) : 100;
        outputs.barInvestido.style.width = `${investidoPct}%`;
        outputs.barRendimento.style.width = `${100 - investidoPct}%`;
    };

    container.addEventListener('input', recalculate);
    container.addEventListener('change', recalculate);

    recalculate();
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
