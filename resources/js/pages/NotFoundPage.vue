<script setup>
import BaseButton from '@/components/common/BaseButton.vue';
</script>

<template>
    <main class="nf">
        <div class="container text-center">
            <h1 class="text-gradient">404</h1>
            <h3>Halaman tidak ditemukan</h3>
            <p class="text-muted">Alamat yang Anda tuju tidak tersedia atau sudah dipindahkan.</p>
            <BaseButton @click="$router.push('/')">Kembali ke Beranda</BaseButton>
        </div>
    </main>
</template>

<style scoped>
.nf {
    min-height: 100vh;
    display: grid;
    place-items: center;
    background: var(--gradient-hero);
}
.nf h1 { font-size: 6rem; margin-bottom: var(--space-2); }
</style>