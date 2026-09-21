<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StockVial from '@/Components/StockVial.vue';
import { faDigits, formatToman } from '@/lib/format';

const props = defineProps({
    branch: { type: Object, required: true },
    items: { type: Array, required: true },
    products: { type: Array, required: true },
    units: { type: Array, required: true },
});

const page = usePage();

/* ── Materials board ─────────────────────────────────────────── */

const items = ref(props.items.map((item) => ({ ...item })));

const lowCount = computed(() => items.value.filter((item) => item.is_low && item.is_active).length);

let inventoryChannel = null;

onMounted(() => {
    // Live meters: every stock move on the branch repaints its vial.
    inventoryChannel = window.Echo.private(`branch.${props.branch.id}.inventory`)
        .listen('.stock.changed', (payload) => {
            const item = items.value.find((candidate) => candidate.id === payload.id);

            if (!item) {
                return;
            }

            item.current_stock = payload.current_stock;
            item.low_stock_threshold = payload.low_stock_threshold;
            item.is_low = payload.low;
        });
});

onBeforeUnmount(() => {
    if (inventoryChannel) {
        window.Echo.leave(`branch.${props.branch.id}.inventory`);
    }
});

const busyItemId = ref(null);
const adjustForms = ref({});

function adjustFor(item) {
    if (!adjustForms.value[item.id]) {
        adjustForms.value[item.id] = { delta: '', reason: '' };
    }

    return adjustForms.value[item.id];
}

function submitAdjust(item) {
    const form = adjustFor(item);
    busyItemId.value = item.id;

    router.post(
        route('admin.inventory.items.adjust', item.id),
        { delta: form.delta, reason: form.reason },
        {
            preserveScroll: true,
            onSuccess: () => {
                form.delta = '';
                form.reason = '';
            },
            onFinish: () => {
                busyItemId.value = null;
            },
        },
    );
}

function toggleItem(item) {
    busyItemId.value = item.id;

    router.post(
        route('admin.inventory.items.toggle', item.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                busyItemId.value = null;
            },
        },
    );
}

const wasteForms = ref({});

function wasteFor(item) {
    if (!wasteForms.value[item.id]) {
        wasteForms.value[item.id] = { quantity: '', reason: '', expired_on: '' };
    }

    return wasteForms.value[item.id];
}

function submitWaste(item) {
    const form = wasteFor(item);
    busyItemId.value = item.id;

    router.post(
        route('admin.inventory.items.waste', item.id),
        {
            quantity: form.quantity,
            reason: form.reason,
            expired_on: form.expired_on || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                form.quantity = '';
                form.reason = '';
                form.expired_on = '';
            },
            onFinish: () => {
                busyItemId.value = null;
            },
        },
    );
}

const newItem = ref({ name: '', unit: 'kg', current_stock: '', low_stock_threshold: '' });
const addingItem = ref(false);

function submitNewItem() {
    addingItem.value = true;

    router.post(
        route('admin.inventory.items.store'),
        newItem.value,
        {
            preserveScroll: true,
            onSuccess: () => {
                newItem.value = { name: '', unit: 'kg', current_stock: '', low_stock_threshold: '' };
            },
            onFinish: () => {
                addingItem.value = false;
            },
        },
    );
}

/* ── Cost preview ────────────────────────────────────────────── */

/**
 * Live material cost of the recipe being edited: Σ(line qty × unit cost).
 * Components apply on top of it in the saved chain; this is just the base.
 */
const liveMaterialCost = computed(() => {
    if (editingProductId.value === null) {
        return 0;
    }

    return recipeLines.value.reduce((total, line) => {
        const item = items.value.find((candidate) => candidate.id === Number(line.inventory_item_id));
        const quantity = parseFloat(line.quantity_per_unit) || 0;

        return total + (item ? item.unit_cost * quantity : 0);
    }, 0);
});

/* ── Recipe editor ───────────────────────────────────────────── */

const editingProductId = ref(null);
const recipeLines = ref([]);
const recipeErrors = ref({});

function startRecipe(product) {
    editingProductId.value = product.id;
    recipeErrors.value = {};
    recipeLines.value = product.recipes.map((line) => ({
        inventory_item_id: line.inventory_item_id,
        quantity_per_unit: line.quantity_per_unit,
    }));
}

function cancelRecipe() {
    editingProductId.value = null;
    recipeLines.value = [];
}

function addRecipeLine() {
    const firstItem = items.value[0];

    if (!firstItem) {
        return;
    }

    recipeLines.value.push({ inventory_item_id: firstItem.id, quantity_per_unit: '' });
}

function removeRecipeLine(index) {
    recipeLines.value.splice(index, 1);
}

function saveRecipe(product) {
    router.post(
        route('admin.products.recipe.save', product.id),
        { lines: recipeLines.value },
        {
            preserveScroll: true,
            onError: (errors) => {
                recipeErrors.value = errors;
            },
            onSuccess: () => {
                cancelRecipe();
            },
        },
    );
}

function itemName(id) {
    return items.value.find((item) => item.id === Number(id))?.name ?? '';
}

function itemUnitLabel(id) {
    return items.value.find((item) => item.id === Number(id))?.unit_label ?? '';
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-6xl px-4 py-6">
            <header class="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">انبار و فرمول تولید</h1>
                    <p class="mt-1 text-sm opacity-60">{{ branch.name }}</p>
                </div>
                <p
                    v-if="lowCount > 0"
                    class="glass-flat rounded-xl px-4 py-2 text-sm font-bold text-red-500"
                >
                    {{ faDigits(lowCount) }} متریال زیر خط هشدار
                </p>
            </header>

            <p
                v-if="page.props.errors && Object.keys(page.props.errors).length > 0"
                class="glass mb-4 rounded-2xl border-red-400/40 p-4 text-sm text-red-500"
            >
                {{ page.props.errors.delta ?? page.props.errors.lines ?? page.props.errors.name }}
            </p>

            <!-- Materials -->
            <section class="mb-10">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-xl font-bold">متریال‌ها</h2>
                    <span class="text-sm opacity-50">{{ faDigits(items.length) }} قلم</span>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="item in items"
                        :key="item.id"
                        class="glass rounded-2xl p-4"
                        :class="item.is_low && item.is_active ? 'ring-1 ring-red-400/60' : ''"
                    >
                        <StockVial
                            :name="item.name"
                            :unit-label="item.unit_label"
                            :current="item.current_stock"
                            :threshold="item.low_stock_threshold"
                            :is-low="item.is_low"
                            :is-active="item.is_active"
                        />

                        <details class="mt-3">
                            <summary class="cursor-pointer text-xs opacity-60 transition hover:opacity-90">
                                اصلاح موجودی
                            </summary>
                            <div class="mt-2 space-y-2">
                                <input
                                    v-model="adjustFor(item).delta"
                                    type="number"
                                    step="0.001"
                                    inputmode="decimal"
                                    placeholder="مقدار (مثلاً ۲ یا ۲-)"
                                    class="glass-flat w-full rounded-xl px-3 py-2 text-sm outline-none"
                                    dir="ltr"
                                >
                                <input
                                    v-model="adjustFor(item).reason"
                                    type="text"
                                    placeholder="دلیل اصلاح"
                                    class="glass-flat w-full rounded-xl px-3 py-2 text-sm outline-none"
                                >
                                <button
                                    type="button"
                                    class="glass-flat w-full cursor-pointer rounded-xl px-3 py-2 text-sm font-bold transition hover:bg-white/10 disabled:opacity-40"
                                    :disabled="busyItemId === item.id"
                                    @click="submitAdjust(item)"
                                >
                                    ثبت اصلاح
                                </button>
                                <button
                                    type="button"
                                    class="w-full cursor-pointer text-xs opacity-50 transition hover:opacity-90"
                                    :disabled="busyItemId === item.id"
                                    @click="toggleItem(item)"
                                >
                                    {{ item.is_active ? 'غیرفعال کردن' : 'فعال کردن' }}
                                </button>
                            </div>
                        </details>

                        <details class="mt-2">
                            <summary class="cursor-pointer text-xs opacity-60 transition hover:opacity-90">
                                ثبت ضایعات
                            </summary>
                            <div class="mt-2 space-y-2">
                                <input
                                    v-model="wasteFor(item).quantity"
                                    type="number"
                                    step="0.001"
                                    min="0.001"
                                    placeholder="مقدار ضایعات"
                                    class="glass-flat w-full rounded-xl px-3 py-2 text-sm outline-none"
                                    dir="ltr"
                                >
                                <input
                                    v-model="wasteFor(item).reason"
                                    type="text"
                                    placeholder="دلیل (مثلاً تاریخ انقضا)"
                                    class="glass-flat w-full rounded-xl px-3 py-2 text-sm outline-none"
                                >
                                <input
                                    v-model="wasteFor(item).expired_on"
                                    type="date"
                                    class="glass-flat w-full rounded-xl px-3 py-2 text-sm outline-none"
                                    aria-label="تاریخ انقضا (اختیاری)"
                                >
                                <button
                                    type="button"
                                    class="w-full cursor-pointer rounded-xl bg-red-500/90 px-3 py-2 text-sm font-bold text-white transition hover:bg-red-600 disabled:opacity-40"
                                    :disabled="busyItemId === item.id"
                                    @click="submitWaste(item)"
                                >
                                    ثبت ضایعات و کسر موجودی
                                </button>
                            </div>
                        </details>
                    </article>
                </div>

                <!-- New material -->
                <form
                    class="glass mt-4 grid gap-3 rounded-2xl p-4 sm:grid-cols-[2fr_1fr_1fr_1fr_auto]"
                    @submit.prevent="submitNewItem"
                >
                    <input
                        v-model="newItem.name"
                        type="text"
                        required
                        placeholder="نام متریال جدید"
                        class="glass-flat rounded-xl px-3 py-2 text-sm outline-none"
                    >
                    <select
                        v-model="newItem.unit"
                        class="glass-flat cursor-pointer rounded-xl px-3 py-2 text-sm outline-none"
                        aria-label="واحد اندازه‌گیری"
                    >
                        <option v-for="unit in units" :key="unit.value" :value="unit.value">
                            {{ unit.label }}
                        </option>
                    </select>
                    <input
                        v-model="newItem.current_stock"
                        type="number"
                        step="0.001"
                        min="0"
                        required
                        placeholder="موجودی اولیه"
                        class="glass-flat rounded-xl px-3 py-2 text-sm outline-none"
                        dir="ltr"
                    >
                    <input
                        v-model="newItem.low_stock_threshold"
                        type="number"
                        step="0.001"
                        min="0"
                        required
                        placeholder="خط هشدار"
                        class="glass-flat rounded-xl px-3 py-2 text-sm outline-none"
                        dir="ltr"
                    >
                    <button
                        type="submit"
                        class="cursor-pointer rounded-xl bg-lajvard-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-lajvard-700 disabled:opacity-40"
                        :disabled="addingItem"
                    >
                        افزودن
                    </button>
                </form>
            </section>

            <!-- Recipes -->
            <section>
                <h2 class="mb-4 text-xl font-bold">فرمول تولید محصولات</h2>

                <div class="space-y-3">
                    <article
                        v-for="product in products"
                        :key="product.id"
                        class="glass rounded-2xl p-4"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="font-bold">{{ product.name }}</h3>
                                <p class="mt-0.5 text-xs opacity-60">
                                    <template v-if="product.recipes.length === 0">
                                        بدون فرمول — مصرف انباری ثبت نمی‌شود
                                    </template>
                                    <template v-else>
                                        {{ product.recipes.map((line) => line.name).join('، ') }}
                                    </template>
                                </p>
                                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                                    <span class="opacity-60">
                                        متریال:
                                        <b class="opacity-100">{{ formatToman(product.material_cost) }}</b>
                                    </span>
                                    <span class="opacity-60">
                                        تمام‌شده:
                                        <b class="text-saffron-600 dark:text-saffron-400">{{ formatToman(product.cost_price) }}</b>
                                    </span>
                                    <span class="opacity-60">
                                        فروش پیشنهادی:
                                        <b class="text-pistachio-600 dark:text-pistachio-400">{{ formatToman(product.suggested_sale_price) }}</b>
                                    </span>
                                    <span
                                        v-if="product.sale_price > 0 && product.cost_price > 0"
                                        class="font-bold"
                                        :class="product.sale_price >= product.cost_price ? 'text-pistachio-600 dark:text-pistachio-400' : 'text-red-500'"
                                    >
                                        حاشیه:
                                        {{ faDigits(Math.round(((product.sale_price - product.cost_price) / product.sale_price) * 100)) }}٪
                                    </span>
                                </div>
                            </div>
                            <div class="flex shrink-0 flex-col gap-2">
                                <button
                                    type="button"
                                    class="glass-flat cursor-pointer rounded-xl px-4 py-2 text-sm transition hover:bg-white/10"
                                    @click="startRecipe(product)"
                                >
                                    ویرایش فرمول
                                </button>
                                <Link
                                    :href="route('admin.products.recipe-versions', product.id)"
                                    class="cursor-pointer text-center text-xs opacity-50 transition hover:opacity-90"
                                >
                                    تاریخچه
                                </Link>
                            </div>
                        </div>

                        <div
                            v-if="editingProductId === product.id"
                            class="mt-4 space-y-2 border-t border-white/10 pt-4"
                        >
                            <p
                                v-if="recipeErrors.lines"
                                class="text-sm font-bold text-red-500"
                            >
                                {{ recipeErrors.lines }}
                            </p>

                            <div
                                v-for="(line, index) in recipeLines"
                                :key="index"
                                class="flex flex-wrap items-center gap-2"
                            >
                                <select
                                    v-model="line.inventory_item_id"
                                    class="glass-flat min-w-40 flex-1 cursor-pointer rounded-xl px-3 py-2 text-sm outline-none"
                                    :aria-label="`متریال خط ${index + 1}`"
                                >
                                    <option v-for="item in items" :key="item.id" :value="item.id">
                                        {{ item.name }}
                                    </option>
                                </select>
                                <div class="relative">
                                    <input
                                        v-model="line.quantity_per_unit"
                                        type="number"
                                        step="0.001"
                                        min="0.001"
                                        required
                                        placeholder="مقدار"
                                        class="glass-flat w-28 rounded-xl px-3 py-2 pl-14 text-sm outline-none"
                                        dir="ltr"
                                        :aria-label="`مقدار خط ${index + 1}`"
                                    >
                                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs opacity-60">
                                        {{ itemUnitLabel(line.inventory_item_id) }}
                                    </span>
                                </div>
                                <button
                                    type="button"
                                    class="cursor-pointer rounded-xl px-2 py-2 text-sm text-red-500 transition hover:bg-red-500/10"
                                    :aria-label="`حذف خط ${index + 1}`"
                                    @click="removeRecipeLine(index)"
                                >
                                    حذف
                                </button>
                            </div>

                            <p class="glass-flat rounded-xl px-3 py-2 text-xs">
                                هزینهٔ متریال این ترکیب:
                                <b>{{ formatToman(liveMaterialCost) }}</b>
                                تومان
                                <template v-if="product.components.length > 0">
                                    <span class="opacity-60">
                                        + اجزا ({{ product.components.map((component) => component.label).join('، ') }})
                                        طبق زنجیرهٔ ذخیره‌شده اعمال می‌شود
                                    </span>
                                </template>
                            </p>

                            <div class="flex flex-wrap gap-2 pt-1">
                                <button
                                    type="button"
                                    class="glass-flat cursor-pointer rounded-xl px-4 py-2 text-sm transition hover:bg-white/10"
                                    @click="addRecipeLine"
                                >
                                    افزودن متریال
                                </button>
                                <button
                                    type="button"
                                    class="cursor-pointer rounded-xl bg-pistachio-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-pistachio-700"
                                    @click="saveRecipe(product)"
                                >
                                    ذخیرهٔ فرمول
                                </button>
                                <button
                                    type="button"
                                    class="cursor-pointer px-3 py-2 text-sm opacity-60 transition hover:opacity-90"
                                    @click="cancelRecipe"
                                >
                                    انصراف
                                </button>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
