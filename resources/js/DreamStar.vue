<script setup>
import { computed } from 'vue';
const props = defineProps({ dream: Object, selected: Boolean, animation: String, zoom: Number });
defineEmits(['select', 'hover', 'leave']);
const radius = computed(() => props.dream.status === 'completed' ? 8 : props.dream.status === 'in_progress' ? 6.5 : 5);
const scale = computed(() => Math.min(5, 1 / props.zoom));
</script>

<template>
    <g :transform="`translate(${dream.x} ${dream.y})`" :style="{ animationDelay: `${(dream.id % 12) * 25}ms` }" class="dream-star-position">
        <g :transform="`scale(${scale})`" :data-dream-id="dream.id" role="button" tabindex="0" :aria-label="`${dream.title}, ${dream.status_label}, ${dream.progress}% das tarefas`" :aria-pressed="selected" class="dream-star" :class="[dream.archived ? 'is-archived' : `is-${dream.status}`, animation && `dream-${animation}`, { 'is-selected': selected, 'is-compact': zoom < 0.55 }]" :style="{ '--dream-intensity': 0.65 + dream.progress / 300 }" @click.stop="$emit('select', dream.id)" @keydown.enter.prevent="$emit('select', dream.id)" @keydown.space.prevent="$emit('select', dream.id)" @mouseenter="$emit('hover', dream.id)" @mouseleave="$emit('leave')" @focus="$emit('hover', dream.id)" @blur="$emit('leave')">
            <circle r="32" fill="transparent" />
            <circle class="dream-star-halo" :r="radius * 3.3" />
            <circle class="dream-star-selection" r="22" />
            <path v-if="dream.status === 'completed'" d="M0-15 3-3 15 0 3 3 0 15-3 3-15 0-3-3Z" class="dream-star-rays" />
            <circle class="dream-star-core" :r="radius" />
            <circle :r="radius * 0.38" fill="#f8faff" />
            <text class="dream-star-name" y="43" text-anchor="middle">{{ dream.title.length > 26 ? dream.title.slice(0, 25) + '…' : dream.title }}</text>
            <text class="dream-star-progress" y="60" text-anchor="middle">{{ dream.archived ? 'Arquivado' : dream.status === 'completed' ? 'Conquista alcançada' : `${dream.progress}% da jornada` }}</text>
        </g>
    </g>
</template>
