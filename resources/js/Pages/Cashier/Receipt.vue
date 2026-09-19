<script setup>
import { formatTomanWithUnit, faDigits } from '@/lib/format';

const props = defineProps({
    order: { type: Object, required: true },
});

function printReceipt() {
    window.print();
}
</script>

<template>
    <div class="mx-auto my-8 w-[80mm] bg-white p-4 font-sans text-night-900 print:my-0 print:w-full print:shadow-none">
        <header class="text-center">
            <h1 class="text-lg font-black">وی‌کیچن</h1>
            <p class="text-xs">سفارش شماره {{ faDigits(order.order_number ?? '—') }}</p>
            <p class="text-xs">{{ order.table?.label ?? 'بیرون‌بر' }}</p>
        </header>

        <ul class="my-4 space-y-1 border-y border-dashed border-night-300 py-3 text-sm">
            <li v-for="item in order.items" :key="item.id" class="flex justify-between gap-2">
                <span>{{ item.quantity }}× {{ item.product_name }}</span>
                <span>{{ formatTomanWithUnit(item.line_total) }}</span>
            </li>
        </ul>

        <dl class="space-y-1 text-sm">
            <div class="flex justify-between">
                <dt>جمع</dt>
                <dd>{{ formatTomanWithUnit(order.subtotal) }}</dd>
            </div>
            <div v-if="order.discount_total > 0" class="flex justify-between">
                <dt>تخفیف</dt>
                <dd>−{{ formatTomanWithUnit(order.discount_total) }}</dd>
            </div>
            <div class="flex justify-between border-t border-dashed border-night-300 pt-2 text-base font-black">
                <dt>قابل پرداخت</dt>
                <dd>{{ formatTomanWithUnit(order.total) }}</dd>
            </div>
        </dl>

        <footer class="mt-4 text-center text-xs opacity-60">
            <p>با تشکر از انتخاب شما 🌿</p>
            <p class="mt-1">{{ new Date(order.paid_at ?? Date.now()).toLocaleString('fa-IR') }}</p>
        </footer>

        <button
            type="button"
            class="mt-6 w-full rounded-xl bg-night-900 py-2 text-sm font-bold text-white print:hidden"
            @click="printReceipt"
        >
            چاپ فیش
        </button>
    </div>
</template>
