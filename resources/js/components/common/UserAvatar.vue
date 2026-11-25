<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    name: { type: String, default: '' },
    url: { type: String, default: null },
    size: { type: Number, default: 40 },
});

const failed = ref(false);

watch(() => props.url, () => (failed.value = false));

const initials = computed(() => {
    const words = props.name.trim().split(/\s+/).filter(Boolean);
    return (words.length > 1 ? words[0][0] + words[1][0] : (words[0] ?? '?').slice(0, 2)).toUpperCase();
});

const style = computed(() => ({
    width: `${props.size}px`,
    height: `${props.size}px`,
    fontSize: `${Math.max(Math.round(props.size * 0.38), 11)}px`,
}));
</script>

<template>
    <span class="avatar" :style="style">
        <img v-if="url && !failed" :src="url" :alt="name" @error="failed = true" />
        <span v-else aria-hidden="true">{{ initials }}</span>
    </span>
</template>

<style scoped>
.avatar {
    display: inline-grid;
    place-items: center;
    flex: none;
    overflow: hidden;
    background: var(--gradient-brand);
    border: 2px solid var(--color-surface);
    border-radius: 50%;
    box-shadow: var(--shadow-sm);
    color: var(--color-text);
    font-weight: 700;
    line-height: 1;
}
.avatar img { width: 100%; height: 100%; object-fit: cover; }
</style>