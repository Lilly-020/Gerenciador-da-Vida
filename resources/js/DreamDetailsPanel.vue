<script setup>
import { computed, nextTick, reactive, ref, watch } from 'vue';
import DreamFields from './DreamFields.vue';
const props = defineProps({ dream: Object, categories: Object, busy: Boolean });
const emit = defineEmits(['close', 'mutate', 'remove']);
const editing = ref(false);
const taskTitle = ref('');
const editTask = ref(null);
const editTitle = ref('');
const form = reactive({});
const panel = ref(null);
const completed = computed(() => props.dream.tasks.filter(task => task.completed).length);
const date = value => value ? new Intl.DateTimeFormat('pt-BR').format(new Date(`${value}T12:00:00`)) : 'Sem prazo';
const money = value => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);
watch(() => props.dream.id, async () => { editing.value = false; taskTitle.value = ''; editTask.value = null; await nextTick(); panel.value?.querySelector('[data-dream-close]')?.focus(); }, { immediate: true });
function startEdit() { Object.assign(form, Object.fromEntries(['title', 'notes', 'category', 'priority', 'target_date', 'financial_goal', 'saved_amount', 'image_url'].map(key => [key, props.dream[key] ?? '']))); editing.value = true; }
function toggleTask(task, event) { const checked = event.target.checked; event.target.checked = task.completed; emit('mutate', { url: task.url, method: 'PATCH', body: { completed: checked } }); }
function addTask() { if (!taskTitle.value.trim()) return; emit('mutate', { url: props.dream.tasks_url, method: 'POST', body: { title: taskTitle.value.trim() }, done: () => { taskTitle.value = ''; } }); }
function renameTask(task) { if (!editTitle.value.trim()) return; emit('mutate', { url: task.url, method: 'PATCH', body: { title: editTitle.value.trim() }, done: () => { editTask.value = null; } }); }
function trap(event) {
    if (event.key !== 'Tab') return;
    const focusable = [...panel.value.querySelectorAll('button, input, textarea, select, a, summary')].filter(el => !el.disabled && el.getClientRects().length);
    const first = focusable[0], last = focusable.at(-1);
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
}
</script>

<template>
    <div class="dream-panel-backdrop" @click.self="emit('close')">
        <aside ref="panel" class="dream-details-panel" role="dialog" aria-modal="true" aria-labelledby="dream-detail-title" @keydown.esc.stop="emit('close')" @keydown="trap">
            <header class="dream-panel-header"><div><p class="dream-eyebrow">{{ categories[dream.category] }} · {{ dream.archived ? 'Arquivado' : dream.status_label }}</p><h2 id="dream-detail-title">{{ dream.title }}</h2></div><button type="button" data-dream-close class="dream-icon-button" aria-label="Fechar detalhes" @click="emit('close')"><svg viewBox="0 0 24 24" fill="none"><path d="m6 6 12 12M6 18 18 6" stroke="currentColor" stroke-width="1.6" /></svg></button></header>
            <div class="dream-panel-scroll">
                <p v-if="dream.status === 'completed'" class="dream-achievement-note">Uma nova conquista ilumina seu universo.</p>
                <form v-if="editing" class="dream-edit-form" @submit.prevent="emit('mutate', { url: dream.url, method: 'PUT', body: { ...form }, done: () => { editing = false; } })"><DreamFields :form="form" :categories="categories" prefix="edit-dream" /><div class="dream-actions"><button class="dream-primary" :disabled="busy">Salvar alterações</button><button type="button" class="dream-button" @click="editing = false">Cancelar</button></div></form>
                <template v-else>
                    <img v-if="dream.image_url" :src="dream.image_url" :alt="`Imagem de ${dream.title}`" class="dream-detail-image" referrerpolicy="no-referrer" loading="lazy" />
                    <p class="dream-description">{{ dream.notes || 'Seu sonho ainda não tem uma descrição.' }}</p>
                    <dl class="dream-metadata"><div><dt>Prioridade</dt><dd>{{ { low: 'Baixa', medium: 'Média', high: 'Alta' }[dream.priority] }}</dd></div><div><dt>Prazo desejado</dt><dd>{{ date(dream.target_date) }}</dd></div><div><dt>Criado em</dt><dd>{{ date(dream.created_at) }}</dd></div><div><dt>Criado por</dt><dd>{{ dream.owner_name }}</dd></div></dl>
                    <button type="button" class="dream-button" @click="startEdit">Editar sonho</button>
                </template>
                <section class="dream-journey" aria-labelledby="dream-journey-title"><div class="dream-section-heading"><h3 id="dream-journey-title">Sua jornada</h3><span>{{ completed }}/{{ dream.tasks.length }} tarefas · {{ dream.progress }}%</span></div><div class="dream-progress" role="progressbar" aria-label="Progresso das tarefas" :aria-valuenow="dream.progress" aria-valuemin="0" aria-valuemax="100"><span :style="{ width: `${dream.progress}%` }" /></div>
                    <p v-if="!dream.tasks.length" class="dream-muted">Divida seu sonho em pequenos passos.</p>
                    <ul class="dream-tasks"><li v-for="task in dream.tasks" :key="task.id"><label><input type="checkbox" :checked="task.completed" :disabled="busy" :aria-label="`Concluir ${task.title}`" @change="toggleTask(task, $event)" /><span v-if="editTask !== task.id" :class="{ 'is-done': task.completed }">{{ task.title }}</span></label><form v-if="editTask === task.id" class="dream-task-rename" @submit.prevent="renameTask(task)"><input v-model="editTitle" required maxlength="255" aria-label="Nome da tarefa" @keydown.esc.stop="editTask = null" /><button :disabled="busy" class="dream-button">Salvar</button><button type="button" class="dream-icon-button" aria-label="Cancelar edição" @click="editTask = null">×</button></form><template v-else><button type="button" class="dream-icon-button" :aria-label="`Editar ${task.title}`" @click="editTask = task.id; editTitle = task.title"><svg viewBox="0 0 24 24" fill="none"><path d="m14 5 5 5M4 20l5-1L20 8a2 2 0 0 0-5-5L4 14Z" stroke="currentColor" stroke-width="1.5" /></svg></button><button type="button" class="dream-icon-button dream-danger" :disabled="busy" :aria-label="`Excluir ${task.title}`" @click="emit('remove', { url: task.url, task: true })"><svg viewBox="0 0 24 24" fill="none"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 10v7m4-7v7" stroke="currentColor" stroke-width="1.5" /></svg></button></template></li></ul>
                    <form class="dream-task-create" @submit.prevent="addTask"><input v-model="taskTitle" required maxlength="255" placeholder="Seu próximo passo…" aria-label="Nova tarefa do sonho" /><button class="dream-primary" :disabled="busy">Adicionar tarefa</button></form>
                </section>
                <section v-if="dream.financial_goal" class="dream-financial"><div class="dream-section-heading"><h3>Meta financeira</h3><span>{{ dream.financial_progress }}%</span></div><p>{{ money(dream.saved_amount) }} de {{ money(dream.financial_goal) }}</p><div class="dream-progress" role="progressbar" aria-label="Progresso financeiro" :aria-valuenow="dream.financial_progress" aria-valuemin="0" aria-valuemax="100"><span :style="{ width: `${dream.financial_progress}%` }" /></div><p class="dream-muted">A conclusão considera as tarefas e o valor da meta financeira.</p></section>
                <p v-if="dream.status !== 'completed'" class="dream-muted dream-completion-hint">{{ dream.tasks.length || dream.financial_goal ? 'Conclua os passos e alcance sua meta financeira, se houver, para realizar este sonho.' : 'Adicione os passos da sua jornada para acompanhar a realização deste sonho.' }}</p>
                <div class="dream-collaboration" v-html="dream.collaboration" />
            </div>
            <footer v-if="dream.can_manage" class="dream-panel-footer"><button type="button" class="dream-button" :disabled="busy" @click="emit('mutate', { url: dream.url, method: 'PUT', body: { title: dream.title, archived: !dream.archived } })">{{ dream.archived ? 'Restaurar sonho' : 'Arquivar sonho' }}</button><button type="button" class="dream-button dream-danger" :disabled="busy" @click="emit('remove', { url: dream.url })">Excluir sonho</button></footer>
        </aside>
    </div>
</template>
