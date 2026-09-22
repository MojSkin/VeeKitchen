<script setup>
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const success = computed(() => page.props.flash?.success);
const error = computed(() => page.props.flash?.error);

const visible = ref(false);
let hideTimer = null;

watch([success, error], () => {
    visible.value = true;

    if (hideTimer) {
        clearTimeout(hideTimer);
    }

    hideTimer = setTimeout(() => {
        visible.value = false;
    }, 4000);
});
</script>

<template>
    <!-- transitions.dev toast: rise from below with a cross-blur.
         Neomorphic float — the canvas-coloured pane sculpted by the
         shared shadow pair, a tinted bar carrying the state. -->
    <Transition
        enter-active-class="t-toast"
        enter-from-class="t-toast"
        enter-to-class="!translate-y-0 !scale-100 !opacity-100 !blur-0"
        leave-active-class="t-toast"
        leave-to-class="t-toast"
    >
        <div
            v-if="visible && (success || error)"
            class="neo-strong fixed inset-x-0 top-5 z-50 mx-auto w-fit max-w-md overflow-hidden rounded-2xl px-1.5 py-1.5"
            role="status"
        >
            <p
                v-if="success"
                class="flex items-center gap-2.5 rounded-xl bg-pistachio-500/10 px-4 py-2.5 text-sm font-bold text-pistachio-700 dark:text-pistachio-400"
            >
                <span
                    class="grid size-5 shrink-0 place-items-center rounded-full bg-pistachio-500/20"
                    aria-hidden="true"
                >
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="size-3"
                    >
                        <path d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </span>
                {{ success }}
            </p>
            <p
                v-if="error"
                class="flex items-center gap-2.5 rounded-xl bg-red-500/10 px-4 py-2.5 text-sm font-bold text-red-600 dark:text-red-400"
            >
                <span
                    class="grid size-5 shrink-0 place-items-center rounded-full bg-red-500/20 text-[11px] font-black"
                    aria-hidden="true"
                >!</span>
                {{ error }}
            </p>
        </div>
    </Transition>
</template>
