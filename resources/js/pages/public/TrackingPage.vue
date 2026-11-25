<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { CloudOff, Download, Droplets, Hourglass, MessageCircle, SearchX } from 'lucide-vue-next';
import BaseBadge from '@/components/common/BaseBadge.vue';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseCard from '@/components/common/BaseCard.vue';
import BaseEmptyState from '@/components/common/BaseEmptyState.vue';
import BaseLoading from '@/components/common/BaseLoading.vue';
import TrackingTimeline from '@/components/tracking/TrackingTimeline.vue';
import { fetchTracking } from '@/services/trackingService';
import { formatDateTime, formatQuantity } from '@/composables/useFormat';

const appName = import.meta.env.VITE_APP_NAME || 'BroSeven! Laundry';
const REFRESH_INTERVAL = 30000;

const route = useRoute();
const token = computed(() => String(route.params.token || ''));

const data = ref(null);
const loading = ref(true);
const error = ref(null);

let timer = null;

const whatsappLink = computed(() =>
    data.value?.business?.whatsapp ? `https://wa.me/${data.value.business.whatsapp}` : null,
);

function describeError(err) {
    const status = err.response?.status;

    if (status === 404) {
        return {
            icon: SearchX,
            title: 'Transaksi tidak ditemukan',
            description: 'Link tracking tidak valid. Periksa kembali link yang dikirim melalui WhatsApp.',
        };
    }
    if (status === 429) {
        return {
            icon: Hourglass,
            title: 'Terlalu banyak permintaan',
            description: 'Silakan tunggu sebentar lalu coba lagi.',
        };
    }
    return {
        icon: CloudOff,
        title: 'Gagal memuat status',
        description: 'Terjadi gangguan koneksi. Silakan coba lagi.',
    };
}

async function load({ silent = false } = {}) {
    if (!silent) loading.value = true;

    try {
        data.value = await fetchTracking(token.value);
        error.value = null;
    } catch (err) {
        // Pembaruan otomatis yang gagal tidak boleh menghapus data yang sudah tampil.
        if (silent && data.value) return;

        data.value = null;
        error.value = describeError(err);
    } finally {
        loading.value = false;
    }
}

function refreshIfActive() {
    const current = data.value;
    if (!current || current.is_completed || current.is_cancelled) return;
    if (document.visibilityState !== 'visible') return;

    load({ silent: true });
}

onMounted(() => {
    load();
    timer = setInterval(refreshIfActive, REFRESH_INTERVAL);
});

onBeforeUnmount(() => clearInterval(timer));

watch(token, () => load());
</script>

<template>
    <main class="track">
        <header class="track__header">
            <div class="container track__header-inner">
                <span class="track__logo"><Droplets :size="20" /></span>
                <RouterLink to="/" class="track__home"><strong>{{ data?.business?.name || appName }}</strong></RouterLink>
            </div>
        </header>

        <div class="container track__content">
            <BaseLoading v-if="loading" label="Memuat status cucian..." />

            <BaseCard v-else-if="error">
                <BaseEmptyState :title="error.title" :description="error.description">
                    <template #icon><component :is="error.icon" :size="32" /></template>
                    <BaseButton variant="secondary" @click="load()">Coba lagi</BaseButton>
                </BaseEmptyState>
            </BaseCard>

            <template v-else-if="data">
                <BaseCard class="track__summary anim-fade-up">
                    <div class="row row--between">
                        <div>
                            <small class="text-muted">Kode transaksi</small>
                            <h1 class="track__code">{{ data.code }}</h1>
                        </div>
                        <BaseBadge :tone="data.status_tone">{{ data.status_label }}</BaseBadge>
                    </div>

                    <p :class="['track__message', `track__message--${data.status_tone}`]">
                        {{ data.pickup.message }}
                    </p>
                </BaseCard>

                <div class="track__grid">
                    <BaseCard>
                        <h3>Detail Cucian</h3>
                        <dl class="track__details">
                            <div><dt>Customer</dt><dd>{{ data.customer_name }}</dd></div>
                            <div><dt>Paket</dt><dd>{{ data.package_name }}</dd></div>
                            <div><dt>Berat/Jumlah</dt><dd>{{ formatQuantity(data.quantity, data.unit) }}</dd></div>
                            <div><dt>Tanggal masuk</dt><dd>{{ formatDateTime(data.received_at) }}</dd></div>
                            <div><dt>Estimasi selesai</dt><dd>{{ formatDateTime(data.estimated_completion_at) }}</dd></div>
                            <div v-if="data.ready_at"><dt>Siap diambil</dt><dd>{{ formatDateTime(data.ready_at) }}</dd></div>
                            <div v-if="data.completed_at"><dt>Diambil</dt><dd>{{ formatDateTime(data.completed_at) }}</dd></div>
                        </dl>
                    </BaseCard>

                    <BaseCard>
                        <h3>Proses Laundry</h3>
                        <TrackingTimeline :steps="data.timeline" />
                    </BaseCard>
                </div>

                <BaseCard v-if="data.qr.available" class="track__qr">
                    <h3>QR Code Bukti Transaksi</h3>
                    <p class="text-muted">Tunjukkan QR Code ini kepada petugas saat mengambil cucian.</p>
                    <img
                        class="track__qr-image"
                        :src="data.qr.image_url"
                        alt="QR Code bukti transaksi"
                        width="220"
                        height="220"
                    />
                    <a class="track__download" :href="data.qr.image_url" :download="`qr-${data.code}.svg`">
                        <Download :size="16" />
                        Simpan QR Code
                    </a>
                </BaseCard>

                <BaseCard v-else-if="data.qr.used" class="track__qr">
                    <p class="text-muted track__used">QR Code ini sudah digunakan untuk pengambilan cucian.</p>
                </BaseCard>

                <div v-if="whatsappLink" class="track__contact">
                    <a :href="whatsappLink" target="_blank" rel="noopener noreferrer">
                        <BaseButton variant="secondary">
                            <MessageCircle :size="18" />
                            Hubungi Kami via WhatsApp
                        </BaseButton>
                    </a>
                </div>
            </template>
        </div>
    </main>
</template>

<style scoped>
.track { min-height: 100vh; background: var(--gradient-hero); }

.track__header {
    background: color-mix(in srgb, var(--color-surface) 85%, transparent);
    border-bottom: 1px solid var(--color-border);
    backdrop-filter: blur(8px);
}

.track__header-inner { display: flex; align-items: center; gap: var(--space-3); padding-block: var(--space-4); }

.track__logo {
    display: grid;
    place-items: center;
    width: 36px;
    height: 36px;
    background: var(--gradient-brand);
    border-radius: var(--radius-md);
}

.track__content { display: grid; gap: var(--space-5); max-width: 880px; padding-block: var(--space-6) var(--space-10); }

.track__code { margin: 0; font-size: var(--text-2xl); letter-spacing: 0.01em; }

.track__message {
    margin: var(--space-4) 0 0;
    padding: var(--space-3) var(--space-4);
    background: var(--color-primary-soft);
    border-radius: var(--radius-md);
    font-weight: 500;
}
.track__message--success { background: var(--color-success-soft); }
.track__message--warning { background: var(--color-warning-soft); }
.track__message--danger { background: var(--color-danger-soft); }

.track__grid { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-5); align-items: start; }

.track__details { display: grid; gap: var(--space-3); margin: var(--space-4) 0 0; }
.track__details div {
    display: flex;
    justify-content: space-between;
    gap: var(--space-4);
    padding-bottom: var(--space-3);
    border-bottom: 1px solid var(--color-border);
}
.track__details div:last-child { border-bottom: 0; padding-bottom: 0; }
.track__details dt { color: var(--color-muted); }
.track__details dd { margin: 0; font-weight: 600; text-align: right; }

.track__qr { display: grid; justify-items: center; text-align: center; }
.track__qr-image {
    margin: var(--space-3) 0;
    padding: var(--space-3);
    background: #fff;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
}
.track__used { margin: 0; }

.track__download {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    padding: 0.55rem 1.1rem;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-full);
    font-size: var(--text-sm);
    font-weight: 600;
    transition: border-color var(--transition-base), box-shadow var(--transition-base);
}
.track__download:hover { border-color: var(--color-primary); box-shadow: var(--shadow-md); }

.track__contact { display: flex; justify-content: center; }

@media (max-width: 720px) {
    .track__grid { grid-template-columns: 1fr; }
}
</style>