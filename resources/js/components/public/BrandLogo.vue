<script setup>
import { ref, watch } from 'vue';
import { Droplets } from 'lucide-vue-next';

const props = defineProps({
    url: { type: String, default: null },
    name: { type: String, default: '' },
    size: { type: Number, default: 40 },
});

const failed = ref(false);

watch(() => props.url, () => (failed.value = false));
</script>

<template>
    <span :class="['logo', { 'logo--image': url && !failed }]" :style="{ width: `${size}px`, height: `${size}px` }">
        <img v-if="url && !failed" :src="url" :alt="name ? `Logo ${name}` : 'Logo'" @error="failed = true" />
        <Droplets v-else :size="Math.round(size * 0.55)" aria-hidden="true" />
    </span>
</template>

<style scoped>
.logo {
    display: inline-grid;
    place-items: center;
    flex: none;
    overflow: hidden;
    background: var(--gradient-brand);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
    color: var(--color-text);
}

.logo--image {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
}

.logo img { width: 100%; height: 100%; object-fit: contain; }
</style>