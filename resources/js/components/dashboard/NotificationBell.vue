<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { Bell, CheckCircle2, Hourglass, PackageCheck } from 'lucide-vue-next';
import { useDismiss } from '@/composables/useDismiss';
import { fetchNotifications } from '@/services/dashboardService';

const POLL_MS = 60000;
const ICONS = { ready: PackageCheck, overdue: Hourglass };

const router = useRouter();
const root = ref(null);
const open = ref(false);
const state = ref({ count: 0, items: [] });
const failed = ref(false);

let timer = null;

useDismiss(root, open, () => (open.value = false));

async function load() {
    if (document.visibilityState !== 'visible') return;

    try {
        state.value = await fetchNotifications();
        failed.value = false;
    } catch {
        failed.value = true;
    }
}

function onVisible() {
    if (document.visibilityState === 'visible') load();
}

onMounted(() => {
    load();
    timer = setInterval(load, POLL_MS);
    document.addEventListener('visibilitychange', onVisible);
});

onBeforeUnmount(() => {
    clearInterval(timer);
    document.removeEventListener('visibilitychange', onVisible);
});

const badge = computed(() => (state.value.count > 99 ? '99+' : String(state.value.count)));

function toggle() {
    open.value = !open.value;
    if (open.value) load();
}

function go(item) {
    open.value = false;
    router.push(item.link);
}
</script>

<template>
    <div ref="root" class="bell">
        <button
            type="button"
            class="bell__button"
            aria-haspopup="true"
            :aria-expanded="open"
            :aria-label="state.count ? `Notifikasi, ${state.count} perlu perhatian` : 'Notifikasi'"
            @click="toggle"
        >
            <Bell :size="20" aria-hidden="true" />
            <span v-if="state.count" class="bell__badge" aria-hidden="true">{{ badge }}</span>
        </button>

        <Transition name="drop">
            <div v-if="open" class="bell__panel" role="region" aria-label="Notifikasi">
                <h3 class="bell__title">Notifikasi</h3>

                <p v-if="failed && !state.items.length" class="bell__empty">Notifikasi tidak dapat dimuat.</p>

                <div v-else-if="!state.items.length" class="bell__empty">
                    <CheckCircle2 :size="28" aria-hidden="true" />
                    <span>Semua beres. Tidak ada yang perlu diperhatikan.</span>
                </div>

                <ul v-else class="bell__list">
                    <li v-for="item in state.items" :key="item.key">
                        <button type="button" class="bell__item" @click="go(item)">
                            <span :class="['bell__icon', `bell__icon--${item.tone}`]">
                                <component :is="ICONS[item.key] ?? Bell" :size="18" aria-hidden="true" />
                            </span>
                            <span class="bell__text">
                                <strong>{{ item.title }}</strong>
                                <small>{{ item.description }}</small>
                            </span>
                        </button>
                    </li>
                </ul>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.bell { position: relative; }

.bell__button {
    position: relative;
    display: grid;
    place-items: center;
    width: 42px;
    height: 42px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 50%;
    transition: background var(--transition-fast), border-color var(--transition-fast);
}
.bell__button:hover { background: var(--color-primary-soft); border-color: var(--color-primary); }

.bell__badge {
    position: absolute;
    top: -4px;
    right: -4px;
    min-width: 20px;
    padding: 0 5px;
    background: var(--color-danger);
    border: 2px solid var(--color-surface);
    border-radius: var(--radius-full);
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    line-height: 16px;
    text-align: center;
}

.bell__panel {
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    z-index: var(--z-dropdown);
    width: min(360px, calc(100vw - 2 * var(--space-4)));
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg);
}

.bell__title { margin: 0; padding: var(--space-4) var(--space-4) var(--space-2); font-size: var(--text-base); }

.bell__empty {
    display: grid;
    justify-items: center;
    gap: var(--space-2);
    margin: 0;
    padding: var(--space-6) var(--space-4);
    color: var(--color-muted);
    font-size: var(--text-sm);
    text-align: center;
}
.bell__empty svg { color: var(--color-success); }

.bell__list { display: grid; padding: var(--space-2); }

.bell__item {
    display: flex;
    align-items: flex-start;
    gap: var(--space-3);
    width: 100%;
    padding: var(--space-3);
    background: transparent;
    border: 0;
    border-radius: var(--radius-md);
    text-align: left;
    transition: background var(--transition-fast);
}
.bell__item:hover { background: var(--color-primary-soft); }

.bell__icon { display: grid; place-items: center; flex: none; width: 36px; height: 36px; border-radius: var(--radius-sm); }
.bell__icon--warning { background: var(--color-warning-soft); color: color-mix(in srgb, var(--color-warning) 55%, #5a3b00); }
.bell__icon--danger { background: var(--color-danger-soft); color: var(--color-danger); }

.bell__text { display: grid; gap: 2px; }
.bell__text small { color: var(--color-muted); }

.drop-enter-active,
.drop-leave-active { transition: opacity var(--transition-fast), transform var(--transition-fast); }
.drop-enter-from,
.drop-leave-to { opacity: 0; transform: translateY(-6px); }
</style>