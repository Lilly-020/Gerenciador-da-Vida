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

        return { left: linkRect.left - navRect.left, width: linkRect.width };
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

            // Phase 1: pull the drop taut between the two tabs.
            const pull = indicator.animate(
                [shapeStyle(from), { ...shapeStyle(stretched), opacity: 0.85 }],
                { duration: 180, easing: 'cubic-bezier(0.3, 0, 0.6, 1)', fill: 'forwards' },
            );

            pull.finished
                .then(() => {
                    // Phase 2: let go — the drop snaps into the new tab with a light bounce.
                    const settle = indicator.animate(
                        [
                            { ...shapeStyle(stretched), opacity: 0.85 },
                            { ...shapeStyle(to), opacity: 1 },
                        ],
                        { duration: 260, easing: 'cubic-bezier(0.34, 1.56, 0.64, 1)', fill: 'forwards' },
                    );

                    return settle.finished;
                })
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

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupNavIndicator);
} else {
    setupNavIndicator();
}
