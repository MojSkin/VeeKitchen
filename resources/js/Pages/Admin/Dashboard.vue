<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { faDigits, formatToman } from '@/lib/format';
import { formatJalaliShort } from '@/lib/jalali';

const props = defineProps({
    salesChart: { type: Object, required: true },
    today: { type: Object, required: true },
    orderStatuses: { type: Array, required: true },
    lowStock: { type: Array, required: true },
    tables: { type: Array, required: true },
    shiftKpis: { type: Object, required: true },
});

/* ── Sales sparkline geometry ─────────────────────────────────── */

const chartWidth = 640;
const chartHeight = 160;
const chartPadding = { top: 12, right: 8, bottom: 22, left: 8 };

const chartMax = computed(() =>
    Math.max(...props.salesChart.days.map((day) => day.revenue), 1),
);

const chartPoints = computed(() => {
    const { top, right, bottom, left } = chartPadding;
    const innerWidth = chartWidth - left - right;
    const innerHeight = chartHeight - top - bottom;
    const count = props.salesChart.days.length;

    return props.salesChart.days.map((day, index) => ({
        ...day,
        label: formatJalaliShort(`${day.date}T12:00:00.000Z`),
        x: left + (count === 1 ? innerWidth / 2 : (index / (count - 1)) * innerWidth),
        y: top + innerHeight - (day.revenue / chartMax.value) * innerHeight,
    }));
});

const linePath = computed(() =>
    chartPoints.value
        .map((point, index) => `${index === 0 ? 'M' : 'L'}${point.x.toFixed(1)},${point.y.toFixed(1)}`)
        .join(' '),
);

const areaPath = computed(() => {
    if (chartPoints.value.length === 0) {
        return '';
    }

    const { bottom } = chartPadding;
    const baseline = chartHeight - bottom;
    const first = chartPoints.value[0];
    const last = chartPoints.value[chartPoints.value.length - 1];

    return `${linePath.value} L${last.x.toFixed(1)},${baseline} L${first.x.toFixed(1)},${baseline} Z`;
});

const statusTone = {
    awaiting_payment: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
    queued: 'bg-saffron-500/15 text-saffron-600 dark:text-saffron-400',
    preparing: 'bg-sky-500/15 text-sky-600 dark:text-sky-400',
    ready: 'bg-pistachio-500/15 text-pistachio-600 dark:text-pistachio-400',
};

const tableTone = {
    free: 'bg-pistachio-500/15 text-pistachio-600 dark:text-pistachio-400',
    reserved: 'bg-sky-500/15 text-sky-600 dark:text-sky-400',
    ordering: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
    awaiting_settlement: 'bg-red-500/15 text-red-500 dark:text-red-400',
};

const openOrders = computed(() =>
    props.orderStatuses.reduce((sum, status) => sum + status.count, 0),
);

const freeTables = computed(() =>
    props.tables.find((table) => table.value === 'free')?.count ?? 0,
);

const totalTables = computed(() =>
    props.tables.reduce((sum, table) => sum + table.count, 0),
);

function ratio(current, threshold) {
    if (threshold <= 0) {
        return current <= 0 ? 100 : 0;
    }

    return Math.min(100, Math.round((current / threshold) * 100));
}

/**
 * The workbook is a streamed binary — not an Inertia visit. Fetched via
 * JS and saved as a blob, so the SPA state (scroll, filters) is never
 * disturbed by a full-page navigation.
 */
function downloadWorkbook() {
    fetch(route('admin.dashboard.export'), {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
        .then((response) => {
            if (!response.ok) throw new Error(`export failed: ${response.status}`);

            return response.blob();
        })
        .then((blob) => {
            const objectUrl = URL.createObjectURL(blob);
            const anchor = document.createElement('a');
            anchor.href = objectUrl;
            anchor.download = 'dashboard-export.xlsx';
            anchor.click();
            URL.revokeObjectURL(objectUrl);
        })
        .catch(() => window.alert('دانلود فایل اکسل ناموفق بود. دوباره تلاش کنید.'));
}

function shortNumber(value) {
    if (value >= 1_000_000) {
        return `${faDigits((value / 1_000_000).toFixed(1))}م`;
    }

    if (value >= 1_000) {
        return `${faDigits(Math.round(value / 1_000))}ه`;
    }

    return faDigits(value);
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-6xl px-4 py-6">
            <header class="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">داشبورد مدیریتی</h1>
                    <p class="mt-1 text-sm opacity-60">
                        نگاه یک‌صفحه‌ای به فروش، آشپزخانه و انبار شعبه
                    </p>
                </div>
                <button
                    type="button"
                    class="glass cursor-pointer rounded-glass px-4 py-2 text-sm font-bold transition hover:bg-white/10"
                    title="دانلود همین ارقام به‌صورت اکسل (KPIها + نمودار ۱۴ روز)"
                    @click="downloadWorkbook"
                >
                    دانلود اکسل (XLSX) ↓
                </button>
            </header>

            <!-- KPI row -->
            <section class="mb-6 grid gap-4 sm:grid-cols-3">
                <article class="glass rounded-glass p-5">
                    <p class="text-xs opacity-60">فروش امروز</p>
                    <p class="mt-1 text-2xl font-bold">{{ formatToman(today.revenue) }}</p>
                    <p class="mt-1 text-xs opacity-50">تومان</p>
                </article>
                <article class="glass rounded-glass p-5">
                    <p class="text-xs opacity-60">سفارش‌های امروز</p>
                    <p class="mt-1 text-2xl font-bold">{{ faDigits(today.orders) }}</p>
                    <p class="mt-1 text-xs opacity-50">
                        میانگین سبد: {{ formatToman(today.average_ticket) }} تومان
                    </p>
                </article>
                <article class="glass rounded-glass p-5">
                    <p class="text-xs opacity-60">سفارش‌های باز</p>
                    <p class="mt-1 text-2xl font-bold">{{ faDigits(openOrders) }}</p>
                    <p class="mt-1 text-xs opacity-50">منتظر پرداخت تا آمادهٔ تحویل</p>
                </article>
            </section>

            <!-- Sales chart -->
            <section class="glass mb-6 rounded-glass p-5">
                <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="font-bold">نمودار فروش ۱۴ روز گذشته</h2>
                    <p class="text-xs opacity-60">
                        جمع: {{ formatToman(salesChart.revenue_total) }} تومان ·
                        {{ faDigits(salesChart.orders_total) }} سفارش
                        <template v-if="salesChart.best_day_label">
                            · بهترین روز: {{ salesChart.best_day_label }}
                        </template>
                    </p>
                </div>

                <svg
                    :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
                    class="w-full"
                    role="img"
                    aria-label="نمودار روند فروش روزانه"
                >
                    <!-- gridlines -->
                    <line
                        v-for="tick in 3"
                        :key="tick"
                        :x1="chartPadding.left"
                        :x2="chartWidth - chartPadding.right"
                        :y1="chartPadding.top + ((chartHeight - chartPadding.top - chartPadding.bottom) / 3) * (tick - 1)"
                        :y2="chartPadding.top + ((chartHeight - chartPadding.top - chartPadding.bottom) / 3) * (tick - 1)"
                        class="stroke-white/10"
                        stroke-dasharray="3 5"
                    />
                    <!-- area + line -->
                    <path :d="areaPath" class="fill-saffron-500/15" />
                    <path :d="linePath" class="stroke-saffron-500" fill="none" stroke-width="2.5" stroke-linejoin="round" />
                    <!-- dots + x labels -->
                    <g v-for="point in chartPoints" :key="point.date">
                        <circle :cx="point.x" :cy="point.y" r="3" class="fill-saffron-500">
                            <title>{{ point.label }} — {{ formatToman(point.revenue) }} تومان ({{ faDigits(point.orders) }} سفارش)</title>
                        </circle>
                        <text
                            v-if="point.date.endsWith(new Date().getDate() < 10 ? '0' + new Date().getDate() : String(new Date().getDate()))"
                            :x="point.x"
                            :y="chartHeight - 6"
                            text-anchor="middle"
                            class="fill-current text-[10px] opacity-60"
                        >امروز</text>
                        <text
                            v-else-if="chartPoints.indexOf(point) % 2 === 0"
                            :x="point.x"
                            :y="chartHeight - 6"
                            text-anchor="middle"
                            class="fill-current text-[10px] opacity-40"
                        >{{ point.label }}</text>
                    </g>
                </svg>
            </section>

            <div class="mb-6 grid gap-4 lg:grid-cols-2">
                <!-- Order pipeline -->
                <section class="glass rounded-glass p-5">
                    <h2 class="mb-3 font-bold">خط لولهٔ سفارش‌ها</h2>
                    <div class="space-y-2">
                        <div
                            v-for="status in orderStatuses"
                            :key="status.value"
                            class="flex items-center justify-between gap-3"
                        >
                            <span class="rounded-full px-3 py-1 text-xs font-bold" :class="statusTone[status.value]">
                                {{ status.label }}
                            </span>
                            <span class="text-sm font-bold">{{ faDigits(status.count) }}</span>
                        </div>
                    </div>
                </section>

                <!-- Shift KPIs (end-of-day pipeline) -->
                <section class="glass rounded-glass p-5">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="font-bold">شیفت‌های امروز</h2>
                        <Link
                            :href="route('admin.end-of-day')"
                            class="text-xs font-bold text-lajvard-600 transition hover:opacity-80 dark:text-lajvard-400"
                        >
                            خط لولهٔ پایان روز →
                        </Link>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between gap-3">
                            <span class="rounded-full bg-pistachio-500/15 px-3 py-1 text-xs font-bold text-pistachio-600 dark:text-pistachio-400">باز</span>
                            <span class="text-sm font-bold">{{ faDigits(shiftKpis.open_shifts) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="rounded-full bg-night-500/15 px-3 py-1 text-xs font-bold text-night-700 dark:text-night-300">بسته</span>
                            <span class="text-sm font-bold">{{ faDigits(shiftKpis.closed_shifts) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span
                                class="rounded-full px-3 py-1 text-xs font-bold"
                                :class="shiftKpis.discrepancy_shifts > 0 ? 'bg-red-500/15 text-red-500 dark:text-red-400' : 'bg-pistachio-500/15 text-pistachio-600 dark:text-pistachio-400'"
                            >
                                مغایرت‌دار
                            </span>
                            <span class="text-sm font-bold">{{ faDigits(shiftKpis.discrepancy_shifts) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3 border-t border-white/10 pt-2">
                            <span class="text-xs opacity-60">جمع مغایرت امروز</span>
                            <span
                                class="text-sm font-bold"
                                :class="shiftKpis.total_discrepancy < 0 ? 'text-red-500 dark:text-red-400' : ''"
                            >
                                {{ formatToman(shiftKpis.total_discrepancy) }}
                            </span>
                        </div>
                    </div>
                </section>

                <!-- Tables -->
                <section class="glass rounded-glass p-5">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="font-bold">سالن غذاخوری</h2>
                        <span class="text-xs opacity-50">{{ faDigits(freeTables) }} آزاد از {{ faDigits(totalTables) }}</span>
                    </div>
                    <div class="space-y-2">
                        <div
                            v-for="table in tables"
                            :key="table.value"
                            class="flex items-center justify-between gap-3"
                        >
                            <span class="rounded-full px-3 py-1 text-xs font-bold" :class="tableTone[table.value]">
                                {{ table.label }}
                            </span>
                            <span class="text-sm font-bold">{{ faDigits(table.count) }}</span>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Low stock -->
            <section class="glass rounded-glass p-5">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-bold">موجودی کم</h2>
                    <Link :href="route('admin.inventory')" class="text-xs opacity-50 transition hover:opacity-90">
                        رفتن به انبار →
                    </Link>
                </div>

                <p v-if="lowStock.length === 0" class="py-4 text-center text-sm opacity-60">
                    همهٔ متریال‌ها بالای خط هشدار هستند.
                </p>

                <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="item in lowStock"
                        :key="item.id"
                        class="glass-flat rounded-2xl p-4"
                    >
                        <div class="flex items-baseline justify-between gap-2">
                            <h3 class="text-sm font-bold">{{ item.name }}</h3>
                            <span class="text-xs text-red-500 dark:text-red-400">زیر خط هشدار</span>
                        </div>
                        <p class="mt-1 text-xs opacity-60">
                            موجودی: {{ faDigits(item.current.toLocaleString('en-US', { maximumFractionDigits: 3 })) }}
                            {{ item.unit_label }}
                            · خط هشدار: {{ faDigits(item.threshold.toLocaleString('en-US', { maximumFractionDigits: 3 })) }}
                        </p>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-white/10" aria-hidden="true">
                            <div
                                class="h-full rounded-full bg-red-400/80"
                                :style="{ width: `${ratio(item.current, item.threshold)}%` }"
                            />
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
