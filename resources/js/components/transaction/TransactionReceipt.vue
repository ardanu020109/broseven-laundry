<script setup>
import { formatCurrency, formatDateTime, formatQuantity } from '@/composables/useFormat';

defineProps({
    transaction: { type: Object, required: true },
    business: { type: Object, default: () => ({}) },
    contact: { type: Object, default: () => ({}) },
});
</script>

<template>
    <section class="rc">
        <header class="rc__head">
            <h1>{{ business.name }}</h1>
            <p v-if="contact.address" class="rc__small">{{ contact.address }}</p>
            <p v-if="contact.whatsapp" class="rc__small">WhatsApp +{{ contact.whatsapp }}</p>
        </header>

        <h2 class="rc__title">BUKTI TRANSAKSI</h2>

        <dl class="rc__list">
            <div><dt>Kode</dt><dd>{{ transaction.code }}</dd></div>
            <div><dt>Tanggal</dt><dd>{{ formatDateTime(transaction.received_at) }}</dd></div>
            <div><dt>Customer</dt><dd>{{ transaction.customer_name }}</dd></div>
            <div><dt>Paket</dt><dd>{{ transaction.package_name }}</dd></div>
            <div><dt>Jumlah</dt><dd>{{ formatQuantity(transaction.quantity, transaction.package_unit) }}</dd></div>
            <div><dt>Harga</dt><dd>{{ formatCurrency(transaction.package_price) }} {{ transaction.package_price_label }}</dd></div>
            <div class="rc__total"><dt>Total</dt><dd>{{ formatCurrency(transaction.total_price) }}</dd></div>
            <div><dt>Estimasi selesai</dt><dd>{{ formatDateTime(transaction.estimated_completion_at) }}</dd></div>
            <div v-if="transaction.received_by"><dt>Diterima oleh</dt><dd>{{ transaction.received_by.name }}</dd></div>
        </dl>

        <div class="rc__qr">
            <img :src="transaction.qr_url" alt="QR Code bukti transaksi" width="170" height="170" />
            <p>Tunjukkan QR Code ini saat mengambil cucian.</p>
        </div>

        <p class="rc__small rc__track">Pantau status cucian:<br />{{ transaction.tracking_url }}</p>

        <p class="rc__thanks">Terima kasih telah menggunakan layanan kami!</p>
    </section>
</template>

<style scoped>
.rc { max-width: 90mm; margin-inline: auto; padding: 4mm; color: #111; font-size: 12px; line-height: 1.5; }

.rc__head { text-align: center; }
.rc__head h1 { margin: 0; font-size: 18px; }
.rc__small { margin: 2px 0 0; font-size: 11px; overflow-wrap: anywhere; }

.rc__title { margin: 12px 0 8px; padding-block: 5px; border-block: 1px dashed #666; font-size: 13px; letter-spacing: 0.12em; text-align: center; }

.rc__list { display: grid; gap: 3px; margin: 0; }
.rc__list div { display: flex; justify-content: space-between; gap: 10px; }
.rc__list dt { color: #444; }
.rc__list dd { margin: 0; font-weight: 600; text-align: right; }
.rc__total { margin-top: 4px; padding-top: 5px; border-top: 1px dashed #666; font-size: 14px; }

.rc__qr { display: grid; justify-items: center; gap: 4px; margin: 14px 0 8px; text-align: center; }
.rc__qr p { margin: 0; font-size: 11px; }

.rc__track { text-align: center; }
.rc__thanks { margin: 12px 0 0; text-align: center; font-weight: 600; }
</style>