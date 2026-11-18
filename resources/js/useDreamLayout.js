export const CATEGORY_KEYS = ['finance', 'family', 'career', 'studies', 'travel', 'health', 'personal', 'other'];

/** Immutable IDs reserve cells on concentric arcs inside disjoint constellation sectors. */
export function dreamPosition(dream) {
    const region = Math.max(0, CATEGORY_KEYS.indexOf(dream.category));
    const slot = Number(dream.id) - 1;
    const ring = Math.floor(Math.sqrt(slot));
    const column = slot - ring * ring;
    const radius = (ring + 1) * 280;
    const angle = region * Math.PI / 4 + ((column + 0.5) / (2 * ring + 1) - 0.5) * 0.64;
    return { x: Math.cos(angle) * radius, y: Math.sin(angle) * radius };
}

export function positionedDreams(dreams) {
    return dreams.map(dream => ({ ...dream, ...dreamPosition(dream) }));
}

export function constellationEdges(dreams) {
    const edges = [];
    for (const category of CATEGORY_KEYS) {
        const group = dreams.filter(dream => dream.category === category).sort((a, b) => a.id - b.id);
        for (let i = 1; i < group.length; i++) {
            const target = group[i];
            let nearest = group[0];
            for (let j = 1; j < i; j++) {
                if (Math.hypot(group[j].x - target.x, group[j].y - target.y) < Math.hypot(nearest.x - target.x, nearest.y - target.y)) nearest = group[j];
            }
            edges.push({ key: `${nearest.id}-${target.id}`, a: nearest, b: target, achieved: nearest.status === 'completed' && target.status === 'completed' });
        }
    }
    return edges;
}
