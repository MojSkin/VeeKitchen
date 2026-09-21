<script setup>
import { computed } from 'vue';
import { faDigits } from '@/lib/format';

const props = defineProps({
    name: { type: String, required: true },
    unitLabel: { type: String, required: true },
    current: { type: Number, required: true },
    threshold: { type: Number, required: true },
    isLow: { type: Boolean, default: false },
    isActive: { type: Boolean, default: true },
});

/**
 * The vial scale always shows the threshold marker: the larger of
 * (current, threshold × 1.25), floored so small stocks still render
 * a tube that reads as "almost empty" rather than "overflowing".
 */
const scaleMax = computed(() => Math.max(props.current, props.threshold * 1.25, 0.001));

const fillPercent = computed(() => Math.min(100, (props.current / scaleMax.value) * 100));

const thresholdPercent = computed(() => Math.min(100, (props.threshold / scaleMax.value) * 100));

const fillTone = computed(() => {
    if (props.current <= props.threshold) {
        return 'bg-red-400/70';
    }

    if (props.current <= props.threshold * 1.5) {
        return 'bg-amber-300/70';
    }

    return 'bg-pistachio-300/70';
});

const fillHeight = computed(() => `${faDigits(Math.round(fillPercent.value))}٪`);
</script>

<template>
    <div class="flex items-stretch gap-3">
        <!-- The vial: a sunken well with the fill rising inside -->
        <div
            class="neo-pressed relative w-9 shrink-0 overflow-hidden rounded-full"
            role="img"
            :aria-label="`موجودی ${name}: ${fillHeight}`"
        >
            <div
                class="absolute inset-x-0 bottom-0 transition-[height] duration-500 ease-glass"
                :class="fillTone"
                :style="{ height: `${fillPercent}%` }"
            />
            <!-- Threshold marker -->
            <div
                class="absolute inset-x-0 border-t-2 border-dashed border-night-900/50 dark:border-night-50/60"
                :style="{ bottom: `${thresholdPercent}%` }"
            />
        </div>

        <div class="min-w-0 flex-1">
            <p class="truncate font-bold" :class="{ 'opacity-40': !isActive }">
                {{ name }}
            </p>
            <p class="text-sm opacity-70">
                <span class="font-black" :class="isLow ? 'text-red-500' : ''">
                    {{ faDigits(current.toLocaleString('en-US', { maximumFractionDigits: 3 })) }}
                </span>
                {{ unitLabel }}
            </p>
            <p v-if="isLow" class="mt-0.5 text-xs font-bold text-red-500">
                زیر خط هشدار
            </p>
        </div>
    </div>
</template>
