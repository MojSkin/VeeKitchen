<script setup>
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

const email = ref('');
const password = ref('');
const remember = ref(false);
const processing = ref(false);
const errors = ref(usePage().props.errors ?? {});

function submit() {
    processing.value = true;

    router.post(route('login.store'), {
        email: email.value,
        password: password.value,
        remember: remember.value,
    }, {
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <PublicLayout>
        <div class="mx-auto flex min-h-dvh max-w-md items-center px-4">
            <div class="glass glass-sheen w-full rounded-glass p-8">
                <h1 class="text-center text-2xl font-bold">ورود به وی‌کیچن</h1>
                <p class="mt-1 text-center text-sm opacity-60">پنل کارکنان</p>

                <form class="mt-6 space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="mb-1 block text-sm" for="email">ایمیل</label>
                        <input
                            id="email"
                            v-model="email"
                            type="email"
                            required
                            autocomplete="email"
                            class="glass-flat w-full rounded-xl px-4 py-3 outline-none"
                            :class="errors.email ? 'ring-2 ring-red-400' : ''"
                        >
                        <p v-if="errors.email" class="mt-1 text-xs text-red-500">{{ errors.email }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm" for="password">رمز عبور</label>
                        <input
                            id="password"
                            v-model="password"
                            type="password"
                            required
                            autocomplete="current-password"
                            class="glass-flat w-full rounded-xl px-4 py-3 outline-none"
                        >
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="remember" type="checkbox" class="accent-saffron-500">
                        مرا به خاطر بسپار
                    </label>

                    <button
                        type="submit"
                        class="w-full rounded-2xl bg-saffron-500 py-3 font-bold text-white transition hover:bg-saffron-600 disabled:opacity-40"
                        :disabled="processing"
                    >
                        ورود
                    </button>
                </form>
            </div>
        </div>
    </PublicLayout>
</template>
