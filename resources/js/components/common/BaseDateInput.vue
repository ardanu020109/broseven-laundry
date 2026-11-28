<script setup>
import { computed, ref, useId, watch } from 'vue';
import { Calendar } from 'lucide-vue-next';
import { isoToDisplay, maskDigits, parseDisplay } from '@/composables/dateInput';

defineOptions({ inheritAttrs: false });

/**
 * Input tanggal berformat dd/mm/yyyy (atau dd/mm/yyyy hh:mm dengan with-time).
 * Nilai v-model selalu ISO: "YYYY-MM-DD" atau "YYYY-MM-DDTHH:mm"; kosong jika tidak valid.
 */
const model = defineModel({ type: String, default: '' });

const props = defineProps({
    label: { type: String, default: '' },
    withTime: Boolean,
    /** ISO (YYYY-MM-DD) */
    min: { type: String, default: '' },
    max: { type: String, default: '' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    disabled: Boolean,
});

const id = useId();
const picker = ref(null);
const text = ref(isoToDisplay(model.value, props.withTime));
const localError = ref('');

const complete = computed(() => (props.withTime ? 16 : 10));
const shownError = computed(() => props.error || localError.value);

// Perubahan dari luar (mis. tombol Reset) memperbarui tampilan; ketikan setengah jadi dipertahankan.
watch(model, (value) => {
    if (parseDisplay(text.value, props.withTime) !== (value || null)) {
        text.value = isoToDisplay(value, props.withTime);
        localError.value = '';
    }
});

function rangeError(iso) {
    const day = iso.slice(0, 10);

    if (props.min && day < props.min.slice(0, 10)) {
        return `Tanggal tidak boleh sebelum ${isoToDisplay(props.min.slice(0, 10))}.`;
    }

    if (props.max && day > props.max.slice(0, 10)) {
        return `Tanggal tidak boleh setelah ${isoToDisplay(props.max.slice(0, 10))}.`;
    }

    return '';
}

function onInput(event) {
    const masked = maskDigits(event.target.value, props.withTime);

    text.value = masked;
    event.target.value = masked; // memastikan huruf yang bukan angka langsung hilang
    localError.value = '';

    if (masked === '') {
        model.value = '';
        return;
    }

    if (masked.length < complete.value) return;

    const iso = parseDisplay(masked, props.withTime);

    if (!iso) {
        model.value = '';
        localError.value = props.withTime ? 'Tanggal atau jam tidak valid.' : 'Tanggal tidak valid.';
        return;
    }

    const range = rangeError(iso);

    if (range) {
        model.value = '';
        localError.value = range;
        return;
    }

    model.value = iso;
}

function onBlur() {
    if (text.value !== '' && text.value.length < complete.value) {
        model.value = '';
        localError.value = props.withTime
            ? 'Lengkapi tanggal dan jam (dd/mm/yyyy hh:mm).'
            : 'Lengkapi tanggal (dd/mm/yyyy).';
    }
}

function onPicked(event) {
    const value = event.target.value;

    if (!value) return;

    text.value = isoToDisplay(value, props.withTime);

    const range = rangeError(value);

    if (range) {
        model.value = '';
        localError.value = range;
        return;
    }

    localError.value = '';
    model.value = value;
}

function openPicker() {
    const el = picker.value;

    if (!el || props.disabled) return;

    try {
        el.showPicker();
    } catch {
        el.focus();
        el.click();
    }
}
</script>

<template>
    <div :class="['field', { 'field--error': shownError }]">
        <label v-if="label" :for="id" class="field__label">{{ label }}</label>

        <div class="field__control">
            <input
                :id="id"
                v-bind="$attrs"
                :value="text"
                type="text"
                inputmode="numeric"
                autocomplete="off"
                :placeholder="withTime ? 'dd/mm/yyyy hh:mm' : 'dd/mm/yyyy'"
                :maxlength="complete"
                :disabled="disabled"
                :aria-invalid="!!shownError"
                class="field__input"
                @input="onInput"
                @blur="onBlur"
            />

            <button type="button" class="field__btn" :disabled="disabled" aria-label="Buka kalender" @click="openPicker">
                <Calendar :size="18" aria-hidden="true" />
            </button>

            <!-- Pemilih tanggal bawaan browser; hanya dipakai untuk memilih, tampilan tetap dd/mm/yyyy. -->
            <input
                ref="picker"
                class="field__native"
                :type="withTime ? 'datetime-local' : 'date'"
                :value="model"
                :min="min || undefined"
                :max="max || undefined"
                tabindex="-1"
                aria-hidden="true"
                @change="onPicked"
            />
        </div>

        <p v-if="shownError" class="field__error">{{ shownError }}</p>
        <p v-else-if="hint" class="field__hint">{{ hint }}</p>
    </div>
</template>

<style scoped>
.field { display: flex; flex-direction: column; gap: var(--space-2); }
.field__label { font-size: var(--text-sm); font-weight: 600; }

.field__control { position: relative; display: flex; align-items: center; }

.field__input {
    width: 100%;
    padding: 0.85rem 3rem 0.85rem 1rem;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    font-variant-numeric: tabular-nums;
    transition: border-color var(--transition-base), box-shadow var(--transition-base);
}
.field__input::placeholder { color: color-mix(in srgb, var(--color-muted) 70%, white); }
.field__input:hover:not(:disabled) { border-color: color-mix(in srgb, var(--color-primary) 80%, var(--color-border)); }
.field__input:focus {
    outline: none;
    border-color: var(--color-primary-strong);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--color-primary) 35%, transparent);
}
.field__input:disabled { opacity: 0.6; cursor: not-allowed; }

.field--error .field__input { border-color: var(--color-danger); }
.field--error .field__input:focus { box-shadow: 0 0 0 4px color-mix(in srgb, var(--color-danger) 20%, transparent); }

.field__btn {
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
.field__btn:hover:not(:disabled) { background: var(--color-primary-soft); color: var(--color-text); }
.field__btn:disabled { opacity: 0.5; cursor: not-allowed; }

.field__native {
    position: absolute;
    right: 0;
    bottom: 0;
    width: 1px;
    height: 1px;
    opacity: 0;
    pointer-events: none;
}

.field__error { margin: 0; font-size: var(--text-sm); color: var(--color-danger); }
.field__hint { margin: 0; font-size: var(--text-sm); color: var(--color-muted); }
</style>