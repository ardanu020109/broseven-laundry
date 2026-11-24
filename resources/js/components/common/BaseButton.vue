<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'primary',
        validator: (v) => ['primary', 'secondary', 'ghost', 'danger'].includes(v),
    },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
    type: { type: String, default: 'button' },
    loading: Boolean,
    disabled: Boolean,
    block: Boolean,
});

const isDisabled = computed(() => props.disabled || props.loading);
</script>

<template>
    <button
        :type="type"
        :disabled="isDisabled"
        :class="['btn', `btn--${variant}`, `btn--${size}`, { 'btn--block': block, 'btn--loading': loading }]"
    >
        <span v-if="loading" class="btn__spinner" aria-hidden="true"></span>
        <span class="btn__content"><slot /></span>
    </button>
</template>

<style scoped>
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-2);
    border: 1px solid transparent;
    border-radius: var(--radius-full);
    font-weight: 600;
    line-height: 1;
    white-space: nowrap;
    transition: transform var(--transition-fast), box-shadow var(--transition-base),
        background var(--transition-base), border-color var(--transition-base);
}

.btn__content {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
}

.btn--sm { padding: 0.55rem 1rem; font-size: var(--text-sm); }
.btn--md { padding: 0.8rem 1.4rem; font-size: var(--text-base); }
.btn--lg { padding: 1rem 1.9rem; font-size: var(--text-lg); }
.btn--block { display: flex; width: 100%; }

.btn:hover:not(:disabled) { transform: translateY(-2px); }
.btn:active:not(:disabled) { transform: translateY(0) scale(0.98); }
.btn:disabled { opacity: 0.55; cursor: not-allowed; }

.btn--primary {
    background: var(--gradient-brand);
    color: var(--color-text);
    box-shadow: var(--shadow-md);
}
.btn--primary:hover:not(:disabled) { box-shadow: var(--shadow-glow); }

.btn--secondary {
    background: var(--color-surface);
    color: var(--color-text);
    border-color: var(--color-border);
    box-shadow: var(--shadow-sm);
}
.btn--secondary:hover:not(:disabled) {
    border-color: var(--color-primary);
    box-shadow: var(--shadow-md);
}

.btn--ghost {
    background: transparent;
    color: var(--color-text);
}
.btn--ghost:hover:not(:disabled) { background: var(--color-primary-soft); }

.btn--danger {
    background: var(--color-danger);
    color: #fff;
    box-shadow: var(--shadow-sm);
}

.btn__spinner {
    width: 1em;
    height: 1em;
    border: 2px solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
}
</style>