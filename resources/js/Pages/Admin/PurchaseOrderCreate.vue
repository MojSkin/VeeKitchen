<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { faDigits, formatToman } from '@/lib/format';

const props = defineProps({
    suppliers: { type: Array, required: true },
    items: { type: Array, required: true },
});

const form = ref({
    supplier_id: '',
    notes: '',
    lines: [{ inventory_item_id: '', quantity: '', unit_cost: '' }],
});

const submitting = ref(false);

const itemById = computed(() => new Map(props.items.map((item) => [item.id, item])));

/** A material can appear on only one line — already-used options gray out. */
function isTaken(line, itemId) {
    return form.value.lines.some(
        (candidate) => candidate !== line && Number(candidate.inventory_item_id) === Number(itemId),
    );
}

const hasFreeItems = computed(() =>
    props.items.some((item) => !form.value.lines.some((line) => Number(line.inventory_item_id) === item.id)),
);

function onLineMaterialChange(line) {
    // Prefill with the material's last purchase cost — the buyer can override.
    const material = itemById.value.get(Number(line.inventory_item_id));

    line.unit_cost = material ? String(material.unit_cost) : '';
}

function addLine() {
    form.value.lines.push({ inventory_item_id: '', quantity: '', unit_cost: '' });
}

function removeLine(index) {
    form.value.lines.splice(index, 1);

    if (form.value.lines.length === 0) {
        addLine();
    }
}

/**
 * Live order total: Σ(quantity × unit cost) over filled lines only —
 * the same arithmetic the backend repeats inside its transaction.
 */
const liveTotal = computed(() =>
    form.value.lines.reduce((total, line) => {
        const quantity = parseFloat(line.quantity) || 0;
        const unitCost = parseInt(line.unit_cost, 10) || 0;

        return total + quantity * unitCost;
    }, 0),
);

const filledLineCount = computed(
    () => form.value.lines.filter((line) => line.inventory_item_id && parseFloat(line.quantity) > 0).length,
);

function submit() {
    submitting.value = true;

    router.post(route('admin.purchase-orders.store'), form.value, {
        preserveScroll: true,
        onFinish: () => {
            submitting.value = false;
        },
    });
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-3xl px-4 py-6">
            <header class="mb-6">
                <Link
                    :href="route('admin.purchase-orders')"
                    class="text-xs opacity-50 transition hover:opacity-90"
                >
                    → بازگشت به سفارش‌های خرید
                </Link>
                <h1 class="mt-2 text-2xl font-bold">سفارش خرید جدید</h1>
                <p class="mt-1 text-sm opacity-60">
                    پیش‌نویس ساخته می‌شود؛ پس از بازبینی، «ثبت نزد تامین‌کننده» را بزنید.
                </p>
            </header>

            <form class="space-y-4" @submit.prevent="submit">
                <!-- Supplier + notes -->
                <section class="glass grid gap-3 rounded-glass p-5 sm:grid-cols-2">
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
                <section class="glass rounded-glass p-5">
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
                            ثبت پیش‌نویس سفارش
                        </button>
                    </div>
                </section>
            </form>
        </div>
    </AppLayout>
</template>
