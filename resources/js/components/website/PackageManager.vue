<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { Archive, ArchiveRestore, ChevronDown, ChevronUp, Eye, EyeOff, Package, Pencil, Plus } from 'lucide-vue-next';
import BaseBadge from '@/components/common/BaseBadge.vue';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseConfirmDialog from '@/components/common/BaseConfirmDialog.vue';
import BaseEmptyState from '@/components/common/BaseEmptyState.vue';
import BaseIconButton from '@/components/common/BaseIconButton.vue';
import BaseInput from '@/components/common/BaseInput.vue';
import BaseLoading from '@/components/common/BaseLoading.vue';
import BaseModal from '@/components/common/BaseModal.vue';
import BaseSelect from '@/components/common/BaseSelect.vue';
import BaseSwitch from '@/components/common/BaseSwitch.vue';
import BaseTable from '@/components/common/BaseTable.vue';
import BaseTextarea from '@/components/common/BaseTextarea.vue';
import { useFormErrors } from '@/composables/useFormErrors';
import { formatCurrency } from '@/composables/useFormat';
import { useToast } from '@/composables/useToast';
import { extractErrorMessage } from '@/services/http';
import { packagesApi } from '@/services/websiteService';

const STATUS_OPTIONS = [
    { value: 'all', label: 'Semua (aktif dan nonaktif)' },
    { value: 'active', label: 'Aktif saja' },
    { value: 'inactive', label: 'Nonaktif saja' },
    { value: 'archived', label: 'Diarsipkan' },
];

const UNIT_OPTIONS = [
    { value: 'kg', label: 'Per kilogram (kg)' },
    { value: 'item', label: 'Per item' },
];

const toast = useToast();
const { errors, setFrom, clear } = useFormErrors();

const items = ref([]);
const status = ref('all');
const loading = ref(false);
const error = ref('');

const modalOpen = ref(false);
const editing = ref(null);
const saving = ref(false);
const form = reactive({ name: '', description: '', price: '', unit: 'kg', estimated_hours: 48, featuresText: '', is_active: true });

const archiveTarget = ref(null);
const archiveOpen = ref(false);
const archiving = ref(false);

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const result = await packagesApi.list({ status: status.value });
        items.value = result.data;
    } catch (err) {
        error.value = extractErrorMessage(err, 'Daftar paket tidak dapat dimuat.');
    } finally {
        loading.value = false;
    }
}

onMounted(load);
watch(status, load);

function resetForm() {
    Object.assign(form, { name: '', description: '', price: '', unit: 'kg', estimated_hours: 48, featuresText: '', is_active: true });
    clear();
}

function openCreate() {
    editing.value = null;
    resetForm();
    modalOpen.value = true;
}

function openEdit(item) {
    editing.value = item;
    resetForm();
    Object.assign(form, {
        name: item.name,
        description: item.description ?? '',
        price: item.price,
        unit: item.unit,
        estimated_hours: item.estimated_hours,
        featuresText: (item.features ?? []).join('\n'),
        is_active: item.is_active,
    });
    modalOpen.value = true;
}

async function save() {
    saving.value = true;
    clear();

    const payload = {
        name: form.name,
        description: form.description,
        price: form.price === '' ? null : Number(form.price),
        unit: form.unit,
        estimated_hours: form.estimated_hours === '' ? null : Number(form.estimated_hours),
        features: form.featuresText.split('\n').map((line) => line.trim()).filter(Boolean),
        is_active: form.is_active,
    };

    try {
        const result = editing.value
            ? await packagesApi.update(editing.value.id, payload)
            : await packagesApi.create(payload);

        modalOpen.value = false;
        toast.success(result.message);
        await load();
    } catch (err) {
        toast.error(setFrom(err) ? 'Periksa kembali isian yang ditandai merah.' : extractErrorMessage(err));
    } finally {
        saving.value = false;
    }
}

async function toggleActive(item) {
    try {
        const result = await packagesApi.update(item.id, { is_active: !item.is_active });
        toast.success(result.data.is_active ? 'Paket diaktifkan.' : 'Paket dinonaktifkan.');
        await load();
    } catch (err) {
        toast.error(extractErrorMessage(err));
    }
}

function askArchive(item) {
    archiveTarget.value = item;
    archiveOpen.value = true;
}

async function confirmArchive() {
    archiving.value = true;

    try {
        const result = await packagesApi.remove(archiveTarget.value.id);
        archiveOpen.value = false;
        toast.success(result.message);
        await load();
    } catch (err) {
        toast.error(extractErrorMessage(err));
    } finally {
        archiving.value = false;
    }
}

async function restore(item) {
    try {
        const result = await packagesApi.restore(item.id);
        toast.success(result.message);
        await load();
    } catch (err) {
        const details = err.response?.data?.errors;
        toast.error(details ? Object.values(details).flat()[0] : extractErrorMessage(err));
    }
}

async function move(index, delta) {
    const target = index + delta;
    if (target < 0 || target >= items.value.length) return;

    const ids = items.value.map((item) => item.id);
    [ids[index], ids[target]] = [ids[target], ids[index]];

    try {
        await packagesApi.reorder(ids);
        await load();
    } catch (err) {
        toast.error(extractErrorMessage(err));
    }
}
</script>

<template>
    <section class="mgr">
        <div class="mgr__toolbar">
            <div class="mgr__filter">
                <BaseSelect v-model="status" label="Tampilkan" :options="STATUS_OPTIONS" />
            </div>
            <BaseButton @click="openCreate">
                <Plus :size="18" />
                Tambah Paket
            </BaseButton>
        </div>

        <BaseLoading v-if="loading && !items.length" label="Memuat paket..." />

        <div v-else-if="error" class="mgr__alert" role="alert">
            {{ error }}
            <BaseButton size="sm" variant="secondary" @click="load">Coba lagi</BaseButton>
        </div>

        <BaseEmptyState
            v-else-if="!items.length"
            title="Belum ada paket"
            :description="status === 'archived' ? 'Tidak ada paket yang diarsipkan.' : 'Tambahkan paket pertama agar tampil di website dan bisa dipilih saat membuat transaksi.'"
        >
            <template #icon><Package :size="32" /></template>
        </BaseEmptyState>

        <template v-else>
            <p v-if="status !== 'all'" class="mgr__note">Pengaturan urutan hanya tersedia pada tampilan "Semua".</p>

            <BaseTable caption="Daftar paket laundry">
                <thead>
                    <tr>
                        <th>Urut</th>
                        <th>Paket</th>
                        <th class="num">Harga</th>
                        <th class="num">Estimasi</th>
                        <th class="num">Transaksi</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody :class="{ 'is-loading': loading }">
                    <tr v-for="(item, index) in items" :key="item.id">
                        <td>
                            <div class="mgr__order">
                                <BaseIconButton label="Naikkan urutan" :disabled="status !== 'all' || index === 0" @click="move(index, -1)">
                                    <ChevronUp :size="16" />
                                </BaseIconButton>
                                <BaseIconButton label="Turunkan urutan" :disabled="status !== 'all' || index === items.length - 1" @click="move(index, 1)">
                                    <ChevronDown :size="16" />
                                </BaseIconButton>
                            </div>
                        </td>
                        <td>
                            <strong>{{ item.name }}</strong>
                            <div class="mgr__sub">{{ item.features.slice(0, 3).join(' · ') }}</div>
                        </td>
                        <td class="num">{{ formatCurrency(item.price) }} <small>{{ item.price_label }}</small></td>
                        <td class="num">{{ item.estimated_hours }} jam</td>
                        <td class="num">{{ item.transactions_count }}</td>
                        <td>
                            <BaseBadge v-if="item.archived" tone="neutral">Diarsipkan</BaseBadge>
                            <BaseBadge v-else-if="item.is_active" tone="success">Aktif</BaseBadge>
                            <BaseBadge v-else tone="warning">Nonaktif</BaseBadge>
                        </td>
                        <td>
                            <div class="mgr__actions">
                                <template v-if="item.archived">
                                    <BaseIconButton label="Pulihkan paket" @click="restore(item)"><ArchiveRestore :size="16" /></BaseIconButton>
                                </template>
                                <template v-else>
                                    <BaseIconButton :label="item.is_active ? 'Nonaktifkan' : 'Aktifkan'" @click="toggleActive(item)">
                                        <component :is="item.is_active ? EyeOff : Eye" :size="16" />
                                    </BaseIconButton>
                                    <BaseIconButton label="Ubah paket" @click="openEdit(item)"><Pencil :size="16" /></BaseIconButton>
                                    <BaseIconButton label="Arsipkan paket" tone="danger" @click="askArchive(item)"><Archive :size="16" /></BaseIconButton>
                                </template>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </BaseTable>
        </template>

        <BaseModal v-model="modalOpen" :title="editing ? 'Ubah Paket' : 'Tambah Paket'" size="lg">
            <form id="package-form" class="mgr__form" novalidate @submit.prevent="save">
                <BaseInput v-model="form.name" label="Nama paket" maxlength="100" :error="errors.name" />
                <BaseTextarea v-model="form.description" label="Deskripsi" :rows="2" :maxlength="500" :error="errors.description" />

                <div class="mgr__grid">
                    <BaseInput v-model="form.price" type="number" min="0" step="100" label="Harga (Rp)" :error="errors.price" />
                    <BaseSelect v-model="form.unit" label="Satuan harga" :options="UNIT_OPTIONS" :error="errors.unit" />
                    <BaseInput v-model="form.estimated_hours" type="number" min="1" max="720" label="Estimasi (jam)" :error="errors.estimated_hours" />
                </div>

                <BaseTextarea
                    v-model="form.featuresText"
                    label="Fasilitas"
                    :rows="4"
                    hint="Tulis satu fasilitas per baris (maksimal 12)."
                    :error="errors.features || errors['features.0']"
                />

                <BaseSwitch v-model="form.is_active" label="Tampilkan di website" hint="Paket nonaktif tidak tampil di website dan tidak bisa dipilih untuk transaksi baru." />
            </form>

            <template #footer>
                <BaseButton variant="ghost" :disabled="saving" @click="modalOpen = false">Batal</BaseButton>
                <BaseButton type="submit" form="package-form" :loading="saving">Simpan</BaseButton>
            </template>
        </BaseModal>

        <BaseConfirmDialog
            v-model="archiveOpen"
            title="Arsipkan paket?"
            :message="`Paket &quot;${archiveTarget?.name ?? ''}&quot; tidak lagi tampil di website dan tidak bisa dipilih untuk transaksi baru. Transaksi yang sudah ada dan laporan tidak terpengaruh. Paket dapat dipulihkan kapan saja.`"
            confirm-label="Ya, arsipkan"
            danger
            :loading="archiving"
            @confirm="confirmArchive"
        />
    </section>
</template>

<style scoped>
.mgr { display: grid; gap: var(--space-4); }
.mgr__toolbar { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: var(--space-3); }
.mgr__filter { width: min(100%, 300px); }
.mgr__note { margin: 0; font-size: var(--text-sm); color: var(--color-muted); }
.mgr__sub { max-width: 32ch; overflow: hidden; text-overflow: ellipsis; font-size: var(--text-xs); color: var(--color-muted); }
.mgr__order, .mgr__actions { display: flex; gap: 2px; }
.mgr__form { display: grid; gap: var(--space-4); }
.mgr__grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: var(--space-4); }
.is-loading { opacity: 0.55; }

.mgr__alert {
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
</style>