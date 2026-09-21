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
            <div class="glass glass-sheen t-panel is-open w-full rounded-3xl p-8">
                <!-- Brand -->
                <div class="mb-6 flex flex-col items-center gap-3">
                    <div class="neo grid size-14 place-items-center rounded-2xl">
                        <span class="text-2xl font-black text-lajvard-600 dark:text-lajvard-300">و</span>
                    </div>
                    <div class="text-center">
                        <h1 class="text-xl font-black">ورود به وی‌کیچن</h1>
                        <p class="mt-1 text-sm opacity-55">پنل کارکنان</p>
                    </div>
                </div>

                <form
                    class="space-y-4"
                    @submit.prevent="submit"
                >
                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium"
                            for="email"
                        >ایمیل</label>
                        <input
                            id="email"
                            v-model="email"
                            type="email"
                            required
                            autocomplete="email"
                            class="neo-pressed w-full rounded-xl px-4 py-3 text-sm outline-none transition focus:ring-2 focus:ring-lajvard-500/40"
                            :class="errors.email ? 'ring-2 ring-red-400' : ''"
                        >
                        <p
                            v-if="errors.email"
                            class="mt-1.5 text-xs text-red-500"
                        >{{ errors.email }}</p>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium"
                            for="password"
                        >رمز عبور</label>
                        <input
                            id="password"
                            v-model="password"
                            type="password"
                            required
                            autocomplete="current-password"
                            class="neo-pressed w-full rounded-xl px-4 py-3 text-sm outline-none transition focus:ring-2 focus:ring-lajvard-500/40"
                        >
                    </div>

                    <label class="flex cursor-pointer items-center gap-2 text-sm opacity-80">
                        <input
                            v-model="remember"
                            type="checkbox"
                            class="accent-lajvard-600"
                        >
                        مرا به خاطر بسپار
                    </label>

                    <button
                        type="submit"
                        class="w-full cursor-pointer rounded-xl bg-lajvard-600 py-3 text-sm font-bold text-white shadow-lg transition-all duration-fast ease-smooth-out hover:bg-lajvard-700 hover:shadow-xl active:scale-[0.98] disabled:opacity-40"
                        :disabled="processing"
                    >
                        ورود
                    </button>
                </form>
            </div>
        </div>
    </PublicLayout>
</template>
