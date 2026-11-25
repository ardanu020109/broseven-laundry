<script setup>
import { computed, ref } from 'vue';
import { Camera, Trash2 } from 'lucide-vue-next';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseCard from '@/components/common/BaseCard.vue';
import UserAvatar from '@/components/common/UserAvatar.vue';
import { useToast } from '@/composables/useToast';
import { deleteAvatar, uploadAvatar } from '@/services/accountService';
import { extractErrorMessage } from '@/services/http';
import { useAuthStore } from '@/stores/auth';

const MAX_KB = 2048;

const auth = useAuthStore();
const toast = useToast();

const input = ref(null);
const busy = ref(false);

const user = computed(() => auth.user);

async function onChange(event) {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (!file) return;

    if (file.size > MAX_KB * 1024) {
        toast.error('Ukuran foto maksimal 2 MB.');
        return;
    }

    busy.value = true;

    try {
        const result = await uploadAvatar(file);
        auth.setUser(result.data);
        toast.success(result.message);
    } catch (error) {
        toast.error(extractErrorMessage(error));
    } finally {
        busy.value = false;
    }
}

async function remove() {
    busy.value = true;

    try {
        const result = await deleteAvatar();
        auth.setUser(result.data);
        toast.success(result.message);
    } catch (error) {
        toast.error(extractErrorMessage(error));
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <BaseCard class="photo">
        <UserAvatar :name="user?.name ?? ''" :url="user?.avatar_url" :size="112" />

        <div class="photo__text">
            <h3>{{ user?.name }}</h3>
            <p class="text-muted">{{ user?.role_label }}</p>
        </div>

        <input
            ref="input"
            type="file"
            class="sr-only"
            accept="image/png,image/jpeg,image/webp"
            @change="onChange"
        />

        <div class="photo__actions">
            <BaseButton size="sm" variant="secondary" :loading="busy" @click="input.click()">
                <Camera :size="16" />
                {{ user?.avatar_url ? 'Ganti foto' : 'Unggah foto' }}
            </BaseButton>
            <BaseButton v-if="user?.avatar_url" size="sm" variant="ghost" :disabled="busy" @click="remove">
                <Trash2 :size="16" />
                Hapus
            </BaseButton>
        </div>

        <small class="text-muted">PNG, JPG, atau WEBP. Maksimal 2 MB. Foto dipotong persegi secara otomatis.</small>
    </BaseCard>
</template>

<style scoped>
.photo { display: grid; justify-items: center; gap: var(--space-3); text-align: center; }
.photo__text h3 { margin: 0; }
.photo__text p { margin: 0; }
.photo__actions { display: flex; flex-wrap: wrap; justify-content: center; gap: var(--space-2); }
</style>