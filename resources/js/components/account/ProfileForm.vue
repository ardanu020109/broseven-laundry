<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Save } from 'lucide-vue-next';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseCard from '@/components/common/BaseCard.vue';
import BaseInput from '@/components/common/BaseInput.vue';
import { useFormErrors } from '@/composables/useFormErrors';
import { useToast } from '@/composables/useToast';
import { updateProfile } from '@/services/accountService';
import { extractErrorMessage } from '@/services/http';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const toast = useToast();
const { errors, setFrom, clear } = useFormErrors();

const form = reactive({ name: '', email: '', phone: '', current_password: '' });
const saving = ref(false);

function hydrate() {
    Object.assign(form, {
        name: auth.user?.name ?? '',
        email: auth.user?.email ?? '',
        phone: auth.user?.phone ?? '',
        current_password: '',
    });
}

hydrate();
watch(() => auth.user?.id, hydrate);

const emailChanged = computed(() => form.email.trim().toLowerCase() !== (auth.user?.email ?? '').toLowerCase());

async function submit() {
    saving.value = true;
    clear();

    const payload = { name: form.name, email: form.email, phone: form.phone || null };
    if (emailChanged.value) payload.current_password = form.current_password;

    try {
        const result = await updateProfile(payload);

        auth.setUser(result.data);
        hydrate();
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
        <h3 class="title">Profil</h3>

        <form class="form" novalidate @submit.prevent="submit">
            <BaseInput v-model="form.name" label="Nama lengkap" maxlength="100" autocomplete="name" :error="errors.name" />

            <div class="form__grid">
                <BaseInput v-model="form.email" type="email" label="Email" maxlength="150" autocomplete="email" :error="errors.email" />
                <BaseInput
                    v-model="form.phone"
                    label="Nomor WhatsApp"
                    placeholder="081234567890"
                    inputmode="tel"
                    autocomplete="tel"
                    :error="errors.phone"
                />
            </div>

            <BaseInput
                v-if="emailChanged"
                v-model="form.current_password"
                type="password"
                label="Password saat ini"
                autocomplete="current-password"
                hint="Diperlukan untuk mengubah email, karena email dipakai untuk login."
                :error="errors.current_password"
            />

            <div class="form__submit">
                <BaseButton type="submit" :loading="saving">
                    <Save :size="18" />
                    Simpan Profil
                </BaseButton>
            </div>
        </form>
    </BaseCard>
</template>

<style scoped>
.title { margin: 0 0 var(--space-4); font-size: var(--text-lg); }
.form { display: grid; gap: var(--space-4); }
.form__grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--space-4); }
.form__submit { display: flex; justify-content: flex-end; }
</style>