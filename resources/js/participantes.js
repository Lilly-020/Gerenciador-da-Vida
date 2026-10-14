/**
 * Apelido suggestions for every "convidar" field (`[data-apelido-search]`):
 * as the user types, fills the field's <datalist> with matching apelidos.
 * Delegated from the document because the cards holding these fields get
 * re-rendered.
 */
export function setupApelidoSearch() {
    let timer = null;

    document.addEventListener('input', (event) => {
        const input = event.target.closest?.('[data-apelido-search]');
        const datalist = input?.list;

        if (!datalist) {
            return;
        }

        clearTimeout(timer);

        timer = setTimeout(async () => {
            const termo = input.value.trim();

            if (termo.length < 2) {
                datalist.replaceChildren();

                return;
            }

            try {
                const response = await fetch(`/usuarios/buscar?q=${encodeURIComponent(termo)}`, {
                    headers: { Accept: 'application/json' },
                });
                const users = response.ok ? await response.json() : [];

                datalist.replaceChildren(
                    ...users.map((user) => {
                        const option = document.createElement('option');

                        option.value = user.apelido;
                        option.label = user.name;

                        return option;
                    }),
                );
            } catch {
                // Suggestions are a convenience; typing the apelido still works.
            }
        }, 200);
    });
}
