<script setup>
const props = defineProps({
    /** [{ key: 'YYYY-MM-DD', count }] */
    points: { type: Array, default: () => [] },
    unit: { type: String, default: 'transaksi' },
});

function max() {
    return Math.max(1, ...props.points.map((point) => point.count));
}

function height(point) {
    return point.count > 0 ? Math.max((point.count / max()) * 100, 6) : 0;
}

function dayLabel(key) {
    return new Intl.DateTimeFormat('id-ID', { weekday: 'short', timeZone: 'Asia/Makassar' })
        .format(new Date(`${key}T12:00:00+08:00`));
}

function fullLabel(key) {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'full', timeZone: 'Asia/Makassar' })
        .format(new Date(`${key}T12:00:00+08:00`));
}
</script>

<template>
    <div class="bars" role="img" :aria-label="`Grafik jumlah ${unit} per hari`">
        <div
            v-for="point in points"
            :key="point.key"
            class="bars__col"
            :title="`${fullLabel(point.key)}: ${point.count} ${unit}`"
        >
            <span class="bars__value">{{ point.count }}</span>
            <div class="bars__track"><div class="bars__bar" :style="{ height: `${height(point)}%` }"></div></div>
            <span class="bars__label">{{ dayLabel(point.key) }}</span>
        </div>
    </div>
</template>

<style scoped>
.bars { display: grid; grid-template-columns: repeat(auto-fit, minmax(0, 1fr)); align-items: end; gap: var(--space-3); height: 220px; }

.bars__col { display: grid; grid-template-rows: auto 1fr auto; justify-items: center; gap: var(--space-2); height: 100%; }
.bars__value { font-size: var(--text-sm); font-weight: 700; font-variant-numeric: tabular-nums; }

.bars__track { display: flex; align-items: flex-end; width: 100%; max-width: 44px; height: 100%; background: var(--color-primary-soft); border-radius: var(--radius-sm); overflow: hidden; }
.bars__bar { width: 100%; background: linear-gradient(180deg, var(--color-primary-strong), var(--color-primary)); border-radius: var(--radius-sm); transition: height var(--transition-slow); }

.bars__label { color: var(--color-muted); font-size: var(--text-xs); text-transform: capitalize; }
</style>