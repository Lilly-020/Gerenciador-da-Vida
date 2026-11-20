<script setup>
import { ref } from 'vue';
import SocialAvatar from './SocialAvatar.vue';
import { socialRequest } from './social-request';

const props = defineProps({ people: Array, base: String, blocked: Boolean, showBlock: Boolean });
const emit = defineEmits(['changed']);
const busyId = ref(null);
const error = ref('');

async function change(person, operation) {
    if (busyId.value !== null) return;
    if (operation === 'block' && !window.confirm(`Bloquear ${person.name}?`)) return;
    busyId.value = person.id;
    error.value = '';
    try {
        const friendship = `${props.base}/friendships/${person.friendship_id}`;
        if (operation === 'add') await socialRequest(`${props.base}/friendships`, { method: 'POST', data: { user_id: person.id } });
        else if (operation === 'block' || operation === 'unblock') await socialRequest(`${props.base}/blocks/${person.id}`, { method: operation === 'block' ? 'POST' : 'DELETE' });
        else if (operation === 'remove') await socialRequest(friendship, { method: 'DELETE' });
        else await socialRequest(friendship, { method: 'PATCH', data: { status: operation } });
        emit('changed', operation);
    } catch (failure) {
        error.value = failure.message;
    } finally {
        busyId.value = null;
    }
}
</script>

<template>
    <div>
        <p v-if="error" class="social-error" role="alert">{{ error }}</p>
        <ul class="social-people">
            <li v-for="person in people" :key="person.id">
                <SocialAvatar :person="person" />
                <div class="social-person">
                    <span v-if="blocked">{{ person.name }}</span>
                    <a v-else :href="`${base}/profile/${person.id}`">{{ person.name }}</a>
                    <small v-if="!blocked && person.relationship === 'sent'">Solicitação enviada</small>
                    <small v-if="!blocked && person.relationship === 'received'">Solicitação recebida</small>
                    <small v-if="!blocked && person.relationship === 'friends'">Amigos</small>
                </div>
                <div class="social-actions">
                    <button v-if="blocked" :disabled="busyId !== null" @click="change(person, 'unblock')">Desbloquear</button>
                    <template v-else>
                        <button v-if="person.relationship === 'none'" :disabled="busyId !== null" @click="change(person, 'add')">Adicionar</button>
                        <template v-else-if="person.relationship === 'received'">
                            <button :disabled="busyId !== null" @click="change(person, 'accepted')">Aceitar</button>
                            <button :disabled="busyId !== null" @click="change(person, 'declined')">Recusar</button>
                        </template>
                        <button v-else :disabled="busyId !== null" @click="change(person, 'remove')">{{ person.relationship === 'friends' ? 'Desfazer amizade' : 'Cancelar' }}</button>
                        <button v-if="showBlock" :disabled="busyId !== null" @click="change(person, 'block')">Bloquear</button>
                    </template>
                </div>
            </li>
        </ul>
    </div>
</template>
