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
    const sidebarToggle = document.getElementById('toggle-sidebar');
    const syncSidebar = () => {
        const expanded = document.documentElement.dataset.sidebar !== 'collapsed';
        const label = expanded ? 'Recolher menu' : 'Expandir menu';
        sidebarToggle.setAttribute('aria-expanded', String(expanded));
        sidebarToggle.setAttribute('aria-label', label);
        sidebarToggle.title = label;
    };
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            document.documentElement.dataset.sidebar = document.documentElement.dataset.sidebar === 'collapsed' ? 'expanded' : 'collapsed';
            try { localStorage.setItem('life-manager-sidebar', document.documentElement.dataset.sidebar); } catch {}
            syncSidebar();
        });
        window.addEventListener('storage', (event) => {
            if (event.key !== 'life-manager-sidebar' && event.key !== null) return;

            document.documentElement.dataset.sidebar = event.newValue === 'collapsed' ? 'collapsed' : 'expanded';
            syncSidebar();
        });
        syncSidebar();
    }

    const close = () => dialog.close();
    openButton.addEventListener('click', () => {
        if (desktop.matches) return;

        dialog.showModal();
        document.body.style.overflow = 'hidden';
        openButton.setAttribute('aria-expanded', 'true');
    });
    closeButton.addEventListener('click', close);
    dialog.addEventListener('click', (event) => {
        const bounds = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) close();
    });
    dialog.addEventListener('close', () => {
        document.body.style.overflow = '';
        openButton.setAttribute('aria-expanded', 'false');
        (desktop.matches ? sidebarToggle : openButton)?.focus();
    });
    desktop.addEventListener('change', (event) => {
        if (event.matches && dialog.open) close();
    });
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
