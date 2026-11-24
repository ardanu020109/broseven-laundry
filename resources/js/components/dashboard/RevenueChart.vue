<script setup>
import { computed, onBeforeUnmount, onMounted, ref, useId } from 'vue';
import { formatCompact, formatCurrency } from '@/composables/useFormat';

/**
 * Grafik batang pemasukan (SVG manual, tanpa library).
 * points: [{ key, total, count }]
 * granularity: hour (key "09"), day (key "2026-11-24"), month (key "2026-11")
 */
const props = defineProps({
    points: { type: Array, default: () => [] },
    granularity: { type: String, default: 'day' },
});

const TZ = 'Asia/Makassar';
const HEIGHT = 280;
const MARGIN = { top: 14, right: 12, bottom: 34, left: 58 };

const gradientId = `revenue-${useId()}`;
const wrap = ref(null);
const width = ref(640);
const activeIndex = ref(null);

let observer = null;

onMounted(() => {
    if (!wrap.value) return;

    width.value = Math.max(wrap.value.clientWidth, 280);

    if (typeof ResizeObserver !== 'undefined') {
        observer = new ResizeObserver((entries) => {
            const next = entries[0]?.contentRect?.width;
            if (next) width.value = Math.max(Math.floor(next), 280);
        });
        observer.observe(wrap.value);
    }
});

onBeforeUnmount(() => observer?.disconnect());

function niceMax(value) {
    if (value <= 0) return 1;

    const exponent = 10 ** Math.floor(Math.log10(value));
    const fraction = value / exponent;
    const steps = [1, 2, 3, 4, 5, 6, 8, 10];

    return (steps.find((step) => fraction <= step) ?? 10) * exponent;
}

const innerWidth = computed(() => width.value - MARGIN.left - MARGIN.right);
const innerHeight = HEIGHT - MARGIN.top - MARGIN.bottom;

const maxTotal = computed(() => props.points.reduce((max, p) => Math.max(max, Number(p.total) || 0), 0));
const yMax = computed(() => niceMax(maxTotal.value));

const ticks = computed(() =>
    Array.from({ length: 6 }, (_, index) => {
        const value = (yMax.value / 5) * index;
        return { value, y: MARGIN.top + innerHeight - (value / yMax.value) * innerHeight };
    }),
);

const slotWidth = computed(() => innerWidth.value / Math.max(props.points.length, 1));
const barWidth = computed(() => Math.min(Math.max(slotWidth.value * 0.66, 3), 40));

const bars = computed(() =>
    props.points.map((point, index) => {
        const total = Number(point.total) || 0;
        const height = total > 0 ? Math.max((total / yMax.value) * innerHeight, 3) : 0;
        const slotX = MARGIN.left + slotWidth.value * index;
        const x = slotX + (slotWidth.value - barWidth.value) / 2;

        return {
            index,
            key: point.key,
            total,
            count: point.count,
            x,
            slotX,
            y: MARGIN.top + innerHeight - height,
            height,
            center: x + barWidth.value / 2,
        };
    }),
);

const labelStep = computed(() => {
    const fit = Math.max(1, Math.floor(innerWidth.value / 62));
    return Math.max(1, Math.ceil(props.points.length / fit));
});

const labelBars = computed(() => bars.value.filter((bar) => bar.index % labelStep.value === 0));
const active = computed(() => (activeIndex.value === null ? null : (bars.value[activeIndex.value] ?? null)));
const tooltipLeft = computed(() => (active.value ? Math.min(Math.max(active.value.center, 76), width.value - 76) : 0));

function labelOf(key, long = false) {
    if (props.granularity === 'hour') {
        return long ? `Pukul ${key}.00 - ${key}.59` : `${key}.00`;
    }

    if (props.granularity === 'month') {
        const date = new Date(`${key}-15T12:00:00+08:00`);
        return new Intl.DateTimeFormat(
            'id-ID',
            long ? { month: 'long', year: 'numeric', timeZone: TZ } : { month: 'short', year: '2-digit', timeZone: TZ },
        ).format(date);
    }

    const date = new Date(`${key}T12:00:00+08:00`);
    return new Intl.DateTimeFormat(
        'id-ID',
        long
            ? { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: TZ }
            : { day: 'numeric', month: 'short', timeZone: TZ },
    ).format(date);
}

function toggle(index) {
    activeIndex.value = activeIndex.value === index ? null : index;
}
</script>

<template>
    <div ref="wrap" class="chart" @mouseleave="activeIndex = null">
        <svg
            class="chart__svg"
            :width="width"
            :height="HEIGHT"
            :viewBox="`0 0 ${width} ${HEIGHT}`"
            role="img"
            aria-label="Grafik batang pemasukan"
        >
            <defs>
                <linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" class="chart__stop-top" />
                    <stop offset="100%" class="chart__stop-bottom" />
                </linearGradient>
            </defs>

            <g class="chart__grid">
                <g v-for="tick in ticks" :key="tick.value">
                    <line :x1="MARGIN.left" :x2="width - MARGIN.right" :y1="tick.y" :y2="tick.y" />
                    <text :x="MARGIN.left - 8" :y="tick.y + 4" text-anchor="end">{{ formatCompact(tick.value) }}</text>
                </g>
            </g>

            <g>
                <rect
                    v-for="bar in bars"
                    :key="bar.key"
                    :class="['chart__bar', { 'is-active': activeIndex === bar.index }]"
                    :x="bar.x"
                    :y="bar.y"
                    :width="barWidth"
                    :height="bar.height"
                    rx="4"
                    :fill="`url(#${gradientId})`"
                />
            </g>

            <g class="chart__labels">
                <text
                    v-for="bar in labelBars"
                    :key="bar.key"
                    :x="bar.center"
                    :y="HEIGHT - 12"
                    text-anchor="middle"
                >
                    {{ labelOf(bar.key) }}
                </text>
            </g>

            <g>
                <rect
                    v-for="bar in bars"
                    :key="`hit-${bar.key}`"
                    class="chart__hit"
                    :x="bar.slotX"
                    :y="MARGIN.top"
                    :width="slotWidth"
                    :height="innerHeight"
                    tabindex="0"
                    :aria-label="`${labelOf(bar.key, true)}: ${formatCurrency(bar.total)}, ${bar.count} transaksi`"
                    @mouseenter="activeIndex = bar.index"
                    @focus="activeIndex = bar.index"
                    @blur="activeIndex = null"
                    @click="toggle(bar.index)"
                />
            </g>
        </svg>

        <div v-if="active" class="chart__tooltip" :style="{ left: `${tooltipLeft}px` }" role="status">
            <strong>{{ labelOf(active.key, true) }}</strong>
            <span>{{ formatCurrency(active.total) }}</span>
            <small>{{ active.count }} transaksi</small>
        </div>
    </div>
</template>

<style scoped>
.chart { position: relative; width: 100%; }
.chart__svg { display: block; max-width: 100%; }

.chart__stop-top { stop-color: var(--color-primary-strong); }
.chart__stop-bottom { stop-color: var(--color-primary); }

.chart__grid line { stroke: var(--color-border); stroke-width: 1; }
.chart__grid text,
.chart__labels text { fill: var(--color-muted); font-size: 11px; }

.chart__bar { transition: opacity var(--transition-fast), filter var(--transition-fast); }
.chart__bar.is-active { filter: brightness(0.92) saturate(1.2); }

.chart__hit { fill: transparent; cursor: pointer; outline: none; }
.chart__hit:hover,
.chart__hit:focus-visible { fill: color-mix(in srgb, var(--color-primary) 14%, transparent); }

.chart__tooltip {
    position: absolute;
    top: 0;
    display: grid;
    gap: 2px;
    padding: var(--space-2) var(--space-3);
    background: var(--color-text);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    color: #fff;
    font-size: var(--text-sm);
    text-align: center;
    white-space: nowrap;
    transform: translateX(-50%);
    pointer-events: none;
}
.chart__tooltip small { opacity: 0.8; }
</style>