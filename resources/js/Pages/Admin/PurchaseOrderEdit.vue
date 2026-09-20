<script setup>
import { usePurchaseOrderForm } from '@/lib/purchaseOrderForm';
import AppLayout from '@/Layouts/AppLayout.vue';
import PurchaseOrderFormFields from '@/Components/PurchaseOrderFormFields.vue';
import { formatToman } from '@/lib/format';

const props = defineProps({
    order: { type: Object, required: true },
    suppliers: { type: Array, required: true },
    items: { type: Array, required: true },
});

const formState = usePurchaseOrderForm({
    suppliers: props.suppliers,
    items: props.items,
    initial: {
        supplier_id: props.order.supplier_id,
        notes: props.order.notes ?? '',
        lines: props.order.items.map((item) => ({
            inventory_item_id: item.inventory_item_id,
            quantity: String(item.quantity),
            unit_cost: String(item.unit_cost),
        })),
    },
    submitRouteName: 'admin.purchase-orders.update',
    routeParams: { order: props.order.id },
    spoofMethod: 'put',
});

const { form, submitting, isTaken, hasFreeItems, onLineMaterialChange, addLine, removeLine, liveTotal, filledLineCount, submit } =
    formState;
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-3xl px-4 py-6">
            <header class="mb-6">
                <a :href="route('admin.purchase-orders')" class="text-xs opacity-50 transition hover:opacity-90">
                    → بازگشت به سفارش‌های خرید
                </a>
                <h1 class="mt-2 text-2xl font-bold">ویرایش پیش‌نویس سفارش #{{ order.id }}</h1>
                <p class="mt-1 text-sm opacity-60">
                    تغییرات روی پیش‌نویس ذخیره می‌شود؛ تا پیش از «ثبت نزد تامین‌کننده» می‌توانید دوباره ویرایش کنید.
                </p>
            </header>

            <form class="space-y-4" @submit.prevent="submit">
                <PurchaseOrderFormFields
                    :suppliers="suppliers"
                    :items="items"
                    :form="form"
                    :is-taken="isTaken"
                    :has-free-items="hasFreeItems"
                    :on-line-material-change="onLineMaterialChange"
                    :add-line="addLine"
                    :remove-line="removeLine"
                    :live-total="liveTotal"
                    :filled-line-count="filledLineCount"
                    :submitting="submitting"
                    submit-label="ذخیرهٔ تغییرات پیش‌نویس"
                />
            </form>
        </div>
    </AppLayout>
</template>
