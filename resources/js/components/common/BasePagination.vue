<script setup>
import { computed } from 'vue';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

const props = defineProps({
    /** Objek meta dari pagination Laravel: current_page, last_page, from, to, total. */
    meta: { type: Object, default: null },
});

const emit = defineEmits(['change']);

const current = computed(() => props.meta?.current_page ?? 1);
const last = computed(() => props.meta?.last_page ?? 1);

const pages = computed(() => {
    const total = last.value;

    if (total <= 7) return Array.from({ length: total }, (_, index) => index + 1);

    const wanted = new Set([1, total, current.value - 1, current.value, current.value + 1]);
    const list = [...wanted].filter((n) => n >= 1 && n <= total).sort((a, b) => a - b);

    const result = [];
    list.forEach((n, index) => {
        if (index > 0 && n - list[index - 1] > 1) result.push('...');
        result.push(n);
    });

    return result;
});

function go(page) {
    if (page >= 1 && page <= last.value && page !== current.value) emit('change', page);
}
</script>

<template>
    <nav v-if="meta && meta.total > 0" class="pager" aria-label="Navigasi halaman">
        <p class="pager__info">
            Menampilkan {{ meta.from }}-{{ meta.to }} dari {{ meta.total }}
        </p>

        <div v-if="last > 1" class="pager__buttons">
            <button type="button" class="pager__btn" :disabled="current <= 1" aria-label="Halaman sebelumnya" @click="go(current - 1)">
                <ChevronLeft :size="16" />
            </button>

            <template v-for="(page, index) in pages" :key="`${page}-${index}`">
                <span v-if="page === '...'" class="pager__gap">&hellip;</span>
                <button
                    v-else
                    type="button"
                    :class="['pager__btn', { 'pager__btn--active': page === current }]"
                    :aria-current="page === current ? 'page' : undefined"
                    @click="go(page)"
                >
                    {{ page }}
                </button>
            </template>

            <button type="button" class="pager__btn" :disabled="current >= last" aria-label="Halaman berikutnya" @click="go(current + 1)">
                <ChevronRight :size="16" />
            </button>
        </div>
    </nav>
</template>

<style scoped>
.pager {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-3);
    margin-top: var(--space-4);
}

.pager__info { margin: 0; font-size: var(--text-sm); color: var(--color-muted); }
.pager__buttons { display: flex; align-items: center; gap: var(--space-1); }
.pager__gap { padding-inline: var(--space-2); color: var(--color-muted); }

.pager__btn {
    display: grid;
    place-items: center;
    min-width: 36px;
    height: 36px;
    padding-inline: var(--space-2);
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    font-size: var(--text-sm);
    font-weight: 600;
    transition: border-color var(--transition-fast), background var(--transition-fast);
}

.pager__btn:hover:not(:disabled) { border-color: var(--color-primary); background: var(--color-primary-soft); }
.pager__btn:disabled { opacity: 0.45; cursor: not-allowed; }

.pager__btn--active {
    background: var(--gradient-brand);
    border-color: transparent;
}
</style>