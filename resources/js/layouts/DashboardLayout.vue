<script setup>
import { computed, onBeforeUnmount, onMounted, watch } from 'vue';
import { RouterView, useRoute } from 'vue-router';
import { Menu } from 'lucide-vue-next';
import UserAvatar from '@/components/common/UserAvatar.vue';
import AppBreadcrumb from '@/components/dashboard/AppBreadcrumb.vue';
import NotificationBell from '@/components/dashboard/NotificationBell.vue';
import ProfileMenu from '@/components/dashboard/ProfileMenu.vue';
import SidebarNav from '@/components/dashboard/SidebarNav.vue';
import { NAV_ITEMS } from '@/components/dashboard/navigation';
import BrandLogo from '@/components/public/BrandLogo.vue';
import { useAuthStore } from '@/stores/auth';
import { useSiteStore } from '@/stores/site';
import { useUiStore } from '@/stores/ui';

const route = useRoute();
const auth = useAuthStore();
const site = useSiteStore();
const ui = useUiStore();

// Menu mengikuti role (kemampuan dari backend). Pembatasan sebenarnya ada di backend.
const items = computed(() => NAV_ITEMS.filter((item) => !item.ability || auth.can(item.ability)));

const crumbs = computed(() => {
    const list = [];

    if (route.path !== '/dashboard') list.push({ label: 'Dashboard', to: '/dashboard' });

    list.push(...(route.meta.breadcrumb ?? []));

    if (route.meta.title) list.push({ label: route.meta.title });

    return list;
});

function lockScroll(locked) {
    document.body.style.overflow = locked && window.innerWidth < 1024 ? 'hidden' : '';
}

function onKeydown(event) {
    if (event.key === 'Escape') ui.closeSidebar();
}

function onResize() {
    if (window.innerWidth >= 1024) ui.closeSidebar();
}

function focusContent() {
    document.getElementById('konten-dashboard')?.focus();
}

watch(() => route.fullPath, () => ui.closeSidebar());
watch(() => ui.sidebarOpen, lockScroll);

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    window.addEventListener('resize', onResize);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('resize', onResize);
    lockScroll(false);
    ui.closeSidebar();
});
</script>

<template>
    <div class="dash">
        <a href="#konten-dashboard" class="skip" @click.prevent="focusContent">Lewati ke konten utama</a>

        <div v-if="ui.sidebarOpen" class="dash__overlay" aria-hidden="true" @click="ui.closeSidebar()"></div>

        <aside id="sidebar" :class="['dash__sidebar', { 'dash__sidebar--open': ui.sidebarOpen }]" aria-label="Sidebar">
            <div class="dash__brand">
                <BrandLogo :url="site.logoUrl" :name="site.name" :size="40" />
                <strong class="dash__brand-name">{{ site.name }}</strong>
            </div>

            <SidebarNav :items="items" @navigate="ui.closeSidebar()" />

            <div class="dash__user">
                <UserAvatar :name="auth.user?.name ?? ''" :url="auth.user?.avatar_url" :size="40" />
                <div class="dash__user-text">
                    <strong>{{ auth.user?.name }}</strong>
                    <small>{{ auth.user?.role_label }}</small>
                </div>
            </div>
        </aside>

        <div class="dash__main">
            <header class="dash__topbar">
                <button
                    type="button"
                    class="dash__menu"
                    aria-controls="sidebar"
                    :aria-expanded="ui.sidebarOpen"
                    aria-label="Buka menu"
                    @click="ui.toggleSidebar()"
                >
                    <Menu :size="22" />
                </button>

                <AppBreadcrumb :items="crumbs" />

                <div class="dash__tools">
                    <NotificationBell />
                    <ProfileMenu />
                </div>
            </header>

            <div id="konten-dashboard" class="dash__content dash-embed" tabindex="-1">
                <RouterView v-slot="{ Component, route: current }">
                    <Transition name="page" mode="out-in">
                        <component :is="Component" :key="current.path" />
                    </Transition>
                </RouterView>
            </div>
        </div>
    </div>
</template>

<style scoped>
.dash { min-height: 100vh; background: var(--color-background); }

.skip { position: absolute; left: -9999px; top: var(--space-3); }
.skip:focus {
    left: var(--space-4);
    z-index: var(--z-toast);
    padding: 0.7rem 1.1rem;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    font-weight: 600;
}

.dash__sidebar {
    position: fixed;
    inset: 0 auto 0 0;
    z-index: var(--z-sidebar);
    display: flex;
    flex-direction: column;
    gap: var(--space-5);
    width: var(--sidebar-width);
    padding: var(--space-5) var(--space-4);
    overflow-y: auto;
    background: var(--color-surface);
    border-right: 1px solid var(--color-border);
    transition: transform var(--transition-base);
}

.dash__brand { display: flex; align-items: center; gap: var(--space-3); padding: 0 var(--space-2); font-size: var(--text-lg); }
.dash__brand-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.dash__user {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    margin-top: auto;
    padding: var(--space-3);
    background: var(--color-primary-soft);
    border-radius: var(--radius-lg);
}
.dash__user-text { display: grid; min-width: 0; }
.dash__user-text strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dash__user-text small { color: var(--color-muted); }

.dash__overlay {
    position: fixed;
    inset: 0;
    z-index: 55;
    background: color-mix(in srgb, #0f1f29 45%, transparent);
    backdrop-filter: blur(2px);
}

.dash__main { margin-left: var(--sidebar-width); min-width: 0; }

.dash__topbar {
    position: sticky;
    top: 0;
    z-index: var(--z-sticky);
    display: flex;
    align-items: center;
    gap: var(--space-4);
    min-height: var(--topbar-height);
    padding-inline: var(--space-6);
    background: color-mix(in srgb, var(--color-background) 85%, transparent);
    border-bottom: 1px solid var(--color-border);
    -webkit-backdrop-filter: blur(12px);
    backdrop-filter: blur(12px);
}

.dash__menu {
    display: none;
    place-items: center;
    width: 42px;
    height: 42px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
}
.dash__menu:hover { background: var(--color-primary-soft); }

.dash__tools { display: flex; align-items: center; gap: var(--space-3); margin-left: auto; }

.dash__content { padding: var(--space-6); outline: none; }

@media (max-width: 1023px) {
    .dash__sidebar { transform: translateX(-100%); box-shadow: var(--shadow-lg); }
    .dash__sidebar--open { transform: translateX(0); }
    .dash__main { margin-left: 0; }
    .dash__menu { display: grid; }
    .dash__topbar { padding-inline: var(--space-4); }
    .dash__content { padding: var(--space-5) var(--space-4); }
}
</style>