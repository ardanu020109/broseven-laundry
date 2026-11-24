<script setup>
defineProps({
    label: { type: String, required: true },
    tone: {
        type: String,
        default: 'neutral',
        validator: (value) => ['neutral', 'danger'].includes(value),
    },
    disabled: Boolean,
});
</script>

<template>
    <button type="button" :class="['icon-btn', `icon-btn--${tone}`]" :aria-label="label" :title="label" :disabled="disabled">
        <slot />
    </button>
</template>

<style scoped>
.icon-btn {
    display: inline-grid;
    place-items: center;
    width: 34px;
    height: 34px;
    background: transparent;
    border: 1px solid transparent;
    border-radius: var(--radius-sm);
    color: var(--color-muted);
    transition: background var(--transition-fast), color var(--transition-fast), border-color var(--transition-fast);
}

.icon-btn:hover:not(:disabled) {
    background: var(--color-primary-soft);
    border-color: var(--color-border);
    color: var(--color-text);
}

.icon-btn--danger:hover:not(:disabled) {
    background: var(--color-danger-soft);
    color: var(--color-danger);
}

.icon-btn:disabled { opacity: 0.35; cursor: not-allowed; }
</style>