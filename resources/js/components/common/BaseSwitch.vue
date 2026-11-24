<script setup>
import { useId } from 'vue';

const model = defineModel({ type: Boolean, default: false });

defineProps({
    label: { type: String, default: '' },
    hint: { type: String, default: '' },
    disabled: Boolean,
});

const id = useId();
</script>

<template>
    <label :for="id" :class="['switch', { 'switch--disabled': disabled }]">
        <input :id="id" v-model="model" type="checkbox" role="switch" class="sr-only" :disabled="disabled" />
        <span class="switch__track" aria-hidden="true"><span class="switch__thumb"></span></span>
        <span class="switch__text">
            <span v-if="label" class="switch__label">{{ label }}</span>
            <small v-if="hint" class="switch__hint">{{ hint }}</small>
        </span>
    </label>
</template>

<style scoped>
.switch { display: inline-flex; align-items: flex-start; gap: var(--space-3); cursor: pointer; }
.switch--disabled { opacity: 0.55; cursor: not-allowed; }

.switch__track {
    position: relative;
    flex: none;
    width: 44px;
    height: 26px;
    margin-top: 1px;
    background: var(--color-border);
    border-radius: var(--radius-full);
    transition: background var(--transition-base);
}

.switch__thumb {
    position: absolute;
    top: 3px;
    left: 3px;
    width: 20px;
    height: 20px;
    background: #fff;
    border-radius: 50%;
    box-shadow: var(--shadow-sm);
    transition: transform var(--transition-base);
}

input:checked + .switch__track { background: var(--color-success); }
input:checked + .switch__track .switch__thumb { transform: translateX(18px); }
input:focus-visible + .switch__track { outline: 3px solid color-mix(in srgb, var(--color-primary) 70%, transparent); outline-offset: 2px; }

.switch__text { display: flex; flex-direction: column; }
.switch__label { font-weight: 600; font-size: var(--text-sm); }
.switch__hint { color: var(--color-muted); }
</style>