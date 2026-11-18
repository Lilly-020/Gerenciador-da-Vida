import { createApp } from 'vue';
import DreamUniverse from './DreamUniverse.vue';

export function mountDreamUniverse() {
    const root = document.getElementById('dreamverse');
    const data = JSON.parse(document.getElementById('dreamverse-data').textContent);
    createApp(DreamUniverse, { initial: data, userId: root.dataset.userId, indexUrl: root.dataset.indexUrl, storeUrl: root.dataset.storeUrl }).mount(root);
    document.getElementById('dreamverse-fallback').hidden = true;
}
