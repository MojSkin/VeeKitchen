<script setup>
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const success = computed(() => page.props.flash?.success);
const error = computed(() => page.props.flash?.error);

const visible = ref(false);

watch([success, error], () => {
    visible.value = true;
    setTimeout(() => {
        visible.value = false;
    }, 4000);
});
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-y-4 opacity-0"
        leave-active-class="transition duration-200 ease-in"
        leave-to-class="opacity-0"
    >
        <div
            v-if="visible && (success || error)"
            class="glass glass-raised fixed inset-x-4 top-4 z-50 mx-auto max-w-md rounded-2xl p-4 text-center"
        >
            <p v-if="success" class="text-sm font-medium text-pistachio-600 dark:text-pistachio-400">
                {{ success }}
            </p>
            <p v-if="error" class="text-sm font-medium text-red-500">
                {{ error }}
            </p>
        </div>
    </Transition>
</template>
