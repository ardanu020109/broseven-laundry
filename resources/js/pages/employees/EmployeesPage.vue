<script setup>
import { onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { ArrowLeft, History, KeyRound, Pencil, Search, UserCheck, UserPlus, UserX, Users } from 'lucide-vue-next';
import BaseBadge from '@/components/common/BaseBadge.vue';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseConfirmDialog from '@/components/common/BaseConfirmDialog.vue';
import BaseEmptyState from '@/components/common/BaseEmptyState.vue';
import BaseIconButton from '@/components/common/BaseIconButton.vue';
import BaseInput from '@/components/common/BaseInput.vue';
import BaseLoading from '@/components/common/BaseLoading.vue';
import BasePagination from '@/components/common/BasePagination.vue';
import BaseSelect from '@/components/common/BaseSelect.vue';
import BaseTable from '@/components/common/BaseTable.vue';
import UserAvatar from '@/components/common/UserAvatar.vue';
import EmployeeActivityModal from '@/components/employees/EmployeeActivityModal.vue';
import EmployeeFormModal from '@/components/employees/EmployeeFormModal.vue';
import EmployeePasswordModal from '@/components/employees/EmployeePasswordModal.vue';
import { formatDateTime } from '@/composables/useFormat';
import { useToast } from '@/composables/useToast';
import { fetchEmployees, setEmployeeStatus } from '@/services/employeeService';
import { extractErrorMessage } from '@/services/http';

const router = useRouter();
const toast = useToast();

const STATUS_OPTIONS = [
    { value: 'all', label: 'Semua status' },
    { value: 'active', label: 'Aktif' },
    { value: 'inactive', label: 'Nonaktif' },
];

const filters = reactive({ search: '', status: 'all' });
const rows = ref([]);
const meta = ref(null);
const page = ref(1);
const loading = ref(false);
const error = ref('');

const formOpen = ref(false);
const editing = ref(null);
const passwordOpen = ref(false);
const activityOpen = ref(false);
const target = ref(null);

const deactivateOpen = ref(false);
const deactivating = ref(false);

let seq = 0;
let timer = null;

async function load() {
    const current = ++seq;
    loading.value = true;
    error.value = '';

    try {
        const result = await fetchEmployees({
            search: filters.search.trim(),
            status: filters.status,
            page: page.value,
            per_page: 10,
        });
        if (current !== seq) return;

        rows.value = result.data;
        meta.value = result.meta;
    } catch (err) {
        if (current !== seq) return;

        rows.value = [];
        meta.value = null;
        error.value = extractErrorMessage(err, 'Daftar karyawan tidak dapat dimuat.');
    } finally {
        if (current === seq) loading.value = false;
    }
}

onMounted(load);
onBeforeUnmount(() => clearTimeout(timer));

watch(
    () => filters.status,
    () => {
        page.value = 1;
        load();
    },
);

watch(
    () => filters.search,
    () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            page.value = 1;
            load();
        }, 400);
    },
);

function onPage(next) {
    page.value = next;
    load();
}

function openCreate() {
    editing.value = null;
    formOpen.value = true;
}

function openEdit(row) {
    editing.value = row;
    formOpen.value = true;
}

function openPassword(row) {
    target.value = row;
    passwordOpen.value = true;
}

function openActivity(row) {
    target.value = row;
    activityOpen.value = true;
}

async function toggle(row) {
    if (row.is_active) {
        target.value = row;
        deactivateOpen.value = true;
        return;
    }

    try {
        const result = await setEmployeeStatus(row.id, true);
        toast.success(result.message);
        await load();
    } catch (err) {
        toast.error(extractErrorMessage(err));
    }
}

async function confirmDeactivate() {
    deactivating.value = true;

    try {
        const result = await setEmployeeStatus(target.value.id, false);
        deactivateOpen.value = false;
        toast.success(result.message);
        await load();
    } catch (err) {
        toast.error(extractErrorMessage(err));
    } finally {
        deactivating.value = false;
    }
}
</script>

<template>
    <main class="emp">
        <div class="container emp__inner">
            <header class="emp__header">
                <BaseButton variant="ghost" size="sm" @click="router.push('/dashboard')">
                    <ArrowLeft :size="16" />
                    Dashboard
                </BaseButton>
                <h1>Kelola Karyawan</h1>
                <p class="text-muted">
                    Tambah akun karyawan, atur ulang password, nonaktifkan akun, dan lihat riwayat aktivitas.
                    Akun yang dinonaktifkan langsung keluar dari semua perangkat dan tidak bisa login.
                </p>
            </header>

            <div class="emp__toolbar">
                <div class="emp__search">
                    <BaseInput
                        v-model="filters.search"
                        type="search"
                        placeholder="Cari nama, email, username, atau WhatsApp"
                        aria-label="Cari karyawan"
                    >
                        <template #icon><Search :size="18" /></template>
                    </BaseInput>
                </div>
                <div class="emp__status">
                    <BaseSelect v-model="filters.status" :options="STATUS_OPTIONS" aria-label="Filter status" />
                </div>
                <BaseButton @click="openCreate">
                    <UserPlus :size="18" />
                    Tambah Karyawan
                </BaseButton>
            </div>

            <BaseLoading v-if="loading && !rows.length" label="Memuat karyawan..." />

            <div v-else-if="error" class="emp__alert" role="alert">
                {{ error }}
                <BaseButton size="sm" variant="secondary" @click="load">Coba lagi</BaseButton>
            </div>

            <BaseEmptyState
                v-else-if="!rows.length"
                title="Belum ada karyawan"
                description="Tidak ada karyawan yang cocok dengan filter ini."
            >
                <template #icon><Users :size="32" /></template>
            </BaseEmptyState>

            <template v-else>
                <BaseTable caption="Daftar karyawan">
                    <thead>
                        <tr>
                            <th>Karyawan</th>
                            <th>Username</th>
                            <th>WhatsApp</th>
                            <th>Status</th>
                            <th>Login terakhir</th>
                            <th class="num">Transaksi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody :class="{ 'is-loading': loading }">
                        <tr v-for="row in rows" :key="row.id">
                            <td>
                                <div class="who">
                                    <UserAvatar :name="row.name" :url="row.avatar_url" :size="38" />
                                    <div>
                                        <strong>{{ row.name }}</strong>
                                        <div class="who__sub">{{ row.email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ row.username || '-' }}</td>
                            <td>{{ row.phone ? `+${row.phone}` : '-' }}</td>
                            <td>
                                <BaseBadge :tone="row.is_active ? 'success' : 'warning'">
                                    {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
                                </BaseBadge>
                            </td>
                            <td>{{ row.last_login_at ? formatDateTime(row.last_login_at) : 'Belum pernah' }}</td>
                            <td class="num">
                                {{ row.transactions_received }} diterima
                                <div class="who__sub">{{ row.transactions_completed }} diserahkan</div>
                            </td>
                            <td>
                                <div class="emp__actions">
                                    <BaseIconButton label="Riwayat aktivitas" @click="openActivity(row)"><History :size="16" /></BaseIconButton>
                                    <BaseIconButton label="Ubah data" @click="openEdit(row)"><Pencil :size="16" /></BaseIconButton>
                                    <BaseIconButton label="Atur ulang password" @click="openPassword(row)"><KeyRound :size="16" /></BaseIconButton>
                                    <BaseIconButton
                                        :label="row.is_active ? 'Nonaktifkan akun' : 'Aktifkan akun'"
                                        :tone="row.is_active ? 'danger' : 'neutral'"
                                        @click="toggle(row)"
                                    >
                                        <component :is="row.is_active ? UserX : UserCheck" :size="16" />
                                    </BaseIconButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </BaseTable>

                <BasePagination :meta="meta" @change="onPage" />
            </template>
        </div>

        <EmployeeFormModal v-model="formOpen" :employee="editing" @saved="load" />
        <EmployeePasswordModal v-model="passwordOpen" :employee="target" />
        <EmployeeActivityModal v-model="activityOpen" :employee="target" />

        <BaseConfirmDialog
            v-model="deactivateOpen"
            title="Nonaktifkan akun?"
            :message="`${target?.name ?? 'Karyawan'} akan langsung keluar dari semua perangkat dan tidak bisa login sampai diaktifkan kembali. Riwayat transaksinya tetap tersimpan.`"
            confirm-label="Ya, nonaktifkan"
            danger
            :loading="deactivating"
            @confirm="confirmDeactivate"
        />
    </main>
</template>

<style scoped>
.emp { min-height: 100vh; background: var(--gradient-hero); padding-block: var(--space-6) var(--space-10); }
.emp__inner { display: grid; gap: var(--space-5); max-width: 1180px; }

.emp__header h1 { margin: var(--space-3) 0 var(--space-2); font-size: var(--text-2xl); }
.emp__header p { max-width: 70ch; margin: 0; }

.emp__toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-3); }
.emp__search { flex: 1 1 280px; }
.emp__status { width: 190px; }

.who { display: flex; align-items: center; gap: var(--space-3); }
.who__sub { font-size: var(--text-xs); color: var(--color-muted); font-weight: 400; }

.emp__actions { display: flex; gap: 2px; }
.is-loading { opacity: 0.55; }

.emp__alert {
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
    .emp__status { width: 100%; }
}
</style>