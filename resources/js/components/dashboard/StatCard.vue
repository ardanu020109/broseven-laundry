<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: String, required: true },
    hint: { type: String, default: '' },
    tone: {
        type: String,
        default: 'primary',
        validator: (value) => ['primary', 'secondary', 'accent', 'success'].includes(value),
    },
});
</script>

<template>
    <div class="stat">
        <span :class="['stat__icon', `stat__icon--${tone}`]" aria-hidden="true"><slot name="icon" /></span>

        <div class="stat__body">
            <span class="stat__label">{{ label }}</span>
            <strong class="stat__value">{{ value }}</strong>
            <small v-if="hint" class="stat__hint">{{ hint }}</small>
        </div>
    </div>
</template>

<style scoped>
.stat {
    display: flex;
    align-items: center;
    gap: var(--space-4);
    padding: var(--space-5);
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    transition: transform var(--transition-base), box-shadow var(--transition-base);
}

.stat:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }

.stat__icon {
    display: grid;
    place-items: center;
    flex: none;
    width: 48px;
    height: 48px;
    border-radius: var(--radius-md);
}
.stat__icon--primary { background: var(--color-primary-soft); color: var(--color-primary-strong); }
.stat__icon--secondary { background: var(--color-secondary-soft); color: var(--color-primary-strong); }
.stat__icon--accent { background: var(--color-accent-soft); color: var(--color-primary-strong); }
.stat__icon--success { background: var(--color-success-soft); color: var(--color-success); }

.stat__body { display: flex; flex-direction: column; min-width: 0; }
.stat__label { font-size: var(--text-sm); color: var(--color-muted); }
.stat__value { font-size: var(--text-xl); line-height: 1.25; overflow-wrap: anywhere; }
.stat__hint { color: var(--color-muted); }
</style>