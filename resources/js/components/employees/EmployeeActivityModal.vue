<script setup>
import { computed, ref, watch } from 'vue';
import { History } from 'lucide-vue-next';
import BaseEmptyState from '@/components/common/BaseEmptyState.vue';
import BaseLoading from '@/components/common/BaseLoading.vue';
import BaseModal from '@/components/common/BaseModal.vue';
import BasePagination from '@/components/common/BasePagination.vue';
import BaseSelect from '@/components/common/BaseSelect.vue';
import { formatDateTime } from '@/composables/useFormat';
import { fetchEmployeeActivities } from '@/services/employeeService';
import { extractErrorMessage } from '@/services/http';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    employee: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue']);

const open = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const GROUPS = [
    { value: 'all', label: 'Semua aktivitas' },
    { value: 'transaction', label: 'Transaksi' },
    { value: 'whatsapp', label: 'WhatsApp' },
    { value: 'auth', label: 'Login dan logout' },
    { value: 'account', label: 'Pengaturan akun' },
];

const group = ref('all');
const items = ref([]);
const meta = ref(null);
const page = ref(1);
const loading = ref(false);
const error = ref('');

let seq = 0;

async function load() {
    if (!props.employee) return;

    const current = ++seq;
    loading.value = true;
    error.value = '';

    try {
        const result = await fetchEmployeeActivities(props.employee.id, {
            group: group.value === 'all' ? '' : group.value,
            page: page.value,
            per_page: 10,
        });
        if (current !== seq) return;

        items.value = result.data;
        meta.value = result.meta;
    } catch (err) {
        if (current !== seq) return;

        items.value = [];
        meta.value = null;
        error.value = extractErrorMessage(err, 'Riwayat aktivitas tidak dapat dimuat.');
    } finally {
        if (current === seq) loading.value = false;
    }
}

watch(
    () => props.modelValue,
    (isOpen) => {
        if (!isOpen) return;

        group.value = 'all';
        page.value = 1;
        items.value = [];
        meta.value = null;
        load();
    },
);

watch(group, () => {
    page.value = 1;
    load();
});

function onPage(next) {
    page.value = next;
    load();
}
</script>

<template>
    <BaseModal v-model="open" :title="`Riwayat Aktivitas: ${employee?.name ?? ''}`" size="lg">
        <div class="act">
            <div class="act__filter">
                <BaseSelect v-model="group" label="Tampilkan" :options="GROUPS" />
            </div>

            <BaseLoading v-if="loading && !items.length" label="Memuat riwayat..." />

            <p v-else-if="error" class="act__error" role="alert">{{ error }}</p>

            <BaseEmptyState
                v-else-if="!items.length"
                title="Belum ada aktivitas"
                description="Aktivitas karyawan akan tercatat di sini."
            >
                <template #icon><History :size="32" /></template>
            </BaseEmptyState>

            <template v-else>
                <ul :class="['act__list', { 'act__list--loading': loading }]">
                    <li v-for="item in items" :key="item.id" class="act__item">
                        <span class="act__dot" aria-hidden="true"></span>
                        <div>
                            <p class="act__text">{{ item.description }}</p>
                            <small class="act__meta">
                                {{ formatDateTime(item.created_at) }}
                                <template v-if="item.ip_address"> &middot; IP {{ item.ip_address }}</template>
                            </small>
                        </div>
                    </li>
                </ul>

                <BasePagination :meta="meta" @change="onPage" />
            </template>
        </div>
    </BaseModal>
</template>

<style scoped>
.act { display: grid; gap: var(--space-4); }
.act__filter { width: min(100%, 260px); }

.act__list { display: grid; gap: 0; transition: opacity var(--transition-base); }
.act__list--loading { opacity: 0.55; }

.act__item {
    display: flex;
    gap: var(--space-3);
    padding: var(--space-3) 0;
    border-bottom: 1px solid var(--color-border);
}
.act__item:last-child { border-bottom: 0; }

.act__dot {
    flex: none;
    width: 10px;
    height: 10px;
    margin-top: 7px;
    background: var(--gradient-brand);
    border-radius: 50%;
}

.act__text { margin: 0; font-weight: 500; }
.act__meta { color: var(--color-muted); }
.act__error { margin: 0; color: var(--color-danger); font-weight: 500; }
</style>