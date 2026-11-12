import { setupSelects, setupDashboardPeriod } from './selects';
import { setupCursos } from './cursos';
import { setupFiltros } from './filtros';
import { setupContas, setupInvestimentos, setupLancamentos } from './financeiro';
import { setupMoneyInputs } from './money';
import { setupApelidoSearch } from './participantes';
import { setupProjetos } from './projetos';
import { setupSonhos } from './sonhos';
import { setupTarefas } from './tarefas';
import { setupNotas } from './notas';
import { setupLifeDashboard } from './dashboard';

function setupNavigation() {
    const dialog = document.getElementById('mobile-navigation');
    const openButton = document.getElementById('open-navigation');
    const closeButton = document.getElementById('close-navigation');

    if (!dialog || !openButton || !closeButton) {
        return;
    }

    const close = () => dialog.close();

    openButton.addEventListener('click', () => {
        dialog.showModal();
        openButton.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    });
    closeButton.addEventListener('click', close);
    dialog.addEventListener('click', (event) => {
        const bounds = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) {
            close();
        }
    });
    dialog.addEventListener('close', () => {
        openButton.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
        openButton.focus();
    });
    window.matchMedia('(min-width: 64rem)').addEventListener('change', (event) => {
        if (event.matches && dialog.open) {
            close();
        }
    });
}

function init() {
    setupNavigation();
    setupMoneyInputs();
    setupApelidoSearch();
    setupSonhos();
    setupProjetos();
    setupCursos();
    setupTarefas();
    setupNotas();
    setupLifeDashboard();
    setupContas();
    setupLancamentos();
    setupInvestimentos();
    setupFiltros();
    setupSelects();
    setupDashboardPeriod();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

// Registers the PWA service worker (public/sw.js) so the app is
// installable on Android/iOS home screens. Registration failure is
// non-fatal — the app works fine without it, just not "installable".
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
