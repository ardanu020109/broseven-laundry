<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { ArrowLeft } from 'lucide-vue-next';
import AvatarPanel from '@/components/account/AvatarPanel.vue';
import PasswordForm from '@/components/account/PasswordForm.vue';
import ProfileForm from '@/components/account/ProfileForm.vue';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseCard from '@/components/common/BaseCard.vue';
import { formatDateTime } from '@/composables/useFormat';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const auth = useAuthStore();

const lastLogin = computed(() => (auth.user?.last_login_at ? formatDateTime(auth.user.last_login_at) : '-'));
</script>

<template>
    <main class="acc">
        <div class="container acc__inner">
            <header class="acc__header">
                <BaseButton variant="ghost" size="sm" @click="router.push('/dashboard')">
                    <ArrowLeft :size="16" />
                    Dashboard
                </BaseButton>
                <h1>Pengaturan Akun</h1>
                <p class="text-muted">Kelola profil, foto, dan keamanan akun Anda.</p>
            </header>

            <div class="acc__grid">
                <aside class="acc__side">
                    <AvatarPanel />

                    <BaseCard>
                        <h3 class="acc__title">Informasi Akun</h3>
                        <dl class="acc__list">
                            <div><dt>Role</dt><dd>{{ auth.user?.role_label }}</dd></div>
                            <div><dt>Username</dt><dd>{{ auth.user?.username || '-' }}</dd></div>
                            <div><dt>Login terakhir</dt><dd>{{ lastLogin }}</dd></div>
                        </dl>
                        <small class="text-muted">Role dan username hanya dapat diatur oleh admin.</small>
                    </BaseCard>
                </aside>

                <div class="acc__main">
                    <ProfileForm />
                    <PasswordForm />
                </div>
            </div>
        </div>
    </main>
</template>

<style scoped>
.acc { min-height: 100vh; background: var(--gradient-hero); padding-block: var(--space-6) var(--space-10); }
.acc__inner { display: grid; gap: var(--space-5); max-width: 1020px; }

.acc__header h1 { margin: var(--space-3) 0 var(--space-2); font-size: var(--text-2xl); }
.acc__header p { margin: 0; }

.acc__grid { display: grid; grid-template-columns: 300px minmax(0, 1fr); gap: var(--space-5); align-items: start; }
.acc__side, .acc__main { display: grid; gap: var(--space-5); }

.acc__title { margin: 0 0 var(--space-3); font-size: var(--text-lg); }
.acc__list { display: grid; gap: var(--space-3); margin: 0 0 var(--space-3); }
.acc__list div { display: flex; justify-content: space-between; gap: var(--space-4); padding-bottom: var(--space-3); border-bottom: 1px solid var(--color-border); }
.acc__list dt { color: var(--color-muted); }
.acc__list dd { margin: 0; font-weight: 600; text-align: right; overflow-wrap: anywhere; }

@media (max-width: 860px) {
    .acc__grid { grid-template-columns: 1fr; }
}
</style>