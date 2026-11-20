<script setup>
defineProps({ person: Object });
</script>

<template>
    <span class="social-avatar" aria-hidden="true">
        <img v-if="person.avatar" :src="person.avatar" alt="" loading="lazy">
        <span v-else>{{ person.name.trim().split(/\s+/).slice(0, 2).map(part => part[0]).join('').toUpperCase() }}</span>
    </span>
</template>
