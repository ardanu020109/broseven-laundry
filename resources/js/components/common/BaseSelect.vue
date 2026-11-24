<script setup>
import { useId } from 'vue';
import { ChevronDown } from 'lucide-vue-next';

defineOptions({ inheritAttrs: false });

const model = defineModel({ type: [String, Number], default: '' });

defineProps({
    label: { type: String, default: '' },
    /** [{ value, label }] */
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: '' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
});

const selectId = useId();
</script>

<template>
    <div :class="['field', { 'field--error': error }]">
        <label v-if="label" :for="selectId" class="field__label">{{ label }}</label>

        <div class="field__control">
            <select
                :id="selectId"
                v-model="model"
                v-bind="$attrs"
                :aria-invalid="!!error"
                class="field__select"
            >
                <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
                <option v-for="option in options" :key="option.value" :value="option.value">
                    {{ option.label }}
                </option>
            </select>
            <ChevronDown :size="18" class="field__chevron" aria-hidden="true" />
        </div>

        <p v-if="error" class="field__error">{{ error }}</p>
        <p v-else-if="hint" class="field__hint">{{ hint }}</p>
    </div>
</template>

<style scoped>
.field { display: flex; flex-direction: column; gap: var(--space-2); }

.field__label { font-size: var(--text-sm); font-weight: 600; }

.field__control { position: relative; display: flex; align-items: center; }

.field__select {
    width: 100%;
    padding: 0.85rem 2.75rem 0.85rem 1rem;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    appearance: none;
    cursor: pointer;
    transition: border-color var(--transition-base), box-shadow var(--transition-base);
}

.field__select:hover { border-color: color-mix(in srgb, var(--color-primary) 80%, var(--color-border)); }

.field__select:focus {
    outline: none;
    border-color: var(--color-primary-strong);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--color-primary) 35%, transparent);
}

.field__chevron {
    position: absolute;
    right: 14px;
    color: var(--color-muted);
    pointer-events: none;
}

.field--error .field__select { border-color: var(--color-danger); }

.field__error { margin: 0; font-size: var(--text-sm); color: var(--color-danger); }
.field__hint { margin: 0; font-size: var(--text-sm); color: var(--color-muted); }
</style>