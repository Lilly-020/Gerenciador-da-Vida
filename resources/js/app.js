import { setupSelects, setupDashboardPeriod } from './selects';
import { setupQuadros } from './quadros';
import { setupFiltros } from './filtros';
import { setupContas, setupInvestimentos, setupLancamentos } from './financeiro';
import { setupMoneyInputs } from './money';
import { setupAppToolbar } from './app-toolbar';
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

    const desktop = window.matchMedia('(min-width: 64rem)');
    const sync = () => {
        const expanded = desktop.matches ? document.documentElement.dataset.sidebar !== 'collapsed' : dialog.open;
        openButton.setAttribute('aria-expanded', String(expanded));
        openButton.setAttribute('aria-controls', desktop.matches ? 'app-sidebar' : 'mobile-navigation');
        const label = desktop.matches ? (expanded ? 'Recolher menu lateral' : 'Expandir menu lateral') : 'Abrir menu';
        openButton.setAttribute('aria-label', label);
        openButton.title = label;
        if (desktop.matches) openButton.removeAttribute('aria-haspopup'); else openButton.setAttribute('aria-haspopup', 'dialog');
    };
    const close = () => dialog.close();
    openButton.addEventListener('click', () => {
        if (desktop.matches) {
            document.documentElement.dataset.sidebar = document.documentElement.dataset.sidebar === 'collapsed' ? 'expanded' : 'collapsed';
            try { localStorage.setItem('life-manager-sidebar', document.documentElement.dataset.sidebar); } catch {}
            sync();
            return;
        }
        dialog.showModal();
        document.body.style.overflow = 'hidden';
        sync();
    });
    closeButton.addEventListener('click', close);
    dialog.addEventListener('click', (event) => {
        const bounds = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) close();
    });
    dialog.addEventListener('close', () => { document.body.style.overflow = ''; sync(); openButton.focus(); });
    desktop.addEventListener('change', (event) => { if (event.matches && dialog.open) close(); sync(); });
    window.addEventListener('storage', (event) => {
        if (event.key === 'life-manager-sidebar') { document.documentElement.dataset.sidebar = event.newValue === 'collapsed' ? 'collapsed' : 'expanded'; sync(); }
    });
    sync();
}

function init() {
    setupNavigation();
    setupAppToolbar();
    setupMoneyInputs();
    setupSonhos();
    setupProjetos();
    setupQuadros();
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
