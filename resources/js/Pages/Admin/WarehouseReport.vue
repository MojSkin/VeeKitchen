<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import JalaliDatePicker from '@/Components/JalaliDatePicker.vue';
import { faDigits, formatToman } from '@/lib/format';
import { formatJalaliDay } from '@/lib/jalali';

const props = defineProps({
    range: { type: Object, required: true },
    report: { type: Object, required: true },
});

/* ── Range selector ───────────────────────────────────────────── */

const presets = [
    { value: 'today', label: 'امروز' },
    { value: 'last7', label: '۷ روز گذشته' },
    { value: 'week', label: 'این هفته' },
    { value: 'month', label: 'این ماه' },
    { value: 'custom', label: 'بازهٔ دلخواه' },
];

const activePreset = ref(props.range.preset);
const customFrom = ref(isoToDate(props.report.from));
const customTo = ref(isoToDate(props.report.to));
const applying = ref(false);

/** Range-bound ISO → picker Date (noon keeps the UTC day stable). */
function isoToDate(iso) {
    const date = new Date(iso);

    return new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate(), 12));
}

/** Picker Date → the Y-m-d wire format the backend's filters expect. */
function isoOf(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}T12:00:00.000Z`;
}

function applyPreset(preset) {
    if (preset === 'custom') {
        activePreset.value = 'custom';

        return; // Wait for the user to pick dates and press «اعمال».
    }

    activePreset.value = preset;
    reload({ range: preset });
}

function applyCustom() {
    if (customFrom.value === null || customTo.value === null) {
        return;
    }

    reload({ range: 'custom', from: isoOf(customFrom.value), to: isoOf(customTo.value) });
}

function reload(params) {
    applying.value = true;

    router.get(
        route('admin.inventory.report'),
        params,
        {
            preserveScroll: true,
            preserveState: true,
            only: ['range', 'report'],
            onFinish: () => {
                applying.value = false;
            },
        },
    );
}

// Keep local inputs in sync when the server resolves a different range.
watch(
    () => props.range,
    (next) => {
        activePreset.value = next.preset;
        customFrom.value = toDateInput(next.from);
        customTo.value = toDateInput(next.to);
    },
);

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
    consumption: 'bg-lajvard-600/15 text-saffron-600 dark:text-saffron-400',
    waste: 'bg-red-500/15 text-red-500 dark:text-red-400',
    adjustment: 'bg-night-500/15 text-night-700 dark:text-night-300',
    purchase: 'bg-pistachio-600/15 text-pistachio-600 dark:text-pistachio-400',
    return: 'bg-pistachio-600/15 text-pistachio-600 dark:text-pistachio-400',
};

function quantity(amount) {
    const value = Math.abs(amount).toLocaleString('en-US', { maximumFractionDigits: 3 });

    return (amount < 0 ? '−' : amount > 0 ? '+' : '') + faDigits(value);
}

function formatDay(iso) {
    // UTC parts — same basis as the data boundaries, so an end-of-day
    // boundary never slides the headline into the next day.
    return formatJalaliDay(iso);
}

const rangeHeadline = computed(() => {
    if (props.range.preset === 'today') {
        return 'گزارش انبار — امروز';
    }

    if (props.range.preset === 'custom') {
        return `گزارش انبار — از ${formatDay(props.range.from)} تا ${formatDay(props.range.to)}`;
    }

    return `گزارش انبار — ${props.range.label}`;
});

function refresh() {
    reload({ range: activePreset.value });
}

/** Server-resolved bound ISO → Y-m-d wire format for export URLs. */
function toDateWire(iso) {
    const date = new Date(iso);
    const year = date.getUTCFullYear();
    const month = String(date.getUTCMonth() + 1).padStart(2, '0');
    const day = String(date.getUTCDate()).padStart(2, '0');

    return `${year}-${month}-${day}T12:00:00.000Z`;
}

/**
 * Exports carry the *server-resolved* bounds of the visible report
 * (not the raw preset) so the file always matches what's on screen.
 * Both are binary responses — triggered via JS so the SPA never falls
 * back to a full-page <a> navigation.
 */
function exportQuery(format) {
    return {
        format,
        range: props.range.preset,
        from: toDateWire(props.range.from),
        to: toDateWire(props.range.to),
    };
}

function downloadExport(format) {
    fetch(route('admin.inventory.report.export', exportQuery(format)), {
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
            anchor.download = `warehouse-report-${toDateInput(props.range.from)}.${format === 'csv' ? 'csv' : 'xlsx'}`;
            anchor.click();
            URL.revokeObjectURL(objectUrl);
        })
        .catch(() => window.alert('دانلود فایل ناموفق بود. دوباره تلاش کنید.'));
}

function openExport(format) {
    window.open(route('admin.inventory.report.export', exportQuery(format)), '_blank', 'noopener');
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-5xl px-4 py-6">
            <header class="mb-4">
                <h1 class="text-2xl font-bold">{{ rangeHeadline }}</h1>
                <p class="mt-1 text-sm opacity-60">
                    گردش انبار به تفکیک نوع حرکت ·
                    {{ faDigits(report.movement_count) }} ردیف دفتر کل
                </p>
            </header>

            <!-- Range controls -->
            <section class="glass mb-4 rounded-2xl p-4">
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-for="preset in presets"
                        :key="preset.value"
                        type="button"
                        class="glass-flat cursor-pointer rounded-xl px-3 py-1.5 text-xs font-bold transition disabled:cursor-default"
                        :class="activePreset === preset.value ? 'ring-1 ring-lajvard-500/60' : 'hover:bg-white/10'"
                        :disabled="applying"
                        @click="applyPreset(preset.value)"
                    >
                        {{ preset.label }}
                    </button>
                </div>

                <div
                    v-if="activePreset === 'custom'"
                    class="mt-3 flex flex-wrap items-end gap-2 border-t border-white/10 pt-3"
                >
                    <label class="block">
                        <span class="mb-1 block text-xs opacity-60">از تاریخ</span>
                        <JalaliDatePicker
                            v-model="customFrom"
                            placeholder="از تاریخ"
                        />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-xs opacity-60">تا تاریخ</span>
                        <JalaliDatePicker
                            v-model="customTo"
                            placeholder="تا تاریخ"
                        />
                    </label>
                    <button
                        type="button"
                        class="cursor-pointer rounded-xl bg-lajvard-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-lajvard-700 disabled:opacity-40"
                        :disabled="applying || customFrom === null || customTo === null"
                        @click="applyCustom"
                    >
                        اعمال
                    </button>
                </div>
            </section>

            <section class="mb-4 text-left">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <button
                        type="button"
                        class="glass-flat cursor-pointer rounded-xl px-4 py-2 text-xs transition hover:bg-white/10"
                        :disabled="applying"
                        @click="refresh"
                    >
                        به‌روزرسانی
                    </button>

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="glass-flat cursor-pointer rounded-xl px-4 py-2 text-xs font-bold transition hover:bg-white/10"
                            @click="downloadExport('xlsx')"
                        >
                            دانلود اکسل (XLSX)
                        </button>
                        <button
                            type="button"
                            class="glass-flat cursor-pointer rounded-xl px-4 py-2 text-xs font-bold transition hover:bg-white/10"
                            @click="downloadExport('csv')"
                        >
                            دانلود CSV
                        </button>
                        <button
                            type="button"
                            class="glass-flat cursor-pointer rounded-xl px-4 py-2 text-xs font-bold transition hover:bg-white/10"
                            @click="openExport('print')"
                        >
                            نسخهٔ چاپی
                        </button>
                    </div>
                </div>
            </section>

            <!-- Balance pipe -->
            <section class="glass mb-6 rounded-2xl p-5">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="min-w-28 flex-1 rounded-2xl bg-pistachio-600/15 p-4 text-center">
                        <p class="text-xs opacity-60">ورودی بازه</p>
                        <p class="mt-1 text-2xl font-bold text-pistachio-600 dark:text-pistachio-400">
                            {{ quantity(inflow) }}
                        </p>
                    </div>
                    <div class="text-2xl opacity-30" aria-hidden="true">←</div>
                    <div class="min-w-28 flex-1 rounded-2xl bg-red-500/15 p-4 text-center">
                        <p class="text-xs opacity-60">خروجی بازه</p>
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

                <!-- Rial value of the range's turnover -->
                <div
                    v-if="report.inflow_value > 0 || report.outflow_value > 0"
                    class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-white/10 pt-4 text-sm"
                >
                    <p class="opacity-70">
                        ارزش خروجی:
                        <strong class="text-red-500 dark:text-red-400">{{ formatToman(report.outflow_value) }}</strong>
                        تومان
                        <span class="opacity-50">(مصرف + ضایعات)</span>
                    </p>
                    <p class="opacity-70">
                        ارزش ورودی:
                        <strong class="text-pistachio-600 dark:text-pistachio-400">{{ formatToman(report.inflow_value) }}</strong>
                        تومان
                        <span class="opacity-50">(خرید + بازگشت + اصلاح)</span>
                    </p>
                </div>
            </section>

            <!-- Per-type breakdown -->
            <div class="space-y-4">
                <section
                    v-for="type in activeTypes"
                    :key="type.type"
                    class="glass rounded-2xl p-5"
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
                                <th class="pb-2 text-right font-medium">جمع مقدار</th>
                                <th class="pb-2 text-right font-medium">قیمت واحد</th>
                                <th class="pb-2 text-left font-medium">ارزش ریالی</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in type.items"
                                :key="item.name"
                                class="border-t border-white/5"
                            >
                                <td class="py-2 font-bold">{{ item.name }}</td>
                                <td class="py-2">
                                    {{ quantity(item.total) }}
                                    <span class="opacity-50">{{ item.unit_label }}</span>
                                </td>
                                <td class="py-2">
                                    <span
                                        v-if="item.effective_cost > 0"
                                        title="قیمت واحد مؤثر این گردش — از اسنپ‌شات قیمت لحظهٔ ثبت هر حرکت"
                                    >
                                        {{ faDigits(item.effective_cost.toLocaleString('en-US')) }} تومان
                                    </span>
                                    <span v-else class="opacity-40" title="قیمتی برای این متریال ثبت نشده">—</span>
                                </td>
                                <td class="py-2 text-left">
                                    <span v-if="item.value > 0" :title="`جمع مقدار × قیمت واحد مؤثر`">
                                        {{ formatToman(item.value) }} تومان
                                    </span>
                                    <span v-else class="opacity-40">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <p
                    v-if="!hasMovements"
                    class="glass rounded-2xl p-8 text-center opacity-60"
                >
                    در این بازه حرکتی در انبار ثبت نشده است.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
