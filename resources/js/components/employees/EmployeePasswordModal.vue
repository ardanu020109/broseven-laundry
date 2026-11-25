<script setup>
import { computed, ref, watch } from 'vue';
import { RefreshCw } from 'lucide-vue-next';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseInput from '@/components/common/BaseInput.vue';
import BaseModal from '@/components/common/BaseModal.vue';
import CredentialsPanel from '@/components/employees/CredentialsPanel.vue';
import { useFormErrors } from '@/composables/useFormErrors';
import { generatePassword } from '@/composables/usePassword';
import { useToast } from '@/composables/useToast';
import { resetEmployeePassword } from '@/services/employeeService';
import { extractErrorMessage } from '@/services/http';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    employee: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue', 'done']);

const toast = useToast();
const { errors, setFrom, clear } = useFormErrors();

const open = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const password = ref('');
const saving = ref(false);
const credentials = ref(null);

watch(
    () => props.modelValue,
    (isOpen) => {
        if (!isOpen) return;

        clear();
        credentials.value = null;
        password.value = generatePassword();
    },
);

async function submit() {
    saving.value = true;
    clear();

    try {
        const result = await resetEmployeePassword(props.employee.id, password.value);

        credentials.value = [
            { label: 'Nama', value: props.employee.name },
            { label: 'Email', value: props.employee.email },
            { label: 'Username', value: props.employee.username || '-' },
            { label: 'Password baru', value: password.value },
        ];

        toast.success(result.message);
        emit('done');
    } catch (error) {
        toast.error(setFrom(error) ? 'Periksa kembali password yang dimasukkan.' : extractErrorMessage(error));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <BaseModal v-model="open" :title="credentials ? 'Password Berhasil Diatur Ulang' : 'Atur Ulang Password'">
        <CredentialsPanel v-if="credentials" :lines="credentials" />

        <form v-else id="employee-password-form" class="form" novalidate @submit.prevent="submit">
            <p class="form__intro">
                Atur password baru untuk <strong>{{ employee?.name }}</strong>. Karyawan akan dikeluarkan dari semua
                perangkat dan harus login ulang dengan password ini.
            </p>

            <div class="form__password">
                <BaseInput
                    v-model="password"
                    label="Password baru"
                    autocomplete="new-password"
                    maxlength="100"
                    hint="Minimal 8 karakter, mengandung huruf dan angka."
                    :error="errors.password"
                />
                <BaseButton variant="secondary" @click="password = generatePassword()">
                    <RefreshCw :size="16" />
                    Buat otomatis
                </BaseButton>
            </div>
        </form>

        <template #footer>
            <template v-if="credentials">
                <BaseButton @click="open = false">Selesai</BaseButton>
            </template>
            <template v-else>
                <BaseButton variant="ghost" :disabled="saving" @click="open = false">Batal</BaseButton>
                <BaseButton type="submit" form="employee-password-form" :loading="saving">Atur Ulang</BaseButton>
            </template>
        </template>
    </BaseModal>
</template>

<style scoped>
.form { display: grid; gap: var(--space-4); }
.form__intro { margin: 0; color: var(--color-muted); }
.form__intro strong { color: var(--color-text); }
.form__password { display: flex; align-items: flex-start; gap: var(--space-3); }
.form__password > :first-child { flex: 1; }
.form__password > :last-child { margin-top: 1.7rem; }

@media (max-width: 560px) {
    .form__password { flex-direction: column; }
    .form__password > :last-child { margin-top: 0; }
}
</style>