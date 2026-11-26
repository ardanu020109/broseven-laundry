<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch, nextTick } from 'vue';
import { Download, Info, Package } from 'lucide-vue-next';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseCard from '@/components/common/BaseCard.vue';
import BaseEmptyState from '@/components/common/BaseEmptyState.vue';
import BaseInput from '@/components/common/BaseInput.vue';
import BaseLoading from '@/components/common/BaseLoading.vue';
import BaseSelect from '@/components/common/BaseSelect.vue';
import BaseSwitch from '@/components/common/BaseSwitch.vue';
import { ensureFonts, loadImage, renderToCanvas } from '@/components/banner/bannerRenderer';
import { setPngDpi } from '@/components/banner/pngMeta';
import { formatNumber } from '@/composables/useFormat';
import { useToast } from '@/composables/useToast';
import { fetchBannerPresets, logBannerExport } from '@/services/bannerService';
import { extractErrorMessage } from '@/services/http';
import { usePublicSiteStore } from '@/stores/publicSite';
import { useSiteStore } from '@/stores/site';

const toast = useToast();
const store = usePublicSiteStore();
const site = useSiteStore();

const isMobile = /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);

const presets = ref(null);
const loading = ref(true);
const error = ref('');
const busy = ref(false);
const canvas = ref(null);
const assets = reactive({ logo: null });

const form = reactive({
    preset: 'spanduk-300x100',
    customW: 200,
    customH: 100,
    rotate: false,
    quality: 'standar',
    headline: 'Daftar Paket & Harga',
    showFeatures: true,
    showEstimate: true,
    showContact: true,
    selected: [],
});

const data = computed(() => store.data);
const allPresets = computed(() => presets.value?.groups.flatMap((group) => group.presets) ?? []);
const chosen = computed(() => (data.value?.packages ?? []).filter((pkg) => form.selected.includes(pkg.id)));
const qualityOptions = computed(() => (presets.value?.qualities ?? []).map((q) => ({ value: q.key, label: q.label })));

const size = computed(() => {
    const limits = presets.value?.limits;
    let w;
    let h;

    if (form.preset === 'custom') {
        w = Number(form.customW);
        h = Number(form.customH);
    } else {
        const preset = allPresets.value.find((item) => item.key === form.preset);
        w = preset?.width_cm;
        h = preset?.height_cm;
    }

    if (form.rotate) [w, h] = [h, w];

    const within = (v) => Number.isFinite(v) && v >= (limits?.min_cm ?? 10) && v <= (limits?.max_cm ?? 600);

    return { w, h, valid: within(w) && within(h) };
});

const pixels = computed(() => {
    if (!presets.value || !size.value.valid) return null;

    const { limits, qualities } = presets.value;
    const quality = qualities.find((q) => q.key === form.quality) ?? qualities[0];
    const longCm = Math.max(size.value.w, size.value.h);
    const longPx = Math.min(quality.max_side_px, Math.round((longCm / 2.54) * limits.max_dpi));
    const scale = longPx / longCm;

    let w = Math.max(100, Math.round(size.value.w * scale));
    let h = Math.max(100, Math.round(size.value.h * scale));
    const maxArea = isMobile ? limits.max_area_px_mobile : limits.max_area_px;
    let reduced = false;

    if (w * h > maxArea) {
        const factor = Math.sqrt(maxArea / (w * h));
        w = Math.round(w * factor);
        h = Math.round(h * factor);
        reduced = true;
    }

    return { w, h, reduced, dpi: Math.round(w / (size.value.w / 2.54)) };
});

const model = computed(() => ({
    name: data.value?.business.name ?? '',
    tagline: data.value?.business.tagline ?? '',
    logo: assets.logo,
    colors: site.colors,
    headline: form.headline.trim(),
    packages: chosen.value.map((pkg) => ({
        name: pkg.name,
        price: pkg.price,
        priceLabel: pkg.price_label,
        estimateLabel: pkg.estimated_label,
        features: pkg.features ?? [],
    })),
    contact: {
        whatsapp: data.value?.contact.whatsapp ?? '',
        address: data.value?.contact.address ?? '',
        hours: data.value?.contact.operating_hours ?? '',
    },
    options: { showFeatures: form.showFeatures, showEstimate: form.showEstimate, showContact: form.showContact },
}));

function sampleText() {
    const d = data.value;

    return [
        d.business.name, d.business.tagline ?? '', form.headline,
        ...d.packages.flatMap((p) => [p.name, p.price_label, p.estimated_label, ...(p.features ?? [])]),
        d.contact.address ?? '', d.contact.operating_hours ?? '', 'Estimasi WhatsApp Daftar Paket Harga',
    ];
}

async function load() {
    loading.value = true;
    error.value = '';

    try {
        const [meta] = await Promise.all([fetchBannerPresets(), store.load({ force: true })]);

        if (!store.data) throw new Error('Konten website tidak dapat dimuat.');

        presets.value = meta;
        form.selected = store.data.packages.map((pkg) => pkg.id);
        assets.logo = await loadImage(store.data.business.logo_url);
        await ensureFonts(sampleText());
    } catch (err) {
        error.value = extractErrorMessage(err, 'Data banner tidak dapat dimuat.');
    } finally {
        loading.value = false;
    }

    await nextTick();
    schedule();
}

let timer = null;

function renderPreview() {
    if (!canvas.value || !size.value.valid || !chosen.value.length) return;

    const long = 1100;
    const ratio = size.value.w / size.value.h;
    const w = ratio >= 1 ? long : Math.max(200, Math.round(long * ratio));
    const h = ratio >= 1 ? Math.max(100, Math.round(long / ratio)) : long;

    renderToCanvas(canvas.value, w, h, model.value);
}

function schedule() {
    clearTimeout(timer);
    timer = setTimeout(renderPreview, 120);
}

watch([model, size], schedule);
onMounted(load);
onBeforeUnmount(() => clearTimeout(timer));

function toggle(id) {
    form.selected = form.selected.includes(id) ? form.selected.filter((item) => item !== id) : [...form.selected, id];
}

function slug(text) {
    return String(text).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'banner';
}

async function download() {
    if (!pixels.value || !chosen.value.length) return;

    busy.value = true;

    const out = document.createElement('canvas');

    try {
        await ensureFonts(sampleText());
        renderToCanvas(out, pixels.value.w, pixels.value.h, model.value);

        const blob = await new Promise((resolve) => out.toBlob(resolve, 'image/png'));

        if (!blob) throw new Error('canvas');

        // Ukuran fisik tertanam (piksel per meter) agar percetakan membaca ukuran sebenarnya.
        const final = await setPngDpi(blob, Math.round(pixels.value.w / (size.value.w / 100)));
        const url = URL.createObjectURL(final);
        const link = document.createElement('a');

        link.href = url;
        link.download = `banner-${slug(model.value.name)}-${size.value.w}x${size.value.h}cm.png`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 2000);

        toast.success('Banner PNG berhasil dibuat.');

        logBannerExport({
            preset: form.preset,
            width_cm: size.value.w,
            height_cm: size.value.h,
            quality: form.quality,
            package_count: chosen.value.length,
        }).catch(() => {});
    } catch {
        toast.error('Banner tidak dapat dibuat. Ukuran piksel mungkin terlalu besar untuk browser ini; pilih kualitas yang lebih rendah.');
    } finally {
        out.width = 0;
        out.height = 0;
        busy.value = false;
    }
}
</script>

<template>
    <section class="bn">
        <BaseLoading v-if="loading" label="Menyiapkan banner..." />

        <div v-else-if="error" class="bn__alert" role="alert">
            {{ error }}
            <BaseButton size="sm" variant="secondary" @click="load">Coba lagi</BaseButton>
        </div>

        <BaseEmptyState
            v-else-if="!data?.packages.length"
            title="Belum ada paket aktif"
            description="Tambahkan dan aktifkan paket pada tab Paket Laundry, lalu kembali ke sini."
        >
            <template #icon><Package :size="32" /></template>
        </BaseEmptyState>

        <div v-else class="bn__grid">
            <div class="bn__controls">
                <BaseCard>
                    <h3 class="bn__title">Ukuran Banner</h3>

                    <div v-for="group in presets.groups" :key="group.key" class="bn__group">
                        <h4>{{ group.label }}</h4>
                        <div class="bn__chips" role="radiogroup" :aria-label="group.label">
                            <button
                                v-for="item in group.presets"
                                :key="item.key"
                                type="button"
                                role="radio"
                                :aria-checked="form.preset === item.key"
                                :class="['chip', { 'chip--on': form.preset === item.key }]"
                                @click="form.preset = item.key"
                            >
                                <strong>{{ item.label }}</strong>
                                <small>{{ item.note }}</small>
                            </button>
                        </div>
                    </div>

                    <div class="bn__group">
                        <h4>Lainnya</h4>
                        <div class="bn__chips" role="radiogroup" aria-label="Ukuran khusus">
                            <button
                                type="button"
                                role="radio"
                                :aria-checked="form.preset === 'custom'"
                                :class="['chip', { 'chip--on': form.preset === 'custom' }]"
                                @click="form.preset = 'custom'"
                            >
                                <strong>Ukuran khusus</strong>
                                <small>Tentukan lebar dan tinggi sendiri ({{ presets.limits.min_cm }} - {{ presets.limits.max_cm }} cm)</small>
                            </button>
                        </div>

                        <div v-if="form.preset === 'custom'" class="bn__custom">
                            <BaseInput v-model="form.customW" type="number" label="Lebar (cm)" :min="presets.limits.min_cm" :max="presets.limits.max_cm" />
                            <BaseInput v-model="form.customH" type="number" label="Tinggi (cm)" :min="presets.limits.min_cm" :max="presets.limits.max_cm" />
                        </div>
                    </div>

                    <BaseSwitch v-model="form.rotate" label="Tukar lebar dan tinggi" hint="Ubah banner horizontal menjadi vertikal, atau sebaliknya." />
                    <BaseSelect v-model="form.quality" label="Kualitas gambar" :options="qualityOptions" class="bn__quality" />
                </BaseCard>

                <BaseCard>
                    <h3 class="bn__title">Isi Banner</h3>

                    <BaseInput v-model="form.headline" label="Judul daftar harga" maxlength="60" hint="Kosongkan untuk menyembunyikan." />

                    <div class="bn__switches">
                        <BaseSwitch v-model="form.showFeatures" label="Tampilkan fasilitas" />
                        <BaseSwitch v-model="form.showEstimate" label="Tampilkan estimasi pengerjaan" />
                        <BaseSwitch v-model="form.showContact" label="Tampilkan kontak (WhatsApp, jam, alamat)" />
                    </div>

                    <h4 class="bn__sub">Paket yang ditampilkan</h4>
                    <ul class="bn__packages">
                        <li v-for="pkg in data.packages" :key="pkg.id">
                            <label>
                                <input type="checkbox" :checked="form.selected.includes(pkg.id)" @change="toggle(pkg.id)" />
                                <span>{{ pkg.name }}</span>
                            </label>
                        </li>
                    </ul>
                    <p v-if="chosen.length > 10" class="bn__hint">Banyak paket membuat tulisan mengecil. Pilih paket utama saja agar mudah dibaca.</p>
                    <p v-if="!chosen.length" class="bn__hint bn__hint--error">Pilih minimal satu paket.</p>
                </BaseCard>
            </div>

            <div class="bn__side">
                <BaseCard>
                    <h3 class="bn__title">Pratinjau</h3>

                    <div class="bn__stage">
                        <canvas v-show="chosen.length && size.valid" ref="canvas" class="bn__canvas" aria-label="Pratinjau banner"></canvas>
                        <p v-if="!size.valid" class="bn__hint bn__hint--error">Ukuran tidak valid. Isi lebar dan tinggi {{ presets.limits.min_cm }} - {{ presets.limits.max_cm }} cm.</p>
                    </div>

                    <dl v-if="pixels" class="bn__facts">
                        <div><dt>Ukuran cetak</dt><dd>{{ formatNumber(size.w, 1) }} × {{ formatNumber(size.h, 1) }} cm</dd></div>
                        <div><dt>Ukuran gambar</dt><dd>{{ formatNumber(pixels.w, 0) }} × {{ formatNumber(pixels.h, 0) }} px</dd></div>
                        <div><dt>Kerapatan</dt><dd>±{{ pixels.dpi }} dpi</dd></div>
                    </dl>

                    <p v-if="pixels?.reduced" class="bn__note">
                        <Info :size="16" aria-hidden="true" />
                        Jumlah piksel dikurangi karena batas memori browser di perangkat ini.
                    </p>

                    <BaseButton size="lg" block :loading="busy" :disabled="!pixels || !chosen.length" @click="download">
                        <Download :size="18" />
                        Unduh Banner PNG
                    </BaseButton>

                    <p class="bn__note">
                        <Info :size="16" aria-hidden="true" />
                        Warna mengikuti pengaturan Warna Website. Ukuran cetak sebenarnya tertanam di file PNG, jadi cukup kirim file ke percetakan.
                        Spanduk besar dengan 25 sampai 50 dpi sudah cukup tajam jika dilihat dari jauh.
                    </p>
                </BaseCard>
            </div>
        </div>
    </section>
</template>

<style scoped>
.bn__grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.15fr); gap: var(--space-5); align-items: start; }
.bn__controls { display: grid; gap: var(--space-5); }
.bn__side { position: sticky; top: var(--space-4); }
.bn__title { margin: 0 0 var(--space-4); font-size: var(--text-lg); }

.bn__group { margin-bottom: var(--space-4); }
.bn__group h4 { margin: 0 0 var(--space-2); font-size: var(--text-sm); color: var(--color-muted); }
.bn__chips { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: var(--space-2); }

.chip {
    display: grid;
    gap: 2px;
    padding: var(--space-3);
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    text-align: left;
    transition: border-color var(--transition-fast), background var(--transition-fast);
}
.chip:hover { border-color: var(--color-primary); }
.chip--on { background: var(--color-primary-soft); border-color: var(--color-primary-strong); box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-primary) 45%, transparent); }
.chip strong { font-size: var(--text-sm); }
.chip small { color: var(--color-muted); }

.bn__custom { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); margin-top: var(--space-3); }
.bn__quality { margin-top: var(--space-4); }
.bn__switches { display: grid; gap: var(--space-3); margin: var(--space-4) 0; }
.bn__sub { margin: 0 0 var(--space-2); font-size: var(--text-sm); }
.bn__packages { display: grid; gap: var(--space-2); }
.bn__packages label { display: flex; align-items: center; gap: var(--space-3); cursor: pointer; }
.bn__packages input { width: 18px; height: 18px; accent-color: var(--color-primary-strong); }

.bn__stage { display: grid; place-items: center; min-height: 160px; padding: var(--space-3); background: color-mix(in srgb, var(--color-text) 6%, white); border-radius: var(--radius-lg); }
.bn__canvas { display: block; max-width: 100%; max-height: 520px; width: auto; height: auto; border-radius: var(--radius-sm); box-shadow: var(--shadow-md); }

.bn__facts { display: grid; gap: var(--space-2); margin: var(--space-4) 0; }
.bn__facts div { display: flex; justify-content: space-between; gap: var(--space-4); padding-bottom: var(--space-2); border-bottom: 1px solid var(--color-border); }
.bn__facts dt { color: var(--color-muted); }
.bn__facts dd { margin: 0; font-weight: 600; }

.bn__note { display: flex; align-items: flex-start; gap: var(--space-2); margin: var(--space-4) 0 0; font-size: var(--text-sm); color: var(--color-muted); }
.bn__note svg { flex: none; margin-top: 2px; color: var(--color-primary-strong); }
.bn__hint { margin: var(--space-2) 0 0; font-size: var(--text-sm); color: var(--color-muted); }
.bn__hint--error { color: var(--color-danger); }

.bn__alert {
    display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-3);
    padding: 0.85rem 1rem; background: var(--color-danger-soft);
    border: 1px solid color-mix(in srgb, var(--color-danger) 40%, white);
    border-radius: var(--radius-md); color: var(--color-danger); font-weight: 500;
}

@media (max-width: 1000px) {
    .bn__grid { grid-template-columns: 1fr; }
    .bn__side { position: static; }
}
</style>