<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { RotateCcw, Save, Undo2 } from 'lucide-vue-next';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseCard from '@/components/common/BaseCard.vue';
import BaseConfirmDialog from '@/components/common/BaseConfirmDialog.vue';
import ThemePreview from '@/components/website/ThemePreview.vue';
import { contrastIssues, contrastRatio, normalizeHex } from '@/composables/useColor';
import { useToast } from '@/composables/useToast';
import { extractErrorMessage } from '@/services/http';
import { resetColors, saveColors } from '@/services/websiteService';
import { useSiteStore } from '@/stores/site';

const props = defineProps({
    items: { type: Array, required: true },
});

const emit = defineEmits(['updated']);

const toast = useToast();
const site = useSiteStore();

const draft = reactive({});
const saving = ref(false);
const resetOpen = ref(false);
const resetting = ref(false);
const serverErrors = ref([]);

const keys = computed(() => props.items.map((item) => item.key));
const saved = computed(() => Object.fromEntries(props.items.map((item) => [item.key, item.value])));
const defaults = computed(() => Object.fromEntries(props.items.map((item) => [item.key, item.default_value])));

const normalized = computed(() => Object.fromEntries(keys.value.map((key) => [key, normalizeHex(draft[key])])));
const invalid = computed(() => Object.values(normalized.value).some((value) => value === null));
const dirty = computed(() => keys.value.some((key) => normalized.value[key] !== saved.value[key]));
const allDefault = computed(() => props.items.every((item) => item.is_default));

const preview = computed(() =>
    Object.fromEntries(keys.value.map((key) => [key, normalized.value[key] ?? saved.value[key]])),
);

const issues = computed(() => (keys.value.length ? contrastIssues(preview.value) : {}));
const hasIssues = computed(() => Object.keys(issues.value).length > 0);

const textContrast = computed(() =>
    keys.value.length ? contrastRatio(preview.value.text, preview.value.background).toFixed(1) : '-',
);

function discard() {
    Object.assign(draft, saved.value);
    serverErrors.value = [];
}

watch(() => props.items, discard, { immediate: true });

function fromItems(items) {
    return Object.fromEntries(items.map((item) => [item.key, item.value]));
}

async function save() {
    saving.value = true;
    serverErrors.value = [];

    try {
        const result = await saveColors(normalized.value);

        site.applyColors(fromItems(result.data));
        emit('updated', result.data);
        toast.success(result.message);
    } catch (error) {
        const details = error.response?.data?.errors;

        if (error.response?.status === 422 && details) {
            serverErrors.value = [...new Set(Object.values(details).flat())];
        } else {
            toast.error(extractErrorMessage(error));
        }
    } finally {
        saving.value = false;
    }
}

async function confirmReset() {
    resetting.value = true;

    try {
        const result = await resetColors();

        site.applyColors(fromItems(result.data));
        emit('updated', result.data);
        resetOpen.value = false;
        toast.success(result.message);
    } catch (error) {
        toast.error(extractErrorMessage(error));
    } finally {
        resetting.value = false;
    }
}
</script>

<template>
    <div class="colors">
        <BaseCard>
            <h3 class="colors__title">Palette Website</h3>
            <p class="text-muted colors__intro">
                Perubahan warna berlaku untuk website publik dan dashboard. Pratinjau di samping langsung
                mengikuti pilihan Anda, dan baru berlaku setelah disimpan.
            </p>

            <div class="colors__rows">
                <div v-for="item in items" :key="item.key" class="color">
                    <div class="color__head">
                        <label :for="`hex-${item.key}`" class="color__label">{{ item.label }}</label>
                        <button
                            type="button"
                            class="color__default"
                            :disabled="normalized[item.key] === defaults[item.key]"
                            @click="draft[item.key] = defaults[item.key]"
                        >
                            Default {{ defaults[item.key] }}
                        </button>
                    </div>

                    <div class="color__controls">
                        <input
                            type="color"
                            class="color__picker"
                            :aria-label="`Pilih ${item.label}`"
                            :value="(normalized[item.key] ?? saved[item.key]).toLowerCase()"
                            @input="draft[item.key] = $event.target.value.toUpperCase()"
                        />
                        <input
                            :id="`hex-${item.key}`"
                            v-model="draft[item.key]"
                            :class="['color__hex', { 'color__hex--invalid': normalized[item.key] === null }]"
                            maxlength="7"
                            spellcheck="false"
                            autocomplete="off"
                            :aria-invalid="normalized[item.key] === null"
                        />
                    </div>

                    <p v-if="normalized[item.key] === null" class="color__warn">Gunakan kode HEX 6 digit, contoh #ADD8E6.</p>
                    <p v-else-if="issues[item.key]" class="color__warn">{{ issues[item.key] }}</p>
                </div>
            </div>

            <p class="colors__contrast">Kontras teks dan latar: <strong>{{ textContrast }}:1</strong> (minimal 4,5:1)</p>

            <div v-if="serverErrors.length" class="colors__errors" role="alert">
                <p v-for="message in serverErrors" :key="message">{{ message }}</p>
            </div>

            <div class="colors__actions">
                <BaseButton :loading="saving" :disabled="!dirty || invalid || hasIssues" @click="save">
                    <Save :size="18" />
                    Simpan Warna
                </BaseButton>
                <BaseButton variant="ghost" :disabled="!dirty || saving" @click="discard">
                    <Undo2 :size="18" />
                    Batalkan Perubahan
                </BaseButton>
                <BaseButton variant="secondary" :disabled="allDefault || saving" @click="resetOpen = true">
                    <RotateCcw :size="18" />
                    Reset ke Warna Default
                </BaseButton>
            </div>
        </BaseCard>

        <div class="colors__preview">
            <h3 class="colors__title">Pratinjau</h3>
            <ThemePreview v-if="keys.length" :colors="preview" :name="site.name" />
        </div>

        <BaseConfirmDialog
            v-model="resetOpen"
            title="Reset warna ke default?"
            message="Seluruh warna website akan dikembalikan ke Light Blue, Perano, Ice Cold, dan warna dasar bawaan. Perubahan ini langsung disimpan."
            confirm-label="Ya, reset"
            :loading="resetting"
            @confirm="confirmReset"
        />
    </div>
</template>

<style scoped>
.colors { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr); gap: var(--space-5); align-items: start; }
.colors__title { margin: 0 0 var(--space-3); font-size: var(--text-lg); }
.colors__intro { margin: 0 0 var(--space-4); }
.colors__rows { display: grid; gap: var(--space-4); }

.color__head { display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); flex-wrap: wrap; }
.color__label { font-size: var(--text-sm); font-weight: 600; }
.color__default {
    padding: 0;
    background: transparent;
    border: 0;
    color: var(--color-primary-strong);
    font-size: var(--text-xs);
    font-weight: 600;
    text-decoration: underline;
}
.color__default:disabled { opacity: 0.4; text-decoration: none; cursor: default; }

.color__controls { display: flex; align-items: center; gap: var(--space-3); margin-top: var(--space-2); }

.color__picker {
    width: 52px;
    height: 46px;
    padding: 3px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    cursor: pointer;
}

.color__hex {
    width: 9rem;
    padding: 0.75rem 1rem;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    text-transform: uppercase;
}
.color__hex:focus {
    outline: none;
    border-color: var(--color-primary-strong);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--color-primary) 35%, transparent);
}
.color__hex--invalid { border-color: var(--color-danger); }

.color__warn { margin: var(--space-2) 0 0; font-size: var(--text-sm); color: var(--color-danger); }

.colors__contrast { margin: var(--space-4) 0 0; font-size: var(--text-sm); color: var(--color-muted); }

.colors__errors {
    margin-top: var(--space-4);
    padding: var(--space-3) var(--space-4);
    background: var(--color-danger-soft);
    border: 1px solid color-mix(in srgb, var(--color-danger) 40%, white);
    border-radius: var(--radius-md);
    color: var(--color-danger);
    font-size: var(--text-sm);
}
.colors__errors p { margin: 0; }
.colors__errors p + p { margin-top: var(--space-1); }

.colors__actions { display: flex; flex-wrap: wrap; gap: var(--space-3); margin-top: var(--space-5); }

.colors__preview { position: sticky; top: var(--space-4); }

@media (max-width: 980px) {
    .colors { grid-template-columns: 1fr; }
    .colors__preview { position: static; }
}
</style>