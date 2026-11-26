<script setup>
import { onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ClipboardList, Eye, Plus, ScanLine, Search, X } from 'lucide-vue-next';
import BaseBadge from '@/components/common/BaseBadge.vue';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseEmptyState from '@/components/common/BaseEmptyState.vue';
import BaseIconButton from '@/components/common/BaseIconButton.vue';
import BaseInput from '@/components/common/BaseInput.vue';
import BaseDateInput from '@/components/common/BaseDateInput.vue';
import BaseLoading from '@/components/common/BaseLoading.vue';
import BasePagination from '@/components/common/BasePagination.vue';
import BaseSelect from '@/components/common/BaseSelect.vue';
import BaseTable from '@/components/common/BaseTable.vue';
import { STATUS_FILTERS, isOverdue } from '@/components/transaction/transactionStatus';
import { formatCurrency, formatDateTime, formatQuantity } from '@/composables/useFormat';
import { extractErrorMessage } from '@/services/http';
import { fetchTransactions } from '@/services/transactionService';

const route = useRoute();
const router = useRouter();

/** Semua filter tersimpan di URL, sehingga tombol Back, refresh, dan link notifikasi bekerja. */
function readQuery(query) {
    return {
        search: String(query.search ?? ''),
        status: String(query.status ?? ''),
        overdue: query.overdue === '1',
        date_from: String(query.date_from ?? ''),
        date_to: String(query.date_to ?? ''),
        page: Math.max(parseInt(query.page, 10) || 1, 1),
    };
}

const state = reactive(readQuery(route.query));
const searchInput = ref(state.search);

const rows = ref([]);
const meta = ref(null);
const loading = ref(false);
const error = ref('');

let seq = 0;
let timer = null;

function hasFilters() {
    return !!(state.search || state.status || state.overdue || state.date_from || state.date_to);
}

function push(partial) {
    const next = { ...state, ...partial };
    const query = {};

    if (next.search) query.search = next.search;
    if (next.status) query.status = next.status;
    if (next.overdue) query.overdue = '1';
    if (next.date_from) query.date_from = next.date_from;
    if (next.date_to) query.date_to = next.date_to;
    if (next.page > 1) query.page = String(next.page);

    router.replace({ query });
}

async function load() {
    const current = ++seq;
    loading.value = true;
    error.value = '';

    try {
        const result = await fetchTransactions({
            search: state.search,
            status: state.status,
            overdue: state.overdue ? 1 : '',
            date_from: state.date_from,
            date_to: state.date_to,
            page: state.page,
            per_page: 15,
        });
        if (current !== seq) return;

        rows.value = result.data;
        meta.value = result.meta;
    } catch (err) {
        if (current !== seq) return;

        rows.value = [];
        meta.value = null;
        error.value = extractErrorMessage(err, 'Daftar transaksi tidak dapat dimuat.');
    } finally {
        if (current === seq) loading.value = false;
    }
}

watch(
    () => route.query,
    (query) => {
        if (route.name !== 'transactions') return;

        Object.assign(state, readQuery(query));
        searchInput.value = state.search;
        load();
    },
);

watch(searchInput, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        const term = value.trim();
        if (term !== state.search) push({ search: term, page: 1 });
    }, 400);
});

onMounted(load);
onBeforeUnmount(() => clearTimeout(timer));

function clearFilters() {
    searchInput.value = '';
    router.replace({ query: {} });
}
</script>

<template>
    <main class="tx">
        <header class="tx__head">
            <div>
                <h1>Transaksi Laundry</h1>
                <p class="text-muted">Cari, pantau, dan kelola seluruh transaksi laundry.</p>
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

        <div class="tx__filters">
            <div class="tx__search">
                <BaseInput
                    v-model="searchInput"
                    type="search"
                    placeholder="Cari kode, nama, atau nomor WhatsApp"
                    aria-label="Cari transaksi"
                >
                    <template #icon><Search :size="18" /></template>
                </BaseInput>
            </div>

            <div class="tx__field">
                <BaseSelect
                    :model-value="state.status"
                    :options="STATUS_FILTERS"
                    aria-label="Filter status"
                    @update:model-value="(value) => push({ status: value, page: 1 })"
                />
            </div>

            <div class="tx__field">
                <BaseDateInput
                    :model-value="state.date_from"
                    aria-label="Tanggal awal"
                    :max="state.date_to || undefined"
                    @update:model-value="(value) => push({ date_from: value, page: 1 })"
                />
            </div>

            <div class="tx__field">
                <BaseDateInput
                    :model-value="state.date_to"
                    aria-label="Tanggal akhir"
                    :min="state.date_from || undefined"
                    @update:model-value="(value) => push({ date_to: value, page: 1 })"
                />
            </div>

            <BaseButton v-if="hasFilters()" variant="ghost" @click="clearFilters">
                <X :size="16" />
                Reset
            </BaseButton>
        </div>

        <p v-if="state.overdue" class="tx__chip">
            Hanya cucian yang melewati estimasi selesai
            <button type="button" aria-label="Hapus filter" @click="push({ overdue: false, page: 1 })"><X :size="14" /></button>
        </p>

        <BaseLoading v-if="loading && !rows.length" label="Memuat transaksi..." />

        <div v-else-if="error" class="tx__alert" role="alert">
            {{ error }}
            <BaseButton size="sm" variant="secondary" @click="load">Coba lagi</BaseButton>
        </div>

        <BaseEmptyState
            v-else-if="!rows.length"
            :title="hasFilters() ? 'Tidak ada transaksi yang cocok' : 'Belum ada transaksi'"
            :description="hasFilters() ? 'Coba ubah atau reset filter pencarian.' : 'Buat transaksi pertama untuk mulai mencatat cucian.'"
        >
            <template #icon><ClipboardList :size="32" /></template>
            <BaseButton v-if="hasFilters()" variant="secondary" @click="clearFilters">Reset filter</BaseButton>
            <BaseButton v-else @click="router.push('/transactions/new')">Buat transaksi</BaseButton>
        </BaseEmptyState>

        <template v-else>
            <BaseTable caption="Daftar transaksi laundry">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Customer</th>
                        <th>Paket</th>
                        <th class="num">Jumlah</th>
                        <th class="num">Total</th>
                        <th>Status</th>
                        <th>Diterima</th>
                        <th>Estimasi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody :class="{ 'is-loading': loading }">
                    <tr v-for="row in rows" :key="row.id">
                        <td><RouterLink :to="`/transactions/${row.id}`" class="tx__code">{{ row.code }}</RouterLink></td>
                        <td>
                            {{ row.customer_name }}
                            <div class="tx__sub">+{{ row.customer_phone }}</div>
                        </td>
                        <td>{{ row.package_name }}</td>
                        <td class="num">{{ formatQuantity(row.quantity, row.package_unit) }}</td>
                        <td class="num">{{ formatCurrency(row.total_price) }}</td>
                        <td><BaseBadge :tone="row.status_tone">{{ row.status_label }}</BaseBadge></td>
                        <td>{{ formatDateTime(row.received_at) }}</td>
                        <td>
                            {{ formatDateTime(row.estimated_completion_at) }}
                            <div v-if="isOverdue(row)"><BaseBadge tone="danger" :dot="false">Terlambat</BaseBadge></div>
                        </td>
                        <td>
                            <BaseIconButton label="Lihat detail" @click="router.push(`/transactions/${row.id}`)">
                                <Eye :size="16" />
                            </BaseIconButton>
                        </td>
                    </tr>
                </tbody>
            </BaseTable>

            <BasePagination :meta="meta" @change="(page) => push({ page })" />
        </template>
    </main>
</template>

<style scoped>
.tx { display: grid; gap: var(--space-5); }

.tx__head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-4); }
.tx__head h1 { margin: 0 0 var(--space-1); font-size: var(--text-2xl); }
.tx__head p { margin: 0; }

.tx__filters { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-3); }
.tx__search { flex: 1 1 280px; }
.tx__field { width: 180px; }

.tx__chip {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    justify-self: start;
    margin: 0;
    padding: 0.35rem 0.5rem 0.35rem 0.9rem;
    background: var(--color-danger-soft);
    border: 1px solid color-mix(in srgb, var(--color-danger) 40%, white);
    border-radius: var(--radius-full);
    color: var(--color-danger);
    font-size: var(--text-sm);
    font-weight: 600;
}
.tx__chip button { display: grid; place-items: center; width: 22px; height: 22px; background: transparent; border: 0; border-radius: 50%; color: inherit; }
.tx__chip button:hover { background: color-mix(in srgb, var(--color-danger) 15%, transparent); }

.tx__code { font-weight: 700; text-decoration: underline; text-decoration-color: var(--color-primary); text-underline-offset: 3px; }
.tx__sub { font-size: var(--text-xs); color: var(--color-muted); }
.is-loading { opacity: 0.55; }

.tx__alert {
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

@media (max-width: 640px) {
    .tx__field { width: calc(50% - var(--space-2)); }
}
</style>