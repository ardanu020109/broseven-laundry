<script setup>
import { ref } from 'vue';
import { Check, Copy } from 'lucide-vue-next';
import BaseButton from '@/components/common/BaseButton.vue';
import { copyText } from '@/composables/useClipboard';

const props = defineProps({
    /** [{ label, value }] */
    lines: { type: Array, required: true },
});

const copied = ref(false);
const failed = ref(false);
let timer = null;

async function copy() {
    const text = props.lines.map((line) => `${line.label}: ${line.value}`).join('\n');
    const ok = await copyText(text);

    copied.value = ok;
    failed.value = !ok;

    clearTimeout(timer);
    if (ok) timer = setTimeout(() => (copied.value = false), 2500);
}
</script>

<template>
    <div class="cred">
        <dl class="cred__list">
            <div v-for="line in lines" :key="line.label">
                <dt>{{ line.label }}</dt>
                <dd>{{ line.value }}</dd>
            </div>
        </dl>

        <p class="cred__note">
            Catat dan berikan data ini kepada karyawan. Password tidak dapat dilihat lagi setelah jendela ini ditutup.
        </p>

        <BaseButton variant="secondary" size="sm" @click="copy">
            <component :is="copied ? Check : Copy" :size="16" />
            {{ copied ? 'Tersalin' : 'Salin semua' }}
        </BaseButton>

        <p v-if="failed" class="cred__error" role="alert">Tidak dapat menyalin otomatis. Salin secara manual.</p>
    </div>
</template>

<style scoped>
.cred { display: grid; gap: var(--space-4); justify-items: start; }

.cred__list {
    display: grid;
    gap: var(--space-2);
    width: 100%;
    margin: 0;
    padding: var(--space-4);
    background: var(--color-accent-soft);
    border: 1px solid var(--color-accent);
    border-radius: var(--radius-lg);
}
.cred__list div { display: flex; justify-content: space-between; gap: var(--space-4); }
.cred__list dt { color: var(--color-muted); font-size: var(--text-sm); }
.cred__list dd { margin: 0; font-weight: 600; text-align: right; overflow-wrap: anywhere; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }

.cred__note { margin: 0; font-size: var(--text-sm); color: var(--color-muted); }
.cred__error { margin: 0; font-size: var(--text-sm); color: var(--color-danger); }
</style>