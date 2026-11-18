import { postForm } from './board';

export function setupAppToolbar() {
    const toggle = document.querySelector('[data-notifications-toggle]');
    if (!toggle) return;
    const panel = document.querySelector('[data-notifications-panel]');
    const body = panel.querySelector('[data-notifications-body]');
    const updateCount = (count) => document.querySelectorAll('[data-app-notification-count]').forEach((badge) => { badge.textContent = String(count); badge.hidden = count === 0; });
    const position = () => {
        const rect = toggle.getBoundingClientRect();
        panel.style.left = `${Math.max(8, Math.min(rect.right - panel.offsetWidth, innerWidth - panel.offsetWidth - 8))}px`;
        panel.style.top = `${rect.bottom + 8}px`;
        panel.style.maxHeight = `${Math.max(160, (window.visualViewport?.height || innerHeight) - rect.bottom - 24)}px`;
    };
    let loading = false;
    let lastHtml = '';
    const refresh = async () => {
        if (loading || document.hidden || !navigator.onLine) return;
        loading = true;
        try {
            const response = await fetch(toggle.dataset.url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!response.ok) return;
            const data = await response.json(); updateCount(data.count);
            if (data.html !== lastHtml) { body.innerHTML = data.html; lastHtml = data.html; }
            if (panel.matches(':popover-open')) position();
        } catch {} finally { loading = false; }
    };
    const close = () => { if (panel.matches(':popover-open')) panel.hidePopover(); toggle.setAttribute('aria-expanded', 'false'); };
    toggle.addEventListener('click', () => { if (panel.matches(':popover-open')) { close(); return; } panel.showPopover(); toggle.setAttribute('aria-expanded', 'true'); position(); refresh(); });
    panel.querySelector('[data-notifications-close]').addEventListener('click', close);
    document.addEventListener('pointerdown', (event) => { if (!panel.contains(event.target) && !toggle.contains(event.target)) close(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
    window.addEventListener('resize', position); window.addEventListener('focus', refresh);
    window.addEventListener('online', refresh); document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    document.addEventListener('app-notification-count', (event) => { updateCount(event.detail); if (panel.matches(':popover-open')) refresh(); });
    setInterval(refresh, 5000);

    const syncTheme = () => {
        const light = document.documentElement.dataset.theme === 'light';
        document.querySelector('[data-theme-moon]').hidden = light;
        document.querySelector('[data-theme-sun]').hidden = !light;
        document.querySelector('[data-toggle-theme]').setAttribute('aria-pressed', String(light));
        document.querySelector('meta[name="theme-color"]').content = light ? '#f8fafc' : '#020617';
    };
    document.querySelector('[data-toggle-theme]').addEventListener('click', () => {
        const theme = document.documentElement.dataset.theme === 'light' ? 'dark' : 'light';
        document.documentElement.dataset.theme = theme;
        try { localStorage.setItem('life-manager-theme', theme); } catch {}
        syncTheme();
    });
    window.addEventListener('storage', (event) => { if (event.key === 'life-manager-theme' && ['light', 'dark'].includes(event.newValue)) { document.documentElement.dataset.theme = event.newValue; syncTheme(); } });
    syncTheme();
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-copy-handle]');
        if (!button) return;
        try { await navigator.clipboard.writeText(button.dataset.copyHandle); button.textContent = 'Apelido copiado'; }
        catch { button.textContent = button.dataset.copyHandle; }
    });
    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('[data-friend-request]');
        if (!form) return;
        event.preventDefault();
        const button = form.querySelector('[type="submit"], button');
        if (button.disabled) return;
        button.disabled = true;
        const output = form.querySelector('[data-friend-request-status]');
        try { const data = await postForm(form.action, new FormData(form)); output.textContent = data.message; form.reset(); }
        catch (failure) { output.textContent = failure.message; }
        finally { button.disabled = false; }
    });
}
