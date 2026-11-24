<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { AlertTriangle, ArrowLeft, Camera, CheckCircle2, PackageCheck, Search, XCircle } from 'lucide-vue-next';
import BaseBadge from '@/components/common/BaseBadge.vue';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseCard from '@/components/common/BaseCard.vue';
import BaseInput from '@/components/common/BaseInput.vue';
import BaseLoading from '@/components/common/BaseLoading.vue';
import QrScannerPanel from '@/components/transaction/QrScannerPanel.vue';
import WhatsAppSendButton from '@/components/transaction/WhatsAppSendButton.vue';
import { completeVerification, lookupVerification } from '@/services/verificationService';
import { extractErrorMessage } from '@/services/http';
import { formatCurrency, formatDateTime, formatQuantity } from '@/composables/useFormat';

const route = useRoute();
const router = useRouter();

const manualInput = ref('');
const scannerOpen = ref(false);
const loading = ref(false);
const completing = ref(false);
const confirming = ref(false);
const error = ref('');
const success = ref('');
const result = ref(null);
const lastInput = ref('');

const transaction = computed(() => result.value?.transaction ?? null);

const tone = computed(() => {
    const reason = result.value?.reason;
    if (reason === 'READY' || reason === 'COMPLETED') return 'success';
    if (reason === 'NOT_READY') return 'warning';
    return 'danger';
});

const toneIcon = computed(() => {
    if (tone.value === 'success') return CheckCircle2;
    if (tone.value === 'warning') return AlertTriangle;
    return XCircle;
});

async function doLookup(raw) {
    const value = String(raw ?? '').trim();

    if (!value) {
        error.value = 'Masukkan kode transaksi atau scan QR Code.';
        return;
    }

    loading.value = true;
    error.value = '';
    success.value = '';
    confirming.value = false;
    result.value = null;

    try {
        result.value = await lookupVerification(value);
        lastInput.value = value;
    } catch (err) {
        error.value = extractErrorMessage(err);
    } finally {
        loading.value = false;
    }
}

function submitManual() {
    doLookup(manualInput.value);
}

function onScan(text) {
    scannerOpen.value = false;
    manualInput.value = '';
    doLookup(text);
}

async function doComplete() {
    completing.value = true;
    error.value = '';

    try {
        const { message, result: completed } = await completeVerification(lastInput.value);
        result.value = completed;
        success.value = message;
        confirming.value = false;
    } catch (err) {
        const message = extractErrorMessage(err);
        // Muat ulang status terbaru (mis. sudah diproses petugas lain), lalu tampilkan alasannya.
        await doLookup(lastInput.value);
        error.value = message;
    } finally {
        completing.value = false;
    }
}

function reset() {
    result.value = null;
    success.value = '';
    error.value = '';
    confirming.value = false;
    manualInput.value = '';
    lastInput.value = '';

    if (route.params.token) router.replace('/verify');
}

onMounted(() => {
    if (route.params.token) doLookup(route.params.token);
});

watch(
    () => route.params.token,
    (token) => {
        if (token) doLookup(token);
    },
);
</script>

<template>
    <main class="verify">
        <div class="container verify__inner">
            <header class="verify__header">
                <BaseButton variant="ghost" size="sm" @click="router.push('/dashboard')">
                    <ArrowLeft :size="16" />
                    Dashboard
                </BaseButton>
                <h1>Verifikasi Transaksi</h1>
                <p class="text-muted">
                    Scan QR Code bukti transaksi atau masukkan kode transaksi untuk memproses pengambilan cucian.
                </p>
            </header>

            <BaseCard class="verify__panel">
                <div class="verify__manual">
                    <BaseInput
                        v-model="manualInput"
                        label="Kode transaksi"
                        placeholder="BS-261124-0001"
                        autocomplete="off"
                        spellcheck="false"
                        @keyup.enter="submitManual"
                    />
                    <BaseButton :loading="loading" @click="submitManual">
                        <Search :size="18" />
                        Cek
                    </BaseButton>
                </div>

                <div class="verify__or"><span>atau</span></div>

                <BaseButton variant="secondary" block @click="scannerOpen = !scannerOpen">
                    <Camera :size="18" />
                    {{ scannerOpen ? 'Tutup Kamera' : 'Scan dengan Kamera' }}
                </BaseButton>

                <QrScannerPanel v-if="scannerOpen" @scan="onScan" />
            </BaseCard>

            <div v-if="error" class="verify__alert" role="alert">{{ error }}</div>
            <div v-if="success" class="verify__success" role="status">{{ success }}</div>

            <BaseLoading v-if="loading" label="Memeriksa transaksi..." />

            <BaseCard v-else-if="result" class="verify__result anim-fade-up">
                <div :class="['verify__banner', `verify__banner--${tone}`]">
                    <component :is="toneIcon" :size="22" />
                    <span>{{ result.message }}</span>
                </div>

                <dl v-if="transaction" class="verify__details">
                    <div><dt>Kode</dt><dd>{{ transaction.code }}</dd></div>
                    <div>
                        <dt>Status</dt>
                        <dd><BaseBadge :tone="transaction.status_tone">{{ transaction.status_label }}</BaseBadge></dd>
                    </div>
                    <div><dt>Customer</dt><dd>{{ transaction.customer_name }}</dd></div>
                    <div><dt>WhatsApp</dt><dd>{{ transaction.customer_phone }}</dd></div>
                    <div><dt>Paket</dt><dd>{{ transaction.package_name }}</dd></div>
                    <div>
                        <dt>Berat/Jumlah</dt>
                        <dd>{{ formatQuantity(transaction.quantity, transaction.package_unit) }}</dd>
                    </div>
                    <div><dt>Total</dt><dd>{{ formatCurrency(transaction.total_price) }}</dd></div>
                    <div><dt>Diterima</dt><dd>{{ formatDateTime(transaction.received_at) }}</dd></div>
                    <div><dt>Estimasi selesai</dt><dd>{{ formatDateTime(transaction.estimated_completion_at) }}</dd></div>
                    <div v-if="transaction.notes"><dt>Catatan</dt><dd>{{ transaction.notes }}</dd></div>
                </dl>

                <WhatsAppSendButton
                    v-if="transaction"
                    :transaction-id="transaction.id"
                    variant="secondary"
                    block
                />

                <div v-if="result.can_complete && !success" class="verify__actions">
                    <BaseButton v-if="!confirming" size="lg" block @click="confirming = true">
                        <PackageCheck :size="20" />
                        Serahkan Cucian
                    </BaseButton>

                    <template v-else>
                        <p class="verify__confirm">
                            Pastikan customer sesuai dengan data di atas. Tindakan ini tidak dapat dibatalkan.
                        </p>
                        <div class="row">
                            <BaseButton variant="secondary" :disabled="completing" @click="confirming = false">
                                Batal
                            </BaseButton>
                            <BaseButton :loading="completing" @click="doComplete">Ya, Serahkan</BaseButton>
                        </div>
                    </template>
                </div>
            </BaseCard>

            <BaseButton v-if="result || success" variant="ghost" @click="reset">
                Verifikasi transaksi lain
            </BaseButton>
        </div>
    </main>
</template>

<style scoped>
.verify { min-height: 100vh; background: var(--gradient-hero); padding-block: var(--space-6) var(--space-10); }
.verify__inner { display: grid; gap: var(--space-5); max-width: 640px; }

.verify__header h1 { margin: var(--space-3) 0 var(--space-2); font-size: var(--text-2xl); }
.verify__header p { margin: 0; }

.verify__panel { display: grid; gap: var(--space-4); }

.verify__manual { display: flex; align-items: flex-end; gap: var(--space-3); }
.verify__manual > :first-child { flex: 1; }

.verify__or {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    color: var(--color-muted);
    font-size: var(--text-sm);
}
.verify__or::before,
.verify__or::after { content: ''; flex: 1; height: 1px; background: var(--color-border); }

.verify__alert,
.verify__success {
    padding: 0.85rem 1rem;
    border-radius: var(--radius-md);
    font-weight: 500;
}
.verify__alert {
    background: var(--color-danger-soft);
    border: 1px solid color-mix(in srgb, var(--color-danger) 40%, white);
    color: var(--color-danger);
}
.verify__success {
    background: var(--color-success-soft);
    border: 1px solid color-mix(in srgb, var(--color-success) 45%, white);
    color: color-mix(in srgb, var(--color-success) 80%, #0b3b2e);
}

.verify__result { display: grid; gap: var(--space-5); }

.verify__banner {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    padding: var(--space-4);
    border-radius: var(--radius-md);
    font-weight: 600;
}
.verify__banner--success { background: var(--color-success-soft); color: color-mix(in srgb, var(--color-success) 80%, #0b3b2e); }
.verify__banner--warning { background: var(--color-warning-soft); color: color-mix(in srgb, var(--color-warning) 55%, #5a3b00); }
.verify__banner--danger { background: var(--color-danger-soft); color: var(--color-danger); }

.verify__details { display: grid; gap: var(--space-3); margin: 0; }
.verify__details div {
    display: flex;
    justify-content: space-between;
    gap: var(--space-4);
    padding-bottom: var(--space-3);
    border-bottom: 1px solid var(--color-border);
}
.verify__details div:last-child { border-bottom: 0; padding-bottom: 0; }
.verify__details dt { color: var(--color-muted); }
.verify__details dd { margin: 0; font-weight: 600; text-align: right; }

.verify__actions { display: grid; gap: var(--space-3); }
.verify__confirm { margin: 0; color: var(--color-muted); }
</style>