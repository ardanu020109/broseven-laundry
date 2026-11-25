<script setup>
import { reactive, ref } from 'vue';
import { KeyRound } from 'lucide-vue-next';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseCard from '@/components/common/BaseCard.vue';
import BaseInput from '@/components/common/BaseInput.vue';
import { useFormErrors } from '@/composables/useFormErrors';
import { useToast } from '@/composables/useToast';
import { updatePassword } from '@/services/accountService';
import { extractErrorMessage } from '@/services/http';

const toast = useToast();
const { errors, setFrom, clear } = useFormErrors();

const form = reactive({ current_password: '', password: '', password_confirmation: '' });
const saving = ref(false);

async function submit() {
    saving.value = true;
    clear();

    try {
        const result = await updatePassword({ ...form });

        Object.assign(form, { current_password: '', password: '', password_confirmation: '' });
        toast.success(result.message);
    } catch (error) {
        toast.error(setFrom(error) ? 'Periksa kembali isian yang ditandai merah.' : extractErrorMessage(error));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <BaseCard>
        <h3 class="title">Ganti Password</h3>

        <form class="form" novalidate @submit.prevent="submit">
            <BaseInput
                v-model="form.current_password"
                type="password"
                label="Password saat ini"
                autocomplete="current-password"
                :error="errors.current_password"
            />

            <div class="form__grid">
                <BaseInput
                    v-model="form.password"
                    type="password"
                    label="Password baru"
                    autocomplete="new-password"
                    hint="Minimal 8 karakter, mengandung huruf dan angka."
                    :error="errors.password"
                />
                <BaseInput
                    v-model="form.password_confirmation"
                    type="password"
                    label="Ulangi password baru"
                    autocomplete="new-password"
                    :error="errors.password_confirmation"
                />
            </div>

            <p class="form__note">Setelah password diganti, perangkat lain akan otomatis keluar. Perangkat ini tetap login.</p>

            <div class="form__submit">
                <BaseButton type="submit" :loading="saving">
                    <KeyRound :size="18" />
                    Ganti Password
                </BaseButton>
            </div>
        </form>
    </BaseCard>
</template>

<style scoped>
.title { margin: 0 0 var(--space-4); font-size: var(--text-lg); }
.form { display: grid; gap: var(--space-4); }
.form__grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--space-4); }
.form__note { margin: 0; font-size: var(--text-sm); color: var(--color-muted); }
.form__submit { display: flex; justify-content: flex-end; }
</style>