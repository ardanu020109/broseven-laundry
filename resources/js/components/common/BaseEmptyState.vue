<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="empty">
        <div v-if="$slots.icon" class="empty__icon"><slot name="icon" /></div>
        <h3 class="empty__title">{{ title }}</h3>
        <p v-if="description" class="empty__description">{{ description }}</p>
        <div v-if="$slots.default" class="empty__actions"><slot /></div>
    </div>
</template>

<style scoped>
.empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-8) var(--space-5);
    text-align: center;
}

.empty__icon {
    display: grid;
    place-items: center;
    width: 72px;
    height: 72px;
    margin-bottom: var(--space-2);
    background: var(--color-primary-soft);
    border-radius: var(--radius-xl);
    color: var(--color-primary-strong);
}

.empty__title { margin: 0; font-size: var(--text-xl); }
.empty__description { max-width: 42ch; margin: 0; color: var(--color-muted); }
.empty__actions { margin-top: var(--space-4); }
</style>