<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import FlashToasts from '@/Components/FlashToasts.vue';
import NavIcon from '@/Components/NavIcon.vue';
import StockAlertBell from '@/Components/StockAlertBell.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';

const page = usePage();
const collapsed = ref(false);

const navLinks = computed(() => {
    const role = page.props.auth.user?.role;

    // Route NAMES, never URLs — resolved through Ziggy at render time.
    if (role === 'admin') {
        return [
            { to: 'admin.dashboard', label: 'داشبورد', icon: 'dashboard' },
            { to: 'admin.tables', label: 'میزها', icon: 'tables' },
            { to: 'admin.inventory', label: 'انبار', icon: 'inventory' },
            { to: 'admin.inventory.report', label: 'گزارش انبار', icon: 'report' },
            { to: 'admin.purchase-orders', label: 'خرید', icon: 'purchase' },
            { to: 'admin.discounts', label: 'تخفیف‌ها', icon: 'discounts' },
            { to: 'admin.end-of-day', label: 'پایان روز', icon: 'register' },
        ];
    }

    if (role === 'cashier') {
        return [{ to: 'cashier.index', label: 'صندوق', icon: 'register' }];
    }

    if (role === 'kitchen') {
        return [{ to: 'kitchen.index', label: 'آشپزخانه', icon: 'kitchen' }];
    }

    return [];
});

const user = computed(() => page.props.auth.user);

function isActive(link) {
    return route().current(link.to);
}

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <div class="flex min-h-dvh">
        <FlashToasts />

        <!-- Sidebar: glass pane floating over the aurora, RTL leading edge -->
        <aside
            class="glass sticky top-0 z-30 flex h-dvh shrink-0 flex-col gap-1 p-3 transition-[width] duration-fast ease-smooth-out"
            :class="collapsed ? 'w-[4.5rem]' : 'w-60'"
        >
            <!-- Brand -->
            <div class="mb-2 flex items-center gap-3 px-1 py-2">
                <div class="neo grid size-10 shrink-0 place-items-center rounded-xl">
                    <span class="text-lg font-black text-lajvard-600 dark:text-lajvard-300">و</span>
                </div>
                <div
                    v-if="!collapsed"
                    class="min-w-0"
                >
                    <p class="truncate text-sm font-black leading-tight">وی‌کیچن</p>
                    <p class="truncate text-[11px] opacity-55">پنل مدیریت شعبه</p>
                </div>
            </div>

            <!-- Nav -->
            <nav class="flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto no-scrollbar">
                <Link
                    v-for="link in navLinks"
                    :key="link.to"
                    :href="route(link.to)"
                    class="group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition-[background-color,box-shadow] duration-quick ease-smooth-out"
                    :class="isActive(link)
                        ? 'neo font-bold text-lajvard-700 dark:text-lajvard-200'
                        : 'font-medium opacity-65 hover:opacity-100 hover:bg-white/45 dark:hover:bg-white/5'"
                    :title="collapsed ? link.label : undefined"
                >
                    <NavIcon :name="link.icon" />
                    <span
                        v-if="!collapsed"
                        class="truncate"
                    >{{ link.label }}</span>
                    <!-- Active rail -->
                    <span
                        v-if="isActive(link)"
                        class="absolute -left-3 top-1/2 h-6 w-1 -translate-y-1/2 rounded-full bg-lajvard-500"
                    />
                </Link>
            </nav>

            <!-- User card -->
            <div
                class="neo-pressed mt-2 flex items-center gap-3 rounded-xl p-2"
                :class="collapsed ? 'justify-center' : ''"
            >
                <div class="grid size-8 shrink-0 place-items-center rounded-lg bg-lajvard-500/15 text-xs font-black text-lajvard-600 dark:text-lajvard-300">
                    {{ user?.name?.slice(0, 1) ?? '؟' }}
                </div>
                <div
                    v-if="!collapsed"
                    class="min-w-0 flex-1"
                >
                    <p class="truncate text-xs font-bold">{{ user?.name }}</p>
                    <p class="truncate text-[11px] opacity-55">{{ user?.roleLabel }}</p>
                </div>
            </div>

            <div class="mt-2 flex items-center justify-between gap-1">
                <button
                    type="button"
                    class="glass-flat flex h-9 flex-1 cursor-pointer items-center justify-center rounded-xl text-sm transition active:scale-95"
                    :class="collapsed ? 'text-red-500' : 'text-red-500/90'"
                    :aria-label="'خروج از حساب'"
                    title="خروج"
                    @click="logout"
                >
                    <NavIcon name="logout" />
                    <span
                        v-if="!collapsed"
                        class="ms-2 text-xs font-bold"
                    >خروج</span>
                </button>
            </div>
        </aside>

        <!-- Main column -->
        <div class="flex min-w-0 flex-1 flex-col">
            <!-- Topbar -->
            <header class="flex items-center justify-between gap-3 px-6 py-3">
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="glass-flat grid size-9 cursor-pointer place-items-center rounded-xl transition active:scale-95"
                        :aria-label="collapsed ? 'باز کردن سایدبار' : 'جمع کردن سایدبار'"
                        @click="collapsed = !collapsed"
                    >
                        <NavIcon
                            name="chevron"
                            class="transition-transform duration-fast"
                            :class="collapsed ? 'rotate-180' : ''"
                        />
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    <StockAlertBell v-if="page.props.auth.user?.role === 'admin'" />
                    <ThemeToggle />
                </div>
            </header>

            <main class="min-w-0 flex-1 px-6 pb-10">
                <slot />
            </main>
        </div>
    </div>
</template>
