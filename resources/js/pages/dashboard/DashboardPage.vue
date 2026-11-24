<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { LogOut, ScanLine, Settings, TrendingUp } from 'lucide-vue-next';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseCard from '@/components/common/BaseCard.vue';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const router = useRouter();
const loggingOut = ref(false);

const lastLogin = computed(() => {
    if (!auth.user?.last_login_at) return '-';
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'full',
        timeStyle: 'short',
        timeZone: 'Asia/Makassar',
    }).format(new Date(auth.user.last_login_at));
});

async function logout() {
    loggingOut.value = true;
    try {
        await auth.logout();
    } finally {
        loggingOut.value = false;
        router.replace({ name: 'login' });
    }
}
</script>

<template>
    <main class="dash">
        <div class="container dash__inner">
            <BaseCard class="dash__card">
                <p class="text-muted">Sementara. Dashboard lengkap dibuat di Fase 13.</p>
                <h1>Halo, {{ auth.user?.name }}</h1>

                <dl class="dash__list">
                    <div><dt>Email</dt><dd>{{ auth.user?.email }}</dd></div>
                    <div><dt>Username</dt><dd>{{ auth.user?.username || '-' }}</dd></div>
                    <div><dt>Role</dt><dd><span class="dash__role">{{ auth.user?.role_label }}</span></dd></div>
                    <div><dt>Login terakhir</dt><dd>{{ lastLogin }}</dd></div>
                </dl>

                <div class="row">
                    <BaseButton @click="router.push('/verify')">
                        <ScanLine :size="18" />
                        Verifikasi QR
                    </BaseButton>
                    <BaseButton v-if="auth.can('view-reports')" @click="router.push('/reports')">
                        <TrendingUp :size="18" />
                        Laporan
                    </BaseButton>
                    <BaseButton v-if="auth.can('manage-website')" @click="router.push('/website-settings')">
                        <Settings :size="18" />
                        Pengaturan Website
                    </BaseButton>
                    <BaseButton variant="secondary" :loading="loggingOut" @click="logout">
                        <LogOut :size="18" />
                        Keluar
                    </BaseButton>
                </div>
            </BaseCard>
        </div>
    </main>
</template>

<style scoped>
.dash { min-height: 100vh; display: grid; place-items: center; background: var(--gradient-hero); padding-block: var(--space-8); }
.dash__inner { display: grid; place-items: center; }
.dash__card { width: 100%; max-width: 520px; }
.dash__list { display: grid; gap: var(--space-3); margin: var(--space-5) 0; }
.dash__list div { display: flex; justify-content: space-between; gap: var(--space-4); padding-bottom: var(--space-3); border-bottom: 1px solid var(--color-border); }
.dash__list dt { color: var(--color-muted); }
.dash__list dd { margin: 0; font-weight: 600; text-align: right; }
.dash__role { padding: 0.2rem 0.75rem; background: var(--color-accent-soft); border: 1px solid var(--color-accent); border-radius: var(--radius-full); font-size: var(--text-sm); }
</style>