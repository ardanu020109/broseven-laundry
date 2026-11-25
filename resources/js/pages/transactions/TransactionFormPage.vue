<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Save } from 'lucide-vue-next';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseCard from '@/components/common/BaseCard.vue';
import BaseEmptyState from '@/components/common/BaseEmptyState.vue';
import BaseInput from '@/components/common/BaseInput.vue';
import BaseLoading from '@/components/common/BaseLoading.vue';
import BaseSelect from '@/components/common/BaseSelect.vue';
import BaseTextarea from '@/components/common/BaseTextarea.vue';
import { useFormErrors } from '@/composables/useFormErrors';
import { formatCurrency, toDateTimeLocal } from '@/composables/useFormat';
import { useToast } from '@/composables/useToast';
import { extractErrorMessage } from '@/services/http';
import {
    createTransaction,
    fetchActivePackages,
    fetchTransaction,
    updateTransaction,
} from '@/services/transactionService';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const { errors, setFrom, clear } = useFormErrors();

const id = computed(() => (route.params.id ? Number(route.params.id) : null));
const isEdit = computed(() => id.value !== null);

const loading = ref(true);
const loadError = ref('');
const saving = ref(false);
const packages = ref([]);
const tx = ref(null);
const originalEstimate = ref('');

const form = reactive({
    customer_name: '',
    customer_phone: '',
    package_id: '',
    quantity: '',
    estimated_completion_at: '',
    notes: '',
});

// Paket dan jumlah terkunci setelah pengerjaan dimulai, agar harga tidak bergeser.
const locked = computed(() => isEdit.value && !!tx.value && tx.value.status !== 'RECEIVED');

const options = computed(() => {
    const list = packages.value.map((item) => ({
        value: item.id,
        label: `${item.name} - ${formatCurrency(item.price)} ${item.price_label}`,
    }));

    if (tx.value && !list.some((option) => option.value === tx.value.package_id)) {
        list.unshift({ value: tx.value.package_id, label: `${tx.value.package_name} (paket saat ini)` });
    }

    return list;
});

const selected = computed(() => packages.value.find((item) => item.id === Number(form.package_id)) ?? null);

const unit = computed(() => selected.value?.unit ?? tx.value?.package_unit ?? 'kg');

const unitPrice = computed(() => {
    // Selama paket tidak diganti, server memakai harga snapshot transaksi.
    if (isEdit.value && tx.value && Number(form.package_id) === tx.value.package_id) return tx.value.package_price;
    return selected.value?.price ?? null;
});

const estimateTotal = computed(() => {
    const quantity = Number(form.quantity);

    if (unitPrice.value === null || !(quantity > 0)) return null;

    return Math.round(unitPrice.value * quantity * 100) / 100;
});

const estimateHint = computed(() =>
    !isEdit.value && selected.value
        ? `Kosongkan untuk memakai estimasi paket (${selected.value.estimated_hours} jam).`
        : '',
);

async function load() {
    loading.value = true;
    loadError.value = '';

    try {
        const [activePackages, current] = await Promise.all([
            fetchActivePackages(),
            isEdit.value ? fetchTransaction(id.value) : Promise.resolve(null),
        ]);

        packages.value = activePackages;

        if (current) {
            tx.value = current;
            originalEstimate.value = toDateTimeLocal(current.estimated_completion_at);

            Object.assign(form, {
                customer_name: current.customer_name,
                customer_phone: current.customer_phone,
                package_id: current.package_id,
                quantity: String(current.quantity),
                estimated_completion_at: originalEstimate.value,
                notes: current.notes ?? '',
            });
        }
    } catch (error) {
        loadError.value = error.response?.status === 404
            ? 'Transaksi tidak ditemukan.'
            : extractErrorMessage(error, 'Form tidak dapat dimuat.');
    } finally {
        loading.value = false;
    }
}

onMounted(load);

function buildPayload() {
    const payload = {
        customer_name: form.customer_name,
        customer_phone: form.customer_phone,
        notes: form.notes,
    };

    if (!locked.value) {
        payload.package_id = form.package_id === '' ? null : Number(form.package_id);
        payload.quantity = form.quantity === '' ? null : Number(form.quantity);
    }

    // Estimasi hanya dikirim jika diubah (agar transaksi yang sudah terlambat tetap bisa diedit).
    if (form.estimated_completion_at && (!isEdit.value || form.estimated_completion_at !== originalEstimate.value)) {
        payload.estimated_completion_at = form.estimated_completion_at;
    }

    return payload;
}

async function submit() {
    saving.value = true;
    clear();

    try {
        const payload = buildPayload();

        if (isEdit.value) {
            const result = await updateTransaction(id.value, payload);

            toast.success(result.message);
            router.replace(`/transactions/${id.value}`);
        } else {
            const result = await createTransaction(payload);

            toast.success(result.message);
            router.replace({ path: `/transactions/${result.data.id}`, query: { created: '1' } });
        }
    } catch (error) {
        toast.error(setFrom(error) ? 'Periksa kembali isian yang ditandai merah.' : extractErrorMessage(error));
    } finally {
        saving.value = false;
    }
}

function cancel() {
    router.push(isEdit.value ? `/transactions/${id.value}` : '/transactions');
}
</script>

<template>
    <main class="form">
        <header class="form__head">
            <h1>{{ isEdit ? 'Ubah Transaksi' : 'Transaksi Baru' }}</h1>
            <p class="text-muted">
                {{ isEdit ? tx?.code : 'Catat cucian yang diterima. Harga total dihitung otomatis oleh sistem.' }}
            </p>
        </header>

        <BaseLoading v-if="loading" label="Memuat form..." />

        <BaseCard v-else-if="loadError">
            <BaseEmptyState title="Form tidak dapat dibuka" :description="loadError">
                <BaseButton variant="secondary" @click="router.push('/transactions')">Kembali ke daftar</BaseButton>
            </BaseEmptyState>
        </BaseCard>

        <BaseCard v-else-if="isEdit && tx && !tx.can.update">
            <BaseEmptyState
                title="Transaksi tidak dapat diubah"
                description="Transaksi yang sudah selesai atau dibatalkan tidak dapat diubah."
            >
                <BaseButton variant="secondary" @click="router.push(`/transactions/${id}`)">Lihat detail</BaseButton>
            </BaseEmptyState>
        </BaseCard>

        <form v-else class="form__body" novalidate @submit.prevent="submit">
            <p v-if="errors.transaction" class="form__alert" role="alert">{{ errors.transaction }}</p>

            <BaseCard>
                <h3 class="form__title">Data Customer</h3>

                <div class="form__grid">
                    <BaseInput
                        v-model="form.customer_name"
                        label="Nama customer"
                        maxlength="100"
                        autocomplete="off"
                        :error="errors.customer_name"
                    />
                    <BaseInput
                        v-model="form.customer_phone"
                        label="Nomor WhatsApp"
                        placeholder="081234567890"
                        inputmode="tel"
                        autocomplete="off"
                        hint="Dipakai untuk mengirim informasi status cucian."
                        :error="errors.customer_phone"
                    />
                </div>
            </BaseCard>

            <BaseCard>
                <h3 class="form__title">Cucian</h3>

                <p v-if="locked" class="form__note">
                    Paket dan berat/jumlah tidak dapat diubah karena cucian sudah diproses.
                </p>

                <div class="form__grid">
                    <BaseSelect
                        v-model="form.package_id"
                        label="Paket laundry"
                        placeholder="Pilih paket"
                        :options="options"
                        :disabled="locked"
                        :error="errors.package_id"
                    />
                    <BaseInput
                        v-model="form.quantity"
                        type="number"
                        :label="unit === 'kg' ? 'Berat (kg)' : 'Jumlah (item)'"
                        min="0.01"
                        :step="unit === 'kg' ? '0.01' : '1'"
                        inputmode="decimal"
                        :disabled="locked"
                        :hint="unit === 'kg' ? 'Maksimal 2 angka di belakang koma.' : 'Bilangan bulat.'"
                        :error="errors.quantity"
                    />
                </div>

                <div class="form__total">
                    <span>Perkiraan total</span>
                    <strong v-if="estimateTotal !== null">{{ formatCurrency(estimateTotal) }}</strong>
                    <strong v-else-if="isEdit && tx">{{ formatCurrency(tx.total_price) }}</strong>
                    <strong v-else>-</strong>
                    <small>Total akhir dihitung oleh sistem berdasarkan harga paket.</small>
                </div>
            </BaseCard>

            <BaseCard>
                <h3 class="form__title">Estimasi dan Catatan</h3>

                <div class="form__stack">
                    <BaseInput
                        v-model="form.estimated_completion_at"
                        type="datetime-local"
                        label="Estimasi selesai"
                        :hint="estimateHint"
                        :error="errors.estimated_completion_at"
                    />
                    <BaseTextarea
                        v-model="form.notes"
                        label="Catatan (opsional)"
                        :rows="3"
                        :maxlength="1000"
                        :error="errors.notes"
                    />
                </div>
            </BaseCard>

            <div class="form__actions">
                <BaseButton variant="ghost" :disabled="saving" @click="cancel">Batal</BaseButton>
                <BaseButton type="submit" size="lg" :loading="saving">
                    <Save :size="18" />
                    {{ isEdit ? 'Simpan Perubahan' : 'Simpan Transaksi' }}
                </BaseButton>
            </div>
        </form>
    </main>
</template>

<style scoped>
.form { display: grid; gap: var(--space-5); max-width: 820px; }

.form__head h1 { margin: 0 0 var(--space-1); font-size: var(--text-2xl); }
.form__head p { margin: 0; }

.form__body { display: grid; gap: var(--space-5); }
.form__title { margin: 0 0 var(--space-4); font-size: var(--text-lg); }
.form__grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: var(--space-4); }
.form__stack { display: grid; gap: var(--space-4); }

.form__note { margin: 0 0 var(--space-4); padding: var(--space-3) var(--space-4); background: var(--color-warning-soft); border-radius: var(--radius-md); font-size: var(--text-sm); }

.form__total {
    display: grid;
    gap: 2px;
    margin-top: var(--space-4);
    padding: var(--space-4);
    background: var(--color-accent-soft);
    border: 1px solid var(--color-accent);
    border-radius: var(--radius-lg);
}
.form__total span { color: var(--color-muted); font-size: var(--text-sm); }
.form__total strong { font-size: var(--text-2xl); }
.form__total small { color: var(--color-muted); }

.form__alert { margin: 0; padding: 0.85rem 1rem; background: var(--color-danger-soft); border: 1px solid color-mix(in srgb, var(--color-danger) 40%, white); border-radius: var(--radius-md); color: var(--color-danger); font-weight: 500; }

.form__actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: var(--space-3); }
</style>