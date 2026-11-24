<script setup>
import BaseButton from '@/components/common/BaseButton.vue';
import BaseModal from '@/components/common/BaseModal.vue';

const open = defineModel({ type: Boolean, default: false });

defineProps({
    title: { type: String, default: 'Konfirmasi' },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: 'Ya, lanjutkan' },
    cancelLabel: { type: String, default: 'Batal' },
    danger: Boolean,
    loading: Boolean,
});

const emit = defineEmits(['confirm']);
</script>

<template>
    <BaseModal v-model="open" :title="title" size="sm">
        <p class="confirm__text">{{ message }}</p>
        <slot />

        <template #footer>
            <BaseButton variant="ghost" :disabled="loading" @click="open = false">{{ cancelLabel }}</BaseButton>
            <BaseButton :variant="danger ? 'danger' : 'primary'" :loading="loading" @click="emit('confirm')">
                {{ confirmLabel }}
            </BaseButton>
        </template>
    </BaseModal>
</template>

<style scoped>
.confirm__text { margin: 0; color: var(--color-text); }
</style>