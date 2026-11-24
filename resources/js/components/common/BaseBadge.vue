<script setup>
defineProps({
    tone: {
        type: String,
        default: 'neutral',
        validator: (value) => ['neutral', 'info', 'success', 'warning', 'danger'].includes(value),
    },
    dot: { type: Boolean, default: true },
});
</script>

<template>
    <span :class="['badge', `badge--${tone}`]">
        <span v-if="dot" class="badge__dot" aria-hidden="true"></span>
        <slot />
    </span>
</template>

<style scoped>
.badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.3rem 0.8rem;
    border: 1px solid transparent;
    border-radius: var(--radius-full);
    font-size: var(--text-sm);
    font-weight: 600;
    line-height: 1.2;
    white-space: nowrap;
}

.badge__dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: currentColor;
}

.badge--neutral {
    background: color-mix(in srgb, var(--color-muted) 12%, white);
    border-color: color-mix(in srgb, var(--color-muted) 25%, white);
    color: var(--color-text);
}

.badge--info {
    background: var(--color-primary-soft);
    border-color: var(--color-primary);
    color: var(--color-primary-strong);
}

.badge--success {
    background: var(--color-success-soft);
    border-color: color-mix(in srgb, var(--color-success) 45%, white);
    color: color-mix(in srgb, var(--color-success) 80%, #0b3b2e);
}

.badge--warning {
    background: var(--color-warning-soft);
    border-color: color-mix(in srgb, var(--color-warning) 55%, white);
    color: color-mix(in srgb, var(--color-warning) 55%, #5a3b00);
}

.badge--danger {
    background: var(--color-danger-soft);
    border-color: color-mix(in srgb, var(--color-danger) 45%, white);
    color: var(--color-danger);
}
</style>