<script setup>
defineProps({
    hoverable: Boolean,
    padded: { type: Boolean, default: true },
});
</script>

<template>
    <div :class="['card', { 'card--hover': hoverable, 'card--padded': padded }]">
        <slot />
    </div>
</template>

<style scoped>
.card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    transition: transform var(--transition-base), box-shadow var(--transition-base),
        border-color var(--transition-base);
}

.card--padded { padding: var(--space-5); }

.card--hover:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
    border-color: var(--color-primary);
}
</style>