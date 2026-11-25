<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { RefreshCw } from 'lucide-vue-next';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseInput from '@/components/common/BaseInput.vue';
import BaseModal from '@/components/common/BaseModal.vue';
import CredentialsPanel from '@/components/employees/CredentialsPanel.vue';
import { useFormErrors } from '@/composables/useFormErrors';
import { generatePassword } from '@/composables/usePassword';
import { useToast } from '@/composables/useToast';
import { createEmployee, updateEmployee } from '@/services/employeeService';
import { extractErrorMessage } from '@/services/http';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    /** null = tambah karyawan baru */
    employee: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue', 'saved']);

const toast = useToast();
const { errors, setFrom, clear } = useFormErrors();

const open = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const form = reactive({ name: '', username: '', email: '', phone: '', password: '' });
const saving = ref(false);
const credentials = ref(null);

const isEdit = computed(() => !!props.employee);

watch(
    () => props.modelValue,
    (isOpen) => {
        if (!isOpen) return;

        clear();
        credentials.value = null;

        if (props.employee) {
            Object.assign(form, {
                name: props.employee.name ?? '',
                username: props.employee.username ?? '',
                email: props.employee.email ?? '',
                phone: props.employee.phone ?? '',
                password: '',
            });
        } else {
            Object.assign(form, { name: '', username: '', email: '', phone: '', password: generatePassword() });
        }
    },
);

async function submit() {
    saving.value = true;
    clear();

    try {
        if (isEdit.value) {
            const result = await updateEmployee(props.employee.id, {
                name: form.name,
                username: form.username || null,
                email: form.email,
                phone: form.phone || null,
            });

            toast.success(result.message);
            emit('saved');
            open.value = false;
        } else {
            const result = await createEmployee({
                name: form.name,
                username: form.username || null,
                email: form.email,
                phone: form.phone || null,
                password: form.password,
            });

            credentials.value = [
                { label: 'Nama', value: result.data.name },
                { label: 'Email', value: result.data.email },
                { label: 'Username', value: result.data.username || '-' },
                { label: 'Password', value: form.password },
            ];

            toast.success(result.message);
            emit('saved');
        }
    } catch (error) {
        toast.error(setFrom(error) ? 'Periksa kembali isian yang ditandai merah.' : extractErrorMessage(error));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <BaseModal v-model="open" :title="credentials ? 'Karyawan Berhasil Dibuat' : isEdit ? 'Ubah Data Karyawan' : 'Tambah Karyawan'">
        <CredentialsPanel v-if="credentials" :lines="credentials" />

        <form v-else id="employee-form" class="form" novalidate @submit.prevent="submit">
            <BaseInput v-model="form.name" label="Nama lengkap" maxlength="100" autocomplete="off" :error="errors.name" />

            <div class="form__grid">
                <BaseInput v-model="form.email" type="email" label="Email" maxlength="150" autocomplete="off" :error="errors.email" />
                <BaseInput
                    v-model="form.username"
                    label="Username (opsional)"
                    maxlength="30"
                    autocomplete="off"
                    hint="Huruf kecil, angka, titik, strip, garis bawah."
                    :error="errors.username"
                />
            </div>

            <BaseInput
                v-model="form.phone"
                label="Nomor WhatsApp (opsional)"
                placeholder="081234567890"
                inputmode="tel"
                :error="errors.phone"
            />

            <div v-if="!isEdit" class="form__password">
                <BaseInput
                    v-model="form.password"
                    label="Password awal"
                    autocomplete="new-password"
                    maxlength="100"
                    hint="Minimal 8 karakter, mengandung huruf dan angka."
                    :error="errors.password"
                />
                <BaseButton variant="secondary" @click="form.password = generatePassword()">
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
                <BaseButton type="submit" form="employee-form" :loading="saving">
                    {{ isEdit ? 'Simpan' : 'Tambah Karyawan' }}
                </BaseButton>
            </template>
        </template>
    </BaseModal>
</template>

<style scoped>
.form { display: grid; gap: var(--space-4); }
.form__grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); }
.form__password { display: flex; align-items: flex-start; gap: var(--space-3); }
.form__password > :first-child { flex: 1; }
.form__password > :last-child { margin-top: 1.7rem; }

@media (max-width: 560px) {
    .form__password { flex-direction: column; }
    .form__password > :last-child { margin-top: 0; }
}
</style>