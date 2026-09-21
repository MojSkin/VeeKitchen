<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { faDigits, formatToman, formatTomanWithUnit } from '@/lib/format';
import { formatJalaliDayTime } from '@/lib/jalali';

const props = defineProps({
    branchId: { type: Number, required: true },
    shift: { type: Object, default: null },
    history: { type: Array, default: () => [] },
});

/* ── Open form ──────────────────────────────────────────────────── */

const openingCash = ref('');
const opening = ref(false);

function submitOpen() {
    opening.value = true;

    router.post(route('cashier.shift.open'), {
        opening_cash: Math.round(Number(openingCash.value) || 0),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            openingCash.value = '';
        },
        onFinish: () => {
            opening.value = false;
        },
    });
}

/* ── Cash movement form ─────────────────────────────────────────── */

const movement = ref({ type: 'withdrawal', amount: '', reason: '' });
const moving = ref(false);

const movementTypes = [
    { value: 'withdrawal', label: 'برداشت (خروج از صندوق)' },
    { value: 'deposit', label: 'واریز (شارژ صندوق)' },
    { value: 'adjustment', label: 'اصلاح دفتری' },
];

function submitMovement() {
    moving.value = true;

    router.post(route('cashier.shift.movement'), {
        type: movement.value.type,
        amount: Math.round(Number(movement.value.amount) || 0),
        reason: movement.value.reason,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            movement.value.amount = '';
            movement.value.reason = '';
        },
        onFinish: () => {
            moving.value = false;
        },
    });
}

/* ── Close form ─────────────────────────────────────────────────── */

const countedCash = ref('');
const closing = ref(false);

const expectedCash = computed(() => props.shift?.expected_cash ?? 0);

const liveDiscrepancy = computed(() => {
    if (countedCash.value === '') {
        return null;
    }

    return Math.round(Number(countedCash.value) || 0) - expectedCash.value;
});

function submitClose() {
    if (!window.confirm('شیفت بسته شود؟ بعد از بستن، پرداخت جدید تا شیفت بعدی ممکن نیست.')) {
        return;
    }

    closing.value = true;

    router.post(route('cashier.shift.close'), {
        counted_cash: Math.round(Number(countedCash.value) || 0),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            countedCash.value = '';
        },
        onFinish: () => {
            closing.value = false;
        },
    });
}

/* ── Presentation ───────────────────────────────────────────────── */

function dayTime(iso) {
    if (iso === null || iso === undefined) {
        return '—';
    }

    return formatJalaliDayTime(iso);
}

function discrepancyText(value) {
    if (value === null || value === undefined) {
        return '—';
    }

    if (value === 0) {
        return 'مغایرت صفر ✓';
    }

    return value > 0
        ? `مازاد ${formatToman(value)}`
        : `کسری ${formatToman(Math.abs(value))}`;
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-3xl px-4 py-6">
            <header class="mb-6">
                <h1 class="text-2xl font-bold">شیفت و صندوق</h1>
                <p class="mt-1 text-sm opacity-60">
                    پرداخت‌ها فقط داخل شیفت باز ثبت می‌شوند.
                </p>
            </header>

            <!-- No open shift: the opening card -->
            <section
                v-if="!shift"
                class="glass rounded-2xl p-6"
            >
                <h2 class="text-lg font-bold">شروع شیفت</h2>
                <p class="mt-1 text-sm opacity-60">
                    موجودی اولیهٔ صندوق (پولِ شروع روز) را وارد کنید.
                </p>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <input
                        v-model="openingCash"
                        type="number"
                        min="0"
                        required
                        placeholder="مثلاً ۵۰۰۰۰۰"
                        class="glass-flat w-48 rounded-xl px-3 py-2 text-sm outline-none"
                        dir="ltr"
                    >
                    <button
                        type="button"
                        class="cursor-pointer rounded-xl bg-pistachio-600 px-6 py-2 text-sm font-bold text-white transition hover:bg-pistachio-700 disabled:opacity-40"
                        :disabled="opening || openingCash === ''"
                        @click="submitOpen"
                    >
                        باز کردن شیفت
                    </button>
                </div>
            </section>

            <!-- Open shift: live till -->
            <template v-else>
                <section class="glass rounded-2xl p-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold">شیفت باز</h2>
                        <span class="rounded-full bg-pistachio-600/15 px-3 py-1 text-xs font-bold text-pistachio-600 dark:text-pistachio-400">
                            از {{ dayTime(shift.opened_at) }}
                        </span>
                    </div>

                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                        <div class="glass-flat rounded-xl p-3">
                            <dt class="opacity-60">موجودی اولیه</dt>
                            <dd class="mt-1 font-bold">{{ formatTomanWithUnit(shift.opening_cash) }}</dd>
                        </div>
                        <div class="glass-flat rounded-xl p-3">
                            <dt class="opacity-60">دریافت نقدی</dt>
                            <dd class="mt-1 font-bold text-pistachio-600 dark:text-pistachio-400">{{ formatTomanWithUnit(shift.cash_payments) }}</dd>
                        </div>
                        <div class="glass-flat rounded-xl p-3">
                            <dt class="opacity-60">دریافت کارت‌خوان</dt>
                            <dd class="mt-1 font-bold">{{ formatTomanWithUnit(shift.card_payments) }}</dd>
                        </div>
                        <div class="glass-flat rounded-xl p-3">
                            <dt class="opacity-60">حرکات نقدی (خالص)</dt>
                            <dd class="mt-1 font-bold">{{ formatTomanWithUnit(shift.movements_net) }}</dd>
                        </div>
                        <div class="glass-flat col-span-2 rounded-xl p-3 ring-1 ring-lajvard-500/40">
                            <dt class="opacity-60">موجودی مورد انتظار صندوق</dt>
                            <dd class="mt-1 text-lg font-black text-saffron-600 dark:text-saffron-400">{{ formatTomanWithUnit(shift.expected_cash) }}</dd>
                        </div>
                    </dl>
                </section>

                <!-- Cash movement -->
                <section class="glass mt-4 rounded-2xl p-6">
                    <h2 class="text-lg font-bold">حرکت نقدی</h2>
                    <p class="mt-1 text-sm opacity-60">
                        برداشت از صندوق یا شارژ آن — هر حرکت با دلیل ثبت می‌شود.
                    </p>

                    <div class="mt-4 grid gap-2 sm:grid-cols-[1fr_1fr_2fr_auto]">
                        <select
                            v-model="movement.type"
                            class="glass-flat cursor-pointer rounded-xl px-3 py-2 text-sm outline-none"
                            aria-label="نوع حرکت"
                        >
                            <option v-for="type in movementTypes" :key="type.value" :value="type.value">
                                {{ type.label }}
                            </option>
                        </select>
                        <input
                            v-model="movement.amount"
                            type="number"
                            min="1"
                            required
                            placeholder="مبلغ (تومان)"
                            class="glass-flat rounded-xl px-3 py-2 text-sm outline-none"
                            dir="ltr"
                        >
                        <input
                            v-model="movement.reason"
                            type="text"
                            required
                            placeholder="دلیل (مثلاً واریز به خزانه)"
                            class="glass-flat rounded-xl px-3 py-2 text-sm outline-none"
                        >
                        <button
                            type="button"
                            class="cursor-pointer rounded-xl bg-lajvard-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-lajvard-700 disabled:opacity-40"
                            :disabled="moving || movement.amount === '' || movement.reason.trim() === ''"
                            @click="submitMovement"
                        >
                            ثبت
                        </button>
                    </div>
                </section>

                <!-- Close -->
                <section class="glass mt-4 rounded-2xl p-6">
                    <h2 class="text-lg font-bold">پایان شیفت</h2>
                    <p class="mt-1 text-sm opacity-60">
                        پول صندوق را بشمارید و عدد واقعی را وارد کنید؛ مغایرت خودکار محاسبه و ثبت می‌شود.
                    </p>

                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <input
                            v-model="countedCash"
                            type="number"
                            min="0"
                            required
                            placeholder="شمارش واقعی (تومان)"
                            class="glass-flat w-48 rounded-xl px-3 py-2 text-sm outline-none"
                            dir="ltr"
                        >
                        <button
                            type="button"
                            class="cursor-pointer rounded-xl bg-red-500/90 px-6 py-2 text-sm font-bold text-white transition hover:bg-red-600 disabled:opacity-40"
                            :disabled="closing || countedCash === ''"
                            @click="submitClose"
                        >
                            بستن شیفت
                        </button>
                    </div>

                    <p
                        v-if="liveDiscrepancy !== null"
                        class="mt-3 rounded-xl px-3 py-2 text-sm font-bold"
                        :class="liveDiscrepancy === 0
                            ? 'bg-pistachio-600/15 text-pistachio-600 dark:text-pistachio-400'
                            : 'bg-red-500/15 text-red-500'"
                    >
                        {{ discrepancyText(liveDiscrepancy) }}
                    </p>
                </section>
            </template>

            <!-- History -->
            <section
                v-if="history.length > 0"
                class="mt-8"
            >
                <h2 class="mb-3 text-lg font-bold">شیفت‌های قبلی</h2>

                <div class="space-y-2">
                    <article
                        v-for="past in history"
                        :key="past.id"
                        class="glass-flat rounded-xl p-4 text-sm"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-bold">
                                شیفت {{ faDigits(past.id) }} · {{ dayTime(past.opened_at) }} تا {{ dayTime(past.closed_at) }}
                            </p>
                            <span
                                class="rounded-full px-3 py-1 text-xs font-bold"
                                :class="past.discrepancy === 0
                                    ? 'bg-pistachio-600/15 text-pistachio-600 dark:text-pistachio-400'
                                    : 'bg-red-500/15 text-red-500'"
                            >
                                {{ discrepancyText(past.discrepancy) }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs opacity-60">
                            نقدی {{ formatToman(past.cash_payments) }} · کارت {{ formatToman(past.card_payments) }} ·
                            حرکات {{ formatToman(past.movements_net) }} · شمارش {{ formatToman(past.counted_cash) }}
                            از انتظار {{ formatToman(past.expected_cash) }}
                        </p>
                    </article>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
