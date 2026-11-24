<script setup>
import { reactive, ref } from 'vue';
import { Plus, Save, Trash2 } from 'lucide-vue-next';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseCard from '@/components/common/BaseCard.vue';
import BaseInput from '@/components/common/BaseInput.vue';
import BaseTextarea from '@/components/common/BaseTextarea.vue';
import ImageUploader from '@/components/website/ImageUploader.vue';
import { useFormErrors } from '@/composables/useFormErrors';
import { useToast } from '@/composables/useToast';
import { extractErrorMessage } from '@/services/http';
import { deleteImage, saveSettings, uploadImage } from '@/services/websiteService';
import { useSiteStore } from '@/stores/site';

const props = defineProps({
    settings: { type: Object, required: true },
});

const emit = defineEmits(['updated']);

const toast = useToast();
const site = useSiteStore();
const { errors, setFrom, clear } = useFormErrors();

const form = reactive({
    business_name: '',
    tagline: '',
    description: '',
    hero_title: '',
    hero_subtitle: '',
    address: '',
    whatsapp: '',
    operating_hours: '',
    social_links: { instagram: '', facebook: '', tiktok: '', youtube: '' },
    vision: '',
    missions: [],
});

const logoUrl = ref(null);
const faviconUrl = ref(null);
const saving = ref(false);
const busy = reactive({ logo: false, favicon: false });

const SOCIALS = [
    ['instagram', 'Instagram', 'https://instagram.com/akun-anda'],
    ['facebook', 'Facebook', 'https://facebook.com/halaman-anda'],
    ['tiktok', 'TikTok', 'https://tiktok.com/@akun-anda'],
    ['youtube', 'YouTube', 'https://youtube.com/@kanal-anda'],
];

function hydrate(settings) {
    Object.assign(form, {
        business_name: settings.business_name ?? '',
        tagline: settings.tagline ?? '',
        description: settings.description ?? '',
        hero_title: settings.hero_title ?? '',
        hero_subtitle: settings.hero_subtitle ?? '',
        address: settings.address ?? '',
        whatsapp: settings.whatsapp ?? '',
        operating_hours: settings.operating_hours ?? '',
        vision: settings.vision ?? '',
    });

    form.social_links = {
        instagram: settings.social_links?.instagram ?? '',
        facebook: settings.social_links?.facebook ?? '',
        tiktok: settings.social_links?.tiktok ?? '',
        youtube: settings.social_links?.youtube ?? '',
    };
    form.missions = [...(settings.missions ?? [])];

    logoUrl.value = settings.logo_url ?? null;
    faviconUrl.value = settings.favicon_url ?? null;
}

hydrate(props.settings);

function branding(settings) {
    site.applyBranding({
        name: settings.business_name,
        logo_url: settings.logo_url,
        favicon_url: settings.favicon_url,
    });
}

async function submit() {
    saving.value = true;
    clear();

    try {
        const result = await saveSettings({
            ...form,
            social_links: { ...form.social_links },
            missions: [...form.missions],
        });

        hydrate(result.data);
        emit('updated', result.data);
        branding(result.data);
        toast.success(result.message);
    } catch (error) {
        toast.error(setFrom(error) ? 'Periksa kembali isian yang ditandai merah.' : extractErrorMessage(error));
    } finally {
        saving.value = false;
    }
}

async function pick(kind, file) {
    busy[kind] = true;

    try {
        const result = await uploadImage(kind, file);

        logoUrl.value = result.data.logo_url;
        faviconUrl.value = result.data.favicon_url;
        emit('updated', result.data);
        branding(result.data);
        toast.success(result.message);
    } catch (error) {
        toast.error(extractErrorMessage(error));
    } finally {
        busy[kind] = false;
    }
}

async function remove(kind) {
    busy[kind] = true;

    try {
        const result = await deleteImage(kind);

        logoUrl.value = result.data.logo_url;
        faviconUrl.value = result.data.favicon_url;
        emit('updated', result.data);
        branding(result.data);
        toast.success(result.message);
    } catch (error) {
        toast.error(extractErrorMessage(error));
    } finally {
        busy[kind] = false;
    }
}

function addMission() {
    if (form.missions.length < 10) form.missions.push('');
}
</script>

<template>
    <div class="identity">
        <BaseCard>
            <h3 class="identity__title">Logo dan Favicon</h3>
            <div class="identity__uploads">
                <ImageUploader
                    label="Logo bisnis"
                    :url="logoUrl"
                    :busy="busy.logo"
                    hint="PNG, JPG, atau WEBP. Maksimal 2 MB (4000 x 4000 piksel)."
                    @select="(file) => pick('logo', file)"
                    @remove="remove('logo')"
                />
                <ImageUploader
                    label="Favicon"
                    :url="faviconUrl"
                    :busy="busy.favicon"
                    :max-kb="512"
                    accept="image/png,image/webp,image/x-icon,image/vnd.microsoft.icon,.ico"
                    hint="PNG, WEBP, atau ICO. Maksimal 512 KB. Disarankan persegi."
                    @select="(file) => pick('favicon', file)"
                    @remove="remove('favicon')"
                />
            </div>
        </BaseCard>

        <form class="identity__form" novalidate @submit.prevent="submit">
            <BaseCard>
                <h3 class="identity__title">Identitas Bisnis</h3>
                <div class="identity__grid">
                    <BaseInput v-model="form.business_name" label="Nama bisnis" maxlength="100" :error="errors.business_name" />
                    <BaseInput v-model="form.tagline" label="Slogan" maxlength="160" :error="errors.tagline" />
                    <BaseInput v-model="form.hero_title" label="Judul beranda" maxlength="120" :error="errors.hero_title" />
                </div>
                <div class="identity__stack">
                    <BaseTextarea v-model="form.hero_subtitle" label="Subjudul beranda" :rows="2" :maxlength="300" :error="errors.hero_subtitle" />
                    <BaseTextarea v-model="form.description" label="Deskripsi bisnis" :rows="4" :maxlength="1000" :error="errors.description" />
                </div>
            </BaseCard>

            <BaseCard>
                <h3 class="identity__title">Kontak dan Jam Operasional</h3>
                <div class="identity__grid">
                    <BaseInput
                        v-model="form.whatsapp"
                        label="Nomor WhatsApp"
                        placeholder="081234567890"
                        inputmode="tel"
                        hint="Disimpan dalam format internasional (62...)."
                        :error="errors.whatsapp"
                    />
                    <BaseInput v-model="form.operating_hours" label="Jam operasional" placeholder="Setiap hari, 08.00 - 20.00 WITA" maxlength="150" :error="errors.operating_hours" />
                </div>
                <div class="identity__stack">
                    <BaseTextarea v-model="form.address" label="Alamat" :rows="2" :maxlength="300" :error="errors.address" />
                </div>
            </BaseCard>

            <BaseCard>
                <h3 class="identity__title">Media Sosial</h3>
                <div class="identity__grid">
                    <BaseInput
                        v-for="[key, label, placeholder] in SOCIALS"
                        :key="key"
                        v-model="form.social_links[key]"
                        type="url"
                        :label="label"
                        :placeholder="placeholder"
                        :error="errors[`social_links.${key}`]"
                    />
                </div>
            </BaseCard>

            <BaseCard>
                <h3 class="identity__title">Visi dan Misi</h3>
                <div class="identity__stack">
                    <BaseTextarea v-model="form.vision" label="Visi" :rows="3" :maxlength="500" :error="errors.vision" />

                    <div class="missions">
                        <span class="missions__label">Misi</span>

                        <div v-for="(mission, index) in form.missions" :key="index" class="missions__row">
                            <BaseInput
                                v-model="form.missions[index]"
                                :aria-label="`Misi ${index + 1}`"
                                :placeholder="`Misi ${index + 1}`"
                                maxlength="200"
                                :error="errors[`missions.${index}`]"
                            />
                            <button type="button" class="missions__remove" :aria-label="`Hapus misi ${index + 1}`" @click="form.missions.splice(index, 1)">
                                <Trash2 :size="16" />
                            </button>
                        </div>

                        <p v-if="errors.missions" class="missions__error">{{ errors.missions }}</p>

                        <BaseButton variant="secondary" size="sm" :disabled="form.missions.length >= 10" @click="addMission">
                            <Plus :size="16" />
                            Tambah misi
                        </BaseButton>
                    </div>
                </div>
            </BaseCard>

            <div class="identity__submit">
                <BaseButton type="submit" size="lg" :loading="saving">
                    <Save :size="18" />
                    Simpan Pengaturan
                </BaseButton>
            </div>
        </form>
    </div>
</template>

<style scoped>
.identity { display: grid; gap: var(--space-5); }
.identity__form { display: grid; gap: var(--space-5); }
.identity__title { margin: 0 0 var(--space-4); font-size: var(--text-lg); }
.identity__uploads { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-5); }
.identity__grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: var(--space-4); }
.identity__stack { display: grid; gap: var(--space-4); margin-top: var(--space-4); }
.identity__submit { display: flex; justify-content: flex-end; }

.missions { display: grid; gap: var(--space-3); justify-items: start; }
.missions__label { font-size: var(--text-sm); font-weight: 600; }
.missions__row { display: flex; align-items: flex-start; gap: var(--space-2); width: 100%; }
.missions__row > :first-child { flex: 1; }
.missions__remove {
    display: grid;
    place-items: center;
    width: 46px;
    height: 46px;
    background: transparent;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    color: var(--color-muted);
    transition: background var(--transition-fast), color var(--transition-fast);
}
.missions__remove:hover { background: var(--color-danger-soft); color: var(--color-danger); }
.missions__error { margin: 0; font-size: var(--text-sm); color: var(--color-danger); }
</style>