import test from 'node:test';
import assert from 'node:assert/strict';
import { CATEGORY_KEYS, constellationEdges, dreamPosition, positionedDreams } from '../resources/js/useDreamLayout.js';

test('dreams keep the same positions after refresh, filtering, insertion, and deletion', () => {
    const first = { id: 27, category: 'travel' };
    const second = { id: 4, category: 'family' };
    const position = dreamPosition(first);
    assert.deepEqual(dreamPosition({ ...first, progress: 100, status: 'completed' }), position);
    assert.deepEqual(positionedDreams([second, first, { id: 28, category: 'travel' }]).find(dream => dream.id === 27), { ...first, ...position });
    assert.deepEqual(positionedDreams([first])[0], { ...first, ...position });
});

test('deterministic constellation sectors keep stars separated across many dreams', () => {
    const buckets = new Map();
    for (const category of CATEGORY_KEYS) {
        for (let id = 1; id <= 1000; id++) {
            const p = dreamPosition({ id, category });
            const x = Math.floor(p.x / 80), y = Math.floor(p.y / 80);
            for (let dx = -1; dx <= 1; dx++) for (let dy = -1; dy <= 1; dy++) {
                for (const neighbor of buckets.get(`${x + dx}:${y + dy}`) || []) {
                    assert.ok(Math.hypot(p.x - neighbor.x, p.y - neighbor.y) >= 80);
                }
            }
            const key = `${x}:${y}`;
            buckets.set(key, [...(buckets.get(key) || []), p]);
        }
    }
});

test('connections stay inside their constellation and distinguish completed journeys', () => {
    const dreams = positionedDreams([
        { id: 1, category: 'travel', status: 'completed' },
        { id: 2, category: 'travel', status: 'completed' },
        { id: 3, category: 'family', status: 'not_started' },
        { id: 4, category: 'travel', status: 'in_progress' },
    ]);
    const edges = constellationEdges(dreams);
    assert.equal(edges.length, 2);
    assert.ok(edges.every(edge => edge.a.category === edge.b.category));
    assert.equal(edges.filter(edge => edge.achieved).length, 1);
});
