<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { announceOrder, preferredMode } from '@/lib/tts';
import { formatClock } from '@/lib/format';

const props = defineProps({
    branch: { type: Object, required: true },
    readyOrders: { type: Array, required: true },
});

const readyOrders = ref([...props.readyOrders]);
const soundEnabled = ref(false);
const mode = ref('audio');
const announcedIds = ref(new Set());

let pickupChannel = null;

onMounted(() => {
    mode.value = preferredMode();

    pickupChannel = window.Echo.channel(`branch.${props.branch.id}.pickup`)
        .listen('.order.status-changed', (event) => {
            if (event.status === 'ready') {
                router.reload({ only: ['readyOrders'] });
            }
        });
});

onBeforeUnmount(() => {
    if (pickupChannel) {
        window.Echo.leave(`branch.${props.branch.id}.pickup`);
    }
});

/**
 * One user gesture unlocks the audio; afterwards the display speaks freely.
 */
async function enableSound() {
    soundEnabled.value = true;

    // Speak a silent/short warm-up clip so autoplay stays unlocked.
    const latest = readyOrders.value[readyOrders.value.length - 1];

    if (latest) {
        await announce(latest);
    }
}

async function announce(order) {
    await announceOrder(
        { orderNumber: order.order_number, tableLabel: order.table_label ?? null },
        { mode: mode.value },
    );

    announcedIds.value.add(order.id);
}

function announceAgain(order) {
    if (soundEnabled.value) {
        announce(order);
    }
}
</script>

<template>
    <div class="flex min-h-dvh flex-col bg-night-950 text-night-50">
        <!-- Header -->
        <header class="flex items-center justify-between px-8 py-5">
            <h1 class="text-2xl font-bold">{{ branch.name }}</h1>
            <button
                v-if="!soundEnabled"
                type="button"
                class="rounded-2xl bg-lajvard-600 px-6 py-3 text-lg font-bold text-white"
                @click="enableSound"
            >
                🔊 فعال‌سازی صدا
            </button>
            <span v-else class="text-sm opacity-60">صدای اعلام فعال است · حالت: {{ mode === 'speech' ? 'مرورگر' : 'فایل صوتی' }}</span>
        </header>

        <!-- Ready queue -->
        <main class="flex flex-1 flex-wrap content-start items-start justify-center gap-8 px-8 pb-16">
            <article
                v-for="order in readyOrders"
                :key="order.id"
                class="glass-strong flex w-80 flex-col items-center rounded-2xl bg-white/5 p-10 text-center"
            >
                <p class="text-xs uppercase tracking-widest opacity-60">آماده تحویل</p>
                <p class="mt-3 text-8xl font-black text-pistachio-400">
                    {{ order.order_number }}
                </p>
                <p class="mt-2 text-lg opacity-80">{{ order.table_label ?? 'بیرون‌بر' }}</p>
                <p class="mt-1 text-sm opacity-50">{{ formatClock(order.ready_at) }}</p>

                <button
                    v-if="soundEnabled"
                    type="button"
                    class="glass-flat mt-6 rounded-xl px-4 py-2 text-sm"
                    @click="announceAgain(order)"
                >
                    تکرار اعلان
                </button>
            </article>

            <div v-if="readyOrders.length === 0" class="m-auto text-center opacity-40">
                <p class="text-3xl">سفارش آماده‌ای نیست</p>
                <p class="mt-2 text-lg">به‌محض آماده شدن، همین‌جا نمایش داده می‌شود</p>
            </div>
        </main>
    </div>
</template>
