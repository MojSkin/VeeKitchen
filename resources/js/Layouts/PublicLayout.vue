<script setup>
import FlashToasts from '@/Components/FlashToasts.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
</script>

<template>
    <div class="min-h-dvh">
        <FlashToasts />
        <div class="fixed end-4 top-4 z-30">
            <ThemeToggle />
        </div>
        <slot />
    </div>
</template>
