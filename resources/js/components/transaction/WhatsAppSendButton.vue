<script setup>
import { ref } from 'vue';
import { Check, Copy, ExternalLink, Info, MessageCircle } from 'lucide-vue-next';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseLoading from '@/components/common/BaseLoading.vue';
import BaseModal from '@/components/common/BaseModal.vue';
import BaseSelect from '@/components/common/BaseSelect.vue';
import { fetchWhatsAppPreview, recordWhatsAppOpened } from '@/services/whatsappService';
import { extractErrorMessage } from '@/services/http';

/**
 * Tombol "Kirim WhatsApp" yang dapat dipakai di halaman mana pun.
 * Pemakaian: <WhatsAppSendButton :transaction-id="transaction.id" />
 *
 * Catatan: Click-to-Chat hanya membuka WhatsApp dengan pesan terisi.
 * Pesan baru terkirim setelah petugas menekan Kirim di WhatsApp.
 */
const props = defineProps({
    transactionId: { type: Number, required: true },
    label: { type: String, default: 'Kirim WhatsApp' },
    variant: { type: String, default: 'primary' },
    size: { type: String, default: 'md' },
    block: Boolean,
});

const emit = defineEmits(['opened']);

const open = ref(false);
const loading = ref(false);
const error = ref('');
const preview = ref(null);
const selected = ref('');
const opened = ref(false);
const copied = ref(false);
const copyFailed = ref(false);

let requestSeq = 0;
let copyTimer = null;

async function load(template = null) {
    const seq = ++requestSeq;
    loading.value = true;
    error.value = '';

    try {
        const data = await fetchWhatsAppPreview(props.transactionId, template);
        if (seq !== requestSeq) return;

        preview.value = data;
        selected.value = data.template;
    } catch (err) {
        if (seq !== requestSeq) return;

        preview.value = null;
        error.value = extractErrorMessage(err, 'Pesan WhatsApp tidak dapat disiapkan.');
    } finally {
        if (seq === requestSeq) loading.value = false;
    }
}

function resetFeedback() {
    opened.value = false;
    copied.value = false;
    copyFailed.value = false;
}

function openModal() {
    resetFeedback();
    preview.value = null;
    selected.value = '';
    open.value = true;
    load();
}

function onTemplateChange(value) {
    resetFeedback();
    load(value || null);
}

function onOpened() {
    opened.value = true;
    emit('opened', preview.value?.template ?? null);

    // Pencatatan tidak boleh menghalangi pembukaan WhatsApp.
    recordWhatsAppOpened(props.transactionId, preview.value?.template ?? null).catch(() => {});
}

async function copyMessage() {
    const text = preview.value?.message;
    if (!text) return;

    copyFailed.value = false;

    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
        } else {
            const area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            const ok = document.execCommand('copy');
            document.body.removeChild(area);
            if (!ok) throw new Error('copy failed');
        }

        copied.value = true;
        clearTimeout(copyTimer);
        copyTimer = setTimeout(() => (copied.value = false), 2500);
    } catch {
        copyFailed.value = true;
    }
}
</script>

<template>
    <BaseButton :variant="variant" :size="size" :block="block" @click="openModal">
        <MessageCircle :size="18" />
        {{ label }}
    </BaseButton>

    <BaseModal v-model="open" title="Kirim Pesan WhatsApp">
        <BaseLoading v-if="loading && !preview" label="Menyiapkan pesan..." />

        <div v-else-if="error" class="wa__error" role="alert">
            <span>{{ error }}</span>
            <BaseButton size="sm" variant="secondary" @click="load(selected || null)">Coba lagi</BaseButton>
        </div>

        <div v-else-if="preview" class="wa">
            <p class="wa__to">Penerima: <strong>+{{ preview.phone }}</strong></p>

            <BaseSelect
                label="Jenis pesan"
                :model-value="selected"
                :options="preview.templates"
                @update:model-value="onTemplateChange"
            />

            <div :class="['wa__bubble', { 'wa__bubble--loading': loading }]">{{ preview.message }}</div>

            <p class="wa__notice">
                <Info :size="16" />
                <span>{{ preview.notice }}</span>
            </p>

            <p v-if="opened" class="wa__opened" role="status">
                WhatsApp dibuka di tab baru. Pesan baru terkirim setelah Anda menekan tombol Kirim di WhatsApp.
            </p>
            <p v-if="copyFailed" class="wa__copy-error" role="alert">
                Pesan tidak dapat disalin otomatis. Salin manual dari kotak pesan di atas.
            </p>
        </div>

        <template #footer>
            <BaseButton variant="ghost" @click="open = false">Tutup</BaseButton>

            <BaseButton v-if="preview" variant="secondary" :disabled="loading" @click="copyMessage">
                <component :is="copied ? Check : Copy" :size="16" />
                {{ copied ? 'Tersalin' : 'Salin Pesan' }}
            </BaseButton>

            <a
                v-if="preview && !loading"
                class="wa__open"
                :href="preview.whatsapp_url"
                target="_blank"
                rel="noopener noreferrer"
                @click="onOpened"
            >
                <ExternalLink :size="16" />
                Buka WhatsApp
            </a>
        </template>
    </BaseModal>
</template>

<style scoped>
.wa { display: grid; gap: var(--space-4); }

.wa__to { margin: 0; color: var(--color-muted); }
.wa__to strong { color: var(--color-text); }

.wa__bubble {
    padding: var(--space-4);
    background: var(--color-accent-soft);
    border: 1px solid var(--color-accent);
    border-radius: var(--radius-lg);
    font-size: var(--text-sm);
    line-height: 1.6;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    max-height: 280px;
    overflow-y: auto;
    transition: opacity var(--transition-base);
}
.wa__bubble--loading { opacity: 0.5; }

.wa__notice {
    display: flex;
    align-items: flex-start;
    gap: var(--space-2);
    margin: 0;
    padding: var(--space-3) var(--space-4);
    background: var(--color-primary-soft);
    border-radius: var(--radius-md);
    font-size: var(--text-sm);
}
.wa__notice svg { flex: none; margin-top: 2px; color: var(--color-primary-strong); }

.wa__opened {
    margin: 0;
    padding: var(--space-3) var(--space-4);
    background: var(--color-success-soft);
    border-radius: var(--radius-md);
    font-size: var(--text-sm);
    font-weight: 500;
}

.wa__copy-error { margin: 0; font-size: var(--text-sm); color: var(--color-danger); }

.wa__error {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-3);
    padding: var(--space-3) var(--space-4);
    background: var(--color-danger-soft);
    border: 1px solid color-mix(in srgb, var(--color-danger) 40%, white);
    border-radius: var(--radius-md);
    color: var(--color-danger);
    font-weight: 500;
}

.wa__open {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-2);
    padding: 0.8rem 1.4rem;
    background: var(--gradient-brand);
    border-radius: var(--radius-full);
    box-shadow: var(--shadow-md);
    color: var(--color-text);
    font-weight: 600;
    line-height: 1;
    transition: transform var(--transition-fast), box-shadow var(--transition-base);
}
.wa__open:hover { transform: translateY(-2px); box-shadow: var(--shadow-glow); }
.wa__open:active { transform: translateY(0) scale(0.98); }
</style>