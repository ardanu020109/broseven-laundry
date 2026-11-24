<script setup>
import { ref } from 'vue';
import { ImagePlus, Trash2 } from 'lucide-vue-next';
import BaseButton from '@/components/common/BaseButton.vue';
import { useToast } from '@/composables/useToast';

const props = defineProps({
    label: { type: String, required: true },
    url: { type: String, default: null },
    hint: { type: String, default: '' },
    accept: { type: String, default: 'image/png,image/jpeg,image/webp' },
    maxKb: { type: Number, default: 2048 },
    busy: Boolean,
});

const emit = defineEmits(['select', 'remove']);

const input = ref(null);
const toast = useToast();

function onChange(event) {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (!file) return;

    if (file.size > props.maxKb * 1024) {
        toast.error(`Ukuran file maksimal ${props.maxKb} KB.`);
        return;
    }

    emit('select', file);
}
</script>

<template>
    <div class="uploader">
        <span class="uploader__label">{{ label }}</span>

        <div class="uploader__body">
            <div class="uploader__preview">
                <img v-if="url" :src="url" :alt="label" />
                <ImagePlus v-else :size="26" aria-hidden="true" />
            </div>

            <div class="uploader__actions">
                <input ref="input" type="file" class="sr-only" :accept="accept" @change="onChange" />
                <BaseButton size="sm" variant="secondary" :loading="busy" @click="input.click()">
                    {{ url ? 'Ganti' : 'Unggah' }}
                </BaseButton>
                <BaseButton v-if="url" size="sm" variant="ghost" :disabled="busy" @click="emit('remove')">
                    <Trash2 :size="14" />
                    Hapus
                </BaseButton>
            </div>
        </div>

        <small v-if="hint" class="uploader__hint">{{ hint }}</small>
    </div>
</template>

<style scoped>
.uploader { display: grid; gap: var(--space-2); }
.uploader__label { font-size: var(--text-sm); font-weight: 600; }
.uploader__body { display: flex; align-items: center; gap: var(--space-4); flex-wrap: wrap; }

.uploader__preview {
    display: grid;
    place-items: center;
    width: 96px;
    height: 96px;
    overflow: hidden;
    background: var(--color-primary-soft);
    border: 1px dashed var(--color-primary);
    border-radius: var(--radius-lg);
    color: var(--color-primary-strong);
}
.uploader__preview img { width: 100%; height: 100%; object-fit: contain; background: #fff; }

.uploader__actions { display: flex; gap: var(--space-2); flex-wrap: wrap; }
.uploader__hint { color: var(--color-muted); }
</style>