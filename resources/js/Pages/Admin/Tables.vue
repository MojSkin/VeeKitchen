<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    tables: { type: Array, required: true },
});

const busyTableId = ref(null);

const toneClasses = {
    green: 'bg-pistachio-500/15 text-pistachio-600 dark:text-pistachio-400',
    sky: 'bg-sky-500/15 text-sky-600 dark:text-sky-400',
    amber: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
    violet: 'bg-violet-500/15 text-violet-600 dark:text-violet-400',
};

function rotateToken(table) {
    busyTableId.value = table.id;

    router.post(
        route('admin.tables.rotate', table.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                busyTableId.value = null;
            },
        },
    );
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-6xl px-4 py-6">
            <h1 class="mb-6 text-2xl font-bold">مدیریت میزها</h1>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <article
                    v-for="table in tables"
                    :key="table.id"
                    class="glass rounded-glass p-5"
                >
                    <header class="flex items-center justify-between">
                        <h2 class="text-lg font-bold">{{ table.label }}</h2>
                        <span
                            class="rounded-full px-3 py-1 text-xs font-medium"
                            :class="toneClasses[table.status] ?? toneClasses.green"
                        >
                            {{ table.status_label }}
                        </span>
                    </header>

                    <p class="mt-1 text-sm opacity-60">
                        ظرفیت: {{ table.capacity }} نفر
                        <template v-if="table.open_orders > 0">
                            · {{ table.open_orders }} سفارش باز
                        </template>
                    </p>

                    <footer class="mt-4 flex gap-2">
                        <a
                            :href="route('admin.tables.qr', table.id)"
                            target="_blank"
                            class="glass-flat flex-1 rounded-xl px-3 py-2 text-center text-sm"
                        >
                            QR
                        </a>
                        <button
                            type="button"
                            class="glass-flat flex-1 rounded-xl px-3 py-2 text-sm disabled:opacity-40"
                            :disabled="busyTableId === table.id"
                            @click="rotateToken(table)"
                        >
                            تازه‌سازی توکن
                        </button>
                    </footer>
                </article>
            </div>
        </div>
    </AppLayout>
</template>
