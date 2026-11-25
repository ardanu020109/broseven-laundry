<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { CheckCircle2, ClipboardList, Hourglass, PackageCheck, Plus, ScanLine, Wallet } from 'lucide-vue-next';
import BaseBadge from '@/components/common/BaseBadge.vue';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseCard from '@/components/common/BaseCard.vue';
import BaseEmptyState from '@/components/common/BaseEmptyState.vue';
import BaseLoading from '@/components/common/BaseLoading.vue';
import BaseTable from '@/components/common/BaseTable.vue';
import MiniBarChart from '@/components/dashboard/MiniBarChart.vue';
import RevenueChart from '@/components/dashboard/RevenueChart.vue';
import StatCard from '@/components/dashboard/StatCard.vue';
import { formatCurrency, formatDateTime, formatNumber, formatQuantity, todayKey } from '@/composables/useFormat';
import { fetchDashboard } from '@/services/dashboardService';
import { extractErrorMessage } from '@/services/http';
import { useAuthStore } from '@/stores/auth';

const REFRESH_MS = 60000;

const router = useRouter();
const auth = useAuthStore();

const data = ref(null);
const loading = ref(true);
const error = ref('');

let timer = null;

const firstName = computed(() => (auth.user?.name ?? '').split(/\s+/)[0] || 'Pengguna');

const dateLabel = computed(() =>
    new Intl.DateTimeFormat('id-ID', { dateStyle: 'full', timeZone: 'Asia/Makassar' }).format(new Date()),
);

const stats = computed(() => data.value?.stats ?? {});

const revenuePoints = computed(() =>
    (data.value?.series ?? []).map((day) => ({ key: day.key, total: day.income ?? 0, count: day.completed ?? 0 })),
);

async function load({ silent = false } = {}) {
    if (!silent) loading.value = true;

    try {
        data.value = await fetchDashboard();
        error.value = '';
    } catch (err) {
        // Penyegaran otomatis yang gagal tidak menghapus data yang sudah tampil.
        if (!silent || !data.value) error.value = extractErrorMessage(err, 'Dashboard tidak dapat dimuat.');
    } finally {
        loading.value = false;
    }
}

function refreshIfVisible() {
    if (document.visibilityState === 'visible') load({ silent: true });
}

onMounted(() => {
    load();
    timer = setInterval(refreshIfVisible, REFRESH_MS);
});

onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <main class="home">
        <header class="home__head">
            <div>
                <h1>Halo, {{ firstName }}!</h1>
                <p class="text-muted">{{ dateLabel }}</p>
            </div>

            <div class="row">
                <BaseButton @click="router.push('/transactions/new')">
                    <Plus :size="18" />
                    Transaksi Baru
                </BaseButton>
                <BaseButton variant="secondary" @click="router.push('/verify')">
                    <ScanLine :size="18" />
                    Verifikasi QR
                </BaseButton>
            </div>
        </header>

        <BaseLoading v-if="loading && !data" label="Memuat dashboard..." />

        <div v-else-if="error && !data" class="home__alert" role="alert">
            {{ error }}
            <BaseButton size="sm" variant="secondary" @click="load()">Coba lagi</BaseButton>
        </div>

        <template v-else-if="data">
            <section class="home__stats" aria-label="Ringkasan hari ini">
                <RouterLink :to="`/transactions?date_from=${todayKey()}&date_to=${todayKey()}`" class="home__link">
                    <StatCard label="Transaksi hari ini" :value="formatNumber(stats.transactions_today, 0)" tone="primary">
                        <template #icon><ClipboardList :size="22" /></template>
                    </StatCard>
                </RouterLink>

                <RouterLink to="/transactions?status=IN_PROGRESS" class="home__link">
                    <StatCard label="Cucian dalam proses" :value="formatNumber(stats.in_progress, 0)" tone="secondary">
                        <template #icon><Hourglass :size="22" /></template>
                    </StatCard>
                </RouterLink>

                <RouterLink to="/transactions?status=READY" class="home__link">
                    <StatCard label="Siap diambil" :value="formatNumber(stats.ready, 0)" tone="accent">
                        <template #icon><PackageCheck :size="22" /></template>
                    </StatCard>
                </RouterLink>

                <RouterLink to="/transactions?status=COMPLETED" class="home__link">
                    <StatCard label="Selesai hari ini" :value="formatNumber(stats.completed_today, 0)" tone="success">
                        <template #icon><CheckCircle2 :size="22" /></template>
                    </StatCard>
                </RouterLink>

                <RouterLink v-if="data.can_view_revenue" to="/reports" class="home__link">
                    <StatCard label="Pemasukan hari ini" :value="formatCurrency(stats.revenue_today)" tone="success">
                        <template #icon><Wallet :size="22" /></template>
                    </StatCard>
                </RouterLink>
            </section>

            <section :class="['home__charts', { 'home__charts--single': !data.can_view_revenue }]">
                <BaseCard>
                    <h3 class="home__title">Transaksi 7 Hari Terakhir</h3>
                    <MiniBarChart :points="data.series" />
                </BaseCard>

                <BaseCard v-if="data.can_view_revenue">
                    <h3 class="home__title">Pemasukan 7 Hari Terakhir</h3>
                    <RevenueChart :points="revenuePoints" granularity="day" />
                </BaseCard>
            </section>

            <BaseCard>
                <div class="row row--between">
                    <h3 class="home__title">Transaksi Terbaru</h3>
                    <BaseButton variant="ghost" size="sm" @click="router.push('/transactions')">Lihat semua</BaseButton>
                </div>

                <BaseEmptyState
                    v-if="!data.recent.length"
                    title="Belum ada transaksi"
                    description="Transaksi yang dibuat akan tampil di sini."
                >
                    <template #icon><ClipboardList :size="32" /></template>
                    <BaseButton @click="router.push('/transactions/new')">Buat transaksi pertama</BaseButton>
                </BaseEmptyState>

                <BaseTable v-else caption="Transaksi terbaru">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Customer</th>
                            <th>Paket</th>
                            <th class="num">Jumlah</th>
                            <th class="num">Total</th>
                            <th>Status</th>
                            <th>Diterima</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in data.recent" :key="row.id">
                            <td><RouterLink :to="`/transactions/${row.id}`" class="home__code">{{ row.code }}</RouterLink></td>
                            <td>{{ row.customer_name }}</td>
                            <td>{{ row.package_name }}</td>
                            <td class="num">{{ formatQuantity(row.quantity, row.unit) }}</td>
                            <td class="num">{{ formatCurrency(row.total_price) }}</td>
                            <td><BaseBadge :tone="row.status_tone">{{ row.status_label }}</BaseBadge></td>
                            <td>{{ formatDateTime(row.received_at) }}</td>
                        </tr>
                    </tbody>
                </BaseTable>
            </BaseCard>
        </template>
    </main>
</template>

<style scoped>
.home { display: grid; gap: var(--space-5); }

.home__head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-4); }
.home__head h1 { margin: 0 0 var(--space-1); font-size: var(--text-2xl); }
.home__head p { margin: 0; text-transform: capitalize; }

.home__stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: var(--space-4); }
.home__link { display: block; color: inherit; border-radius: var(--radius-lg); }

.home__charts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--space-5); }
.home__charts--single { grid-template-columns: 1fr; }
.home__title { margin: 0 0 var(--space-4); font-size: var(--text-lg); }

.home__code { font-weight: 700; text-decoration: underline; text-decoration-color: var(--color-primary); text-underline-offset: 3px; }

.home__alert {
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

@media (max-width: 960px) {
    .home__charts { grid-template-columns: 1fr; }
}
</style>