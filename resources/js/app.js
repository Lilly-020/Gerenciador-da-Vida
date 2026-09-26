import { setupCursorTrail } from './cursor';
import { setupCursos } from './cursos';
import { setupCustosFixos, setupInvestimentos, setupLancamentos } from './financeiro';
import { setupProjetos } from './projetos';
import { setupSonhos } from './sonhos';
import { setupTarefas } from './tarefas';

/**
 * Animated "liquid" indicator for the top navigation.
 *
 * On click, the pill stretches from the current tab towards the target tab
 * (like a drop of liquid being pulled), then snaps into place with a small
 * bounce before the browser navigates to the new page.
 */
function setupNavIndicator() {
    const nav = document.getElementById('nav-tabs');
    const indicator = document.getElementById('nav-indicator');

    if (!nav || !indicator) {
        return;
    }

    const links = Array.from(nav.querySelectorAll('[data-nav-link]'));
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const measure = (el) => {
        const navRect = nav.getBoundingClientRect();
        const linkRect = el.getBoundingClientRect();

        // nav can scroll horizontally now (mobile), and the indicator lives
        // inside that scrolled content — so its `left` needs to be in
        // content coordinates, not "distance from the container's visible
        // edge". getBoundingClientRect() only gives the latter, so add back
        // the current scroll offset to undo it.
        return { left: linkRect.left - navRect.left + nav.scrollLeft, width: linkRect.width };
    };

    const shapeStyle = ({ left, width }) => ({
        transform: `translateX(${left}px)`,
        width: `${width}px`,
    });

    const activeLink = () => links.find((link) => link.getAttribute('aria-current') === 'page') ?? links[0];

    let current = activeLink() ? measure(activeLink()) : null;

    if (current) {
        Object.assign(indicator.style, shapeStyle(current));
        indicator.style.opacity = '1';

        // On narrow screens the nav scrolls horizontally instead of
        // wrapping — make sure the active tab is actually visible on load.
        activeLink()?.scrollIntoView({ inline: 'center', block: 'nearest' });
    }

    let busy = false;

    links.forEach((link) => {
        link.addEventListener('click', (event) => {
            const isCurrent = link.getAttribute('aria-current') === 'page';
            const isPlainClick =
                event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;

            if (isCurrent || !isPlainClick || reduceMotion || !current || busy) {
                return;
            }

            event.preventDefault();
            busy = true;

            const from = current;
            const to = measure(link);
            const stretched = {
                left: Math.min(from.left, to.left),
                width: Math.max(from.left + from.width, to.left + to.width) - Math.min(from.left, to.left),
            };

            current = to;

            // One continuous animation (not two chained ones — awaiting a
            // `.finished` promise between them left a tiny gap where a
            // frame could be dropped). Per-keyframe easing keeps the same
            // two-part feel: pull the drop taut, then let go with a light
            // bounce into place.
            const animation = indicator.animate(
                [
                    { ...shapeStyle(from), offset: 0 },
                    { ...shapeStyle(stretched), opacity: 0.85, offset: 180 / 440, easing: 'cubic-bezier(0.3, 0, 0.6, 1)' },
                    { ...shapeStyle(to), opacity: 1, offset: 1, easing: 'cubic-bezier(0.34, 1.56, 0.64, 1)' },
                ],
                { duration: 440, fill: 'forwards' },
            );

            animation.finished
                .catch(() => {})
                .finally(() => {
                    window.location.href = link.href;
                });
        });
    });

    window.addEventListener('resize', () => {
        const active = activeLink();

        if (!active || busy) {
            return;
        }

        current = measure(active);
        Object.assign(indicator.style, shapeStyle(current));
    });
}

function init() {
    setupNavIndicator();
    setupCursorTrail();
    setupSonhos();
    setupProjetos();
    setupCursos();
    setupTarefas();
    setupLancamentos();
    setupCustosFixos();
    setupInvestimentos();
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
