<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { faDigits, formatToman } from '@/lib/format';

const props = defineProps({
    orders: { type: Array, required: true },
    statuses: { type: Array, required: true },
});

const statusTones = {
    draft: 'slate',
    ordered: 'amber',
    received: 'green',
    cancelled: 'red',
};

const toneClasses = {
    slate: 'bg-night-500/15 text-night-700 dark:text-night-300',
    amber: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
    green: 'bg-pistachio-500/15 text-pistachio-600 dark:text-pistachio-400',
    red: 'bg-red-500/15 text-red-600 dark:text-red-400',
};

const counts = computed(() => {
    const map = { draft: 0, ordered: 0, received: 0, cancelled: 0 };

    for (const order of props.orders) {
        map[order.status] = (map[order.status] ?? 0) + 1;
    }

    return map;
});

const busyId = ref(null);
const expandedId = ref(null);

function run(order, routeName, successFlashExpected = true) {
    busyId.value = order.id;

    router.post(
        route(routeName, order.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                busyId.value = null;
            },
        },
    );
}

function toggleItems(order) {
    expandedId.value = expandedId.value === order.id ? null : order.id;
}

function formatDay(iso) {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);

    return faDigits(`${date.getFullYear()}/${date.getMonth() + 1}/${date.getDate()}`);
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-6xl px-4 py-6">
            <header class="mb-6 flex flex-wrap items-end justify-between gap-3">
                <h1 class="text-2xl font-bold">سفارش‌های خرید</h1>
                <div class="flex gap-2 text-xs">
                    <span
                        v-for="status in statuses"
                        :key="status.value"
                        class="glass-flat rounded-full px-3 py-1.5 font-bold"
                        :class="toneClasses[statusTones[status.value]]"
                    >
                        {{ status.label }}: {{ faDigits(counts[status.value] ?? 0) }}
                    </span>
                </div>
            </header>

            <div class="space-y-3">
                <article
                    v-for="order in orders"
                    :key="order.id"
                    class="glass rounded-glass p-5"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-bold">{{ order.supplier_name }}</h2>
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-bold"
                                    :class="toneClasses[statusTones[order.status]]"
                                >
                                    {{ order.status_label }}
                                </span>
                                <span class="text-xs opacity-50">#{{ faDigits(order.id) }}</span>
                            </div>
                            <p class="mt-1 text-sm opacity-70">
                                {{ faDigits(order.items.length) }} قلم
                                · {{ formatToman(order.total) }} تومان
                                <template v-if="order.ordered_at">
                                    · ثبت: {{ formatDay(order.ordered_at) }}
                                </template>
                                <template v-if="order.received_at">
                                    · دریافت: {{ formatDay(order.received_at) }}
                                </template>
                            </p>
                            <p v-if="order.notes" class="mt-1 text-xs opacity-50">
                                {{ order.notes }}
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-wrap gap-2">
                            <button
                                v-if="order.status === 'draft'"
                                type="button"
                                class="cursor-pointer rounded-xl bg-saffron-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-saffron-600 disabled:opacity-40"
                                :disabled="busyId === order.id"
                                @click="run(order, 'admin.purchase-orders.submit')"
                            >
                                ثبت نزد تامین‌کننده
                            </button>
                            <button
                                v-if="order.status === 'ordered'"
                                type="button"
                                class="cursor-pointer rounded-xl bg-pistachio-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-pistachio-600 disabled:opacity-40"
                                :disabled="busyId === order.id"
                                @click="run(order, 'admin.purchase-orders.receive')"
                            >
                                دریافت شد
                            </button>
                            <button
                                v-if="order.status === 'draft' || order.status === 'ordered'"
                                type="button"
                                class="glass-flat cursor-pointer rounded-xl px-4 py-2 text-sm text-red-500 transition hover:bg-red-500/10 disabled:opacity-40"
                                :disabled="busyId === order.id"
                                @click="run(order, 'admin.purchase-orders.cancel')"
                            >
                                کنسل
                            </button>
                            <button
                                type="button"
                                class="glass-flat cursor-pointer rounded-xl px-4 py-2 text-sm transition hover:bg-white/10"
                                @click="toggleItems(order)"
                            >
                                {{ expandedId === order.id ? 'بستن اقلام' : 'اقلام' }}
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="expandedId === order.id"
                        class="mt-4 border-t border-white/10 pt-3"
                    >
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs opacity-50">
                                    <th class="pb-2 text-right font-medium">متریال</th>
                                    <th class="pb-2 text-right font-medium">مقدار</th>
                                    <th class="pb-2 text-right font-medium">هزینهٔ واحد</th>
                                    <th class="pb-2 text-right font-medium">جمع خط</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(item, index) in order.items"
                                    :key="index"
                                    class="border-t border-white/5"
                                >
                                    <td class="py-2 font-bold">{{ item.name }}</td>
                                    <td class="py-2">
                                        {{ faDigits(item.quantity.toLocaleString('en-US', { maximumFractionDigits: 3 })) }}
                                        {{ item.unit_label }}
                                    </td>
                                    <td class="py-2">{{ formatToman(item.unit_cost) }}</td>
                                    <td class="py-2">{{ formatToman(item.line_total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>

                <p
                    v-if="orders.length === 0"
                    class="glass rounded-glass p-8 text-center opacity-60"
                >
                    هنوز سفارش خریدی ثبت نشده است.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
