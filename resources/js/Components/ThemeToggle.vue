<script setup>
import { onMounted, ref } from 'vue';
import { currentTheme, toggleTheme, watchSystemTheme } from '@/lib/theme';

const theme = ref(currentTheme());

function flip() {
    theme.value = toggleTheme();
}

onMounted(() => {
    theme.value = currentTheme();
    watchSystemTheme();
});
</script>

<template>
    <button
        type="button"
        class="glass-flat flex h-9 w-9 items-center justify-center rounded-xl text-base transition active:scale-95"
        :title="theme === 'dark' ? 'حالت روشن' : 'حالت تیره'"
        :aria-label="theme === 'dark' ? 'تغییر به حالت روشن' : 'تغییر به حالت تیره'"
        @click="flip"
    >
        <span v-if="theme === 'dark'">☀️</span>
        <span v-else>🌙</span>
    </button>
</template>
