<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { ArrowLeft, Calculator, Download, Printer, Receipt, Scale, Search, Wallet } from 'lucide-vue-next';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseCard from '@/components/common/BaseCard.vue';
import BaseEmptyState from '@/components/common/BaseEmptyState.vue';
import BaseInput from '@/components/common/BaseInput.vue';
import BaseDateInput from '@/components/common/BaseDateInput.vue';
import BaseLoading from '@/components/common/BaseLoading.vue';
import BasePagination from '@/components/common/BasePagination.vue';
import BaseSelect from '@/components/common/BaseSelect.vue';
import BaseTable from '@/components/common/BaseTable.vue';
import RevenueChart from '@/components/dashboard/RevenueChart.vue';
import StatCard from '@/components/dashboard/StatCard.vue';
import {
    downloadReportExcel,
    fetchReport,
    fetchReportPrint,
    fetchReportTransactions,
} from '@/services/reportService';
import { extractErrorMessage } from '@/services/http';
import {
    formatCurrency,
    formatDateTime,
    formatNumber,
    formatPercent,
    formatQuantity,
    todayKey,
} from '@/composables/useFormat';

const router = useRouter();

const PERIODS = [
    { value: 'today', label: 'Hari ini' },
    { value: 'this_month', label: 'Bulan ini' },
    { value: 'this_year', label: 'Tahun ini' },
    { value: 'custom', label: 'Rentang tanggal' },
];

const GRANULARITY_LABEL = { hour: 'per jam', day: 'per hari', month: 'per bulan' };

const filters = reactive({
    period: 'this_month',
    date_from: todayKey().slice(0, 8) + '01',
    date_to: todayKey(),
    package_id: '',
    search: '',
});

const report = ref(null);
const packages = ref([]);
const rows = ref([]);
const meta = ref(null);
const page = ref(1);

const loadingReport = ref(false);
const loadingRows = ref(false);
const error = ref('');
const rowsError = ref('');
const actionError = ref('');
const exporting = ref(false);
const printing = ref(false);
const printData = ref(null);

let reportSeq = 0;
let rowsSeq = 0;
let searchTimer = null;

const summary = computed(() => report.value?.summary ?? null);
const hasIncome = computed(() => (summary.value?.transaction_count ?? 0) > 0);

const packageOptions = computed(() => [
    { value: '', label: 'Semua paket' },
    ...packages.value.map((item) => ({
        value: item.id,
        label: item.archived ? `${item.name} (diarsipkan)` : item.name,
    })),
]);

const customError = computed(() => {
    if (filters.period !== 'custom') return '';
    if (!filters.date_from || !filters.date_to) return 'Isi tanggal awal dan tanggal akhir.';
    if (filters.date_from > filters.date_to) return 'Tanggal akhir tidak boleh sebelum tanggal awal.';
    return '';
});

function baseParams() {
    const params = { period: filters.period, package_id: filters.package_id };

    if (filters.period === 'custom') {
        params.date_from = filters.date_from;
        params.date_to = filters.date_to;
    }

    return params;
}

async function loadReport() {
    const seq = ++reportSeq;
    loadingReport.value = true;
    error.value = '';

    try {
        const data = await fetchReport(baseParams());
        if (seq !== reportSeq) return;

        report.value = data;
        packages.value = data.options?.packages ?? packages.value;
    } catch (err) {
        if (seq !== reportSeq) return;

        report.value = null;
        error.value = extractErrorMessage(err, 'Laporan tidak dapat dimuat.');
    } finally {
        if (seq === reportSeq) loadingReport.value = false;
    }
}

async function loadRows() {
    const seq = ++rowsSeq;
    loadingRows.value = true;
    rowsError.value = '';

    try {
        const result = await fetchReportTransactions({
            ...baseParams(),
            search: filters.search.trim(),
            page: page.value,
            per_page: 10,
        });
        if (seq !== rowsSeq) return;

        rows.value = result.data;
        meta.value = result.meta;
    } catch (err) {
        if (seq !== rowsSeq) return;

        rows.value = [];
        meta.value = null;
        rowsError.value = extractErrorMessage(err, 'Rincian transaksi tidak dapat dimuat.');
    } finally {
        if (seq === rowsSeq) loadingRows.value = false;
    }
}

function refresh() {
    if (customError.value) return;

    page.value = 1;
    loadReport();
    loadRows();
}

function onPage(next) {
    page.value = next;
    loadRows();
}

watch(
    () => [filters.period, filters.package_id, filters.date_from, filters.date_to].join('|'),
    refresh,
);

watch(
    () => filters.search,
    () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            page.value = 1;
            loadRows();
        }, 400);
    },
);

async function exportExcel() {
    exporting.value = true;
    actionError.value = '';

    try {
        await downloadReportExcel(baseParams());
    } catch (err) {
        actionError.value = extractErrorMessage(err, 'File Excel tidak dapat diunduh.');
    } finally {
        exporting.value = false;
    }
}

async function printReport() {
    printing.value = true;
    actionError.value = '';

    try {
        printData.value = await fetchReportPrint(baseParams());
        await nextTick();
        window.print();
    } catch (err) {
        actionError.value = extractErrorMessage(err, 'Data cetak tidak dapat dimuat.');
    } finally {
        printing.value = false;
    }
}

onMounted(refresh);
onBeforeUnmount(() => clearTimeout(searchTimer));
</script>

<template>
    <main class="report">
        <div class="screen-only">
            <div class="container report__inner">
                <header class="report__header">
                    <BaseButton variant="ghost" size="sm" @click="router.push('/dashboard')">
                        <ArrowLeft :size="16" />
                        Dashboard
                    </BaseButton>
                    <h1>Laporan Pemasukan</h1>
                    <p class="text-muted">
                        Pemasukan dihitung dari transaksi yang sudah selesai (cucian sudah diambil customer),
                        berdasarkan tanggal pengambilan. Transaksi dibatalkan dan yang masih diproses tidak dihitung.
                        Zona waktu: WITA (Asia/Makassar).
                    </p>
                </header>

                <BaseCard class="report__filters">
                    <div class="periods" role="group" aria-label="Periode laporan">
                        <button
                            v-for="item in PERIODS"
                            :key="item.value"
                            type="button"
                            :class="['periods__btn', { 'periods__btn--active': filters.period === item.value }]"
                            :aria-pressed="filters.period === item.value"
                            @click="filters.period = item.value"
                        >
                            {{ item.label }}
                        </button>
                    </div>

                    <div class="report__filter-grid">
                        <BaseSelect v-model="filters.package_id" label="Paket laundry" :options="packageOptions" />

                        <template v-if="filters.period === 'custom'">
                            <BaseDateInput v-model="filters.date_from" label="Tanggal awal" :max="filters.date_to" />
                            <BaseDateInput v-model="filters.date_to" label="Tanggal akhir" :min="filters.date_from" />
                        </template>
                    </div>

                    <p v-if="customError" class="report__inline-error" role="alert">{{ customError }}</p>

                    <div class="report__actions">
                        <BaseButton variant="secondary" :loading="exporting" :disabled="!hasIncome" @click="exportExcel">
                            <Download :size="18" />
                            Export Excel
                        </BaseButton>
                        <BaseButton variant="secondary" :loading="printing" :disabled="!hasIncome" @click="printReport">
                            <Printer :size="18" />
                            Cetak
                        </BaseButton>
                    </div>
                </BaseCard>

                <div v-if="actionError" class="report__alert" role="alert">{{ actionError }}</div>

                <BaseLoading v-if="loadingReport && !report" label="Memuat laporan..." />

                <div v-else-if="error" class="report__alert" role="alert">
                    {{ error }}
                    <BaseButton size="sm" variant="secondary" @click="refresh">Coba lagi</BaseButton>
                </div>

                <template v-else-if="report">
                    <p class="report__period">
                        Periode: <strong>{{ report.period.label }}</strong>
                        <span v-if="report.package"> &middot; Paket: <strong>{{ report.package.name }}</strong></span>
                    </p>

                    <section class="report__stats" aria-label="Ringkasan">
                        <StatCard label="Total pemasukan" :value="formatCurrency(summary.total_income)" tone="success">
                            <template #icon><Wallet :size="22" /></template>
                        </StatCard>
                        <StatCard label="Jumlah transaksi" :value="formatNumber(summary.transaction_count, 0)" tone="primary">
                            <template #icon><Receipt :size="22" /></template>
                        </StatCard>
                        <StatCard
                            label="Total berat cucian"
                            :value="formatQuantity(summary.total_weight_kg, 'kg')"
                            :hint="summary.total_items > 0 ? `+ ${formatNumber(summary.total_items)} item (paket satuan)` : ''"
                            tone="accent"
                        >
                            <template #icon><Scale :size="22" /></template>
                        </StatCard>
                        <StatCard label="Rata-rata transaksi" :value="formatCurrency(summary.average_transaction)" tone="secondary">
                            <template #icon><Calculator :size="22" /></template>
                        </StatCard>
                    </section>

                    <BaseCard>
                        <div class="row row--between">
                            <h3 class="report__title">Grafik Pemasukan</h3>
                            <small class="text-muted">{{ GRANULARITY_LABEL[report.series.granularity] }}</small>
                        </div>

                        <RevenueChart
                            v-if="hasIncome"
                            :points="report.series.points"
                            :granularity="report.series.granularity"
                        />
                        <BaseEmptyState
                            v-else
                            title="Belum ada pemasukan"
                            description="Tidak ada transaksi selesai pada periode dan paket yang dipilih."
                        >
                            <template #icon><Wallet :size="32" /></template>
                        </BaseEmptyState>
                    </BaseCard>

                    <BaseCard v-if="report.by_package.length">
                        <h3 class="report__title">Pemasukan per Paket</h3>

                        <BaseTable caption="Rincian pemasukan per paket">
                            <thead>
                                <tr>
                                    <th>Paket</th>
                                    <th class="num">Transaksi</th>
                                    <th class="num">Jumlah</th>
                                    <th class="num">Pemasukan</th>
                                    <th>Kontribusi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in report.by_package" :key="`${item.package_id}-${item.unit}`">
                                    <td>{{ item.name }}</td>
                                    <td class="num">{{ item.transaction_count }}</td>
                                    <td class="num">{{ formatQuantity(item.quantity, item.unit) }}</td>
                                    <td class="num">{{ formatCurrency(item.total_income) }}</td>
                                    <td>
                                        <div class="share">
                                            <span class="share__bar"><span :style="{ width: `${item.share_percent}%` }"></span></span>
                                            <span class="share__value">{{ formatPercent(item.share_percent) }}</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </BaseTable>
                    </BaseCard>

                    <BaseCard>
                        <div class="row row--between">
                            <h3 class="report__title">Rincian Transaksi</h3>
                            <div class="report__search">
                                <BaseInput
                                    v-model="filters.search"
                                    type="search"
                                    placeholder="Cari kode atau nama customer"
                                    aria-label="Cari transaksi"
                                >
                                    <template #icon><Search :size="18" /></template>
                                </BaseInput>
                            </div>
                        </div>

                        <BaseLoading v-if="loadingRows && !rows.length" label="Memuat transaksi..." />

                        <div v-else-if="rowsError" class="report__alert" role="alert">
                            {{ rowsError }}
                            <BaseButton size="sm" variant="secondary" @click="loadRows">Coba lagi</BaseButton>
                        </div>

                        <BaseEmptyState
                            v-else-if="!rows.length"
                            title="Tidak ada transaksi"
                            description="Tidak ada transaksi selesai yang cocok dengan filter ini."
                        >
                            <template #icon><Receipt :size="32" /></template>
                        </BaseEmptyState>

                        <template v-else>
                            <BaseTable caption="Rincian transaksi selesai">
                                <thead>
                                    <tr>
                                        <th>Kode</th>
                                        <th>Selesai</th>
                                        <th>Customer</th>
                                        <th>Paket</th>
                                        <th class="num">Jumlah</th>
                                        <th class="num">Total</th>
                                        <th>Diserahkan oleh</th>
                                    </tr>
                                </thead>
                                <tbody :class="{ 'is-loading': loadingRows }">
                                    <tr v-for="row in rows" :key="row.id">
                                        <td><strong>{{ row.code }}</strong></td>
                                        <td>{{ formatDateTime(row.completed_at) }}</td>
                                        <td>{{ row.customer_name }}</td>
                                        <td>{{ row.package_name }}</td>
                                        <td class="num">{{ formatQuantity(row.quantity, row.package_unit) }}</td>
                                        <td class="num">{{ formatCurrency(row.total_price) }}</td>
                                        <td>{{ row.completed_by?.name ?? '-' }}</td>
                                    </tr>
                                </tbody>
                            </BaseTable>

                            <BasePagination :meta="meta" @change="onPage" />
                        </template>
                    </BaseCard>
                </template>
            </div>
        </div>

        <!-- Tata letak khusus cetak -->
        <section v-if="printData" class="print-only rp">
            <header class="rp__header">
                <h1>{{ printData.business.name }}</h1>
                <h2>Laporan Pemasukan</h2>
                <p>Periode: {{ printData.period.label }} ({{ printData.period.timezone }})</p>
                <p>Paket: {{ printData.package?.name ?? 'Semua paket' }}</p>
                <p>Dicetak: {{ formatDateTime(printData.generated_at) }}</p>
            </header>

            <table class="rp__table">
                <tbody>
                    <tr><th>Total pemasukan</th><td>{{ formatCurrency(printData.summary.total_income) }}</td></tr>
                    <tr><th>Jumlah transaksi</th><td>{{ formatNumber(printData.summary.transaction_count, 0) }}</td></tr>
                    <tr>
                        <th>Total berat cucian</th>
                        <td>
                            {{ formatQuantity(printData.summary.total_weight_kg, 'kg') }}
                            <template v-if="printData.summary.total_items > 0">
                                + {{ formatNumber(printData.summary.total_items) }} item
                            </template>
                        </td>
                    </tr>
                    <tr><th>Rata-rata transaksi</th><td>{{ formatCurrency(printData.summary.average_transaction) }}</td></tr>
                </tbody>
            </table>

            <h3>Pemasukan per Paket</h3>
            <table class="rp__table">
                <thead>
                    <tr><th>Paket</th><th>Transaksi</th><th>Jumlah</th><th>Pemasukan</th><th>Kontribusi</th></tr>
                </thead>
                <tbody>
                    <tr v-for="item in printData.by_package" :key="`${item.package_id}-${item.unit}`">
                        <td>{{ item.name }}</td>
                        <td>{{ item.transaction_count }}</td>
                        <td>{{ formatQuantity(item.quantity, item.unit) }}</td>
                        <td>{{ formatCurrency(item.total_income) }}</td>
                        <td>{{ formatPercent(item.share_percent) }}</td>
                    </tr>
                </tbody>
            </table>

            <h3>Rincian Transaksi</h3>
            <table class="rp__table rp__table--small">
                <thead>
                    <tr>
                        <th>Kode</th><th>Selesai</th><th>Customer</th><th>Paket</th><th>Jumlah</th><th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in printData.transactions" :key="row.id">
                        <td>{{ row.code }}</td>
                        <td>{{ formatDateTime(row.completed_at) }}</td>
                        <td>{{ row.customer_name }}</td>
                        <td>{{ row.package_name }}</td>
                        <td>{{ formatQuantity(row.quantity, row.package_unit) }}</td>
                        <td>{{ formatCurrency(row.total_price) }}</td>
                    </tr>
                </tbody>
            </table>

            <p v-if="printData.truncated" class="rp__note">
                Hanya {{ formatNumber(printData.limit, 0) }} transaksi pertama yang ditampilkan.
                Gunakan Export Excel untuk data lengkap.
            </p>
        </section>
    </main>
</template>

<style scoped>
@page { size: A4 portrait; margin: 14mm; }

.report { min-height: 100vh; background: var(--gradient-hero); padding-block: var(--space-6) var(--space-10); }
.report__inner { display: grid; gap: var(--space-5); max-width: 1080px; }

.report__header h1 { margin: var(--space-3) 0 var(--space-2); font-size: var(--text-2xl); }
.report__header p { max-width: 70ch; margin: 0; }

.report__filters { display: grid; gap: var(--space-4); }

.periods { display: flex; flex-wrap: wrap; gap: var(--space-2); }
.periods__btn {
    padding: 0.55rem 1.1rem;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-full);
    font-size: var(--text-sm);
    font-weight: 600;
    transition: border-color var(--transition-fast), background var(--transition-fast), box-shadow var(--transition-fast);
}
.periods__btn:hover { border-color: var(--color-primary); }
.periods__btn--active { background: var(--gradient-brand); border-color: transparent; box-shadow: var(--shadow-md); }

.report__filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); }

.report__actions { display: flex; flex-wrap: wrap; align-items: flex-end; gap: var(--space-3); }

.report__inline-error { margin: 0; font-size: var(--text-sm); color: var(--color-danger); }

.report__alert {
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

.report__period { margin: 0; }

.report__stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: var(--space-4); }

.report__title { margin: 0 0 var(--space-4); font-size: var(--text-lg); }
.report__search { width: min(100%, 320px); margin-bottom: var(--space-4); }

.is-loading { opacity: 0.55; }

.share { display: flex; align-items: center; gap: var(--space-3); min-width: 150px; }
.share__bar { flex: 1; height: 8px; background: var(--color-primary-soft); border-radius: var(--radius-full); overflow: hidden; }
.share__bar > span { display: block; height: 100%; background: var(--gradient-brand); border-radius: inherit; }
.share__value { width: 3.5rem; text-align: right; font-variant-numeric: tabular-nums; }

/* Cetak */
.print-only { display: none; }

.rp { color: #111; font-size: 12px; }
.rp__header { margin-bottom: 16px; text-align: center; }
.rp__header h1 { margin: 0; font-size: 20px; }
.rp__header h2 { margin: 2px 0 8px; font-size: 15px; }
.rp__header p { margin: 0; }
.rp h3 { margin: 16px 0 6px; font-size: 13px; }
.rp__table { width: 100%; border-collapse: collapse; }
.rp__table th,
.rp__table td { padding: 5px 7px; text-align: left; vertical-align: top; border: 1px solid #999; }
.rp__table thead th { background: #eee; }
.rp__table tbody th { width: 38%; background: #f5f5f5; }
.rp__table--small { font-size: 11px; }
.rp__table tr { break-inside: avoid; }
.rp__note { margin-top: 10px; font-style: italic; }

@media print {
    .screen-only { display: none !important; }
    .print-only { display: block; }
    .report { min-height: 0; padding: 0; background: #fff; }
}

@media (max-width: 640px) {
    .report__actions > * { flex: 1 1 100%; }
}
</style>