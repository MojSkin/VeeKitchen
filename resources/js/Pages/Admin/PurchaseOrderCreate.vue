<script setup>
import { Link } from '@inertiajs/vue3';
import { usePurchaseOrderForm } from '@/lib/purchaseOrderForm';
import AppLayout from '@/Layouts/AppLayout.vue';
import PurchaseOrderFormFields from '@/Components/PurchaseOrderFormFields.vue';
import { faDigits, formatToman } from '@/lib/format';

const props = defineProps({
    suppliers: { type: Array, required: true },
    items: { type: Array, required: true },
});

const formState = usePurchaseOrderForm({
    suppliers: props.suppliers,
    items: props.items,
    submitRouteName: 'admin.purchase-orders.store',
});

const { form, submitting, isTaken, hasFreeItems, onLineMaterialChange, addLine, removeLine, liveTotal, filledLineCount, submit } =
    formState;
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-3xl px-4 py-6">
            <header class="mb-6">
                <Link :href="route('admin.purchase-orders')" class="text-xs opacity-50 transition hover:opacity-90">
                    → بازگشت به سفارش‌های خرید
                </Link>
                <h1 class="mt-2 text-2xl font-bold">سفارش خرید جدید</h1>
                <p class="mt-1 text-sm opacity-60">
                    پیش‌نویس ساخته می‌شود؛ پس از بازبینی، «ثبت نزد تامین‌کننده» را بزنید.
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
                    submit-label="ثبت پیش‌نویس سفارش"
                />
            </form>
        </div>
    </AppLayout>
</template>
