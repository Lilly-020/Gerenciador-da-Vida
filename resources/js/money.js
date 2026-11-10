const nativeValue = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
const controls = new WeakMap();
const format = (amount) => `R$ ${amount.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

/**
 * Format monetary values while keeping the named input numeric for form
 * submissions, modal prefills and investment simulations.
 */
export function setupMoneyInputs() {
    const setup = (root) => {
        root.querySelectorAll('input[data-money], input[data-money-when-financial]').forEach((input) => {
            if (controls.has(input)) return;
            const control = enhance(input);
            controls.set(input, control);
            if (input.hasAttribute('data-money-when-financial')) {
                const type = input.form?.elements.namedItem('goal_type');
                const sync = () => control.setActive(type?.value === 'financial');
                type?.addEventListener('change', sync);
                sync();
            }
        });
    };
    setup(document);
    new MutationObserver((records) => {
        if (records.some((record) => record.addedNodes.length)) setup(document);
    }).observe(document.body, { childList: true, subtree: true });
}

function enhance(input) {
    const initialValue = input.value;
    const originalType = input.type;
    const originalId = input.id;
    const required = input.required;
    const signed = input.hasAttribute('data-money-signed');
    const display = document.createElement('input');
    let active = true;

    display.type = 'text';
    display.inputMode = signed ? 'text' : 'numeric';
    display.autocomplete = 'off';
    display.className = input.className;
    display.placeholder = 'R$ 0,00';
    display.disabled = input.disabled;
    display.readOnly = input.readOnly;
    ['aria-label', 'aria-describedby', 'aria-invalid'].forEach((attribute) => {
        if (input.hasAttribute(attribute)) display.setAttribute(attribute, input.getAttribute(attribute));
    });
    input.before(display);

    const render = () => {
        const raw = nativeValue.get.call(input);
        const amount = parseFloat(raw);
        display.value = raw === '' || Number.isNaN(amount) ? '' : format(amount);
        let error = '';
        if (raw !== '' && Number.isFinite(amount)) {
            if (input.min !== '' && amount < Number(input.min)) error = `Informe um valor igual ou maior que ${format(Number(input.min))}.`;
            if (input.max !== '' && amount > Number(input.max)) error = `Informe um valor igual ou menor que ${format(Number(input.max))}.`;
        }
        display.setCustomValidity(active ? error : '');
    };

    const setActive = (enabled) => {
        active = enabled;
        input.type = enabled ? 'hidden' : originalType;
        input.required = !enabled && required;
        display.hidden = !enabled;
        display.required = enabled && required;
        if (originalId) {
            input.removeAttribute('id');
            display.removeAttribute('id');
            (enabled ? display : input).id = originalId;
        }
        render();
    };

    Object.defineProperty(input, 'value', {
        get: () => nativeValue.get.call(input),
        set: (value) => {
            nativeValue.set.call(input, value ?? '');
            render();
        },
    });

    display.addEventListener('input', () => {
        const negative = signed && display.value.includes('-');
        const digits = display.value.replace(/\D/g, '').slice(0, 14);
        const value = digits === '' ? '' : ((negative ? -1 : 1) * parseInt(digits, 10) / 100).toFixed(2);
        nativeValue.set.call(input, value);
        render();
        if (negative && digits === '') display.value = '-';
        input.dispatchEvent(new Event('input', { bubbles: true }));
    });
    input.addEventListener('input', render);
    input.form?.addEventListener('reset', () => {
        setTimeout(() => { input.value = initialValue; });
    });
    setActive(true);

    return { setActive };
}
