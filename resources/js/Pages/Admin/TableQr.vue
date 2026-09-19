<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';

defineProps({
    table: { type: Object, required: true },
    url: { type: String, required: true },
});

function printPage() {
    window.print();
}
</script>

<template>
    <PublicLayout>
        <div class="mx-auto max-w-xl px-4 py-10 text-center">
            <div class="glass glass-sheen rounded-glass p-8 print:shadow-none">
                <h1 class="text-2xl font-bold">{{ table.label }}</h1>
                <p class="mt-2 text-sm opacity-70">برای مشاهده منو و سفارش، اسکن کنید</p>

                <!-- The QR image itself is rendered client-side from the token URL. -->
                <div class="mx-auto mt-6 w-64">
                    <img
                        :src="`https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(url)}`"
                        :alt="`QR میز ${table.label}`"
                        class="w-full rounded-2xl bg-white p-2"
                        loading="lazy"
                    >
                </div>

                <p class="mt-4 break-all text-xs opacity-50">{{ url }}</p>

                <button
                    type="button"
                    class="mt-6 rounded-2xl bg-saffron-500 px-6 py-3 font-bold text-white print:hidden"
                    @click="printPage"
                >
                    چاپ
                </button>
            </div>
        </div>
    </PublicLayout>
</template>
