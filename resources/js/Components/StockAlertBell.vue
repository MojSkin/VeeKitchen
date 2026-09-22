<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { faDigits } from '@/lib/format';

const page = usePage();

const alerts = computed(() => page.props.stockAlerts ?? { count: 0, items: [] });
const count = computed(() => alerts.value.count ?? 0);

const open = ref(false);
const closing = ref(false);
let inventoryChannel = null;
const root = ref(null);

onMounted(() => {
    // Live counter: any stock move on the branch re-pulls the shared prop.
    if (page.props.auth.branchId) {
        inventoryChannel = window.Echo.private(`branch.${page.props.auth.branchId}.inventory`)
            .listen('.stock.changed', () => {
                router.reload({ only: ['stockAlerts'] });
            });
    }

    document.addEventListener('click', onClickOutside);
});

onBeforeUnmount(() => {
    if (inventoryChannel) {
        window.Echo.leave(`branch.${page.props.auth.branchId}.inventory`);
    }

    document.removeEventListener('click', onClickOutside);
});

function onClickOutside(event) {
    if (open.value && root.value && !root.value.contains(event.target)) {
        close();
    }
}

/** transitions.dev dropdown close: is-closing animates before unmount. */
function close() {
    closing.value = true;

    setTimeout(() => {
        open.value = false;
        closing.value = false;
    }, 150);
}

function toggle() {
    if (open.value) {
        close();
    } else {
        open.value = true;
    }
}

function markAllRead() {
    router.post(route('admin.notifications.read-all'), {}, { preserveScroll: true });
}

function timeLabel(iso) {
    if (!iso) {
        return '';
    }

    const diffMinutes = Math.floor((Date.now() - new Date(iso).getTime()) / 60000);

    if (diffMinutes < 1) {
        return 'همین حالا';
    }

    if (diffMinutes < 60) {
        return `${faDigits(diffMinutes)} دقیقه پیش`;
    }

    const hours = Math.floor(diffMinutes / 60);

    return `${faDigits(hours)} ساعت پیش`;
}
</script>

<template>
    <div
        ref="root"
        class="relative"
    >
        <button
            type="button"
            class="neo-click relative grid size-9 cursor-pointer place-items-center rounded-xl"
            :aria-label="`اعلان‌ها${count > 0 ? `، ${count} خوانده‌نشده` : ''}`"
            :aria-expanded="open"
            @click="toggle"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.7"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="size-[18px]"
                aria-hidden="true"
            >
                <path d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
            </svg>
            <!-- transitions.dev badge: diagonal slide with spring pop-in -->
            <Transition
                enter-active-class="transition duration-500 ease-bounce"
                enter-from-class="translate-x-2 translate-y-3 scale-0 opacity-0"
                enter-to-class="translate-x-0 translate-y-0 scale-100 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-to-class="scale-0 opacity-0"
            >
                <span
                    v-if="count > 0"
                    class="absolute -top-1 -left-1 grid h-4.5 min-w-4.5 place-items-center rounded-full bg-red-500 px-1 text-[10px] font-black text-white shadow-sm"
                >
                    {{ faDigits(count > 99 ? '+۹۹' : count) }}
                </span>
            </Transition>
        </button>

        <!-- transitions.dev dropdown: origin-aware scale+fade -->
        <div
            v-if="open"
            class="t-dropdown glass-strong absolute left-0 top-full z-40 mt-2 w-80 rounded-2xl p-3"
            :class="[open && !closing ? 'is-open' : '', closing ? 'opacity-0' : '']"
            data-origin="top-left"
            role="dialog"
            aria-label="هشدار موجودی"
        >
            <div class="mb-2 flex items-center justify-between px-1">
                <p class="text-sm font-bold">هشدار موجودی</p>
                <button
                    v-if="count > 0"
                    type="button"
                    class="cursor-pointer text-xs opacity-60 transition hover:opacity-100"
                    @click="markAllRead"
                >
                    همه خوانده شد
                </button>
            </div>

            <ul
                v-if="alerts.items.length > 0"
                class="space-y-2"
            >
                <li
                    v-for="item in alerts.items"
                    :key="item.id"
                    class="neo-pressed rounded-xl p-3"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-bold text-red-500">{{ item.title }}</p>
                        <span class="text-[11px] opacity-50">{{ timeLabel(item.date) }}</span>
                    </div>
                    <p class="mt-1 text-xs opacity-80">{{ item.message }}</p>
                </li>
            </ul>

            <p
                v-else
                class="px-1 py-4 text-center text-sm opacity-50"
            >
                هشدار خوانده‌نشده‌ای نیست.
            </p>
        </div>
    </div>
</template>
