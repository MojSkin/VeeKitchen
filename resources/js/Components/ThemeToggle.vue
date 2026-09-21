<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import NavIcon from '@/Components/NavIcon.vue';
import { currentTheme, toggleTheme, watchSystemTheme } from '@/lib/theme';

const theme = ref(currentTheme());
let observer = null;

onBeforeUnmount(() => {
    observer?.disconnect();
});

function flip() {
    theme.value = toggleTheme();
}

onMounted(() => {
    theme.value = currentTheme();
    watchSystemTheme();

    // Keep in sync when another surface (e.g. a second toggle) flips it.
    observer = new MutationObserver(() => {
        theme.value = currentTheme();
    });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});
</script>

<template>
    <button
        type="button"
        class="neo-click grid size-9 cursor-pointer place-items-center rounded-xl"
        :title="theme === 'dark' ? 'حالت روشن' : 'حالت تیره'"
        :aria-label="theme === 'dark' ? 'تغییر به حالت روشن' : 'تغییر به حالت تیره'"
        @click="flip"
    >
        <!-- transitions.dev icon swap: cross-fade with blur + scale -->
        <Transition
            mode="out-in"
            enter-active-class="t-icon-swap is-entering"
            enter-to-class="t-icon-swap"
            leave-active-class="t-icon-swap"
            leave-to-class="t-icon-swap is-entering"
        >
            <NavIcon
                v-if="theme === 'dark'"
                key="sun"
                name="sun"
            />
            <NavIcon
                v-else
                key="moon"
                name="moon"
            />
        </Transition>
    </button>
</template>
