<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import QrScanner from 'qr-scanner';
import BaseLoading from '@/components/common/BaseLoading.vue';

const emit = defineEmits(['scan']);

const video = ref(null);
const status = ref('starting'); // starting | scanning | error
const message = ref('');

let scanner = null;
let handled = false;

function describeError(error) {
    const text = String(error?.message ?? error ?? '');

    if (/denied|permission|notallowed/i.test(text)) {
        return 'Izin kamera ditolak. Izinkan akses kamera di browser, atau gunakan input manual.';
    }
    if (/not found|notfound|no camera/i.test(text)) {
        return 'Kamera tidak ditemukan di perangkat ini. Gunakan input manual.';
    }
    return 'Kamera tidak dapat dibuka. Gunakan input manual.';
}

onMounted(async () => {
    if (!window.isSecureContext) {
        status.value = 'error';
        message.value = 'Kamera hanya dapat dipakai lewat HTTPS atau localhost. Gunakan input manual.';
        return;
    }

    try {
        if (!(await QrScanner.hasCamera())) {
            status.value = 'error';
            message.value = 'Perangkat ini tidak memiliki kamera. Gunakan input manual.';
            return;
        }

        scanner = new QrScanner(
            video.value,
            (result) => {
                if (handled) return;
                handled = true;
                emit('scan', result.data);
            },
            {
                returnDetailedScanResult: true,
                highlightScanRegion: true,
                highlightCodeOutline: true,
                preferredCamera: 'environment',
                maxScansPerSecond: 8,
            },
        );

        await scanner.start();
        status.value = 'scanning';
    } catch (error) {
        status.value = 'error';
        message.value = describeError(error);
    }
});

onBeforeUnmount(() => {
    scanner?.stop();
    scanner?.destroy();
    scanner = null;
});
</script>

<template>
    <div class="scanner">
        <div class="scanner__frame">
            <video ref="video" class="scanner__video" playsinline muted></video>

            <div v-if="status !== 'scanning'" class="scanner__overlay">
                <BaseLoading v-if="status === 'starting'" label="Membuka kamera..." />
                <p v-else class="scanner__error">{{ message }}</p>
            </div>
        </div>

        <p class="scanner__hint">Arahkan kamera ke QR Code pada bukti transaksi.</p>
    </div>
</template>

<style scoped>
.scanner { display: grid; gap: var(--space-3); }

.scanner__frame {
    position: relative;
    overflow: hidden;
    aspect-ratio: 4 / 3;
    background: #0f1f29;
    border-radius: var(--radius-lg);
}

.scanner__video { width: 100%; height: 100%; object-fit: cover; }

.scanner__overlay {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    padding: var(--space-5);
    background: color-mix(in srgb, var(--color-surface) 92%, transparent);
    text-align: center;
}

.scanner__error { margin: 0; color: var(--color-danger); font-weight: 500; }
.scanner__hint { margin: 0; text-align: center; font-size: var(--text-sm); color: var(--color-muted); }
</style>