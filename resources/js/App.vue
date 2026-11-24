<script setup>
import { RouterView } from 'vue-router';
import ToastHost from '@/components/common/ToastHost.vue';
</script>

<template>
    <RouterView v-slot="{ Component, route }">
        <Transition name="page" mode="out-in">
            <component :is="Component" :key="route.path" />
        </Transition>
    </RouterView>

    <ToastHost />
</template>