<script setup>
import { computed, ref } from 'vue';
import { faDigits, formatToman } from '@/lib/format';

const props = defineProps({
    product: { type: Object, required: true },
    versions: { type: Array, required: true },
});

/* ── Version list ────────────────────────────────────────────── */

const selectedIds = ref([]);

function toggleSelect(version) {
    const index = selectedIds.value.indexOf(version.id);

    if (index >= 0) {
        selectedIds.value.splice(index, 1);

        return;
    }

    // Only the last two picks matter; older picks rotate out.
    selectedIds.value.push(version.id);

    if (selectedIds.value.length > 2) {
        selectedIds.value.shift();
    }
}

function isSelected(version) {
    return selectedIds.value.includes(version.id);
}

const compareA = computed(() => props.versions.find((version) => version.id === selectedIds.value[0]) ?? null);
const compareB = computed(() => props.versions.find((version) => version.id === selectedIds.value[1]) ?? null);

/* ── Diff ────────────────────────────────────────────────────── */

const diffRows = computed(() => {
    if (! compareA.value || ! compareB.value) {
        return [];
    }

    const [older, newer] = [compareA.value, compareB.value];
    const names = [...new Set([
        ...older.lines.map((line) => line.name),
        ...newer.lines.map((line) => line.name),
    ])];

    const rows = names.map((name) => {
        const a = older.lines.find((line) => line.name === name) ?? null;
        const b = newer.lines.find((line) => line.name === name) ?? null;
        const aQty = a ? a.quantity_per_unit : 0;
        const bQty = b ? b.quantity_per_unit : 0;
        const aCost = a ? a.quantity_per_unit * a.unit_cost : 0;
        const bCost = b ? b.quantity_per_unit * b.unit_cost : 0;

        return {
            kind: 'line',
            label: name,
            a: a ? `${faDigits(a.quantity_per_unit)} ${a.unit_label} × ${formatToman(a.unit_cost)}` : '—',
            b: b ? `${faDigits(b.quantity_per_unit)} ${b.unit_label} × ${formatToman(b.unit_cost)}` : '—',
            delta: bCost - aCost,
        };
    });

    rows.push({
        kind: 'total',
        label: 'هزینهٔ متریال',
        a: `${formatToman(older.material_cost)} تومان`,
        b: `${formatToman(newer.material_cost)} تومان`,
        delta: newer.material_cost - older.material_cost,
    });
    rows.push({
        kind: 'total',
        label: 'قیمت تمام‌شده',
        a: `${formatToman(older.cost_price)} تومان`,
        b: `${formatToman(newer.cost_price)} تومان`,
        delta: newer.cost_price - older.cost_price,
    });
    rows.push({
        kind: 'total',
        label: 'فروش پیشنهادی',
        a: `${formatToman(older.suggested_sale_price)} تومان`,
        b: `${formatToman(newer.suggested_sale_price)} تومان`,
        delta: newer.suggested_sale_price - older.suggested_sale_price,
    });

    return rows;
});

function timeLabel(iso) {
    if (! iso) {
        return '';
    }

    const date = new Date(iso);

    return faDigits(`${date.getFullYear()}/${date.getMonth() + 1}/${date.getDate()}`);
}
</script>

<template>
    <div class="mx-auto max-w-4xl px-4 py-6">
        <header class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold">تاریخچهٔ فرمول</h1>
                <p class="mt-1 text-sm opacity-60">{{ product.name }}</p>
            </div>
            <a
                :href="route('admin.inventory')"
                class="glass-flat rounded-xl px-4 py-2 text-sm transition hover:bg-white/10"
            >
                بازگشت به انبار
            </a>
        </header>

        <p
            v-if="versions.length === 0"
            class="glass rounded-glass p-8 text-center opacity-60"
        >
            هنوز نسخه‌ای ثبت نشده — با اولین ذخیرهٔ فرمول، یک نسخه ساخته می‌شود.
        </p>

        <div v-else class="space-y-6">
            <!-- Versions -->
            <div class="space-y-3">
                <article
                    v-for="version in versions"
                    :key="version.id"
                    class="glass rounded-glass p-4"
                    :class="isSelected(version) ? 'ring-1 ring-saffron-500/70' : ''"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <label class="flex cursor-pointer items-center gap-3">
                            <input
                                type="checkbox"
                                :checked="isSelected(version)"
                                class="h-4 w-4 accent-saffron-500"
                                :aria-label="`انتخاب نسخهٔ ${version.version_number} برای مقایسه`"
                                @change="toggleSelect(version)"
                            >
                            <span class="font-bold">نسخهٔ {{ faDigits(version.version_number) }}</span>
                            <span class="text-xs opacity-50">
                                {{ timeLabel(version.saved_at) }}
                                <template v-if="version.author"> · {{ version.author }}</template>
                            </span>
                        </label>
                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs">
                            <span class="opacity-60">
                                متریال: <b class="opacity-100">{{ formatToman(version.material_cost) }}</b>
                            </span>
                            <span class="opacity-60">
                                تمام‌شده: <b class="text-saffron-600 dark:text-saffron-400">{{ formatToman(version.cost_price) }}</b>
                            </span>
                            <span class="opacity-60">
                                فروش پیشنهادی: <b class="text-pistachio-600 dark:text-pistachio-400">{{ formatToman(version.suggested_sale_price) }}</b>
                            </span>
                        </div>
                    </div>

                    <p class="mt-2 text-xs opacity-70">
                        {{ version.lines.map((line) => `${line.name} (${faDigits(line.quantity_per_unit)} ${line.unit_label})`).join('، ') }}
                    </p>
                </article>
            </div>

            <!-- Compare -->
            <section v-if="compareA && compareB" class="glass rounded-glass p-5">
                <h2 class="mb-4 text-lg font-bold">
                    مقایسهٔ نسخهٔ {{ faDigits(compareA.version_number) }} با نسخهٔ {{ faDigits(compareB.version_number) }}
                </h2>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs opacity-50">
                                <th class="pb-2 text-right font-medium">مورد</th>
                                <th class="pb-2 text-right font-medium">نسخهٔ {{ faDigits(compareA.version_number) }}</th>
                                <th class="pb-2 text-right font-medium">نسخهٔ {{ faDigits(compareB.version_number) }}</th>
                                <th class="pb-2 text-right font-medium">تغییر هزینه</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in diffRows"
                                :key="row.label"
                                class="border-t border-white/5"
                                :class="row.kind === 'total' ? 'font-bold' : ''"
                            >
                                <td class="py-2">{{ row.label }}</td>
                                <td class="py-2 opacity-80" dir="auto">{{ row.a }}</td>
                                <td class="py-2 opacity-80" dir="auto">{{ row.b }}</td>
                                <td
                                    class="py-2"
                                    :class="row.delta > 0 ? 'text-red-500' : row.delta < 0 ? 'text-pistachio-500' : 'opacity-40'"
                                >
                                    <template v-if="row.delta > 0">+{{ formatToman(row.delta) }}</template>
                                    <template v-else-if="row.delta < 0">−{{ formatToman(Math.abs(row.delta)) }}</template>
                                    <template v-else>بدون تغییر</template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <p v-else class="glass rounded-glass p-4 text-center text-sm opacity-60">
                برای مقایسه، دو نسخه را تیک بزنید.
            </p>
        </div>
    </div>
</template>
