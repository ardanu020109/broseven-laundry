<script setup>
import { computed, ref, useId } from 'vue';
import { Eye, EyeOff } from 'lucide-vue-next';

defineOptions({ inheritAttrs: false });

const model = defineModel({ type: [String, Number], default: '' });

const props = defineProps({
    label: { type: String, default: '' },
    type: { type: String, default: 'text' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
});

const inputId = useId();
const visible = ref(false);

const isPassword = computed(() => props.type === 'password');
const currentType = computed(() => (isPassword.value && visible.value ? 'text' : props.type));
</script>

<template>
    <div :class="['field', { 'field--error': error }]">
        <label v-if="label" :for="inputId" class="field__label">{{ label }}</label>

        <div class="field__control">
            <span v-if="$slots.icon" class="field__icon"><slot name="icon" /></span>

            <input
                :id="inputId"
                v-model="model"
                v-bind="$attrs"
                :type="currentType"
                :aria-invalid="!!error"
                :class="['field__input', { 'field__input--icon': $slots.icon, 'field__input--toggle': isPassword }]"
            />

            <button
                v-if="isPassword"
                type="button"
                class="field__toggle"
                :aria-label="visible ? 'Sembunyikan password' : 'Tampilkan password'"
                @click="visible = !visible"
            >
                <component :is="visible ? EyeOff : Eye" :size="18" />
            </button>
        </div>

        <p v-if="error" class="field__error">{{ error }}</p>
        <p v-else-if="hint" class="field__hint">{{ hint }}</p>
    </div>
</template>

<style scoped>
.field { display: flex; flex-direction: column; gap: var(--space-2); }

.field__label { font-size: var(--text-sm); font-weight: 600; }

.field__control { position: relative; display: flex; align-items: center; }

.field__icon {
    position: absolute;
    left: 14px;
    display: flex;
    color: var(--color-muted);
    pointer-events: none;
}

.field__input {
    width: 100%;
    padding: 0.85rem 1rem;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    transition: border-color var(--transition-base), box-shadow var(--transition-base);
}

.field__input--icon { padding-left: 2.75rem; }
.field__input--toggle { padding-right: 3rem; }

.field__input::placeholder { color: color-mix(in srgb, var(--color-muted) 70%, white); }

.field__input:hover { border-color: color-mix(in srgb, var(--color-primary) 80%, var(--color-border)); }

.field__input:focus {
    outline: none;
    border-color: var(--color-primary-strong);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--color-primary) 35%, transparent);
}

.field--error .field__input { border-color: var(--color-danger); }
.field--error .field__input:focus {
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--color-danger) 20%, transparent);
}

.field__toggle {
    position: absolute;
    right: 8px;
    display: flex;
    padding: 8px;
    background: transparent;
    border: 0;
    border-radius: var(--radius-sm);
    color: var(--color-muted);
    transition: background var(--transition-fast), color var(--transition-fast);
}
.field__toggle:hover { background: var(--color-primary-soft); color: var(--color-text); }

.field__error { margin: 0; font-size: var(--text-sm); color: var(--color-danger); }
.field__hint { margin: 0; font-size: var(--text-sm); color: var(--color-muted); }
</style>