<script setup>
import { onMounted, reactive, ref } from 'vue';
import { ChevronDown, ChevronUp, Eye, EyeOff, ListOrdered, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import BaseBadge from '@/components/common/BaseBadge.vue';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseCard from '@/components/common/BaseCard.vue';
import BaseConfirmDialog from '@/components/common/BaseConfirmDialog.vue';
import BaseEmptyState from '@/components/common/BaseEmptyState.vue';
import BaseIconButton from '@/components/common/BaseIconButton.vue';
import BaseInput from '@/components/common/BaseInput.vue';
import BaseLoading from '@/components/common/BaseLoading.vue';
import BaseModal from '@/components/common/BaseModal.vue';
import BaseSelect from '@/components/common/BaseSelect.vue';
import BaseSwitch from '@/components/common/BaseSwitch.vue';
import BaseTextarea from '@/components/common/BaseTextarea.vue';
import { useFormErrors } from '@/composables/useFormErrors';
import { useToast } from '@/composables/useToast';
import { extractErrorMessage } from '@/services/http';
import { proceduresApi } from '@/services/websiteService';

const toast = useToast();
const { errors, setFrom, clear } = useFormErrors();

const items = ref([]);
const icons = ref([]);
const loading = ref(false);
const error = ref('');

const modalOpen = ref(false);
const editing = ref(null);
const saving = ref(false);
const form = reactive({ title: '', description: '', icon: '', is_active: true });

const deleteTarget = ref(null);
const deleteOpen = ref(false);
const deleting = ref(false);

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const result = await proceduresApi.list();
        items.value = result.data;
        icons.value = [{ value: '', label: 'Tanpa ikon' }, ...result.icons];
    } catch (err) {
        error.value = extractErrorMessage(err, 'Daftar prosedur tidak dapat dimuat.');
    } finally {
        loading.value = false;
    }
}

onMounted(load);

function openCreate() {
    editing.value = null;
    clear();
    Object.assign(form, { title: '', description: '', icon: '', is_active: true });
    modalOpen.value = true;
}

function openEdit(item) {
    editing.value = item;
    clear();
    Object.assign(form, { title: item.title, description: item.description ?? '', icon: item.icon ?? '', is_active: item.is_active });
    modalOpen.value = true;
}

async function save() {
    saving.value = true;
    clear();

    const payload = { title: form.title, description: form.description, icon: form.icon || null, is_active: form.is_active };

    try {
        const result = editing.value
            ? await proceduresApi.update(editing.value.id, payload)
            : await proceduresApi.create(payload);

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
        await proceduresApi.update(item.id, { is_active: !item.is_active });
        await load();
    } catch (err) {
        toast.error(extractErrorMessage(err));
    }
}

function askDelete(item) {
    deleteTarget.value = item;
    deleteOpen.value = true;
}

async function confirmDelete() {
    deleting.value = true;

    try {
        const result = await proceduresApi.remove(deleteTarget.value.id);
        deleteOpen.value = false;
        toast.success(result.message);
        await load();
    } catch (err) {
        toast.error(extractErrorMessage(err));
    } finally {
        deleting.value = false;
    }
}

async function move(index, delta) {
    const target = index + delta;
    if (target < 0 || target >= items.value.length) return;

    const ids = items.value.map((item) => item.id);
    [ids[index], ids[target]] = [ids[target], ids[index]];

    try {
        await proceduresApi.reorder(ids);
        await load();
    } catch (err) {
        toast.error(extractErrorMessage(err));
    }
}
</script>

<template>
    <section class="mgr">
        <div class="mgr__toolbar">
            <p class="text-muted mgr__intro">Langkah-langkah yang tampil pada bagian "Prosedur" di website, sesuai urutan di bawah.</p>
            <BaseButton @click="openCreate">
                <Plus :size="18" />
                Tambah Prosedur
            </BaseButton>
        </div>

        <BaseLoading v-if="loading && !items.length" label="Memuat prosedur..." />

        <div v-else-if="error" class="mgr__alert" role="alert">
            {{ error }}
            <BaseButton size="sm" variant="secondary" @click="load">Coba lagi</BaseButton>
        </div>

        <BaseEmptyState v-else-if="!items.length" title="Belum ada prosedur" description="Tambahkan langkah pertama prosedur laundry.">
            <template #icon><ListOrdered :size="32" /></template>
        </BaseEmptyState>

        <ol v-else class="mgr__list">
            <li v-for="(item, index) in items" :key="item.id">
                <BaseCard :class="{ 'mgr__off': !item.is_active }">
                    <div class="mgr__row">
                        <span class="mgr__number">{{ index + 1 }}</span>

                        <div class="mgr__text">
                            <strong>{{ item.title }}</strong>
                            <p>{{ item.description }}</p>
                            <BaseBadge v-if="!item.is_active" tone="warning">Disembunyikan</BaseBadge>
                        </div>

                        <div class="mgr__actions">
                            <BaseIconButton label="Naikkan urutan" :disabled="index === 0" @click="move(index, -1)"><ChevronUp :size="16" /></BaseIconButton>
                            <BaseIconButton label="Turunkan urutan" :disabled="index === items.length - 1" @click="move(index, 1)"><ChevronDown :size="16" /></BaseIconButton>
                            <BaseIconButton :label="item.is_active ? 'Sembunyikan' : 'Tampilkan'" @click="toggleActive(item)">
                                <component :is="item.is_active ? EyeOff : Eye" :size="16" />
                            </BaseIconButton>
                            <BaseIconButton label="Ubah prosedur" @click="openEdit(item)"><Pencil :size="16" /></BaseIconButton>
                            <BaseIconButton label="Hapus prosedur" tone="danger" @click="askDelete(item)"><Trash2 :size="16" /></BaseIconButton>
                        </div>
                    </div>
                </BaseCard>
            </li>
        </ol>

        <BaseModal v-model="modalOpen" :title="editing ? 'Ubah Prosedur' : 'Tambah Prosedur'">
            <form id="procedure-form" class="mgr__form" novalidate @submit.prevent="save">
                <BaseInput v-model="form.title" label="Judul langkah" maxlength="150" :error="errors.title" />
                <BaseTextarea v-model="form.description" label="Penjelasan" :rows="3" :maxlength="500" :error="errors.description" />
                <BaseSelect v-model="form.icon" label="Ikon" :options="icons" :error="errors.icon" />
                <BaseSwitch v-model="form.is_active" label="Tampilkan di website" />
            </form>

            <template #footer>
                <BaseButton variant="ghost" :disabled="saving" @click="modalOpen = false">Batal</BaseButton>
                <BaseButton type="submit" form="procedure-form" :loading="saving">Simpan</BaseButton>
            </template>
        </BaseModal>

        <BaseConfirmDialog
            v-model="deleteOpen"
            title="Hapus prosedur?"
            :message="`Langkah &quot;${deleteTarget?.title ?? ''}&quot; akan dihapus dari website. Tindakan ini tidak dapat dibatalkan.`"
            confirm-label="Ya, hapus"
            danger
            :loading="deleting"
            @confirm="confirmDelete"
        />
    </section>
</template>

<style scoped>
.mgr { display: grid; gap: var(--space-4); }
.mgr__toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-3); }
.mgr__intro { margin: 0; max-width: 60ch; }
.mgr__list { display: grid; gap: var(--space-3); }
.mgr__off { opacity: 0.65; }
.mgr__row { display: flex; align-items: flex-start; gap: var(--space-4); }
.mgr__number { display: grid; place-items: center; flex: none; width: 34px; height: 34px; background: var(--gradient-brand); border-radius: 50%; font-weight: 700; }
.mgr__text { flex: 1; min-width: 0; }
.mgr__text p { margin: var(--space-1) 0 var(--space-2); color: var(--color-muted); font-size: var(--text-sm); }
.mgr__actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 2px; }
.mgr__form { display: grid; gap: var(--space-4); }

.mgr__alert {
    display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-3);
    padding: 0.85rem 1rem; background: var(--color-danger-soft);
    border: 1px solid color-mix(in srgb, var(--color-danger) 40%, white);
    border-radius: var(--radius-md); color: var(--color-danger); font-weight: 500;
}

@media (max-width: 640px) {
    .mgr__row { flex-wrap: wrap; }
    .mgr__actions { width: 100%; justify-content: flex-start; }
}
</style>