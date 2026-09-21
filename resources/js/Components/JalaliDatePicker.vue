<script setup>
import { computed, ref, watch } from 'vue';
import * as jf from 'date-fns-jalali';
import { faDigits } from '@/lib/format';

/**
 * Jalali date/time picker — a port of VeePanel's VeeDatePicker, restyled
 * for VeeKitchen's neomorphic lajvard theme. Persian-first: the Jalali
 * calendar is the only calendar, all numerals render as Persian digits,
 * and the week starts on Saturday.
 *
 * v-model carries a JS Date (or ISO string); the value itself stays
 * Gregorian under the hood — only the presentation is Jalali.
 */
const props = defineProps({
    /** Selected value: Date, ISO string, or null. */
    modelValue: { type: [Date, String], default: null },
    label: { type: String, default: '' },
    /** Show hour/minute selects under the day grid. */
    withTime: { type: Boolean, default: false },
    placeholder: { type: String, default: '' },
    clearable: { type: Boolean, default: true },
    disabled: { type: Boolean, default: false },
    error: { type: String, default: '' },
    /** Disabled range bounds: Date or ISO string. */
    min: { type: [Date, String], default: null },
    max: { type: [Date, String], default: null },
});

const emit = defineEmits(['update:modelValue', 'change']);

const modelDate = computed(() => {
    if (props.modelValue instanceof Date) {
        return Number.isNaN(props.modelValue.getTime()) ? null : props.modelValue;
    }

    if (typeof props.modelValue === 'string' && props.modelValue) {
        const parsed = new Date(props.modelValue);

        return Number.isNaN(parsed.getTime()) ? null : parsed;
    }

    return null;
});

/** Month/year currently on display, in the Jalali calendar. */
const viewDate = ref(modelDate.value ?? new Date());
const viewYear = computed(() => jf.getYear(viewDate.value));
const viewMonth = computed(() => jf.getMonth(viewDate.value));

watch(
    () => props.modelValue,
    (value) => {
        if (modelDate.value) {
            viewDate.value = modelDate.value;
        }
    },
);

const isOpen = ref(false);
const rootRef = ref(null);

const MONTH_NAMES = [
    'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
    'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند',
];

/** شنبه‌شروع — Saturday leads the Persian week. */
const WEEKDAY_LABELS = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];

function pad(value) {
    return String(value).padStart(2, '0');
}

function makeDate(year, month, day) {
    let date = jf.setYear(new Date(2000, 0, 1), year);

    date = jf.setMonth(date, month);

    return jf.setDate(date, Math.min(day, jf.getDaysInMonth(date)));
}

function parseBound(value) {
    if (value instanceof Date) {
        return value;
    }

    if (typeof value === 'string' && value) {
        const parsed = new Date(value);

        return Number.isNaN(parsed.getTime()) ? null : parsed;
    }

    return null;
}

function isDisabledDay(day) {
    if (day === null) {
        return true;
    }

    const cell = makeDate(viewYear.value, viewMonth.value, day);
    const min = parseBound(props.min);
    const max = parseBound(props.max);

    return (min !== null && cell < min) || (max !== null && cell > max);
}

const grid = computed(() => {
    const first = jf.startOfMonth(viewDate.value);
    const daysInMonth = jf.getDaysInMonth(first);
    // date-fns getDay(): 0=Sunday … 6=Saturday → shift so Saturday leads.
    const lead = (jf.getDay(first) + 1) % 7;

    const cells = [];

    for (let i = 0; i < lead; i++) {
        cells.push(null);
    }

    for (let day = 1; day <= daysInMonth; day++) {
        cells.push(day);
    }

    while (cells.length % 7 !== 0) {
        cells.push(null);
    }

    return cells;
});

function isSelected(day) {
    return modelDate.value !== null && day !== null && jf.isSameDay(modelDate.value, makeDate(viewYear.value, viewMonth.value, day));
}

function isToday(day) {
    return day !== null && jf.isToday(makeDate(viewYear.value, viewMonth.value, day));
}

function moveMonth(delta) {
    viewDate.value = jf.addMonths(viewDate.value, delta);
}

function selectDay(day) {
    if (day === null || isDisabledDay(day)) {
        return;
    }

    const base = modelDate.value ?? new Date();
    const date = makeDate(viewYear.value, viewMonth.value, day);

    if (props.withTime) {
        date.setHours(base.getHours(), base.getMinutes(), 0, 0);
    }

    emit('update:modelValue', date);
    emit('change', date);

    if (!props.withTime) {
        isOpen.value = false;
    }
}

function selectToday() {
    const now = new Date();

    viewDate.value = new Date();

    const date = makeDate(jf.getYear(now), jf.getMonth(now), jf.getDate(now));

    if (props.withTime) {
        date.setHours(now.getHours(), now.getMinutes(), 0, 0);
    }

    emit('update:modelValue', date);
    emit('change', date);
}

function clear() {
    emit('update:modelValue', null);
    emit('change', null);
}

function setHour(hour) {
    const base = modelDate.value ?? new Date();
    const date = new Date(base);

    date.setHours(hour, base.getMinutes(), 0, 0);

    emit('update:modelValue', date);
    emit('change', date);
}

function setMinute(minute) {
    const base = modelDate.value ?? new Date();
    const date = new Date(base);

    date.setHours(base.getHours(), minute, 0, 0);

    emit('update:modelValue', date);
    emit('change', date);
}

function toggleOpen() {
    if (props.disabled) {
        return;
    }

    isOpen.value = !isOpen.value;

    if (isOpen.value && modelDate.value) {
        viewDate.value = modelDate.value;
    }
}

function onClickOutside(event) {
    if (isOpen.value && rootRef.value && !rootRef.value.contains(event.target)) {
        isOpen.value = false;
    }
}

document.addEventListener('click', onClickOutside);

const displayText = computed(() => {
    if (!modelDate.value) {
        return '';
    }

    const year = jf.getYear(modelDate.value);
    const month = jf.getMonth(modelDate.value) + 1;
    const day = jf.getDate(modelDate.value);
    let text = `${year}/${pad(month)}/${pad(day)}`;

    if (props.withTime) {
        text += ` ${pad(modelDate.value.getHours())}:${pad(modelDate.value.getMinutes())}`;
    }

    return faDigits(text);
});

const hoursOptions = computed(() => Array.from({ length: 24 }, (_, i) => i));
const minutesOptions = computed(() => Array.from({ length: 60 }, (_, i) => i));
</script>

<template>
    <div
        ref="rootRef"
        class="relative inline-block w-full"
    >
        <label
            v-if="label"
            class="mb-1 block text-sm font-medium opacity-80"
        >
            {{ label }}
        </label>

        <button
            type="button"
            class="neo-pressed flex w-full cursor-pointer items-center gap-2 rounded-xl px-3 py-2 text-start text-sm transition disabled:opacity-50"
            :disabled="disabled"
            :aria-haspopup="'dialog'"
            :aria-expanded="isOpen"
            @click="toggleOpen"
            @keydown.esc="isOpen = false"
        >
            <span
                class="flex-1 truncate"
                :class="displayText ? '' : 'opacity-50'"
            >
                {{ displayText || placeholder || 'انتخاب تاریخ' }}
            </span>
            <span
                v-if="clearable && modelDate"
                class="text-xs opacity-50 transition hover:opacity-100"
                aria-label="پاک کردن"
                @click.stop="clear"
            >✕</span>
            <span
                v-else
                class="opacity-50"
                aria-hidden="true"
            >📅</span>
        </button>

        <p
            v-if="error"
            class="mt-1 text-xs text-red-500 dark:text-red-400"
        >
            {{ error }}
        </p>

        <Transition name="vee-pop">
            <div
                v-if="isOpen"
                role="dialog"
                aria-label="تقویم جلالی"
                class="t-dropdown glass-strong absolute start-0 top-full z-40 mt-2 w-full min-w-72 rounded-2xl p-3 is-open"
                data-origin="top-left"
                @keydown.esc="isOpen = false"
            >
                <!-- Month navigation -->
                <div class="mb-2 flex items-center justify-between">
                    <button
                        type="button"
                        class="glass-flat rounded-lg px-2 py-1 text-xs transition active:scale-95"
                        aria-label="ماه قبل"
                        @click="moveMonth(-1)"
                    >›</button>

                    <span class="text-sm font-bold">{{ MONTH_NAMES[viewMonth] }} {{ faDigits(viewYear) }}</span>

                    <button
                        type="button"
                        class="glass-flat rounded-lg px-2 py-1 text-xs transition active:scale-95"
                        aria-label="ماه بعد"
                        @click="moveMonth(1)"
                    >‹</button>
                </div>

                <!-- Weekday header -->
                <div class="mb-1 grid grid-cols-7 text-center">
                    <span
                        v-for="label in WEEKDAY_LABELS"
                        :key="label"
                        class="py-1 text-xs font-medium opacity-60"
                    >{{ label }}</span>
                </div>

                <!-- Day grid -->
                <div class="grid grid-cols-7 gap-0.5">
                    <button
                        v-for="(day, index) in grid"
                        :key="index"
                        type="button"
                        :disabled="day === null || isDisabledDay(day)"
                        class="aspect-square rounded-lg text-sm transition active:scale-95 disabled:invisible"
                        :class="[
                            isSelected(day)
                                ? 'bg-lajvard-600 font-bold text-white shadow-glass'
                                : isToday(day)
                                    ? 'font-bold text-lajvard-600 dark:text-lajvard-400'
                                    : 'hover:bg-lajvard-100/60 dark:hover:bg-night-800/60',
                            isDisabledDay(day) && day !== null ? 'cursor-not-allowed opacity-30' : '',
                        ]"
                        @click="selectDay(day)"
                    >
                        {{ day === null ? '' : faDigits(day) }}
                    </button>
                </div>

                <!-- Time selects -->
                <div
                    v-if="withTime"
                    class="mt-3 flex items-center justify-center gap-2 border-t border-white/30 pt-3 dark:border-white/10"
                >
                    <select
                        :value="modelDate?.getHours() ?? 12"
                        class="neo-pressed rounded-lg px-2 py-1 text-sm"
                        aria-label="ساعت"
                        @change="setHour(Number($event.target.value))"
                    >
                        <option
                            v-for="h in hoursOptions"
                            :key="h"
                            :value="h"
                        >{{ faDigits(pad(h)) }}</option>
                    </select>
                    <span class="opacity-50">:</span>
                    <select
                        :value="modelDate?.getMinutes() ?? 0"
                        class="neo-pressed rounded-lg px-2 py-1 text-sm"
                        aria-label="دقیقه"
                        @change="setMinute(Number($event.target.value))"
                    >
                        <option
                            v-for="m in minutesOptions"
                            :key="m"
                            :value="m"
                        >{{ faDigits(pad(m)) }}</option>
                    </select>
                </div>

                <!-- Footer -->
                <div class="mt-2 flex items-center justify-between border-t border-white/30 pt-2 dark:border-white/10">
                    <button
                        type="button"
                        class="rounded-lg px-2 py-1 text-xs font-bold text-lajvard-600 transition hover:bg-lajvard-100/60 dark:text-lajvard-400 dark:hover:bg-night-800/60"
                        @click="selectToday"
                    >امروز</button>
                    <button
                        type="button"
                        class="rounded-lg px-2 py-1 text-xs opacity-60 transition hover:opacity-100"
                        @click="isOpen = false"
                    >بستن</button>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.vee-pop-enter-active,
.vee-pop-leave-active {
    transition: opacity 120ms ease, transform 120ms ease;
}

.vee-pop-enter-from,
.vee-pop-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}
</style>
