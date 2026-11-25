<script setup>
import { RouterView } from 'vue-router';
import ToastHost from '@/components/common/ToastHost.vue';
</script>

<template>
    <RouterView v-slot="{ Component, route }">
        <Transition name="page" mode="out-in">
            <!-- Layout dashboard dipertahankan saat berpindah halaman; halaman lain dimuat ulang per path. -->
            <component :is="Component" :key="route.meta.layout === 'dashboard' ? 'dashboard' : route.path" />
        </Transition>
    </RouterView>

    <ToastHost />
</template>