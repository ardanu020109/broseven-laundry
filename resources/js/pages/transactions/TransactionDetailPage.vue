<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Check, Copy, ExternalLink, Pencil, Printer, SearchX, Trash2 } from 'lucide-vue-next';
import BaseBadge from '@/components/common/BaseBadge.vue';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseCard from '@/components/common/BaseCard.vue';
import BaseConfirmDialog from '@/components/common/BaseConfirmDialog.vue';
import BaseEmptyState from '@/components/common/BaseEmptyState.vue';
import BaseLoading from '@/components/common/BaseLoading.vue';
import StatusActions from '@/components/transaction/StatusActions.vue';
import StatusHistory from '@/components/transaction/StatusHistory.vue';
import TransactionReceipt from '@/components/transaction/TransactionReceipt.vue';
import WhatsAppSendButton from '@/components/transaction/WhatsAppSendButton.vue';
import { isOverdue } from '@/components/transaction/transactionStatus';
import { copyText } from '@/composables/useClipboard';
import { formatCurrency, formatDateTime, formatQuantity } from '@/composables/useFormat';
import { useToast } from '@/composables/useToast';
import { extractErrorMessage } from '@/services/http';
import { deleteTransaction, fetchTransaction } from '@/services/transactionService';
import { usePublicSiteStore } from '@/stores/publicSite';
import { useSiteStore } from '@/stores/site';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const publicSite = usePublicSiteStore();
const siteStore = useSiteStore();

const id = computed(() => Number(route.params.id));

const tx = ref(null);
const loading = ref(true);
const notFound = ref(false);
const error = ref('');
const copied = ref(false);

const deleteOpen = ref(false);
const deleting = ref(false);

const justCreated = computed(() => route.query.created === '1');
const business = computed(() => publicSite.data?.business ?? { name: siteStore.name });
const contact = computed(() => publicSite.data?.contact ?? {});

async function load() {
    loading.value = true;
    error.value = '';
    notFound.value = false;

    try {
        tx.value = await fetchTransaction(id.value);
    } catch (err) {
        tx.value = null;

        if (err.response?.status === 404) notFound.value = true;
        else error.value = extractErrorMessage(err, 'Transaksi tidak dapat dimuat.');
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    load();
    publicSite.load();
});

watch(id, load);

function onUpdated(updated) {
    tx.value = updated;
}

async function copyTracking() {
    copied.value = await copyText(tx.value.tracking_url);

    if (copied.value) setTimeout(() => (copied.value = false), 2500);
    else toast.error('Tidak dapat menyalin otomatis. Salin link secara manual.');
}

async function printReceipt() {
    await publicSite.load();
    await nextTick();
    window.print();
}

async function confirmDelete() {
    deleting.value = true;

    try {
        const result = await deleteTransaction(tx.value.id);

        toast.success(result.message);
        router.replace('/transactions');
    } catch (err) {
        toast.error(extractErrorMessage(err));
        deleteOpen.value = false;
        load();
    } finally {
        deleting.value = false;
    }
}

function dismissCallout() {
    router.replace({ query: {} });
}
</script>

<template>
    <main class="detail">
        <BaseLoading v-if="loading && !tx" label="Memuat transaksi..." />

        <BaseCard v-else-if="notFound">
            <BaseEmptyState title="Transaksi tidak ditemukan" description="Transaksi mungkin sudah dihapus atau alamatnya salah.">
                <template #icon><SearchX :size="32" /></template>
                <BaseButton variant="secondary" @click="router.push('/transactions')">Kembali ke daftar</BaseButton>
            </BaseEmptyState>
        </BaseCard>

        <div v-else-if="error" class="detail__alert" role="alert">
            {{ error }}
            <BaseButton size="sm" variant="secondary" @click="load">Coba lagi</BaseButton>
        </div>

        <template v-else-if="tx">
            <div class="screen-only detail__screen">
                <header class="detail__head">
                    <div>
                        <p class="detail__eyebrow">Transaksi</p>
                        <h1>{{ tx.code }}</h1>
                    </div>
                    <div class="row">
                        <BaseBadge v-if="isOverdue(tx)" tone="danger" :dot="false">Melewati estimasi</BaseBadge>
                        <BaseBadge :tone="tx.status_tone">{{ tx.status_label }}</BaseBadge>
                    </div>
                </header>

                <div v-if="justCreated" class="detail__callout" role="status">
                    <div>
                        <strong>Transaksi berhasil dibuat.</strong>
                        <p>Kirim informasi transaksi dan link tracking ke customer lewat WhatsApp.</p>
                    </div>
                    <div class="row">
                        <WhatsAppSendButton :transaction-id="tx.id" label="Kirim WhatsApp" />
                        <BaseButton variant="ghost" size="sm" @click="dismissCallout">Tutup</BaseButton>
                    </div>
                </div>

                <div class="detail__grid">
                    <div class="detail__col">
                        <BaseCard>
                            <h3 class="detail__title">Rincian</h3>

                            <dl class="detail__list">
                                <div><dt>Customer</dt><dd>{{ tx.customer_name }}</dd></div>
                                <div><dt>WhatsApp</dt><dd>+{{ tx.customer_phone }}</dd></div>
                                <div><dt>Paket</dt><dd>{{ tx.package_name }}</dd></div>
                                <div>
                                    <dt>Harga paket</dt>
                                    <dd>{{ formatCurrency(tx.package_price) }} {{ tx.package_price_label }}</dd>
                                </div>
                                <div>
                                    <dt>{{ tx.package_unit === 'kg' ? 'Berat' : 'Jumlah' }}</dt>
                                    <dd>{{ formatQuantity(tx.quantity, tx.package_unit) }}</dd>
                                </div>
                                <div class="detail__total"><dt>Total</dt><dd>{{ formatCurrency(tx.total_price) }}</dd></div>
                                <div><dt>Diterima</dt><dd>{{ formatDateTime(tx.received_at) }}</dd></div>
                                <div><dt>Diterima oleh</dt><dd>{{ tx.received_by?.name ?? '-' }}</dd></div>
                                <div><dt>Estimasi selesai</dt><dd>{{ formatDateTime(tx.estimated_completion_at) }}</dd></div>
                                <div v-if="tx.ready_at"><dt>Siap diambil</dt><dd>{{ formatDateTime(tx.ready_at) }}</dd></div>
                                <div v-if="tx.completed_at"><dt>Diambil</dt><dd>{{ formatDateTime(tx.completed_at) }}</dd></div>
                                <div v-if="tx.completed_by"><dt>Diserahkan oleh</dt><dd>{{ tx.completed_by.name }}</dd></div>
                                <div v-if="tx.cancel_reason"><dt>Alasan batal</dt><dd>{{ tx.cancel_reason }}</dd></div>
                                <div v-if="tx.notes"><dt>Catatan</dt><dd class="detail__notes">{{ tx.notes }}</dd></div>
                            </dl>
                        </BaseCard>

                        <BaseCard>
                            <h3 class="detail__title">Riwayat Status</h3>
                            <StatusHistory :histories="tx.status_histories" />
                        </BaseCard>
                    </div>

                    <aside class="detail__col">
                        <BaseCard>
                            <h3 class="detail__title">Aksi</h3>

                            <div class="detail__actions">
                                <StatusActions :transaction="tx" @updated="onUpdated" @failed="load" />

                                <WhatsAppSendButton :transaction-id="tx.id" variant="secondary" block />

                                <BaseButton v-if="tx.can.update" variant="secondary" block @click="router.push(`/transactions/${tx.id}/edit`)">
                                    <Pencil :size="18" />
                                    Ubah Transaksi
                                </BaseButton>

                                <BaseButton v-if="tx.can.delete" variant="ghost" block @click="deleteOpen = true">
                                    <Trash2 :size="18" />
                                    Hapus Transaksi
                                </BaseButton>
                            </div>
                        </BaseCard>

                        <BaseCard class="detail__qr">
                            <h3 class="detail__title">Bukti Transaksi</h3>

                            <img :src="tx.qr_url" alt="QR Code transaksi" width="190" height="190" />

                            <p v-if="tx.verification?.used" class="detail__used">QR Code ini sudah digunakan untuk pengambilan.</p>

                            <BaseButton block @click="printReceipt">
                                <Printer :size="18" />
                                Cetak Bukti
                            </BaseButton>

                            <div class="detail__links">
                                <BaseButton variant="secondary" size="sm" @click="copyTracking">
                                    <component :is="copied ? Check : Copy" :size="16" />
                                    {{ copied ? 'Tersalin' : 'Salin link tracking' }}
                                </BaseButton>
                                <a :href="tx.tracking_url" target="_blank" rel="noopener noreferrer" class="detail__open">
                                    <ExternalLink :size="16" />
                                    Buka halaman customer
                                </a>
                            </div>
                        </BaseCard>
                    </aside>
                </div>
            </div>

            <TransactionReceipt class="print-only" :transaction="tx" :business="business" :contact="contact" />

            <BaseConfirmDialog
                v-model="deleteOpen"
                title="Hapus transaksi?"
                message="Transaksi akan dihapus dari daftar. Transaksi yang sudah selesai tidak dapat dihapus."
                confirm-label="Ya, hapus"
                danger
                :loading="deleting"
                @confirm="confirmDelete"
            />
        </template>
    </main>
</template>

<style scoped>
.detail { display: grid; gap: var(--space-5); }
.detail__screen { display: grid; gap: var(--space-5); }

.detail__head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-3); }
.detail__head h1 { margin: 0; font-size: var(--text-2xl); }
.detail__eyebrow { margin: 0; color: var(--color-muted); font-size: var(--text-sm); }

.detail__callout {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-4);
    padding: var(--space-4) var(--space-5);
    background: var(--color-success-soft);
    border: 1px solid color-mix(in srgb, var(--color-success) 45%, white);
    border-radius: var(--radius-lg);
}
.detail__callout p { margin: var(--space-1) 0 0; font-size: var(--text-sm); color: var(--color-muted); }

.detail__grid { display: grid; grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr); gap: var(--space-5); align-items: start; }
.detail__col { display: grid; gap: var(--space-5); }
.detail__title { margin: 0 0 var(--space-4); font-size: var(--text-lg); }

.detail__list { display: grid; gap: var(--space-3); margin: 0; }
.detail__list div { display: flex; justify-content: space-between; gap: var(--space-4); padding-bottom: var(--space-3); border-bottom: 1px solid var(--color-border); }
.detail__list div:last-child { padding-bottom: 0; border-bottom: 0; }
.detail__list dt { color: var(--color-muted); }
.detail__list dd { margin: 0; font-weight: 600; text-align: right; overflow-wrap: anywhere; }
.detail__total dd { font-size: var(--text-xl); }
.detail__notes { white-space: pre-line; }

.detail__actions { display: grid; gap: var(--space-3); }

.detail__qr { display: grid; justify-items: center; gap: var(--space-3); text-align: center; }
.detail__qr .detail__title { justify-self: start; margin-bottom: 0; }
.detail__qr img { padding: var(--space-3); background: #fff; border: 1px solid var(--color-border); border-radius: var(--radius-lg); }
.detail__used { margin: 0; color: var(--color-muted); font-size: var(--text-sm); }
.detail__links { display: grid; justify-items: center; gap: var(--space-2); }
.detail__open { display: inline-flex; align-items: center; gap: var(--space-2); color: var(--color-primary-strong); font-size: var(--text-sm); font-weight: 600; text-decoration: underline; }

.detail__alert {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-3);
    padding: 0.85rem 1rem;
    background: var(--color-danger-soft);
    border: 1px solid color-mix(in srgb, var(--color-danger) 40%, white);
    border-radius: var(--radius-md);
    color: var(--color-danger);
    font-weight: 500;
}

.print-only { display: none; }

@media print {
    .screen-only { display: none !important; }
    .print-only { display: block; }
}

@media (max-width: 960px) {
    .detail__grid { grid-template-columns: 1fr; }
}
</style>