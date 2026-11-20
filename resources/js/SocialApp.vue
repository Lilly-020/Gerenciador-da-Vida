<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import SocialAvatar from './SocialAvatar.vue';
import SocialPeople from './SocialPeople.vue';
import SocialPostCard from './SocialPostCard.vue';
import SocialRichEditor from './SocialRichEditor.vue';
import { socialRequest } from './social-request';
import { useSocialFeed } from './useSocialFeed';
import { confirmDialog } from './dialog';

const props = defineProps({ base: String, profileSettings: String, viewer: Number, person: Number, section: String });
const searchText = ref('');
const peopleMode = ref(['friends', 'requests'].includes(props.section) ? props.section : '');
const people = ref([]);
const peopleNext = ref(null);
const suggestions = ref([]);
const requests = ref([]);
const ownProfile = ref(null);
const profile = ref(null);
const notifications = ref([]);
const notificationsOpen = ref(false);
const notificationsNext = ref(null);
const unread = ref(0);
const peopleLoading = ref(false);
const writing = ref(false);
const error = ref('');
const status = ref('');
const composer = ref(null);
const composerOpen = ref(false);
const feedAuthor = ref('');
const feedOrder = ref('recent');
const feedFilters = [{ key: '', label: 'Todos' }, { key: 'friends', label: 'Amigos' }, { key: 'me', label: 'Você' }];
const fileInput = ref(null);
const files = ref([]);
const content = ref({ content: '', content_html: '' });
const focusedPost = new URLSearchParams(location.search).get('post');
const csrf = document.querySelector('meta[name="csrf-token"]').content;
const peopleLabels = { search: 'Resultados da pesquisa', friends: 'Amigos', requests: 'Solicitações de amizade', suggestions: 'Sugestões de amizade', blocked: 'Pessoas bloqueadas' };
const showFeed = computed(() => props.section === 'feed' && !peopleMode.value);
const openCommentsPost = ref(null);
let peopleVersion = 0;

function endpoint(path, parameters = {}) {
    const url = new URL(`${props.base}${path}`, location.origin);
    for (const [key, value] of Object.entries(parameters)) {
        if (value !== null && value !== '') url.searchParams.set(key, value);
    }
    return url;
}

function showFailure(failure) { error.value = failure.message; }

const {
    feed, posts, next: feedNext, loading: feedLoading, visiblePosts, beforeHeight, afterHeight,
    trackItem, loadMore, reload: loadFeed, refreshNewPosts, removePost: removeFeedPost, updatePost,
} = useSocialFeed({
    requestUrl: parameters => endpoint('/feed', { person: props.person, post: focusedPost, author: feedAuthor.value, order: feedOrder.value, ...parameters }),
    enabled: showFeed,
    order: feedOrder,
    singlePost: Boolean(focusedPost),
    onError: showFailure,
});

watch([visiblePosts, showFeed], ([visible, active]) => {
    if (!active || !visible.some(post => post.id === openCommentsPost.value)) {
        openCommentsPost.value = null;
    }
});

function toggleComments(postId) {
    openCommentsPost.value = openCommentsPost.value === postId ? null : postId;
}

async function loadProfile() {
    ownProfile.value = await socialRequest(endpoint(`/profile/${props.viewer}/data`));
    if (props.person) {
        profile.value = props.person === props.viewer ? ownProfile.value : await socialRequest(endpoint(`/profile/${props.person}/data`));
    }
}

async function loadSidebar() {
    const [suggested, pending] = await Promise.all([
        socialRequest(endpoint('/people', { type: 'suggestions' })),
        socialRequest(endpoint('/people', { type: 'requests' })),
    ]);
    suggestions.value = suggested.items.slice(0, 3);
    requests.value = pending.items.filter(person => person.relationship === 'received').slice(0, 3);
}

async function loadPeople(mode, cursor = null) {
    const version = ++peopleVersion;
    peopleMode.value = mode;
    peopleLoading.value = true;
    if (!cursor) { people.value = []; peopleNext.value = null; }
    try {
        const result = await socialRequest(endpoint('/people', { type: mode, q: mode === 'search' ? searchText.value.trim() : '', cursor }));
        if (version !== peopleVersion) return;
        people.value = cursor ? [...people.value, ...result.items] : result.items;
        peopleNext.value = result.next;
    } catch (failure) { if (version === peopleVersion) showFailure(failure); }
    finally { if (version === peopleVersion) peopleLoading.value = false; }
}

async function loadNotifications(cursor = null) {
    try {
        const result = await socialRequest(endpoint('/notifications', { cursor }));
        notifications.value = cursor ? [...notifications.value, ...result.items] : result.items;
        notificationsNext.value = result.next;
        unread.value = result.unread;
    } catch (failure) { showFailure(failure); }
}

function focusComposer() {
    peopleMode.value = '';
    composerOpen.value = true;
    nextTick(() => composer.value?.focus());
}

function selectImages(event) {
    const selected = [...event.target.files];
    event.target.value = '';
    if (!selected.length) return;
    files.value = selected;
    if (files.value.length) composerOpen.value = true;
}

function removeImage(index) {
    files.value.splice(index, 1);
}

function resetComposer() {
    content.value = { content: '', content_html: '' };
    files.value = [];
    if (fileInput.value) fileInput.value.value = '';
    composerOpen.value = false;
}

async function cancelPublication() {
    if (writing.value) return;
    const hasDraft = content.value.content.trim().length > 0 || files.value.length > 0;
    if (hasDraft && !await confirmDialog('Descartar o texto e as fotos desta publicação?', { confirmLabel: 'Descartar', cancelLabel: 'Continuar editando', danger: true })) return;
    resetComposer();
}

function filterFeed(author) {
    if (feedAuthor.value === author) return;
    feedAuthor.value = author;
    openCommentsPost.value = null;
    error.value = '';
    return loadFeed();
}

function sortFeed(order) {
    if (feedOrder.value === order) return;
    feedOrder.value = order;
    openCommentsPost.value = null;
    error.value = '';
    return loadFeed();
}

function toggleNotifications() {
    notificationsOpen.value = !notificationsOpen.value;
    if (notificationsOpen.value) loadNotifications();
}

async function relationshipChanged(operation) {
    if (operation === 'block' && props.person) { location.assign(props.base); return; }
    error.value = '';
    if (peopleMode.value) loadPeople(peopleMode.value);
    if (props.section === 'feed') loadFeed();
    await Promise.all([loadProfile(), loadSidebar(), loadNotifications()]).catch(showFailure);
}

async function publish() {
    if (writing.value) return;
    writing.value = true;
    error.value = '';
    status.value = '';
    const data = new FormData();
    data.set('content', content.value.content);
    data.set('content_html', content.value.content_html);
    for (const image of files.value) data.append('images[]', image);

    try {
        await socialRequest(endpoint('/posts'), { method: 'POST', data });
        resetComposer();
        status.value = 'Publicação criada.';
        const updateFeed = feedAuthor.value === 'friends' ? filterFeed('') : refreshNewPosts();
        await Promise.all([updateFeed, loadProfile()]).catch(showFailure);
    } catch (failure) { showFailure(failure); }
    finally { writing.value = false; }
}

function removePost(id) {
    removeFeedPost(id);
    loadProfile().catch(showFailure);
}

onMounted(() => {
    if (props.section === 'feed') loadFeed();
    if (peopleMode.value) loadPeople(peopleMode.value);
    Promise.all([loadProfile(), loadSidebar(), loadNotifications()]).catch(showFailure);
    if (new URLSearchParams(location.search).has('create')) focusComposer();
});
</script>

<template>
    <section class="social-page w-full" aria-labelledby="social-heading">
        <div class="space-y-5">
            <header class="social-header">
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-indigo-300">Seu círculo de amigos</p>
                    <h1 id="social-heading" class="text-3xl font-semibold text-white">Social</h1>
                    <p class="mt-2 text-sm text-slate-400">Compartilhe momentos e acompanhe seus amigos.</p>
                </div>
                <div class="social-actions social-header-actions">
                    <a v-if="person || section !== 'feed' || focusedPost" :href="`${base}?create=1`" class="social-primary">Criar publicação</a>
                    <button v-else class="social-primary" @click="focusComposer">Criar publicação</button>
                    <button :aria-expanded="notificationsOpen" aria-controls="social-notifications" @click="toggleNotifications">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0v5l-2 3h16l-2-3ZM10 20h4"/></svg>
                        Notificações <span v-if="unread" class="social-count">{{ unread }}</span>
                    </button>
                </div>
            </header>

            <nav class="social-nav" aria-label="Navegação do Social">
                <a :href="base" :aria-current="showFeed && !person ? 'page' : null">Feed</a>
                <a :href="`${base}/friends`" :aria-current="peopleMode === 'friends' ? 'page' : null">Amigos</a>
                <a :href="`${base}/requests`" :aria-current="peopleMode === 'requests' ? 'page' : null">Solicitações</a>
                <a :href="`${base}/profile/${viewer}`" :aria-current="showFeed && person === viewer ? 'page' : null">Meu perfil</a>
            </nav>

            <form class="social-search" @submit.prevent="loadPeople('search')">
                <input v-model="searchText" type="search" aria-label="Nome da pessoa" placeholder="Buscar pessoas pelo nome" maxlength="120" required>
                <button type="submit">Buscar</button>
            </form>

            <section v-if="notificationsOpen" id="social-notifications" class="social-card social-notifications" aria-label="Notificações do Social">
                <h2>Notificações</h2>
                <p v-if="!notifications.length">Nenhuma notificação do Social.</p>
                <form v-for="item in notifications" :key="item.id" method="post" :action="`${base}/notifications/${item.id}`">
                    <input type="hidden" name="_token" :value="csrf">
                    <button class="social-notification" :class="{ 'social-unread': !item.read }">
                        <strong>{{ item.title }}</strong><small>{{ item.when }}<span v-if="!item.read"> · Não lida</span></small>
                    </button>
                </form>
                <div v-if="notificationsNext" class="social-actions"><button @click="loadNotifications(notificationsNext)">Carregar mais</button></div>
            </section>

            <p v-if="error" class="social-error" role="alert">{{ error }}</p>
            <p v-if="status" class="social-status" role="status">{{ status }}</p>

            <div class="social-columns">
                <div class="social-stack social-main-column">
                    <section v-if="peopleMode" class="social-card">
                        <div class="social-section-heading">
                            <h2>{{ peopleLabels[peopleMode] }}</h2>
                            <div v-if="section === 'friends'" class="social-actions">
                                <button :aria-pressed="peopleMode === 'friends'" @click="loadPeople('friends')">Amigos</button>
                                <button :aria-pressed="peopleMode === 'suggestions'" @click="loadPeople('suggestions')">Sugestões</button>
                                <button :aria-pressed="peopleMode === 'blocked'" @click="loadPeople('blocked')">Bloqueados</button>
                            </div>
                        </div>
                        <p v-if="peopleLoading" role="status">Carregando pessoas…</p>
                        <p v-else-if="!people.length">Nenhuma pessoa encontrada.</p>
                        <SocialPeople v-if="people.length" :people="people" :base="base" :blocked="peopleMode === 'blocked'" show-block @changed="relationshipChanged" />
                        <div v-if="peopleNext" class="social-actions"><button :disabled="peopleLoading" @click="loadPeople(peopleMode, peopleNext)">Carregar mais pessoas</button></div>
                    </section>

                    <template v-if="showFeed">
                        <section v-if="profile" class="social-card social-profile">
                            <div class="social-cover"><img v-if="profile.cover" :src="profile.cover" alt=""></div>
                            <div class="social-profile-body">
                                <div class="social-post-header"><SocialAvatar :person="profile" /><h2>{{ profile.name }}</h2></div>
                                <p v-if="profile.bio" class="social-post-text">{{ profile.bio }}</p>
                                <p class="social-muted">{{ profile.friends_count }} amigos · {{ profile.posts_count }} publicações visíveis</p>
                                <SocialPeople v-if="!profile.editable" :people="[profile]" :base="base" show-block @changed="relationshipChanged" />
                            </div>
                        </section>

                        <form v-if="!person && !focusedPost" class="social-card social-form social-composer" @submit.prevent="publish">
                            <div class="social-composer-heading">
                                <SocialAvatar v-if="ownProfile" :person="ownProfile" />
                                <button v-if="!composerOpen" type="button" class="social-composer-prompt" aria-expanded="false" @click="focusComposer">O que você está pensando?</button>
                                <template v-else>
                                    <span class="text-sm font-semibold">Criar publicação</span>
                                    <div class="social-composer-options">
                                        <button type="button" class="social-composer-collapse" aria-expanded="true" @click="composerOpen = false">Recolher</button>
                                        <button type="button" class="social-composer-collapse" :disabled="writing" @click="cancelPublication">Cancelar</button>
                                    </div>
                                </template>
                            </div>
                            <SocialRichEditor v-if="composerOpen" ref="composer" v-model="content" label="O que você está pensando?" placeholder="Escreva sua publicação…" />
                            <ul v-if="composerOpen && files.length" class="social-composer-files social-actions" aria-label="Fotos anexadas">
                                <li v-for="(file, index) in files" :key="index">
                                    <span :title="file.name">{{ file.name }}</span>
                                    <button type="button" :aria-label="`Remover foto ${file.name}`" title="Remover foto" :disabled="writing" @click="removeImage(index)">×</button>
                                </li>
                            </ul>
                            <div class="social-composer-actions">
                                <label class="social-upload">
                                    <input ref="fileInput" aria-label="Adicionar fotos" type="file" multiple accept="image/jpeg,image/png,image/webp" :disabled="writing" @change="selectImages">
                                    <span>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="m3 16 5-5 4 4 3-3 6 6"/><circle cx="15.5" cy="8.5" r="1"/></svg>
                                        {{ files.length ? `${files.length} foto(s)` : 'Foto' }}
                                    </span>
                                </label>
                                <span v-if="composerOpen" class="social-muted">Visível para amigos</span>
                                <button type="submit" class="social-primary" :disabled="writing || (!content.content.trim() && !files.length)">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" aria-hidden="true"><path d="m3 11 18-8-8 18-2-8-8-2Zm8 2L21 3"/></svg>
                                    {{ writing ? 'Publicando…' : 'Publicar' }}
                                </button>
                            </div>
                        </form>
                        <div v-if="!person && !focusedPost" class="social-feed-tools">
                            <div class="social-feed-filters" role="group" aria-label="Filtrar publicações">
                                <button v-for="filter in feedFilters" :key="filter.key" type="button" :aria-pressed="feedAuthor === filter.key" @click="filterFeed(filter.key)">{{ filter.label }}</button>
                            </div>
                            <div class="social-feed-order">
                                <select :value="feedOrder" aria-label="Ordenar publicações" @change="sortFeed($event.target.value)">
                                    <option value="recent">Mais recentes</option>
                                    <option value="oldest">Mais antigas</option>
                                </select>
                            </div>
                        </div>
                        <p v-if="feedLoading && !posts.length" class="social-card" role="status">Carregando publicações…</p>
                        <p v-else-if="!posts.length && !error" class="social-card">Nenhuma publicação disponível.</p>
                        <div ref="feed" class="social-feed">
                            <div v-if="beforeHeight" :style="{ height: `${beforeHeight}px` }" aria-hidden="true"></div>
                            <div v-for="post in visiblePosts" :key="post.id" :ref="element => trackItem(post.id, element)" :data-feed-post="post.id" class="social-feed-item">
                                <SocialPostCard :post="post" :base="base" :comments-open="openCommentsPost === post.id" @toggle-comments="toggleComments(post.id)" @deleted="removePost" @updated="updatePost" />
                            </div>
                            <div v-if="afterHeight" :style="{ height: `${afterHeight}px` }" aria-hidden="true"></div>
                        </div>
                        <div v-if="feedNext" class="social-actions">
                            <button :disabled="feedLoading" @click="loadMore">{{ feedLoading ? 'Carregando…' : 'Carregar mais publicações' }}</button>
                        </div>
                        <div v-if="focusedPost" class="social-actions"><a :href="base">Voltar ao feed</a></div>
                    </template>
                </div>

                <aside class="social-stack social-sidebar" aria-label="Perfil e amizades">
                    <section v-if="ownProfile" class="social-card social-profile-summary" aria-label="Meu perfil">
                        <div class="social-profile-banner" aria-hidden="true"><img v-if="ownProfile.cover" :src="ownProfile.cover" alt=""></div>
                        <div class="social-profile-overview">
                            <a class="social-profile-identity" :href="`${base}/profile/${viewer}`">
                                <SocialAvatar :person="ownProfile" />
                                <span><strong>{{ ownProfile.name }}</strong><small v-if="ownProfile.handle">{{ ownProfile.handle }}</small></span>
                            </a>
                            <div class="social-profile-stats">
                                <a :href="`${base}/profile/${viewer}`"><strong>{{ ownProfile.posts_count }}</strong><small>Publicações</small></a>
                                <a :href="`${base}/friends`"><strong>{{ ownProfile.friends_count }}</strong><small>Amigos</small></a>
                                <a :href="`${base}/requests`"><strong>{{ ownProfile.requests_count }}</strong><small>Solicitações</small></a>
                            </div>
                            <div class="social-actions social-profile-edit">
                                <a :href="profileSettings">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m15 5 4 4M4 20l5-1L20 8a2.8 2.8 0 0 0-4-4L5 15l-1 5Z"/></svg>
                                    Editar perfil
                                </a>
                            </div>
                        </div>
                    </section>
                    <section class="social-card social-people-card">
                        <div class="social-section-heading"><h2><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="9" cy="7" r="3"/><path d="M3 20v-2a5 5 0 0 1 5-5h2a5 5 0 0 1 5 5v2ZM19 7v6m-3-3h6"/></svg>Solicitações</h2><a :href="`${base}/requests`">Ver todas</a></div>
                        <p v-if="!requests.length">Nenhuma solicitação recebida.</p>
                        <SocialPeople v-else :people="requests" :base="base" @changed="relationshipChanged" />
                    </section>
                    <section class="social-card social-people-card">
                        <div class="social-section-heading"><h2><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="9" cy="7" r="3"/><path d="M3 20v-2a5 5 0 0 1 5-5h2a5 5 0 0 1 5 5v2M16 4a3 3 0 0 1 0 6m1 3a5 5 0 0 1 4 5v2"/></svg>Pessoas que talvez conheça</h2></div>
                        <p v-if="!suggestions.length">Sem sugestões no momento.</p>
                        <SocialPeople v-else :people="suggestions" :base="base" @changed="relationshipChanged" />
                        <button v-if="suggestions.length" class="social-more-people" type="button" @click="loadPeople('suggestions')">Ver mais sugestões</button>
                    </section>
                </aside>
            </div>
        </div>
    </section>
</template>
