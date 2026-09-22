<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { formatTomanWithUnit, statusTone } from '@/lib/format';

const props = defineProps({
    order: { type: Object, required: true },
    guestToken: { type: String, required: true },
});

const status = ref(props.order.status);
const orderNumber = ref(props.order.order_number);

const STEPS = [
    { value: 'awaiting_payment', label: 'منتظر پرداخت' },
    { value: 'queued', label: 'در صف آماده‌سازی' },
    { value: 'preparing', label: 'در حال آماده‌سازی' },
    { value: 'ready', label: 'آماده تحویل' },
    { value: 'delivered', label: 'تحویل شد' },
];

const currentStep = computed(() => STEPS.findIndex((step) => step.value === status.value));
const tone = computed(() => statusTone(status.value));

const toneClasses = {
    amber: 'text-amber-500',
    sky: 'text-sky-500',
    violet: 'text-violet-500',
    green: 'text-pistachio-500',
    slate: 'text-night-400',
    red: 'text-red-500',
};

let pollTimer = null;

function refresh() {
    fetch(route('orders.track', [props.order.id, props.guestToken]), {
        headers: { Accept: 'text/html, application/json' },
    })
        .catch(() => {});
}

onMounted(() => {
    const channel = window.Echo.channel(`order.${props.order.id}`);

    channel.listen('.order.paid', () => {
        status.value = 'queued';
    });

    channel.listen('.order.status-changed', (event) => {
        status.value = event.status;
        orderNumber.value = event.order_number;
    });

    // Poll fallback in case the socket drops silently.
    pollTimer = setInterval(refresh, 15000);
});

onBeforeUnmount(() => {
    clearInterval(pollTimer);
    window.Echo.leave(`order.${props.order.id}`);
});
</script>

<template>
    <PublicLayout>
        <div class="mx-auto max-w-2xl px-4 py-8">
            <div class="glass glass-sheen rounded-2xl p-6 text-center">
                <p class="text-sm opacity-70">سفارش</p>

                <p v-if="orderNumber" class="text-6xl font-black text-saffron-500">
                    {{ orderNumber }}
                </p>
                <p v-else class="mt-2 text-lg font-bold opacity-80">در انتظار پرداخت</p>

                <p :class="['mt-3 text-xl font-bold', toneClasses[tone]]">
                    {{ STEPS[currentStep]?.label ?? 'کنسل شده' }}
                </p>

                <div class="mt-3 text-sm">
                    <template v-if="order.discount_total > 0">
                        <p class="opacity-60">
                            جمع: <span class="line-through">{{ formatTomanWithUnit(order.subtotal) }}</span>
                        </p>
                        <p class="font-bold text-saffron-600 dark:text-saffron-400">
                            تخفیف: −{{ formatTomanWithUnit(order.discount_total) }}
                        </p>
                        <p class="mt-1 text-lg font-bold">
                            {{ formatTomanWithUnit(order.total) }}
                        </p>
                    </template>
                    <p v-else class="opacity-60">
                        {{ formatTomanWithUnit(order.total) }}
                    </p>
                </div>
            </div>

            <!-- Progress steps -->
            <ol class="mt-8 space-y-3" v-if="status !== 'cancelled'">
                <li
                    v-for="(step, index) in STEPS"
                    :key="step.value"
                    class="glass-flat flex items-center gap-3 rounded-2xl px-4 py-3"
                    :class="index <= currentStep ? 'opacity-100' : 'opacity-40'"
                >
                    <span
                        class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold"
                        :class="index <= currentStep ? 'bg-lajvard-600 text-white' : 'bg-night-200 dark:bg-night-800'"
                    >
                        {{ index + 1 }}
                    </span>
                    <span class="font-medium">{{ step.label }}</span>
                </li>
            </ol>

            <p v-else class="glass mt-6 rounded-2xl p-4 text-center text-red-500">
                این سفارش کنسل شده است.
            </p>
        </div>
    </PublicLayout>
</template>
