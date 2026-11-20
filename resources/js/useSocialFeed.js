import { computed, nextTick, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { socialRequest } from './social-request';

const pageSize = 15;
const estimatedHeight = 280;

export function useSocialFeed({ requestUrl, enabled, singlePost, onError }) {
    const feed = ref(null);
    const posts = ref([]);
    const next = ref(null);
    const loading = ref(false);
    const start = ref(0);
    const heights = ref(new Map());
    const items = new Map();
    let observer;
    let frame = 0;
    let timer;
    let generation = 0;
    let polling = false;
    let automaticRetry = true;
    let ready = false;
    let restoringPosition = false;
    let newestId = 0;

    const offsets = computed(() => {
        const values = [0];
        for (const post of posts.value) values.push(values.at(-1) + (heights.value.get(post.id) ?? estimatedHeight));
        return values;
    });
    const visiblePosts = computed(() => posts.value.slice(start.value, start.value + pageSize));
    const beforeHeight = computed(() => offsets.value[start.value] ?? 0);
    const afterHeight = computed(() => offsets.value.at(-1) - (offsets.value[start.value + visiblePosts.value.length] ?? 0));

    function syncWindow() {
        frame = 0;
        if (!feed.value || !enabled.value || restoringPosition) return;
        const bounds = feed.value.getBoundingClientRect();
        const position = Math.max(0, -bounds.top);
        const first = offsets.value.findIndex(offset => offset > position) - 1;
        start.value = Math.max(0, Math.min(Math.max(0, posts.value.length - pageSize), first < 0 && posts.value.length ? posts.value.length - pageSize : first - 2));
        if (next.value && !loading.value && automaticRetry && bounds.bottom < window.innerHeight + 350) loadMore();
    }

    function scheduleWindow() {
        if (!frame) frame = requestAnimationFrame(syncWindow);
    }

    function trackItem(id, element) {
        const previous = items.get(id);
        if (previous === element) return;
        if (previous) {
            observer?.unobserve(previous);
            items.delete(id);
        }
        if (element) {
            items.set(id, element);
            observer?.observe(element);
        }
    }

    async function loadMore() {
        if (loading.value || !next.value) return;
        loading.value = true;
        const version = generation;
        try {
            const result = await socialRequest(requestUrl({ cursor: next.value }));
            if (version !== generation) return;
            posts.value.push(...result.items);
            next.value = result.next;
            automaticRetry = true;
            await nextTick();
            scheduleWindow();
        } catch (failure) {
            if (version !== generation) return;
            automaticRetry = false;
            onError(failure);
        } finally {
            if (version === generation) loading.value = false;
        }
    }

    async function reload() {
        const version = ++generation;
        loading.value = true;
        ready = false;
        automaticRetry = true;
        next.value = null;
        posts.value = [];
        heights.value.clear();
        start.value = 0;
        try {
            const result = await socialRequest(requestUrl({}));
            if (version !== generation) return;
            posts.value = result.items;
            next.value = result.next;
            newestId = result.items[0]?.id ?? 0;
            ready = true;
            await nextTick();
            scheduleWindow();
        } catch (failure) {
            if (version === generation) onError(failure);
        } finally {
            if (version === generation) loading.value = false;
        }
    }

    async function refreshNewPosts() {
        if (!ready || polling || !enabled.value || singlePost || document.hidden) return;
        polling = true;
        const version = generation;
        try {
            const result = await socialRequest(requestUrl({ after: newestId }));
            if (version !== generation || !result.items.length) return;
            const incoming = [...result.items].reverse();
            newestId = incoming[0].id;
            const keepPosition = feed.value && feed.value.getBoundingClientRect().top < 0;
            restoringPosition = Boolean(keepPosition);
            if (keepPosition) start.value += incoming.length;
            posts.value.unshift(...incoming);
            await nextTick();
            if (keepPosition && version === generation) window.scrollBy(0, offsets.value[incoming.length]);
            restoringPosition = false;
            scheduleWindow();
        } catch {
            // A temporary connection failure is retried on the next poll.
        } finally {
            polling = false;
        }
    }

    function removePost(id) {
        posts.value = posts.value.filter(post => post.id !== id);
        heights.value.delete(id);
        start.value = Math.min(start.value, Math.max(0, posts.value.length - pageSize));
        nextTick(scheduleWindow);
    }

    function updatePost(id, changes) {
        const post = posts.value.find(item => item.id === id);
        if (post) Object.assign(post, changes);
    }

    watch(enabled, active => { if (active) nextTick(scheduleWindow); });
    onMounted(() => {
        observer = new ResizeObserver(entries => {
            for (const entry of entries) {
                const height = entry.target.getBoundingClientRect().height;
                if (height > 0) heights.value.set(Number(entry.target.dataset.feedPost), height);
            }
            scheduleWindow();
        });
        items.forEach(element => observer.observe(element));
        window.addEventListener('scroll', scheduleWindow, { passive: true });
        window.addEventListener('resize', scheduleWindow);
        document.addEventListener('visibilitychange', refreshNewPosts);
        timer = window.setInterval(refreshNewPosts, 5000);
    });
    onBeforeUnmount(() => {
        generation++;
        observer?.disconnect();
        cancelAnimationFrame(frame);
        clearInterval(timer);
        window.removeEventListener('scroll', scheduleWindow);
        window.removeEventListener('resize', scheduleWindow);
        document.removeEventListener('visibilitychange', refreshNewPosts);
    });

    return { feed, posts, next, loading, visiblePosts, beforeHeight, afterHeight, trackItem, loadMore, reload, refreshNewPosts, removePost, updatePost };
}
