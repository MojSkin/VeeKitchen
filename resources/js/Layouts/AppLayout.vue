<script setup>
import { router, usePage } from '@inertiajs/vue3';
import FlashToasts from '@/Components/FlashToasts.vue';

const page = usePage();

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <div class="min-h-dvh">
        <FlashToasts />

        <header class="glass sticky top-0 z-20 px-4 py-3">
            <div class="mx-auto flex max-w-7xl items-center justify-between">
                <p class="font-black text-saffron-500">وی‌کیچن</p>
                <div class="flex items-center gap-3">
                    <span v-if="page.props.auth.user" class="text-sm opacity-70">
                        {{ page.props.auth.user.name }} · {{ page.props.auth.user.roleLabel }}
                    </span>
                    <button
                        type="button"
                        class="glass-flat rounded-xl px-3 py-2 text-sm"
                        @click="logout"
                    >
                        خروج
                    </button>
                </div>
            </div>
        </header>

        <main>
            <slot />
        </main>
    </div>
</template>
