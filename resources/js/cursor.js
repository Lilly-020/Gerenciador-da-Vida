/**
 * Custom cursor: a bright dot with a soft, fading tail of smaller dots
 * trailing behind it — like a drop of light being dragged across the screen.
 *
 * Skipped on touch devices (no fine pointer) and when the user prefers
 * reduced motion. Text inputs keep the native text cursor (see app.css).
 */
export function setupCursorTrail() {
    const supportsFinePointer = window.matchMedia('(pointer: fine)').matches;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!supportsFinePointer || reduceMotion) {
        return;
    }

    document.body.classList.add('has-custom-cursor');

    const sizes = [18, 13, 10, 8, 6, 5, 4];
    const trail = sizes.map((size, index) => {
        const el = document.createElement('span');
        el.className = 'cursor-dot';
        el.style.width = `${size}px`;
        el.style.height = `${size}px`;

        document.body.appendChild(el);

        return { el, half: size / 2, x: 0, y: 0, targetOpacity: index === 0 ? 0.95 : 0.75 - index * 0.1 };
    });

    let mouseX = window.innerWidth / 2;
    let mouseY = window.innerHeight / 2;
    let started = false;

    const show = () => trail.forEach((dot) => (dot.el.style.opacity = String(dot.targetOpacity)));
    const hide = () => trail.forEach((dot) => (dot.el.style.opacity = '0'));

    window.addEventListener('mousemove', (event) => {
        mouseX = event.clientX;
        mouseY = event.clientY;

        if (!started) {
            started = true;
            trail.forEach((dot) => {
                dot.x = mouseX;
                dot.y = mouseY;
            });
            show();
        }
    });

    document.addEventListener('mouseleave', hide);
    document.addEventListener('mouseenter', () => started && show());

    const render = () => {
        let targetX = mouseX;
        let targetY = mouseY;

        trail.forEach((dot, index) => {
            const ease = index === 0 ? 0.4 : 0.32;
            dot.x += (targetX - dot.x) * ease;
            dot.y += (targetY - dot.y) * ease;
            dot.el.style.transform = `translate3d(${dot.x - dot.half}px, ${dot.y - dot.half}px, 0)`;
            targetX = dot.x;
            targetY = dot.y;
        });

        requestAnimationFrame(render);
    };

    requestAnimationFrame(render);
}
