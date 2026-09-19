<script setup>
import FlashToasts from '@/Components/FlashToasts.vue';
</script>

<template>
    <div class="min-h-dvh">
        <FlashToasts />
        <slot />
    </div>
</template>
