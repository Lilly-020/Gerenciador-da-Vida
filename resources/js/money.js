const nativeValue = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');

const format = (amount) =>
    `R$ ${amount.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

/**
 * Currency mask for every `<input data-money>`: digits typed fill in from
 * the cents ("1", "2", "3" → R$ 0,01 → R$ 0,12 → R$ 1,23).
 *
 * The original input stays in the form as a hidden field holding the plain
 * number ("1234.56"), so everything that reads or writes it — form
 * submits, the edit modals' prefill, the simulator — keeps working
 * untouched; a visible text input shown in its place does the formatting.
 */
export function setupMoneyInputs() {
    document.querySelectorAll('input[data-money]').forEach(enhance);
}

function enhance(input) {
    const initialValue = input.value;
    const display = document.createElement('input');

    display.type = 'text';
    display.inputMode = 'numeric';
    display.autocomplete = 'off';
    display.className = input.className;
    display.placeholder = 'R$ 0,00';
    display.required = input.required;

    // The label's `for` should focus the field the user actually types in.
    if (input.id) {
        display.id = input.id;
        input.removeAttribute('id');
    }

    input.type = 'hidden';
    input.before(display);

    const render = () => {
        const raw = nativeValue.get.call(input);
        const amount = parseFloat(raw);

        display.value = raw === '' || Number.isNaN(amount) ? '' : format(amount);
    };

    // Values assigned from elsewhere (modal prefill) must reach the mask too.
    Object.defineProperty(input, 'value', {
        get: () => nativeValue.get.call(input),
        set: (value) => {
            nativeValue.set.call(input, value ?? '');
            render();
        },
    });

    display.addEventListener('input', () => {
        const digits = display.value.replace(/\D/g, '').slice(0, 13);

        nativeValue.set.call(input, digits === '' ? '' : (parseInt(digits, 10) / 100).toFixed(2));
        render();
        input.dispatchEvent(new Event('input', { bubbles: true }));
    });

    // form.reset() leaves hidden inputs alone, so restore it by hand.
    input.form?.addEventListener('reset', () => {
        setTimeout(() => {
            input.value = initialValue;
        });
    });

    render();
}
