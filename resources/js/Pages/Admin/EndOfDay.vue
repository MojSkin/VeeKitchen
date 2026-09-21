<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { faDigits, formatToman, formatTomanWithUnit } from '@/lib/format';
import { formatJalaliDayTime } from '@/lib/jalali';

const props = defineProps({
    branch: { type: Object, required: true },
    pipeline: { type: Object, required: true },
});

function money(value) {
    return formatTomanWithUnit(value ?? 0);
}

function signedMoney(value) {
    if (value === 0) {
        return formatToman(0);
    }

    return (value > 0 ? '+' : '−') + formatToman(Math.abs(value));
}

/* Binary exports open via window.open — the SPA state stays untouched. */
function exportUrl(shiftId, format) {
    return route('admin.shift-settlement.export', {
        shift: shiftId,
        format,
    });
}

function downloadXlsx(shiftId) {
    window.open(exportUrl(shiftId, 'xlsx'), '_blank');
}

function openPrint(shiftId) {
    window.open(exportUrl(shiftId, 'print'), '_blank');
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-6xl px-4 py-6">
            <header class="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">خط لولهٔ پایان روز</h1>
                    <p class="mt-1 text-sm opacity-60">
                        شیفت‌های امروزِ {{ branch.name }} با تسویهٔ مالی و مغایرت‌ها
                    </p>
                </div>
                <Link
                    :href="route('admin.dashboard')"
                    class="glass-flat rounded-xl px-4 py-2 text-sm font-bold transition"
                >
                    ← داشبورد
                </Link>
            </header>

            <!-- Day summary -->
            <section class="glass mb-4 grid gap-4 rounded-2xl p-5 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <p class="text-xs opacity-60">شیفت باز</p>
                    <p class="mt-1 text-2xl font-black text-pistachio-600 dark:text-pistachio-400">
                        {{ faDigits(pipeline.open_shifts) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs opacity-60">شیفت بسته</p>
                    <p class="mt-1 text-2xl font-black">{{ faDigits(pipeline.closed_shifts) }}</p>
                </div>
                <div>
                    <p class="text-xs opacity-60">جمع مغایرت امروز</p>
                    <p
                        class="mt-1 text-2xl font-black"
                        :class="pipeline.total_discrepancy < 0 ? 'text-red-500 dark:text-red-400' : ''"
                    >
                        {{ signedMoney(pipeline.total_discrepancy) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs opacity-60">سفارش امروز (ثبت/پرداخت/کنسل)</p>
                    <p class="mt-1 text-2xl font-black">
                        {{ faDigits(pipeline.orders.placed) }}
                        <span class="text-sm opacity-50">/ {{ faDigits(pipeline.orders.paid) }} / {{ faDigits(pipeline.orders.cancelled) }}</span>
                    </p>
                </div>
            </section>

            <!-- Shifts board -->
            <section class="space-y-3">
                <article
                    v-for="shift in pipeline.shifts"
                    :key="shift.id"
                    class="glass rounded-2xl p-5"
                >
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-3">
                            <h2 class="font-bold">{{ shift.cashier }}</h2>
                            <span
                                class="rounded-full px-3 py-1 text-xs font-bold"
                                :class="shift.is_open
                                    ? 'bg-pistachio-600/15 text-pistachio-600 dark:text-pistachio-400'
                                    : 'bg-night-500/15 text-night-700 dark:text-night-300'"
                            >
                                {{ shift.is_open ? 'باز' : 'بسته' }}
                            </span>
                            <span
                                v-if="shift.is_kitchen"
                                class="rounded-full bg-night-500/15 px-3 py-1 text-xs font-bold text-night-700 dark:text-night-300"
                            >
                                آشپزخانه
                            </span>
                            <span
                                v-if="! shift.is_open && shift.discrepancy !== 0"
                                class="rounded-full bg-red-500/15 px-3 py-1 text-xs font-bold text-red-500 dark:text-red-400"
                            >
                                مغایرت {{ signedMoney(shift.discrepancy) }}
                            </span>
                        </div>
                        <p class="text-xs opacity-60">
                            {{ formatJalaliDayTime(shift.opened_at) }}
                            <template v-if="shift.closed_at">
                                تا {{ formatJalaliDayTime(shift.closed_at) }}
                            </template>
                            <template v-else> — در جریان</template>
                        </p>
                    </div>

                    <div class="grid gap-3 text-sm sm:grid-cols-3 lg:grid-cols-6">
                        <div>
                            <p class="text-xs opacity-60">موجودی اولیه</p>
                            <p class="mt-0.5 font-bold">{{ money(shift.opening_cash) }}</p>
                        </div>
                        <template v-if="! shift.is_kitchen">
                            <div>
                                <p class="text-xs opacity-60">دریافت نقدی</p>
                                <p class="mt-0.5 font-bold">{{ money(shift.cash_payments) }}</p>
                            </div>
                            <div>
                                <p class="text-xs opacity-60">دریافت کارت</p>
                                <p class="mt-0.5 font-bold">{{ money(shift.card_payments) }}</p>
                            </div>
                            <div>
                                <p class="text-xs opacity-60">حرکت نقدی (خالص)</p>
                                <p class="mt-0.5 font-bold">{{ signedMoney(shift.movements_net) }}</p>
                            </div>
                            <div>
                                <p class="text-xs opacity-60">صندوق انتظار</p>
                                <p class="mt-0.5 font-bold">{{ money(shift.expected_cash) }}</p>
                            </div>
                            <div>
                                <p class="text-xs opacity-60">{{ shift.is_open ? '— هنوز شمارش نشده' : 'شمارش واقعی' }}</p>
                                <p class="mt-0.5 font-bold">
                                    {{ shift.is_open ? '—' : money(shift.counted_cash) }}
                                </p>
                            </div>
                        </template>
                        <p
                            v-else
                            class="self-center text-xs opacity-50"
                        >
                            شیفت حضور آشپزخانه — بدون تسویهٔ صندوق
                        </p>
                    </div>

                    <div
                        v-if="! shift.is_kitchen && ! shift.is_open"
                        class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-white/20 pt-3 dark:border-white/5"
                    >
                        <p
                            v-if="shift.closed_by"
                            class="text-xs opacity-50"
                        >
                            بستن توسط {{ shift.closed_by }}
                        </p>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="glass-flat cursor-pointer rounded-xl px-3 py-1.5 text-xs font-bold transition hover:opacity-80"
                                @click="openPrint(shift.id)"
                            >
                                نسخهٔ چاپی
                            </button>
                            <button
                                type="button"
                                class="glass-flat cursor-pointer rounded-xl px-3 py-1.5 text-xs font-bold transition hover:opacity-80"
                                @click="downloadXlsx(shift.id)"
                            >
                                دانلود اکسل (XLSX)
                            </button>
                        </div>
                    </div>

                    <p
                        v-if="! shift.is_open && shift.closed_by && shift.is_kitchen"
                        class="mt-3 text-xs opacity-50"
                    >
                        بستن توسط {{ shift.closed_by }}
                    </p>
                </article>

                <p
                    v-if="pipeline.shifts.length === 0"
                    class="glass rounded-2xl p-8 text-center text-sm opacity-60"
                >
                    امروز شیفتِ ثبت‌شده‌ای نیست.
                </p>
            </section>
        </div>
    </AppLayout>
</template>
