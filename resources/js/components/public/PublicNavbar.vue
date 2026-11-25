<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Menu, Search, X } from 'lucide-vue-next';
import BaseButton from '@/components/common/BaseButton.vue';
import BrandLogo from '@/components/public/BrandLogo.vue';
import { scrollToSection } from '@/composables/useSectionScroll';

const props = defineProps({
    name: { type: String, required: true },
    logoUrl: { type: String, default: null },
    links: { type: Array, default: () => [] },
});

const scrolled = ref(false);
const menuOpen = ref(false);
const active = ref('beranda');

let observer = null;

function onScroll() {
    scrolled.value = window.scrollY > 8;
}

function onKeydown(event) {
    if (event.key === 'Escape') menuOpen.value = false;
}

function onResize() {
    if (window.innerWidth > 900) menuOpen.value = false;
}

function go(id) {
    menuOpen.value = false;
    scrollToSection(id);
}

/** Penanda bagian aktif: bagian yang melewati garis tengah layar. */
function observe() {
    observer?.disconnect();
    observer = null;

    if (typeof IntersectionObserver === 'undefined') return;

    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) active.value = entry.target.id;
            }
        },
        { rootMargin: '-45% 0px -50% 0px', threshold: 0 },
    );

    ['cek-status', ...props.links.map((link) => link.id)].forEach((id) => {
        const element = document.getElementById(id);
        if (element) observer.observe(element);
    });
}

onMounted(() => {
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('keydown', onKeydown);
    window.addEventListener('resize', onResize);
    nextTick(observe);
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('resize', onResize);
    observer?.disconnect();
});

watch(
    () => props.links.map((link) => link.id).join('|'),
    () => nextTick(observe),
);
</script>

<template>
    <header :class="['nav', { 'nav--scrolled': scrolled }]">
        <div class="container nav__inner">
            <a class="nav__brand" href="#beranda" @click.prevent="go('beranda')">
                <BrandLogo :url="logoUrl" :name="name" :size="38" />
                <strong class="nav__name">{{ name }}</strong>
            </a>

            <nav class="nav__links" aria-label="Navigasi utama">
                <a
                    v-for="link in links"
                    :key="link.id"
                    :href="`#${link.id}`"
                    :class="['nav__link', { 'nav__link--active': active === link.id }]"
                    :aria-current="active === link.id ? 'true' : undefined"
                    @click.prevent="go(link.id)"
                >
                    {{ link.label }}
                </a>
            </nav>

            <div class="nav__actions">
                <BaseButton size="sm" class="nav__cta" @click="go('cek-status')">
                    <Search :size="16" />
                    Cek Status Cucian
                </BaseButton>

                <button
                    type="button"
                    class="nav__toggle"
                    :aria-expanded="menuOpen"
                    aria-controls="menu-seluler"
                    :aria-label="menuOpen ? 'Tutup menu' : 'Buka menu'"
                    @click="menuOpen = !menuOpen"
                >
                    <component :is="menuOpen ? X : Menu" :size="22" />
                </button>
            </div>
        </div>

        <Transition name="drop">
            <nav v-if="menuOpen" id="menu-seluler" class="nav__panel" aria-label="Menu seluler">
                <a
                    v-for="link in links"
                    :key="link.id"
                    :href="`#${link.id}`"
                    class="nav__panel-link"
                    @click.prevent="go(link.id)"
                >
                    {{ link.label }}
                </a>
                <BaseButton block @click="go('cek-status')">
                    <Search :size="18" />
                    Cek Status Cucian
                </BaseButton>
            </nav>
        </Transition>
    </header>
</template>

<style scoped>
.nav {
    position: sticky;
    top: 0;
    z-index: var(--z-sticky);
    background: color-mix(in srgb, var(--color-background) 78%, transparent);
    border-bottom: 1px solid transparent;
    -webkit-backdrop-filter: blur(14px);
    backdrop-filter: blur(14px);
    transition: background var(--transition-base), border-color var(--transition-base), box-shadow var(--transition-base);
}

.nav--scrolled {
    background: color-mix(in srgb, var(--color-surface) 90%, transparent);
    border-bottom-color: var(--color-border);
    box-shadow: var(--shadow-sm);
}

.nav__inner { display: flex; align-items: center; gap: var(--space-5); min-height: var(--topbar-height); }

.nav__brand { display: flex; align-items: center; gap: var(--space-3); min-width: 0; margin-right: auto; font-size: var(--text-lg); }
.nav__name { overflow: hidden; max-width: 240px; text-overflow: ellipsis; white-space: nowrap; }

.nav__links { display: flex; gap: var(--space-1); }

.nav__link {
    padding: 0.5rem 0.95rem;
    border-radius: var(--radius-full);
    color: var(--color-muted);
    font-size: var(--text-sm);
    font-weight: 600;
    transition: background var(--transition-fast), color var(--transition-fast);
}
.nav__link:hover,
.nav__link--active { background: var(--color-primary-soft); color: var(--color-text); }

.nav__actions { display: flex; align-items: center; gap: var(--space-2); }

.nav__toggle {
    display: none;
    place-items: center;
    width: 42px;
    height: 42px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    transition: background var(--transition-fast);
}
.nav__toggle:hover { background: var(--color-primary-soft); }

.nav__panel {
    position: absolute;
    top: 100%;
    right: 0;
    left: 0;
    display: grid;
    gap: var(--space-1);
    padding: var(--space-4) var(--space-5) var(--space-5);
    background: var(--color-surface);
    border-bottom: 1px solid var(--color-border);
    box-shadow: var(--shadow-lg);
}

.nav__panel-link {
    padding: 0.85rem 1rem;
    border-radius: var(--radius-md);
    font-weight: 600;
    transition: background var(--transition-fast);
}
.nav__panel-link:hover { background: var(--color-primary-soft); }
.nav__panel :deep(.btn) { margin-top: var(--space-2); }

.drop-enter-active,
.drop-leave-active { transition: opacity var(--transition-base), transform var(--transition-base); }
.drop-enter-from,
.drop-leave-to { opacity: 0; transform: translateY(-8px); }

@media (max-width: 900px) {
    .nav__links { display: none; }
    .nav__toggle { display: grid; }
}

@media (max-width: 560px) {
    .nav__cta { display: none; }
    .nav__name { max-width: 170px; }
}
</style>