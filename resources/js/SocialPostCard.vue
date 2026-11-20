<script setup>
import { computed, ref, watch } from 'vue';
import SocialAvatar from './SocialAvatar.vue';
import SocialRichEditor from './SocialRichEditor.vue';
import { socialRequest } from './social-request';

const props = defineProps({ post: Object, base: String, commentsOpen: Boolean });
const emit = defineEmits(['deleted', 'updated', 'toggle-comments']);
const postUrl = computed(() => `${props.base}/posts/${props.post.id}`);
const liked = ref(props.post.liked);
const likesCount = ref(props.post.likes_count);
const commentsCount = ref(props.post.comments_count);
const body = ref({ content: props.post.content, content_html: props.post.content_html });
const edit = ref(null);
const error = ref('');
const busy = ref(false);
const comments = ref([]);
const nextComment = ref(null);
const moreComments = ref(false);
const expandedComments = ref(false);
const loadingComments = ref(false);
const commentBody = ref('');
const reply = ref(null);
const editComment = ref(null);
const imageDialog = ref(null);
const imageIndex = ref(null);
const shareStatus = ref('');
const visibleImages = computed(() => props.post.images.slice(0, 5));
const firstImageRow = computed(() => [2, 5].includes(visibleImages.value.length) ? 2 : 1);
const reactionLabels = { like: 'Curtir', love: 'Adorei', support: 'Apoiar' };
let commentRequest = 0;

function openImages(index) {
    imageIndex.value = index;
    imageDialog.value.showModal();
}

function moveImage(step) {
    if (imageIndex.value === null) return;
    imageIndex.value = Math.max(0, Math.min(props.post.images.length - 1, imageIndex.value + step));
}

async function copyPostLink() {
    const url = new URL(props.base, location.origin);
    url.searchParams.set('post', props.post.id);
    try {
        await navigator.clipboard.writeText(url.href);
        shareStatus.value = 'Link copiado.';
    } catch {
        shareStatus.value = 'Não foi possível copiar o link.';
    }
}

async function run(operation) {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    try { await operation(); } catch (failure) { error.value = failure.message; }
    finally { busy.value = false; }
}

async function toggleLike() {
    await run(async () => {
        const result = await socialRequest(`${postUrl.value}/like`, { method: 'POST', data: { liked: !liked.value } });
        liked.value = result.liked;
        likesCount.value = result.likes_count;
        emit('updated', props.post.id, { liked: liked.value, likes_count: likesCount.value });
    });
}

async function loadComments(cursor = null) {
    const request = ++commentRequest;
    const url = new URL(`${postUrl.value}/comments`, location.origin);
    if (!expandedComments.value) url.searchParams.set('preview', '1');
    if (cursor) url.searchParams.set('cursor', cursor);
    loadingComments.value = true;
    try {
        const result = await socialRequest(url);
        if (request !== commentRequest) return;
        comments.value = cursor ? [...comments.value, ...result.items] : result.items;
        nextComment.value = result.next;
        moreComments.value = result.has_more;
    } catch (failure) {
        if (request === commentRequest) throw failure;
    } finally {
        if (request === commentRequest) loadingComments.value = false;
    }
}

watch(() => props.commentsOpen, open => {
    commentRequest++;
    comments.value = [];
    expandedComments.value = false;
    moreComments.value = false;
    nextComment.value = null;
    loadingComments.value = false;
    reply.value = null;
    editComment.value = null;
    if (open) loadComments().catch(failure => { error.value = failure.message; });
});

function showMoreComments() {
    run(async () => {
        const wasExpanded = expandedComments.value;
        expandedComments.value = true;
        try { await loadComments(wasExpanded ? nextComment.value : null); }
        catch (failure) {
            expandedComments.value = wasExpanded;
            throw failure;
        }
    });
}

function react(comment, type) {
    run(async () => {
        const result = await socialRequest(`${props.base}/comments/${comment.id}/reaction`, {
            method: 'PUT',
            data: { reaction: comment.my_reaction === type ? null : type },
        });
        Object.assign(comment, result);
    });
}

function publishComment() {
    run(async () => {
        await socialRequest(`${postUrl.value}/comments`, { method: 'POST', data: { content: commentBody.value, parent_id: reply.value?.id ?? null } });
        commentsCount.value++;
        emit('updated', props.post.id, { comments_count: commentsCount.value });
        commentBody.value = '';
        reply.value = null;
        if (props.commentsOpen) await loadComments();
    });
}

function saveComment(comment) {
    run(async () => {
        await socialRequest(`${props.base}/comments/${comment.id}`, { method: 'PATCH', data: { content: editComment.value.content } });
        comment.content = editComment.value.content;
        editComment.value = null;
    });
}

function removeComment(comment) {
    if (!window.confirm('Excluir este comentário?')) return;
    run(async () => {
        await socialRequest(`${props.base}/comments/${comment.id}`, { method: 'DELETE' });
        comments.value = comments.value.filter(item => item.id !== comment.id);
        commentsCount.value--;
        emit('updated', props.post.id, { comments_count: commentsCount.value });
        if (reply.value?.id === comment.id) reply.value = null;
        if (props.commentsOpen && !expandedComments.value) await loadComments();
    });
}

function updatePost() {
    run(async () => {
        const content = await socialRequest(postUrl.value, { method: 'PATCH', data: edit.value });
        body.value = content;
        emit('updated', props.post.id, content);
        edit.value = null;
    });
}

function removePost() {
    if (!window.confirm('Excluir esta publicação?')) return;
    run(async () => {
        await socialRequest(postUrl.value, { method: 'DELETE' });
        emit('deleted', props.post.id);
    });
}
</script>

<template>
    <article class="social-card social-post">
        <header class="social-post-header">
            <SocialAvatar :person="post.author" />
            <div class="social-person">
                <a :href="`${base}/profile/${post.author.id}`">{{ post.author.name }}</a>
                <small>{{ post.when }} · {{ post.visibility === 'private' ? 'Somente eu' : 'Apenas amigos' }}</small>
            </div>
            <details v-if="post.editable" class="social-post-menu">
                <summary aria-label="Opções da publicação">⋯</summary>
                <div class="social-actions">
                    <button :disabled="busy" @click="edit = { content: body.content || '', content_html: body.content_html || '' }">Editar</button>
                    <button :disabled="busy" @click="removePost">Excluir</button>
                </div>
            </details>
        </header>

        <form v-if="edit" class="social-form" @submit.prevent="updatePost">
            <SocialRichEditor v-model="edit" label="Editar texto da publicação" placeholder="Escreva sua publicação…" />
            <div class="social-actions"><button type="submit" class="social-primary" :disabled="busy">Salvar</button><button type="button" @click="edit = null">Cancelar</button></div>
        </form>
        <div v-else-if="body.content_html" class="social-rich-content" v-html="body.content_html"></div>
        <p v-else-if="body.content" class="social-post-text">{{ body.content }}</p>

        <div v-if="post.images.length">
            <div class="social-image-gallery" :class="{ 'social-image-gallery-three': visibleImages.length === 3 }">
                <button v-for="(image, index) in visibleImages" :key="image" type="button" class="social-image-thumb"
                    :class="{ 'social-image-wide': firstImageRow === 1 && index === 0 }"
                    :style="visibleImages.length === 3 ? null : { gridColumn: `span ${6 / (index < firstImageRow ? firstImageRow : visibleImages.length - firstImageRow)}` }"
                    :aria-label="`Abrir imagem ${index + 1} de ${post.images.length} da publicação de ${post.author.name}`"
                    @click="openImages(index)">
                    <img :src="image" alt="" loading="lazy">
                    <span v-if="index === visibleImages.length - 1 && post.images.length > visibleImages.length" aria-hidden="true">+{{ post.images.length - visibleImages.length }}</span>
                </button>
            </div>
            <dialog ref="imageDialog" class="social-image-dialog rounded-2xl border border-white/10 bg-slate-900 p-0 text-slate-100 shadow-2xl backdrop:bg-black/70" aria-label="Imagens da publicação" @close="imageIndex = null" @click.self="imageDialog.close()" @keydown.left.prevent="moveImage(-1)" @keydown.right.prevent="moveImage(1)">
                <div class="space-y-3 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold">Imagens da publicação</p>
                        <div class="social-actions"><button type="button" autofocus @click="imageDialog.close()">Fechar</button></div>
                    </div>
                    <img v-if="imageIndex !== null" :src="post.images[imageIndex]" :alt="`Imagem ${imageIndex + 1} da publicação de ${post.author.name}`" class="social-image-full">
                    <div v-if="post.images.length > 1 && imageIndex !== null" class="social-actions justify-between">
                        <button type="button" :disabled="imageIndex === 0" @click="moveImage(-1)">Anterior</button>
                        <span class="social-muted" aria-live="polite">{{ imageIndex + 1 }} de {{ post.images.length }}</span>
                        <button type="button" :disabled="imageIndex === post.images.length - 1" @click="moveImage(1)">Próxima</button>
                    </div>
                </div>
            </dialog>
        </div>
        <div class="social-actions social-post-actions">
            <button type="button" class="social-like" :aria-pressed="liked" :aria-label="`${liked ? 'Descurtir' : 'Curtir'} publicação. ${likesCount} ${likesCount === 1 ? 'curtida' : 'curtidas'}`" :disabled="busy" @click="toggleLike">
                <svg viewBox="0 0 24 24" :fill="liked ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M20.8 4.6a5.6 5.6 0 0 0-7.9 0l-.9.9-.9-.9a5.6 5.6 0 0 0-7.9 7.9l.9.9L12 21l7.9-7.6.9-.9a5.6 5.6 0 0 0 0-7.9Z"/></svg>
            </button>
            <button type="button" :aria-expanded="commentsOpen" :aria-label="`${commentsCount} ${commentsCount === 1 ? 'comentário' : 'comentários'}`" @click="emit('toggle-comments')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M21 4H3v13h5l4 4 4-4h5Z"/></svg>
                {{ commentsCount }}
            </button>
            <button type="button" aria-label="Copiar link da publicação" title="Copiar link" @click="copyPostLink">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" aria-hidden="true"><path d="m14 4 7 7-7 7v-4c-5 0-8 2-11 6 1-8 5-12 11-12Z"/></svg>
            </button>
            <span v-if="shareStatus" class="social-muted" role="status">{{ shareStatus }}</span>
        </div>
        <p v-if="error" class="social-error" role="alert">{{ error }}</p>

        <section v-if="commentsOpen" class="social-comments" aria-label="Comentários">
            <p v-if="loadingComments && !comments.length" class="social-muted" role="status">Carregando comentários…</p>
            <div v-for="comment in comments" :key="comment.id" class="social-comment" :class="{ 'social-reply': comment.reply_to }">
                <a :href="`${base}/profile/${comment.user.id}`">{{ comment.user.name }}</a>
                <small>{{ comment.when }}<span v-if="comment.reply_to"> · Em resposta a {{ comment.reply_to }}</span></small>
                <form v-if="editComment?.id === comment.id" class="social-form" @submit.prevent="saveComment(comment)">
                    <textarea v-model="editComment.content" aria-label="Editar comentário" required maxlength="2000" rows="2"></textarea>
                    <div class="social-actions"><button type="submit" :disabled="busy">Salvar</button><button type="button" @click="editComment = null">Cancelar</button></div>
                </form>
                <p v-else class="social-post-text">{{ comment.content }}</p>
                <div class="social-actions" role="group" aria-label="Reações ao comentário">
                    <button v-for="(label, type) in reactionLabels" :key="type" type="button" :aria-pressed="comment.my_reaction === type" :disabled="busy" @click="react(comment, type)">
                        {{ label }}<template v-if="comment.reactions[type]"> · {{ comment.reactions[type] }}</template>
                    </button>
                </div>
                <div class="social-actions">
                    <button @click="reply = comment">Responder</button>
                    <button v-if="comment.editable" @click="editComment = { id: comment.id, content: comment.content }">Editar</button>
                    <button v-if="comment.editable" :disabled="busy" @click="removeComment(comment)">Excluir</button>
                </div>
            </div>
            <div v-if="moreComments" class="social-actions"><button :disabled="busy || loadingComments" @click="showMoreComments">Ver mais comentários</button></div>
            <form class="social-form" @submit.prevent="publishComment">
                <div v-if="reply" class="social-actions"><span>Respondendo a {{ reply.user.name }}</span><button type="button" @click="reply = null">Cancelar resposta</button></div>
                <textarea v-model="commentBody" aria-label="Seu comentário" placeholder="Escreva um comentário…" required maxlength="2000" rows="2"></textarea>
                <div class="social-actions"><button type="submit" class="social-primary" :disabled="busy">Comentar</button></div>
            </form>
        </section>
    </article>
</template>
