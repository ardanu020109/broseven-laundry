<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { ChevronDown, Globe, LogOut, User } from 'lucide-vue-next';
import UserAvatar from '@/components/common/UserAvatar.vue';
import { useDismiss } from '@/composables/useDismiss';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const router = useRouter();

const root = ref(null);
const open = ref(false);
const leaving = ref(false);

useDismiss(root, open, () => (open.value = false));

function go(path) {
    open.value = false;
    router.push(path);
}

async function logout() {
    leaving.value = true;

    try {
        await auth.logout();
    } finally {
        leaving.value = false;
        open.value = false;
        router.replace({ name: 'login' });
    }
}
</script>

<template>
    <div ref="root" class="profile">
        <button type="button" class="profile__button" aria-haspopup="true" :aria-expanded="open" @click="open = !open">
            <UserAvatar :name="auth.user?.name ?? ''" :url="auth.user?.avatar_url" :size="34" />
            <span class="profile__name">{{ auth.user?.name }}</span>
            <ChevronDown :size="16" class="profile__chevron" aria-hidden="true" />
        </button>

        <Transition name="drop">
            <div v-if="open" class="profile__panel" role="menu">
                <div class="profile__head">
                    <strong>{{ auth.user?.name }}</strong>
                    <small>{{ auth.user?.email }}</small>
                    <span class="profile__role">{{ auth.user?.role_label }}</span>
                </div>

                <button type="button" class="profile__item" role="menuitem" @click="go('/account')">
                    <User :size="18" aria-hidden="true" />
                    Pengaturan Akun
                </button>

                <a class="profile__item" role="menuitem" href="/" target="_blank" rel="noopener noreferrer">
                    <Globe :size="18" aria-hidden="true" />
                    Lihat Website
                </a>

                <button type="button" class="profile__item profile__item--danger" role="menuitem" :disabled="leaving" @click="logout">
                    <LogOut :size="18" aria-hidden="true" />
                    {{ leaving ? 'Keluar...' : 'Keluar' }}
                </button>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.profile { position: relative; }

.profile__button {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    padding: 4px var(--space-3) 4px 4px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-full);
    transition: background var(--transition-fast), border-color var(--transition-fast);
}
.profile__button:hover { background: var(--color-primary-soft); border-color: var(--color-primary); }

.profile__name { max-width: 140px; overflow: hidden; font-size: var(--text-sm); font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.profile__chevron { color: var(--color-muted); }

.profile__panel {
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    z-index: var(--z-dropdown);
    display: grid;
    gap: 2px;
    width: 260px;
    padding: var(--space-2);
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg);
}

.profile__head {
    display: grid;
    gap: 2px;
    justify-items: start;
    padding: var(--space-3);
    margin-bottom: var(--space-1);
    border-bottom: 1px solid var(--color-border);
}
.profile__head small { color: var(--color-muted); overflow-wrap: anywhere; }
.profile__role {
    margin-top: var(--space-1);
    padding: 0.15rem 0.7rem;
    background: var(--color-accent-soft);
    border: 1px solid var(--color-accent);
    border-radius: var(--radius-full);
    font-size: var(--text-xs);
    font-weight: 600;
}

.profile__item {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    width: 100%;
    padding: 0.7rem var(--space-3);
    background: transparent;
    border: 0;
    border-radius: var(--radius-md);
    font-weight: 500;
    text-align: left;
    transition: background var(--transition-fast), color var(--transition-fast);
}
.profile__item:hover { background: var(--color-primary-soft); }
.profile__item--danger:hover { background: var(--color-danger-soft); color: var(--color-danger); }
.profile__item:disabled { opacity: 0.6; cursor: wait; }

.drop-enter-active,
.drop-leave-active { transition: opacity var(--transition-fast), transform var(--transition-fast); }
.drop-enter-from,
.drop-leave-to { opacity: 0; transform: translateY(-6px); }

@media (max-width: 640px) {
    .profile__name,
    .profile__chevron { display: none; }
    .profile__button { padding: 4px; }
}
</style>