<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatClock } from '@/lib/format';

const props = defineProps({
    branchId: { type: Number, required: true },
    queue: { type: Array, required: true },
    ready: { type: Array, required: true },
});

const tick = ref(0);

let kitchenChannel = null;
let clockTimer = null;

onMounted(() => {
    kitchenChannel = window.Echo.private(`branch.${props.branchId}.kitchen`)
        .listen('.order.paid', () => {
            router.reload({ only: ['queue'] });
        });

    // Re-render elapsed-time badges every half minute.
    clockTimer = setInterval(() => {
        tick.value += 1;
    }, 30000);
});

onBeforeUnmount(() => {
    if (kitchenChannel) {
        window.Echo.leave(`branch.${props.branchId}.kitchen`);
    }

    clearInterval(clockTimer);
});

function urgencyClass(placedAt) {
    if (!placedAt) {
        return 'opacity-50';
    }

    const minutes = Math.floor((Date.now() - new Date(placedAt).getTime()) / 60000);

    if (minutes >= 15) {
        return 'text-red-500';
    }

    if (minutes >= 8) {
        return 'text-amber-500';
    }

    return 'text-pistachio-500';
}

function elapsedLabel(placedAt) {
    if (!placedAt) {
        return '';
    }

    const minutes = Math.max(0, Math.floor((Date.now() - new Date(placedAt).getTime()) / 60000));

    return `${minutes} دقیقه`;
}

function start(order) {
    router.post(route('kitchen.start', order.id), {}, { preserveScroll: true });
}

function markReady(order) {
    router.post(route('kitchen.ready', order.id), {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <!-- tick is intentionally read so the template re-evaluates on clock ticks -->
        <div class="mx-auto max-w-7xl px-4 py-6" :data-tick="tick">
            <h1 class="mb-6 text-3xl font-bold">صف آشپزخانه</h1>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Queue column -->
                <section>
                    <h2 class="mb-4 text-xl font-bold text-sky-600 dark:text-sky-400">
                        در صف و در حال آماده‌سازی ({{ queue.length }})
                    </h2>

                    <div class="space-y-4">
                        <article
                            v-for="order in queue"
                            :key="order.id"
                            class="glass glass-sheen rounded-glass p-5"
                        >
                            <header class="flex items-start justify-between">
                                <div>
                                    <p class="text-5xl font-black text-saffron-500">
                                        {{ order.order_number }}
                                    </p>
                                    <p class="mt-1 text-sm opacity-70">
                                        {{ order.table?.label ?? 'بیرون‌بر' }}
                                    </p>
                                </div>
                                <div class="text-left">
                                    <p :class="['text-sm font-bold', urgencyClass(order.paid_at)]">
                                        {{ elapsedLabel(order.paid_at) }}
                                    </p>
                                    <p class="text-xs opacity-50">{{ formatClock(order.paid_at) }}</p>
                                </div>
                            </header>

                            <ul class="mt-4 space-y-1 text-lg">
                                <li
                                    v-for="item in order.items"
                                    :key="item.id"
                                    class="flex justify-between rounded-xl px-2 py-1 odd:bg-night-100/40 dark:odd:bg-night-800/40"
                                >
                                    <span class="font-bold">{{ item.quantity }}×</span>
                                    <span class="flex-1 px-2">{{ item.product_name }}</span>
                                </li>
                            </ul>

                            <footer class="mt-4 flex gap-2">
                                <button
                                    v-if="order.status === 'queued'"
                                    type="button"
                                    class="flex-1 rounded-2xl bg-sky-500 py-3 text-lg font-bold text-white transition hover:bg-sky-600"
                                    @click="start(order)"
                                >
                                    شروع آماده‌سازی
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="flex-1 rounded-2xl bg-pistachio-500 py-3 text-lg font-bold text-white transition hover:bg-pistachio-600"
                                    @click="markReady(order)"
                                >
                                    آماده شد
                                </button>
                            </footer>
                        </article>

                        <p v-if="queue.length === 0" class="glass rounded-glass p-6 text-center opacity-50">
                            صف خالی است ✨
                        </p>
                    </div>
                </section>

                <!-- Ready column -->
                <section>
                    <h2 class="mb-4 text-xl font-bold text-pistachio-600 dark:text-pistachio-400">
                        آماده تحویل ({{ ready.length }})
                    </h2>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <article
                            v-for="order in ready"
                            :key="order.id"
                            class="glass rounded-glass p-5 text-center"
                        >
                            <p class="text-4xl font-black text-pistachio-500">{{ order.order_number }}</p>
                            <p class="mt-1 text-sm opacity-70">{{ order.table?.label ?? 'بیرون‌بر' }}</p>
                        </article>

                        <p v-if="ready.length === 0" class="opacity-50">سفارش آماده‌ای نیست.</p>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
