<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { faDigits } from '@/lib/format';

const props = defineProps({
    branch: { type: Object, required: true },
    report: { type: Object, required: true },
});

/* ── Day balance pipe (signature) ─────────────────────────────── */

const inflow = computed(() =>
    props.report.types.reduce((sum, type) => sum + Math.max(0, type.total), 0),
);

const outflow = computed(() =>
    Math.abs(props.report.types.reduce((sum, type) => sum + Math.min(0, type.total), 0)),
);

const net = computed(() => inflow.value - outflow.value);

const hasMovements = computed(() => props.report.movement_count > 0);

/* ── Type sections in narrative order ─────────────────────────── */

const typeOrder = ['consumption', 'waste', 'adjustment', 'purchase', 'return'];

const activeTypes = computed(() =>
    typeOrder
        .map((value) => props.report.types.find((type) => type.type === value))
        .filter((type) => type && type.movements > 0),
);

const typeTone = {
    consumption: 'bg-saffron-500/15 text-saffron-600 dark:text-saffron-400',
    waste: 'bg-red-500/15 text-red-500 dark:text-red-400',
    adjustment: 'bg-night-500/15 text-night-700 dark:text-night-300',
    purchase: 'bg-pistachio-500/15 text-pistachio-600 dark:text-pistachio-400',
    return: 'bg-pistachio-500/15 text-pistachio-600 dark:text-pistachio-400',
};

function quantity(amount) {
    const value = Math.abs(amount).toLocaleString('en-US', { maximumFractionDigits: 3 });

    return (amount < 0 ? '−' : amount > 0 ? '+' : '') + faDigits(value);
}

function refresh() {
    router.reload({ only: ['report'], preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-5xl px-4 py-6">
            <header class="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">گزارش انبار — امروز</h1>
                    <p class="mt-1 text-sm opacity-60">
                        گردش امروز انبار «{{ branch.name }}» به تفکیک نوع حرکت ·
                        {{ faDigits(report.movement_count) }} ردیف دفتر کل
                    </p>
                </div>
                <button
                    type="button"
                    class="glass-flat cursor-pointer rounded-xl px-4 py-2 text-sm font-bold transition hover:bg-white/10"
                    @click="refresh"
                >
                    به‌روزرسانی
                </button>
            </header>

            <!-- Balance pipe -->
            <section class="glass mb-6 rounded-glass p-5">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="min-w-28 flex-1 rounded-2xl bg-pistachio-500/15 p-4 text-center">
                        <p class="text-xs opacity-60">ورودی امروز</p>
                        <p class="mt-1 text-2xl font-bold text-pistachio-600 dark:text-pistachio-400">
                            {{ quantity(inflow) }}
                        </p>
                    </div>
                    <div class="text-2xl opacity-30" aria-hidden="true">←</div>
                    <div class="min-w-28 flex-1 rounded-2xl bg-red-500/15 p-4 text-center">
                        <p class="text-xs opacity-60">خروجی امروز</p>
                        <p class="mt-1 text-2xl font-bold text-red-500 dark:text-red-400">
                            {{ quantity(-outflow) }}
                        </p>
                    </div>
                    <div class="text-2xl opacity-30" aria-hidden="true">=</div>
                    <div class="glass-flat min-w-28 rounded-2xl p-4 text-center">
                        <p class="text-xs opacity-60">جمع خالص</p>
                        <p
                            class="mt-1 text-2xl font-bold"
                            :class="net >= 0 ? 'text-pistachio-600 dark:text-pistachio-400' : 'text-red-500 dark:text-red-400'"
                        >
                            {{ quantity(net) }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- Per-type breakdown -->
            <div class="space-y-4">
                <section
                    v-for="type in activeTypes"
                    :key="type.type"
                    class="glass rounded-glass p-5"
                >
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="rounded-full px-3 py-1 text-xs font-bold" :class="typeTone[type.type]">
                                {{ type.label }}
                            </span>
                            <span class="text-xs opacity-50">{{ faDigits(type.movements) }} ردیف</span>
                        </div>
                        <p class="text-sm font-bold" :class="type.total < 0 ? 'text-red-500 dark:text-red-400' : 'text-pistachio-600 dark:text-pistachio-400'">
                            جمع: {{ quantity(type.total) }}
                        </p>
                    </div>

                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs opacity-50">
                                <th class="pb-2 text-right font-medium">متریال</th>
                                <th class="pb-2 text-left font-medium">جمع مقدار</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in type.items"
                                :key="item.name"
                                class="border-t border-white/5"
                            >
                                <td class="py-2 font-bold">{{ item.name }}</td>
                                <td class="py-2 text-left">
                                    {{ quantity(item.total) }}
                                    <span class="opacity-50">{{ item.unit_label }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <p
                    v-if="!hasMovements"
                    class="glass rounded-glass p-8 text-center opacity-60"
                >
                    امروز حرکتی در انبار ثبت نشده است.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
