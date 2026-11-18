<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { postJson } from './board';
import { confirmDialog } from './dialog';
import { constellationEdges, positionedDreams } from './useDreamLayout';
import DreamStar from './DreamStar.vue';
import DreamFields from './DreamFields.vue';
import DreamDetailsPanel from './DreamDetailsPanel.vue';

const props = defineProps({ initial: Object, userId: String, indexUrl: String, storeUrl: String });
const dreams = ref(props.initial.dreams);
const categories = props.initial.categories;
const preferenceKey = `dreamverse:view:${props.userId}`;
const params = new URLSearchParams(location.search);
const filters = reactive({ query: '', status: params.get('status') || '', category: '', sort: params.get('sort') || '', from: '', to: '' });
const mode = ref('universe');
const filtersOpen = ref(false);
const constellationsOpen = ref(false);
try { if (localStorage.getItem(preferenceKey) === 'list') mode.value = 'list'; } catch {}
const selectedId = ref(null);
const hoveredId = ref(null);
const selected = computed(() => dreams.value.find(dream => dream.id === selectedId.value));
const normalize = value => (value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pt-BR');
const visible = computed(() => {
    const result = dreams.value.filter(dream => (filters.status === 'archived' ? dream.archived : !dream.archived && (!filters.status || dream.status === filters.status)) && (!filters.query || normalize(dream.title).includes(normalize(filters.query))) && (!filters.category || dream.category === filters.category) && (!filters.from || dream.created_at >= filters.from) && (!filters.to || dream.created_at <= filters.to));
    const rank = { high: 0, medium: 1, low: 2 };
    return result.sort((a, b) => filters.sort === 'priority' ? rank[a.priority] - rank[b.priority] || b.id - a.id : filters.sort === 'target_date' ? (a.target_date || '9999').localeCompare(b.target_date || '9999') || b.id - a.id : b.id - a.id);
});
const pageSize = 10;
const listPage = ref(1);
const listContainer = ref(null);
const totalPages = computed(() => Math.max(1, Math.ceil(visible.value.length / pageSize)));
const currentPage = computed(() => Math.min(listPage.value, totalPages.value));
const pageDreams = computed(() => visible.value.slice((currentPage.value - 1) * pageSize, currentPage.value * pageSize));
const firstItem = computed(() => visible.value.length ? (currentPage.value - 1) * pageSize + 1 : 0);
const lastItem = computed(() => Math.min(currentPage.value * pageSize, visible.value.length));
const pageLinks = computed(() => {
    const pages = [];
    for (let number = 1; number <= totalPages.value; number++) {
        if (number === 1 || number === totalPages.value || Math.abs(number - currentPage.value) <= 2) pages.push(number);
    }
    return pages.flatMap((number, index) => index && number - pages[index - 1] > 1 ? [`gap-${number}`, number] : [number]);
});
async function changePage(number) {
    listPage.value = Math.max(1, Math.min(totalPages.value, number));
    await nextTick();
    listContainer.value?.focus({ preventScroll: true });
    listContainer.value?.scrollIntoView({ block: 'start', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
}
watch(() => Object.values(filters), () => { listPage.value = 1; });
watch(totalPages, total => { if (listPage.value > total) listPage.value = total; });
const stars = computed(() => positionedDreams(visible.value));
const edges = computed(() => constellationEdges(stars.value));
const regions = computed(() => Object.entries(categories).map(([key, label]) => {
    const group = stars.value.filter(star => star.category === key);
    return group.length ? { key, label, count: group.length, x: Math.min(...group.map(star => star.x)) - 60, y: Math.min(...group.map(star => star.y)) - 100 } : null;
}).filter(Boolean));
const hovered = computed(() => visible.value.find(dream => dream.id === hoveredId.value));
const activeCount = computed(() => dreams.value.filter(dream => !dream.archived).length);
const achievedCount = computed(() => dreams.value.filter(dream => !dream.archived && dream.status === 'completed').length);
const busy = ref(false);
const error = ref('');
const notice = ref('');
const loading = ref(true);
const pageHidden = ref(document.hidden);
const animations = reactive({});
const timers = new Set();
let destroyed = false;
const later = (callback, delay) => { const timer = setTimeout(() => { timers.delete(timer); callback(); }, delay); timers.add(timer); };
function announce(message) { notice.value = message; later(() => { notice.value = ''; }, 5000); }
function animate(id, type) { animations[id] = type; later(() => { delete animations[id]; }, 1600); }

const stage = ref(null);
const svg = ref(null);
const viewport = reactive({ width: 1000, height: 650 });
const camera = reactive({ x: 0, y: 0, zoom: 1 });
const pointers = new Map();
let lastPinch = null;
let tapDreamId = null;
let gestureStart = null;
let moved = false;
let observer;
let returnFocus;
const drag = ref(false);
const backgroundStars = Array.from({ length: 95 }, (_, index) => ({ x: (index * 67.13) % 100, y: (index * 43.71) % 100, opacity: 0.12 + (index % 5) * 0.08, radius: index % 9 === 0 ? 1.3 : 0.7 }));
function fit() {
    if (!stars.value.length) { camera.x = viewport.width / 2; camera.y = viewport.height / 2; camera.zoom = 1; return; }
    const xs = stars.value.map(star => star.x), ys = stars.value.map(star => star.y);
    const minX = Math.min(...xs), maxX = Math.max(...xs), minY = Math.min(...ys), maxY = Math.max(...ys);
    const desktop = viewport.width >= 1024;
    const left = desktop ? 375 : 45, right = desktop ? 235 : 45;
    const top = desktop ? 80 : 165, bottom = 110;
    const width = Math.max(120, viewport.width - left - right), height = Math.max(160, viewport.height - top - bottom);
    const zoom = Math.min(1.35, Math.max(0.025, Math.min(width / Math.max(320, maxX - minX + 150), height / Math.max(220, maxY - minY + 150))));
    camera.zoom = zoom;
    camera.x = left + width / 2 - (minX + maxX) / 2 * zoom;
    camera.y = top + height / 2 - (minY + maxY) / 2 * zoom;
}
function zoomAt(factor, x = viewport.width / 2, y = viewport.height / 2) {
    const zoom = Math.max(0.025, Math.min(3.5, camera.zoom * factor));
    camera.x = x - (x - camera.x) * zoom / camera.zoom;
    camera.y = y - (y - camera.y) * zoom / camera.zoom;
    camera.zoom = zoom;
}
function point(event) { const rect = svg.value.getBoundingClientRect(); return { x: (event.clientX - rect.left) * viewport.width / rect.width, y: (event.clientY - rect.top) * viewport.height / rect.height }; }
function pointerDown(event) {
    if (event.button && event.pointerType === 'mouse') return;
    if (!pointers.size) { moved = false; gestureStart = point(event); tapDreamId = event.target.closest('[data-dream-id]')?.dataset.dreamId; } else { moved = true; tapDreamId = null; }
    pointers.set(event.pointerId, point(event));
    svg.value.setPointerCapture(event.pointerId);
    drag.value = true;
    hoveredId.value = null;
    if (pointers.size === 2) { const [a, b] = [...pointers.values()]; lastPinch = Math.hypot(a.x - b.x, a.y - b.y); }
}
function pointerMove(event) {
    if (!pointers.has(event.pointerId)) return;
    const previous = pointers.get(event.pointerId), current = point(event);
    pointers.set(event.pointerId, current);
    const dx = current.x - previous.x, dy = current.y - previous.y;
    if (gestureStart && Math.hypot(current.x - gestureStart.x, current.y - gestureStart.y) > 5) moved = true;
    if (pointers.size === 2) {
        const [a, b] = [...pointers.values()], distance = Math.hypot(a.x - b.x, a.y - b.y);
        if (lastPinch && distance) zoomAt(distance / lastPinch, (a.x + b.x) / 2, (a.y + b.y) / 2);
        lastPinch = distance;
    } else { camera.x += dx; camera.y += dy; }
}
function pointerUp(event) { if (!pointers.has(event.pointerId)) return; pointers.delete(event.pointerId); lastPinch = null; drag.value = pointers.size > 0; if (!drag.value) { if (!moved && tapDreamId && event.type === 'pointerup') select(Number(tapDreamId)); tapDreamId = null; later(() => { moved = false; }, 0); } }
function wheel(event) { if (!event.shiftKey && !event.ctrlKey && !event.metaKey) return; event.preventDefault(); const p = point(event); const delta = Math.abs(event.deltaY) >= Math.abs(event.deltaX) ? event.deltaY : event.deltaX; zoomAt(Math.exp(-delta * 0.005), p.x, p.y); }
function keyboard(event) {
    const steps = { ArrowLeft: [70, 0], ArrowRight: [-70, 0], ArrowUp: [0, 70], ArrowDown: [0, -70] };
    if (steps[event.key]) { event.preventDefault(); camera.x += steps[event.key][0]; camera.y += steps[event.key][1]; }
    if (['+', '=', '-'].includes(event.key)) { event.preventDefault(); zoomAt(event.key === '-' ? 0.8 : 1.25); }
    if (event.key === 'Home') { event.preventDefault(); fit(); }
}
function select(id) { if (moved) return; returnFocus = mode.value === 'universe' ? svg.value?.querySelector(`[data-dream-id="${id}"]`) : document.activeElement; selectedId.value = id; hoveredId.value = null; }
function closePanel() { selectedId.value = null; nextTick(() => returnFocus?.isConnected && returnFocus.focus()); }
function setMode(value) { mode.value = value; try { localStorage.setItem(preferenceKey, value); } catch {} if (value === 'universe') nextTick(fit); }
function clearFilters() { Object.assign(filters, { query: '', category: '', status: '', sort: '', from: '', to: '' }); }
const date = value => value ? new Intl.DateTimeFormat('pt-BR').format(new Date(`${value}T12:00:00`)) : 'Sem prazo';

async function mutate({ url, method, body, done, created = false }) {
    if (busy.value) return;
    busy.value = true; error.value = '';
    try {
        const data = await postJson(url, method, body);
        if (data.dream) {
            const previous = dreams.value.find(dream => dream.id === data.dream.id);
            const index = dreams.value.findIndex(dream => dream.id === data.dream.id);
            if (index === -1) dreams.value.push(data.dream); else dreams.value[index] = data.dream;
            if (created) {
                listPage.value = 1;
                clearFilters(); animate(data.dream.id, 'birth'); announce(`Uma nova estrela nasceu: ${data.dream.title}`);
                await nextTick(); fit();
            } else if (previous?.status !== 'completed' && data.dream.status === 'completed') {
                animate(data.dream.id, 'achievement'); announce('Uma nova conquista ilumina seu universo.');
            } else { animate(data.dream.id, 'pulse'); announce('Jornada atualizada.'); }
        }
        done?.();
    } catch (failure) { error.value = failure.message; }
    finally { busy.value = false; }
}
async function remove({ url, task = false }) {
    if (busy.value) return;
    if (!await confirmDialog(task ? 'Excluir esta tarefa?' : 'Excluir este sonho e todas as suas tarefas? Essa ação não pode ser desfeita.', { danger: true, confirmLabel: 'Excluir' })) return;
    if (task) { await mutate({ url, method: 'DELETE' }); return; }
    const id = selectedId.value;
    busy.value = true; error.value = '';
    try { await postJson(url, 'DELETE'); dreams.value = dreams.value.filter(dream => dream.id !== id); closePanel(); announce('Sonho excluído.'); }
    catch (failure) { error.value = failure.message; }
    finally { busy.value = false; }
}

const createDialog = ref(null);
const draft = reactive({ title: '', notes: '', category: 'other', priority: 'medium', target_date: '', financial_goal: '', saved_amount: '', image_url: '' });
function openCreate() { error.value = ''; createDialog.value.showModal(); nextTick(() => createDialog.value.querySelector('[name=title]')?.focus()); }
function create() { mutate({ url: props.storeUrl, method: 'POST', body: { ...draft }, created: true, done: () => { createDialog.value.close(); Object.assign(draft, { title: '', notes: '', category: 'other', priority: 'medium', target_date: '', financial_goal: '', saved_amount: '', image_url: '' }); } }); }
watch(() => [filters.query, filters.category, filters.status, filters.from, filters.to], () => { hoveredId.value = null; nextTick(fit); });
watch(() => selectedId.value, (id) => { document.body.style.overflow = id ? 'hidden' : ''; });
watch(mode, value => { document.body.classList.toggle('dreamverse-immersive', value === 'universe'); }, { immediate: true });
const visibility = () => { pageHidden.value = document.hidden; };
const controller = new AbortController();
onMounted(async () => {
    observer = new ResizeObserver(([entry]) => { viewport.width = entry.contentRect.width; viewport.height = entry.contentRect.height; fit(); });
    observer.observe(stage.value);
    document.addEventListener('visibilitychange', visibility);
    try {
        const response = await fetch(props.indexUrl, { headers: { Accept: 'application/json' }, cache: 'no-store', signal: controller.signal });
        if (!response.ok) throw new Error('Não foi possível atualizar seu universo. Os sonhos já carregados continuam disponíveis.');
        const data = await response.json();
        if (!destroyed) { dreams.value = data.dreams; await nextTick(); fit(); }
    } catch (failure) { if (!destroyed) error.value = failure.message; }
    finally { if (!destroyed) loading.value = false; }
});
onBeforeUnmount(() => { destroyed = true; controller.abort(); observer?.disconnect(); document.removeEventListener('visibilitychange', visibility); timers.forEach(clearTimeout); document.body.style.overflow = ''; document.body.classList.remove('dreamverse-immersive'); });
</script>

<template>
    <section class="dreamverse" :class="{ 'dream-page-hidden': pageHidden, 'is-immersive': mode === 'universe', 'is-narrow': viewport.width < 1024 }" aria-labelledby="dreamverse-title">
        <header class="dreamverse-header"><div><p class="dream-eyebrow">SEU FUTURO, EM CONSTRUÇÃO</p><h1 id="dreamverse-title">Meu Universo<span aria-hidden="true">.</span></h1><p>Cada grande conquista começa com um sonho.</p></div><div class="dream-header-actions"><div class="dream-view-switch" role="group" aria-label="Visualização dos sonhos"><button type="button" :aria-pressed="mode === 'universe'" @click="setMode('universe')">Universo</button><button type="button" :aria-pressed="mode === 'list'" @click="setMode('list')">Lista</button></div><button type="button" class="dream-primary" data-dream-create @click="openCreate"><span aria-hidden="true">+</span> Criar sonho</button></div><div v-if="mode === 'universe'" class="dream-floating-summary"><div><strong>{{ activeCount }}</strong><span>Sonhos</span></div><div><strong>{{ new Set(dreams.filter(dream => !dream.archived).map(dream => dream.category)).size }}</strong><span>Constelações</span></div><div><strong>{{ achievedCount }}</strong><span>Conquistas</span></div></div><button v-if="mode === 'universe'" type="button" class="dream-filter-toggle" :aria-expanded="filtersOpen" aria-controls="dream-filters" @click="filtersOpen = !filtersOpen; constellationsOpen = false"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 5h16l-6 7v7l-4-2v-5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" /></svg>Filtros e busca<span v-if="filters.query || filters.status || filters.from || filters.to" class="dream-live-dot" /></button><details v-if="mode === 'universe'" class="dream-shortcuts"><summary>Comandos do mapa</summary><dl><div><dt><kbd>Shift</kbd> + rolagem</dt><dd>Aproximar ou afastar</dd></div><div><dt>Clicar e arrastar</dt><dd>Mover pelo universo</dd></div><div><dt><kbd>↑</kbd> <kbd>↓</kbd> <kbd>←</kbd> <kbd>→</kbd></dt><dd>Mover pelo teclado</dd></div><div><dt><kbd>+</kbd> / <kbd>−</kbd></dt><dd>Ajustar o zoom</dd></div><div><dt><kbd>Home</kbd></dt><dd>Centralizar o mapa</dd></div><div><dt>Dois dedos</dt><dd>Ampliar no celular</dd></div></dl><p>Para usar o teclado, clique no mapa ou navegue até ele com Tab.</p></details></header>
        <div v-show="mode === 'list'" class="dream-overview"><span><strong>{{ activeCount }}</strong> {{ activeCount === 1 ? 'sonho em sua órbita' : 'sonhos em sua órbita' }}</span><span><i class="dream-gold-dot" /><strong>{{ achievedCount }}</strong> {{ achievedCount === 1 ? 'conquista alcançada' : 'conquistas alcançadas' }}</span><span v-if="loading" class="dream-muted" role="status">Atualizando universo…</span></div>
        <form v-show="mode === 'list' || filtersOpen" id="dream-filters" class="dream-filters" aria-label="Filtrar sonhos" @submit.prevent><div v-if="mode === 'universe'" class="dream-filter-heading"><h2>Filtrar universo</h2><button type="button" class="dream-icon-button" aria-label="Fechar filtros" @click="filtersOpen = false">×</button></div><label class="dream-search"><span class="sr-only">Buscar por título</span><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="6" stroke="currentColor" stroke-width="1.5" /><path d="m15 15 5 5" stroke="currentColor" stroke-width="1.5" /></svg><input v-model="filters.query" type="search" placeholder="Encontre um sonho…" /></label><label><span class="sr-only">Situação</span><select v-model="filters.status"><option value="">Todos os sonhos ativos</option><option value="not_started">Não iniciados</option><option value="in_progress">Em andamento</option><option value="completed">Concluídos</option><option value="archived">Arquivados</option></select></label><label><span class="sr-only">Ordenar por</span><select v-model="filters.sort"><option value="">Mais recentes</option><option value="priority">Prioridade</option><option value="target_date">Prazo desejado</option></select></label><details class="dream-period"><summary>Período</summary><div><label>Criado de<input v-model="filters.from" type="date" /></label><label>Até<input v-model="filters.to" type="date" :min="filters.from" /></label></div></details><button v-if="filters.query || filters.status || filters.category || filters.from || filters.to" type="button" class="dream-clear" @click="clearFilters">Limpar</button></form>
        <nav v-show="mode === 'list' || constellationsOpen || viewport.width >= 1024" class="dream-constellations" aria-label="Constelações"><h2 v-if="mode === 'universe'">Constelações</h2><button type="button" :aria-pressed="!filters.category" @click="filters.category = ''">Todas as constelações</button><button v-for="(label, key) in categories" :key="key" type="button" :aria-pressed="filters.category === key" @click="filters.category = filters.category === key ? '' : key"><span>{{ label }}</span><small v-if="mode === 'universe'">{{ dreams.filter(dream => dream.category === key && (filters.status === 'archived' ? dream.archived : !dream.archived)).length }}</small></button></nav>
        <button v-if="mode === 'universe'" type="button" class="dream-constellation-toggle" :aria-expanded="constellationsOpen" @click="constellationsOpen = !constellationsOpen; filtersOpen = false"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m4 17 5-11 6 8 5-10M4 17l11-3 5 6" stroke="currentColor" stroke-width="1.3" /><circle cx="4" cy="17" r="2" fill="currentColor" /><circle cx="9" cy="6" r="2" fill="currentColor" /><circle cx="15" cy="14" r="2" fill="currentColor" /></svg>Constelações</button>
        <p v-if="error && !selected && !createDialog?.open" class="dream-error" role="alert">{{ error }}</p>
        <div v-show="mode === 'universe'" ref="stage" class="dream-map" :class="{ 'is-dragging': drag }" data-dream-map>
            <svg ref="svg" :viewBox="`0 0 ${viewport.width} ${viewport.height}`" role="group" aria-label="Mapa dos sonhos. Arraste para navegar; segure Shift e role o mouse para ampliar ou reduzir. No teclado, use as setas, mais, menos e Home." tabindex="0" @pointerdown="pointerDown" @pointermove="pointerMove" @pointerup="pointerUp" @pointercancel="pointerUp" @lostpointercapture="pointerUp" @wheel="wheel" @keydown="keyboard" @click.capture="event => { if (moved) { event.stopPropagation(); event.preventDefault(); } }">
                <defs><radialGradient v-for="(color, key) in { blue: '#8194b2', purple: '#9c98ff', gold: '#f5c85b' }" :id="`dream-halo-${key}`" :key="key"><stop offset="0" :stop-color="color" stop-opacity="0.7" /><stop offset="0.3" :stop-color="color" stop-opacity="0.28" /><stop offset="1" :stop-color="color" stop-opacity="0" /></radialGradient></defs>
                <g aria-hidden="true" class="dream-sky"><circle v-for="(star, index) in backgroundStars" :key="index" :cx="`${star.x}%`" :cy="`${star.y}%`" :r="star.radius" :opacity="star.opacity" fill="#cbd5e1" /></g>
                <g :transform="`translate(${camera.x} ${camera.y}) scale(${camera.zoom})`">
                    <g class="dream-connections" aria-hidden="true"><line v-for="edge in edges" :key="edge.key" :x1="edge.a.x" :y1="edge.a.y" :x2="edge.b.x" :y2="edge.b.y" :class="{ 'is-achieved': edge.achieved }" vector-effect="non-scaling-stroke" /></g>
                    <text v-for="region in regions" :key="region.key" :x="region.x" :y="region.y" class="dream-region-name" :font-size="12 / camera.zoom" aria-hidden="true">{{ region.label.toLocaleUpperCase('pt-BR') }}</text>
                    <DreamStar v-for="star in stars" :key="star.id" :dream="star" :zoom="camera.zoom" :selected="selectedId === star.id" :animation="animations[star.id]" @select="select" @hover="hoveredId = $event" @leave="hoveredId = null" />
                </g>
            </svg>
            <div v-if="!visible.length" class="dream-map-empty"><svg viewBox="0 0 80 80" fill="none" aria-hidden="true"><circle cx="40" cy="40" r="25" stroke="#635bff" stroke-opacity="0.35" /><path d="m40 21 4 15 15 4-15 4-4 15-4-15-15-4 15-4Z" fill="#9c98ff" /></svg><h2>{{ dreams.length ? 'Nenhum sonho nesta órbita' : 'Seu universo começa aqui' }}</h2><p>{{ dreams.length ? 'Experimente outra constelação ou ajuste os filtros.' : 'Todo universo começa com uma primeira estrela.' }}</p><button type="button" class="dream-primary" @click="dreams.length ? clearFilters() : openCreate()">{{ dreams.length ? 'Limpar filtros' : '+ Criar meu primeiro sonho' }}</button></div>
            <div v-if="hovered && !drag" class="dream-tooltip" role="status"><span class="dream-eyebrow">{{ categories[hovered.category] }}</span><h3>{{ hovered.title }}</h3><p>{{ hovered.status_label }} · {{ hovered.progress }}%</p><span>{{ hovered.tasks.filter(task => task.completed).length }} de {{ hovered.tasks.length }} tarefas concluídas</span><small>Clique ou pressione Enter para explorar</small></div>
            <div class="dream-map-caption" aria-hidden="true"><span class="dream-live-dot" />{{ visible.length }} {{ visible.length === 1 ? 'estrela' : 'estrelas' }} neste universo</div>
            <div class="dream-map-controls" role="group" aria-label="Navegação do mapa"><button type="button" class="dream-map-button" aria-label="Diminuir zoom" @click="zoomAt(0.8)">−</button><span>{{ Math.round(camera.zoom * 100) }}%</span><button type="button" class="dream-map-button" aria-label="Aumentar zoom" @click="zoomAt(1.25)">+</button><button type="button" class="dream-map-button dream-fit" @click="fit">Centralizar</button></div>
            <p class="dream-map-help">Arraste para explorar <span>·</span> Use dois dedos para ampliar</p>
        </div>
        <div v-if="mode === 'list'" ref="listContainer" class="dream-list" tabindex="-1" :aria-label="`Lista de sonhos, página ${currentPage} de ${totalPages}`"><button v-for="dream in pageDreams" :key="dream.id" type="button" class="dream-list-card" :class="{ 'is-completed': dream.status === 'completed' }" @click="select(dream.id)"><div class="dream-list-top"><span class="dream-eyebrow">{{ categories[dream.category] }}</span><span>{{ dream.archived ? 'Arquivado' : dream.status_label }}</span></div><h2>{{ dream.title }}</h2><p>{{ dream.notes || 'Cada pequeno passo aproxima você desta conquista.' }}</p><div class="dream-list-progress"><span>{{ dream.progress }}%</span><span>{{ dream.tasks.filter(task => task.completed).length }}/{{ dream.tasks.length }} tarefas</span></div><div class="dream-progress"><span :style="{ width: `${dream.progress}%` }" /></div><footer><span>Prioridade {{ { low: 'baixa', medium: 'média', high: 'alta' }[dream.priority] }}</span><span>{{ date(dream.target_date) }}</span></footer></button><div v-if="!visible.length" class="dream-list-empty"><h2>Nenhum sonho nesta situação.</h2><p>Ajuste os filtros ou crie uma nova estrela.</p><button type="button" class="dream-primary" @click="openCreate">+ Criar sonho</button></div></div>
        <nav v-if="mode === 'list' && visible.length" class="dream-pagination" aria-label="Paginação dos sonhos">
            <p role="status" aria-live="polite">Mostrando <strong>{{ firstItem }}–{{ lastItem }}</strong> de <strong>{{ visible.length }}</strong> sonhos</p>
            <div v-if="totalPages > 1" class="dream-pagination-controls">
                <button type="button" class="dream-pagination-previous" :disabled="currentPage === 1" @click="changePage(currentPage - 1)">‹ Anterior</button>
                <ol><li v-for="entry in pageLinks" :key="entry"><span v-if="typeof entry === 'string'" aria-hidden="true">…</span><button v-else type="button" :aria-current="entry === currentPage ? 'page' : undefined" :aria-label="`Ir para página ${entry}`" @click="changePage(entry)">{{ entry }}</button></li></ol>
                <button type="button" class="dream-pagination-next" :disabled="currentPage === totalPages" @click="changePage(currentPage + 1)">Próxima ›</button>
            </div>
        </nav>
        <footer class="dreamverse-footer"><span><i class="dream-status-dot not-started" />Não iniciado</span><span><i class="dream-status-dot in-progress" />Em andamento</span><span><i class="dream-status-dot completed" />Conquista alcançada</span><p>Pequenos passos. Infinitas possibilidades.</p></footer>
        <div class="dream-announcement" role="status" aria-live="polite">{{ notice }}</div>
        <DreamDetailsPanel v-if="selected" :dream="selected" :categories="categories" :busy="busy" @close="closePanel" @mutate="mutate" @remove="remove" />
        <p v-if="error && selected" class="dream-floating-error" role="alert"><span>{{ error }}</span><button type="button" aria-label="Fechar aviso" @click="error = ''">×</button></p>
        <dialog ref="createDialog" class="dream-create-dialog" aria-labelledby="dream-create-title" @click="event => { if (event.target === createDialog) { const bounds = createDialog.getBoundingClientRect(); if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) createDialog.close(); } }"><header class="dream-panel-header"><div><p class="dream-eyebrow">UMA NOVA POSSIBILIDADE</p><h2 id="dream-create-title">Criar sonho</h2></div><button type="button" class="dream-icon-button" aria-label="Fechar criação" @click="createDialog.close()">×</button></header><form @submit.prevent="create"><p class="dream-create-message">Todo universo começa com uma primeira estrela.</p><DreamFields :form="draft" :categories="categories" prefix="new-dream" /><p v-if="error" class="dream-error" role="alert">{{ error }}</p><div class="dream-actions"><button type="button" class="dream-button" @click="createDialog.close()">Cancelar</button><button class="dream-primary" :disabled="busy">{{ busy ? 'Criando…' : 'Criar sonho' }}</button></div></form></dialog>
    </section>
</template>
