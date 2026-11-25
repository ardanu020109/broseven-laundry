<script setup>
import { computed, ref } from 'vue';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseConfirmDialog from '@/components/common/BaseConfirmDialog.vue';
import BaseTextarea from '@/components/common/BaseTextarea.vue';
import { STATUS_ACTIONS } from '@/components/transaction/transactionStatus';
import { useToast } from '@/composables/useToast';
import { extractErrorMessage } from '@/services/http';
import { changeTransactionStatus } from '@/services/transactionService';

const props = defineProps({
    transaction: { type: Object, required: true },
});

const emit = defineEmits(['updated', 'failed']);

const toast = useToast();

const allowed = computed(() => props.transaction.can.change_status);
const forward = computed(() => props.transaction.allowed_transitions.filter((item) => item.value !== 'CANCELLED'));
const canCancel = computed(() => props.transaction.allowed_transitions.some((item) => item.value === 'CANCELLED'));

const busy = ref('');
const dialogOpen = ref(false);
const pending = ref('');
const reason = ref('');
const reasonError = ref('');

async function apply(status, note = null) {
    busy.value = status;

    try {
        const result = await changeTransactionStatus(props.transaction.id, status, note);

        toast.success(result.message);
        emit('updated', result.data);

        return true;
    } catch (error) {
        const noteError = error.response?.data?.errors?.note?.[0];

        if (status === 'CANCELLED' && noteError) {
            reasonError.value = noteError;
        } else {
            toast.error(extractErrorMessage(error));
            // Status mungkin sudah diubah petugas lain: minta halaman memuat ulang.
            emit('failed');
        }

        return false;
    } finally {
        busy.value = '';
    }
}

function start(status) {
    if (status === 'COMPLETED' || status === 'CANCELLED') {
        pending.value = status;
        reason.value = '';
        reasonError.value = '';
        dialogOpen.value = true;
        return;
    }

    apply(status);
}

async function confirm() {
    if (pending.value === 'CANCELLED' && !reason.value.trim()) {
        reasonError.value = 'Alasan pembatalan wajib diisi.';
        return;
    }

    const ok = await apply(pending.value, pending.value === 'CANCELLED' ? reason.value.trim() : null);

    if (ok) dialogOpen.value = false;
}
</script>

<template>
    <div class="actions">
        <p v-if="!allowed" class="actions__note">
            Transaksi ini sudah selesai atau dibatalkan, sehingga statusnya tidak dapat diubah lagi.
        </p>

        <template v-else>
            <BaseButton
                v-for="(item, index) in forward"
                :key="item.value"
                :variant="index === 0 ? 'primary' : 'secondary'"
                :loading="busy === item.value"
                :disabled="busy !== '' && busy !== item.value"
                block
                @click="start(item.value)"
            >
                <component :is="STATUS_ACTIONS[item.value]?.icon" v-if="STATUS_ACTIONS[item.value]" :size="18" />
                {{ STATUS_ACTIONS[item.value]?.label ?? item.label }}
            </BaseButton>

            <BaseButton
                v-if="canCancel"
                variant="ghost"
                block
                :disabled="busy !== ''"
                @click="start('CANCELLED')"
            >
                {{ STATUS_ACTIONS.CANCELLED.label }}
            </BaseButton>
        </template>

        <BaseConfirmDialog
            v-model="dialogOpen"
            :title="pending === 'CANCELLED' ? 'Batalkan transaksi?' : 'Serahkan cucian?'"
            :message="pending === 'CANCELLED'
                ? 'Transaksi yang dibatalkan tidak dapat diaktifkan kembali dan tidak masuk laporan pemasukan.'
                : 'Pastikan customer sudah menerima cucian. Tindakan ini tidak dapat dibatalkan.'"
            :confirm-label="pending === 'CANCELLED' ? 'Ya, batalkan' : 'Ya, serahkan'"
            :danger="pending === 'CANCELLED'"
            :loading="busy !== ''"
            @confirm="confirm"
        >
            <BaseTextarea
                v-if="pending === 'CANCELLED'"
                v-model="reason"
                class="actions__reason"
                label="Alasan pembatalan"
                :rows="3"
                :maxlength="500"
                :error="reasonError"
            />
        </BaseConfirmDialog>
    </div>
</template>

<style scoped>
.actions { display: grid; gap: var(--space-3); }
.actions__note { margin: 0; color: var(--color-muted); font-size: var(--text-sm); }
.actions__reason { margin-top: var(--space-4); }
</style>