<script setup>
import BaseButton from '@/components/common/BaseButton.vue';
</script>

<template>
    <main class="nf">
        <div class="container text-center">
            <h1 class="text-gradient">403</h1>
            <h3>Akses ditolak</h3>
            <p class="text-muted">Akun Anda tidak memiliki izin untuk membuka halaman ini.</p>
            <BaseButton @click="$router.push('/dashboard')">Kembali ke Dashboard</BaseButton>
        </div>
    </main>
</template>

<style scoped>
.nf { min-height: 100vh; display: grid; place-items: center; background: var(--gradient-hero); }
.nf h1 { font-size: 6rem; margin-bottom: var(--space-2); }
</style>