<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatTomanWithUnit, statusTone } from '@/lib/format';

const props = defineProps({
    branchId: { type: Number, required: true },
    pendingOrders: { type: Array, required: true },
    activeOrders: { type: Array, required: true },
    tables: { type: Array, required: true },
    shift: { type: Object, default: null },
});

const pending = ref([...props.pendingOrders]);
const active = ref([...props.activeOrders]);
const payMethod = ref('cash');
const busyOrderId = ref(null);

const toneClasses = {
    amber: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
    sky: 'bg-sky-500/15 text-sky-600 dark:text-sky-400',
    violet: 'bg-violet-500/15 text-violet-600 dark:text-violet-400',
    green: 'bg-pistachio-500/15 text-pistachio-600 dark:text-pistachio-400',
};

let cashierChannel = null;

onMounted(() => {
    cashierChannel = window.Echo.private(`branch.${props.branchId}.cashier`)
        .listen('.order.placed', () => {
            // A fresh pending order arrived — re-fetch keeps totals authoritative.
            router.reload({ only: ['pendingOrders'] });
        });
});

onBeforeUnmount(() => {
    if (cashierChannel) {
        window.Echo.leave(`branch.${props.branchId}.cashier`);
    }
});

function confirmPay(order) {
    busyOrderId.value = order.id;

    router.post(
        route('cashier.pay', order.id),
        { method: payMethod.value },
        {
            preserveScroll: true,
            onFinish: () => {
                busyOrderId.value = null;
            },
        },
    );
}

function releaseTable(table) {
    router.post(route('cashier.tables.release', table.id), {}, { preserveScroll: true });
}

function tableLabel(tableId) {
    return props.tables.find((table) => table.id === tableId)?.label ?? '—';
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-6xl px-4 py-6">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-bold">صندوق</h1>

                <!-- Shift status strip -->
                <Link
                    :href="route('cashier.shift')"
                    class="glass-flat rounded-xl px-4 py-2 text-sm transition hover:bg-white/10"
                    :class="shift ? '' : 'text-amber-600 dark:text-amber-400'"
                >
                    <template v-if="shift">
                        شیفت باز · انتظار {{ formatTomanWithUnit(shift.expected_cash) }}
                    </template>
                    <template v-else>
                        ⚠ شیفت بازی ندارید — پرداخت قفل است
                    </template>
                </Link>

                <label class="glass-flat flex items-center gap-2 rounded-xl px-3 py-2 text-sm">
                    روش پرداخت:
                    <select v-model="payMethod" class="bg-transparent outline-none">
                        <option value="cash">نقدی</option>
                        <option value="card">کارت‌خوان</option>
                    </select>
                </label>
            </div>

            <!-- Pending payment -->
            <section class="mb-8">
                <h2 class="mb-3 text-lg font-bold text-amber-600 dark:text-amber-400">
                    منتظر پرداخت ({{ pending.length }})
                </h2>
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="order in pending"
                        :key="order.id"
                        class="glass rounded-glass p-4"
                    >
                        <header class="flex items-center justify-between">
                            <span class="text-sm opacity-70">
                                {{ tableLabel(order.table?.id) }}
                            </span>
                            <span class="text-xs opacity-50">{{ order.guest_name }}</span>
                        </header>

                        <ul class="mt-3 space-y-1 text-sm">
                            <li v-for="item in order.items" :key="item.id" class="flex justify-between">
                                <span>{{ item.quantity }}× {{ item.product_name }}</span>
                                <span class="opacity-60">{{ formatTomanWithUnit(item.line_total) }}</span>
                            </li>
                        </ul>

                        <footer class="mt-4 flex items-center justify-between">
                            <p class="font-bold">{{ formatTomanWithUnit(order.total) }}</p>
                            <button
                                type="button"
                                class="rounded-xl bg-pistachio-500 px-4 py-2 text-sm font-bold text-white disabled:opacity-40"
                                :disabled="busyOrderId === order.id"
                                @click="confirmPay(order)"
                            >
                                دریافت وجه
                            </button>
                        </footer>
                    </article>

                    <p v-if="pending.length === 0" class="opacity-50">سفارش در انتظار پرداختی نیست.</p>
                </div>
            </section>

            <!-- Active orders -->
            <section>
                <h2 class="mb-3 text-lg font-bold">سفارش‌های جاری ({{ active.length }})</h2>
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="order in active"
                        :key="order.id"
                        class="glass rounded-glass p-4"
                    >
                        <header class="flex items-center justify-between">
                            <p class="text-2xl font-black text-saffron-500">{{ order.order_number }}</p>
                            <span
                                class="rounded-full px-3 py-1 text-xs font-medium"
                                :class="toneClasses[statusTone(order.status)]"
                            >
                                {{ order.status_label }}
                            </span>
                        </header>
                        <p class="mt-1 text-sm opacity-60">
                            {{ tableLabel(order.table?.id) }} · {{ formatTomanWithUnit(order.total) }}
                        </p>
                        <div class="mt-3 flex gap-2">
                            <Link
                                :href="route('cashier.receipt', order.id)"
                                target="_blank"
                                class="glass-flat rounded-xl px-3 py-2 text-sm"
                            >
                                فیش
                            </Link>
                            <button
                                type="button"
                                class="glass-flat rounded-xl px-3 py-2 text-sm"
                                @click="router.post(route('cashier.repeat', order.id), {}, { preserveScroll: true })"
                            >
                                تکرار اعلان
                            </button>
                        </div>
                    </article>

                    <p v-if="active.length === 0" class="opacity-50">سفارش فعالی نیست.</p>
                </div>
            </section>

            <!-- Tables quick-release -->
            <section class="mt-8">
                <h2 class="mb-3 text-lg font-bold">میزها</h2>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="table in tables"
                        :key="table.id"
                        type="button"
                        class="glass-flat rounded-xl px-3 py-2 text-sm"
                        :title="table.status_label"
                        @click="releaseTable(table)"
                    >
                        {{ table.label }}
                    </button>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
