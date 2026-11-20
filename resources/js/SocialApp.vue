<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import SocialAvatar from './SocialAvatar.vue';
import SocialPeople from './SocialPeople.vue';
import SocialPostCard from './SocialPostCard.vue';
import SocialRichEditor from './SocialRichEditor.vue';
import { socialRequest } from './social-request';
import { useSocialFeed } from './useSocialFeed';

const props = defineProps({ base: String, viewer: Number, person: Number, section: String });
const searchText = ref('');
const peopleMode = ref(['friends', 'requests'].includes(props.section) ? props.section : '');
const people = ref([]);
const peopleNext = ref(null);
const suggestions = ref([]);
const friends = ref([]);
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
    requestUrl: parameters => endpoint('/feed', { person: props.person, post: focusedPost, ...parameters }),
    enabled: showFeed,
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
    const [suggested, pending, connected] = await Promise.all([
        socialRequest(endpoint('/people', { type: 'suggestions' })),
        socialRequest(endpoint('/people', { type: 'requests' })),
        socialRequest(endpoint('/people', { type: 'friends' })),
    ]);
    suggestions.value = suggested.items.slice(0, 4);
    friends.value = connected.items.slice(0, 4);
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
    nextTick(() => composer.value?.focus());
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
        content.value = { content: '', content_html: '' };
        files.value = [];
        fileInput.value.value = '';
        status.value = 'Publicação criada.';
        await Promise.all([refreshNewPosts(), loadProfile()]).catch(showFailure);
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
    if (new URLSearchParams(location.search).has('create')) nextTick(() => composer.value?.focus());
});
</script>

<template>
    <section class="social-page w-full" aria-labelledby="social-heading">
        <div class="space-y-5">
            <header class="social-header">
                <h1 id="social-heading" class="text-2xl font-semibold text-white sm:text-3xl">Social</h1>
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
                <div class="social-stack">
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

                        <form v-if="!person && !focusedPost" class="social-card social-form" @submit.prevent="publish">
                            <span class="text-sm font-semibold">No que você está pensando?</span>
                            <SocialRichEditor ref="composer" v-model="content" label="No que você está pensando?" placeholder="Escreva sua publicação…" />
                            <div class="social-composer-actions">
                                <span class="social-muted">Visível apenas para amigos</span>
                                <label class="social-upload"><input ref="fileInput" type="file" multiple accept="image/jpeg,image/png,image/webp" @change="files = [...$event.target.files]"><span>{{ files.length ? `${files.length} imagem(ns)` : 'Adicionar imagens' }}</span></label>
                                <button type="submit" class="social-primary" :disabled="writing || (!content.content.trim() && !files.length)">{{ writing ? 'Publicando…' : 'Publicar' }}</button>
                            </div>
                        </form>
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
                    <section v-if="ownProfile" class="social-card">
                        <h2>Meu perfil</h2>
                        <a class="social-profile-link" :href="`${base}/profile/${viewer}`"><SocialAvatar :person="ownProfile" /><strong>{{ ownProfile.name }}</strong></a>
                        <div class="social-actions"><a :href="`${base}/friends`">{{ ownProfile.friends_count }} amigos</a></div>
                    </section>
                    <section v-if="friends.length" class="social-card">
                        <div class="social-section-heading"><h2>Amigos</h2><a :href="`${base}/friends`">Ver todos</a></div>
                        <ul class="social-people">
                            <li v-for="friend in friends" :key="friend.id">
                                <SocialAvatar :person="friend" />
                                <div class="social-person"><a :href="`${base}/profile/${friend.id}`">{{ friend.name }}</a></div>
                            </li>
                        </ul>
                    </section>
                    <section class="social-card">
                        <div class="social-section-heading"><h2>Solicitações</h2><a :href="`${base}/requests`">Ver todas</a></div>
                        <p v-if="!requests.length">Nenhuma solicitação recebida.</p>
                        <SocialPeople v-else :people="requests" :base="base" @changed="relationshipChanged" />
                    </section>
                    <section class="social-card">
                        <div class="social-section-heading"><h2>Pessoas que talvez conheça</h2></div>
                        <p v-if="!suggestions.length">Sem sugestões no momento.</p>
                        <SocialPeople v-else :people="suggestions" :base="base" @changed="relationshipChanged" />
                    </section>
                </aside>
            </div>
        </div>
    </section>
</template>
