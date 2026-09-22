<script setup>
import { formatToman } from '@/lib/format';
import { faDigits } from '@/lib/format';

defineProps({
    suppliers: { type: Array, required: true },
    items: { type: Array, required: true },
    form: { type: Object, required: true },
    isTaken: { type: Function, required: true },
    hasFreeItems: { type: Boolean, required: true },
    onLineMaterialChange: { type: Function, required: true },
    addLine: { type: Function, required: true },
    removeLine: { type: Function, required: true },
    liveTotal: { type: Number, required: true },
    filledLineCount: { type: Number, required: true },
    submitting: { type: Boolean, default: false },
    submitLabel: { type: String, default: 'ذخیره' },
});
</script>

<template>
    <!-- Supplier + notes -->
    <section class="glass grid gap-3 rounded-2xl p-5 sm:grid-cols-2">
        <label class="block">
            <span class="mb-1 block text-xs opacity-60">تامین‌کننده</span>
            <select
                v-model="form.supplier_id"
                required
                class="glass-flat w-full cursor-pointer rounded-xl px-3 py-2 text-sm outline-none"
            >
                <option value="" disabled>انتخاب کنید…</option>
                <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                    {{ supplier.name }}
                </option>
            </select>
        </label>
        <label class="block">
            <span class="mb-1 block text-xs opacity-60">یادداشت (اختیاری)</span>
            <input
                v-model="form.notes"
                type="text"
                maxlength="1000"
                placeholder="مثلاً: برای پوشش هفتهٔ آینده"
                class="glass-flat w-full rounded-xl px-3 py-2 text-sm outline-none"
            >
        </label>
    </section>

    <!-- Lines -->
    <section class="glass rounded-2xl p-5">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="font-bold">اقلام سفارش</h2>
            <span class="text-xs opacity-50">{{ faDigits(filledLineCount) }} قلم پرشده</span>
        </div>

        <div class="space-y-3">
            <div
                v-for="(line, index) in form.lines"
                :key="index"
                class="glass-flat grid gap-2 rounded-2xl p-3 sm:grid-cols-[2fr_1fr_1.2fr_auto_auto] sm:items-end"
            >
                <label class="block">
                    <span class="mb-1 block text-xs opacity-60">متریال</span>
                    <select
                        v-model="line.inventory_item_id"
                        required
                        class="glass-flat w-full cursor-pointer rounded-xl bg-transparent px-3 py-2 text-sm outline-none"
                        @change="onLineMaterialChange(line)"
                    >
                        <option value="" disabled>انتخاب کنید…</option>
                        <option
                            v-for="item in items"
                            :key="item.id"
                            :value="item.id"
                            :disabled="isTaken(line, item.id)"
                        >
                            {{ item.name }} ({{ item.unit_label }})
                        </option>
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs opacity-60">مقدار</span>
                    <input
                        v-model="line.quantity"
                        type="number"
                        step="0.001"
                        min="0.001"
                        required
                        inputmode="decimal"
                        class="glass-flat w-full rounded-xl px-3 py-2 text-sm outline-none"
                        dir="ltr"
                    >
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs opacity-60">هزینهٔ واحد (تومان)</span>
                    <input
                        v-model="line.unit_cost"
                        type="number"
                        step="1"
                        min="0"
                        inputmode="numeric"
                        class="glass-flat w-full rounded-xl px-3 py-2 text-sm outline-none"
                        dir="ltr"
                    >
                </label>

                <p class="pb-2 text-sm opacity-70 sm:min-w-28 sm:text-left">
                    <template v-if="parseFloat(line.quantity) > 0 && parseInt(line.unit_cost, 10) > 0">
                        {{ formatToman(line.quantity * line.unit_cost) }}
                    </template>
                    <template v-else>—</template>
                </p>

                <button
                    type="button"
                    class="cursor-pointer rounded-xl px-3 py-2 text-xs text-red-400 transition hover:bg-red-500/10"
                    aria-label="حذف خط"
                    @click="removeLine(index)"
                >
                    حذف
                </button>
            </div>
        </div>

        <button
            type="button"
            class="glass-flat mt-3 cursor-pointer rounded-xl px-4 py-2 text-sm font-bold transition hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="!hasFreeItems"
            @click="addLine"
        >
            + افزودن قلم
        </button>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-4">
            <p class="text-sm opacity-70">
                جمع کل: <strong class="text-base">{{ formatToman(liveTotal) }}</strong> تومان
            </p>
            <button
                type="submit"
                class="cursor-pointer rounded-xl bg-saffron-500 px-6 py-2.5 text-sm font-bold text-white transition hover:bg-saffron-600 disabled:opacity-40"
                :disabled="submitting"
            >
                {{ submitLabel }}
            </button>
        </div>
    </section>
</template>
