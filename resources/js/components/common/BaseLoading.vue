<script setup>
defineProps({
    label: { type: String, default: 'Memuat...' },
});
</script>

<template>
    <div class="loading" role="status" aria-live="polite">
        <span class="loading__spinner" aria-hidden="true"></span>
        <span class="loading__label">{{ label }}</span>
    </div>
</template>

<style scoped>
.loading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-3);
    padding: var(--space-6);
    color: var(--color-muted);
}

.loading__spinner {
    width: 22px;
    height: 22px;
    border: 3px solid var(--color-primary-soft);
    border-top-color: var(--color-primary-strong);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
</style>