<script setup>
import { computed, useId } from 'vue';

defineOptions({ inheritAttrs: false });

const model = defineModel({ type: String, default: '' });

const props = defineProps({
    label: { type: String, default: '' },
    rows: { type: Number, default: 3 },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    maxlength: { type: Number, default: undefined },
});

const id = useId();
const count = computed(() => (model.value ?? '').length);
</script>

<template>
    <div :class="['field', { 'field--error': error }]">
        <label v-if="label" :for="id" class="field__label">{{ label }}</label>

        <textarea
            :id="id"
            v-model="model"
            v-bind="$attrs"
            :rows="rows"
            :maxlength="maxlength"
            :aria-invalid="!!error"
            class="field__textarea"
        ></textarea>

        <div class="field__foot">
            <p v-if="error" class="field__error">{{ error }}</p>
            <p v-else-if="hint" class="field__hint">{{ hint }}</p>
            <span v-else></span>
            <small v-if="maxlength" class="field__count">{{ count }}/{{ props.maxlength }}</small>
        </div>
    </div>
</template>

<style scoped>
.field { display: flex; flex-direction: column; gap: var(--space-2); }
.field__label { font-size: var(--text-sm); font-weight: 600; }

.field__textarea {
    width: 100%;
    padding: 0.85rem 1rem;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    resize: vertical;
    line-height: 1.5;
    transition: border-color var(--transition-base), box-shadow var(--transition-base);
}
.field__textarea:hover { border-color: color-mix(in srgb, var(--color-primary) 80%, var(--color-border)); }
.field__textarea:focus {
    outline: none;
    border-color: var(--color-primary-strong);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--color-primary) 35%, transparent);
}
.field--error .field__textarea { border-color: var(--color-danger); }

.field__foot { display: flex; justify-content: space-between; gap: var(--space-3); }
.field__error { margin: 0; font-size: var(--text-sm); color: var(--color-danger); }
.field__hint { margin: 0; font-size: var(--text-sm); color: var(--color-muted); }
.field__count { color: var(--color-muted); font-variant-numeric: tabular-nums; }
</style>