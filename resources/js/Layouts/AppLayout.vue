<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import FlashToasts from '@/Components/FlashToasts.vue';
import StockAlertBell from '@/Components/StockAlertBell.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';

const page = usePage();

const navLinks = computed(() => {
    const role = page.props.auth.user?.role;

    // Route NAMES, never URLs — resolved through Ziggy at render time.
    if (role === 'admin') {
        return [
            { to: 'admin.dashboard', label: 'داشبورد' },
            { to: 'admin.tables', label: 'میزها' },
            { to: 'admin.inventory', label: 'انبار' },
            { to: 'admin.inventory.report', label: 'گزارش انبار' },
            { to: 'admin.purchase-orders', label: 'خرید' },
            { to: 'admin.discounts', label: 'تخفیف‌ها' },
            { to: 'cashier.index', label: 'صندوق' },
        ];
    }

    if (role === 'cashier') {
        return [{ to: 'cashier.index', label: 'صندوق' }];
    }

    if (role === 'kitchen') {
        return [{ to: 'kitchen.index', label: 'آشپزخانه' }];
    }

    return [];
});

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <div class="min-h-dvh">
        <FlashToasts />

        <header class="glass sticky top-0 z-20 px-4 py-3">
            <div class="mx-auto flex max-w-7xl items-center justify-between">
                <div class="flex items-center gap-4">
                    <p class="font-black text-saffron-500">وی‌کیچن</p>
                    <nav class="flex items-center gap-1">
                        <Link
                            v-for="link in navLinks"
                            :key="link.to"
                            :href="route(link.to)"
                            class="rounded-xl px-3 py-1.5 text-sm opacity-70 transition hover:bg-white/10 hover:opacity-100"
                        >
                            {{ link.label }}
                        </Link>
                    </nav>
                </div>
                <div class="flex items-center gap-3">
                    <ThemeToggle />
                    <StockAlertBell v-if="page.props.auth.user?.role === 'admin'" />
                    <span v-if="page.props.auth.user" class="text-sm opacity-70">
                        {{ page.props.auth.user.name }} · {{ page.props.auth.user.roleLabel }}
                    </span>
                    <button
                        type="button"
                        class="glass-flat rounded-xl px-3 py-2 text-sm"
                        @click="logout"
                    >
                        خروج
                    </button>
                </div>
            </div>
        </header>

        <main>
            <slot />
        </main>
    </div>
</template>
