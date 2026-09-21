<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { format } from 'date-fns';
import AppLayout from '@/Layouts/AppLayout.vue';
import JalaliDatePicker from '@/Components/JalaliDatePicker.vue';
import { faDigits, formatToman } from '@/lib/format';
import { formatJalaliDay } from '@/lib/jalali';

const props = defineProps({
    branch: { type: Object, required: true },
    discounts: { type: Array, required: true },
    categories: { type: Array, required: true },
    products: { type: Array, required: true },
    types: { type: Array, required: true },
    scopes: { type: Array, required: true },
});

/* ── Board rows: live state at a glance ─────────────────────────── */

const liveDiscounts = computed(() => props.discounts.filter((discount) => discount.currently_active));
const pausedDiscounts = computed(() => props.discounts.filter((discount) => !discount.is_active));
const scheduledDiscounts = computed(() => props.discounts.filter((discount) => discount.is_active && !discount.currently_active && !startedYet(discount)));
const drainedDiscounts = computed(() => props.discounts.filter((discount) => discount.is_active && discount.currently_active === false && startedYet(discount)));

function startedYet(discount) {
    return discount.starts_at === null || new Date(discount.starts_at) <= new Date();
}

/* ── Create form ────────────────────────────────────────────────── */

const emptyForm = () => ({
    name: '',
    code: '',
    type: 'percentage',
    value: '',
    applies_to: 'entire_order',
    menu_category_id: '',
    product_id: '',
    min_order_total: '',
    starts_at: '',
    expires_at: '',
    usage_limit_total: '',
    usage_limit_per_user: '',
});

const form = ref(emptyForm());
const saving = ref(false);

const needsCategory = computed(() => form.value.applies_to === 'category');
const needsProduct = computed(() => form.value.applies_to === 'product');

function submit() {
    saving.value = true;

    router.post(route('admin.discounts.store'), normalize(form.value), {
        preserveScroll: true,
        onSuccess: () => {
            form.value = emptyForm();
        },
        onFinish: () => {
            saving.value = false;
        },
    });
}

/**
 * Empty strings → null so nullable columns and date windows stay honest.
 * The picker's Date values serialize to Y-m-d strings — the same Gregorian
 * wire format the backend has always validated — while presentation stays
 * Jalali-only.
 */
function normalize(input) {
    return Object.fromEntries(
        Object.entries(input).map(([key, value]) => {
            if ((key === 'starts_at' || key === 'expires_at') && value instanceof Date) {
                return [key, format(value, 'yyyy-MM-dd')];
            }

            return [key, value === '' ? null : value];
        }),
    );
}

/* ── Row actions ────────────────────────────────────────────────── */

const editingId = ref(null);
const editForm = ref(null);
const busyId = ref(null);

function startEdit(discount) {
    editingId.value = discount.id;
    editForm.value = {
        name: discount.name,
        code: discount.code ?? '',
        type: discount.type,
        value: discount.value,
        applies_to: discount.applies_to,
        menu_category_id: '',
        product_id: '',
        min_order_total: discount.min_order_total > 0 ? discount.min_order_total : '',
        starts_at: '',
        expires_at: '',
        usage_limit_total: discount.usage_limit_total ?? '',
        usage_limit_per_user: discount.usage_limit_per_user ?? '',
    };
}

function saveEdit(discount) {
    busyId.value = discount.id;

    router.put(route('admin.discounts.update', discount.id), normalize(editForm.value), {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
        },
        onFinish: () => {
            busyId.value = null;
        },
    });
}

function toggle(discount) {
    busyId.value = discount.id;

    router.post(route('admin.discounts.toggle', discount.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            busyId.value = null;
        },
    });
}

function remove(discount) {
    if (!window.confirm(`تخفیف «${discount.name}» حذف شود؟`)) {
        return;
    }

    busyId.value = discount.id;

    router.delete(route('admin.discounts.destroy', discount.id), {
        preserveScroll: true,
        onFinish: () => {
            busyId.value = null;
        },
    });
}

/* ── Presentation helpers ───────────────────────────────────────── */

function valueLabel(discount) {
    return discount.type === 'percentage'
        ? `${faDigits(discount.value)}٪`
        : formatToman(discount.value);
}

function scopeLabel(discount) {
    return discount.target_name === null
        ? discount.scope_label
        : `${discount.scope_label}: ${discount.target_name}`;
}

function windowLabel(discount) {
    if (discount.starts_at === null && discount.expires_at === null) {
        return null;
    }

    const from = discount.starts_at === null ? null : formatJalaliDay(discount.starts_at);
    const to = discount.expires_at === null ? null : formatJalaliDay(discount.expires_at);

    if (from !== null && to !== null) {
        return `از ${from} تا ${to}`;
    }

    return from !== null ? `از ${from}` : `تا ${to}`;
}


</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-6xl px-4 py-6">
            <header class="mb-6">
                <h1 class="text-2xl font-bold">مدیریت تخفیف‌ها</h1>
                <p class="mt-1 text-sm opacity-60">{{ branch.name }}</p>
            </header>

            <!-- New discount -->
            <form
                class="glass mb-8 rounded-glass p-4"
                @submit.prevent="submit"
            >
                <h2 class="mb-3 text-lg font-bold">تخفیف جدید</h2>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        placeholder="نام تخفیف (مثلاً جشنوارهٔ پیتزا)"
                        class="glass-flat rounded-xl px-3 py-2 text-sm outline-none"
                    >
                    <input
                        v-model="form.code"
                        type="text"
                        placeholder="کد کوپن (اختیاری)"
                        class="glass-flat rounded-xl px-3 py-2 text-sm outline-none"
                        dir="ltr"
                    >
                    <select
                        v-model="form.type"
                        class="glass-flat cursor-pointer rounded-xl px-3 py-2 text-sm outline-none"
                        aria-label="نوع تخفیف"
                    >
                        <option v-for="type in types" :key="type.value" :value="type.value">
                            {{ type.label }}
                        </option>
                    </select>
                    <input
                        v-model="form.value"
                        type="number"
                        min="0"
                        required
                        :placeholder="form.type === 'percentage' ? 'درصد (۰ تا ۱۰۰)' : 'مبلغ به تومان'"
                        class="glass-flat rounded-xl px-3 py-2 text-sm outline-none"
                        dir="ltr"
                    >
                    <select
                        v-model="form.applies_to"
                        class="glass-flat cursor-pointer rounded-xl px-3 py-2 text-sm outline-none"
                        aria-label="دامنهٔ تخفیف"
                    >
                        <option v-for="scope in scopes" :key="scope.value" :value="scope.value">
                            {{ scope.label }}
                        </option>
                    </select>
                    <select
                        v-if="needsCategory"
                        v-model="form.menu_category_id"
                        required
                        class="glass-flat cursor-pointer rounded-xl px-3 py-2 text-sm outline-none"
                        aria-label="دستهٔ منو"
                    >
                        <option value="" disabled>انتخاب دسته…</option>
                        <option v-for="category in categories" :key="category.id" :value="category.id">
                            {{ category.name }}
                        </option>
                    </select>
                    <select
                        v-if="needsProduct"
                        v-model="form.product_id"
                        required
                        class="glass-flat cursor-pointer rounded-xl px-3 py-2 text-sm outline-none"
                        aria-label="محصول"
                    >
                        <option value="" disabled>انتخاب محصول…</option>
                        <option v-for="product in products" :key="product.id" :value="product.id">
                            {{ product.name }}
                        </option>
                    </select>
                    <input
                        v-model="form.min_order_total"
                        type="number"
                        min="0"
                        placeholder="حداقل سفارش (تومان)"
                        class="glass-flat rounded-xl px-3 py-2 text-sm outline-none"
                        dir="ltr"
                    >
                    <JalaliDatePicker
                        v-model="form.starts_at"
                        placeholder="شروع (اختیاری)"
                    />
                    <JalaliDatePicker
                        v-model="form.expires_at"
                        placeholder="پایان (اختیاری)"
                    />
                    <input
                        v-model="form.usage_limit_total"
                        type="number"
                        min="1"
                        placeholder="سقف کل (اختیاری)"
                        class="glass-flat rounded-xl px-3 py-2 text-sm outline-none"
                        dir="ltr"
                    >
                    <input
                        v-model="form.usage_limit_per_user"
                        type="number"
                        min="1"
                        placeholder="سقف هر مشتری (اختیاری)"
                        class="glass-flat rounded-xl px-3 py-2 text-sm outline-none"
                        dir="ltr"
                    >
                </div>

                <button
                    type="submit"
                    class="mt-3 cursor-pointer rounded-xl bg-saffron-500 px-6 py-2 text-sm font-bold text-white transition hover:bg-saffron-600 disabled:opacity-40"
                    :disabled="saving"
                >
                    ساخت تخفیف
                </button>
            </form>

            <!-- Board -->
            <div class="space-y-3">
                <p
                    v-if="discounts.length === 0"
                    class="glass rounded-glass p-8 text-center text-sm opacity-60"
                >
                    هنوز تخفیفی ثبت نشده است.
                </p>

                <article
                    v-for="discount in discounts"
                    :key="discount.id"
                    class="glass rounded-glass p-4"
                    :class="discount.currently_active ? '' : 'opacity-70'"
                >
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-bold">{{ discount.name }}</h3>

                                <span
                                    v-if="discount.code"
                                    class="rounded-full bg-night-500/20 px-2 py-0.5 font-mono text-xs"
                                    dir="ltr"
                                >
                                    {{ discount.code }}
                                </span>

                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-bold"
                                    :class="discount.currently_active
                                        ? 'bg-pistachio-500/15 text-pistachio-600 dark:text-pistachio-400'
                                        : 'bg-night-500/20 opacity-70'"
                                >
                                    {{ discount.currently_active ? 'فعال' : 'غیرفعال' }}
                                </span>
                            </div>

                            <p class="mt-1 text-xs opacity-70">
                                {{ valueLabel(discount) }} · {{ scopeLabel(discount) }}
                                <template v-if="discount.min_order_total > 0">
                                    · حداقل {{ formatToman(discount.min_order_total) }} تومان
                                </template>
                            </p>

                            <p class="mt-1 text-xs opacity-50">
                                <template v-if="windowLabel(discount)">
                                    {{ windowLabel(discount) }} ·
                                </template>
                                مصرف‌شده: {{ faDigits(discount.used_count) }}
                                <template v-if="discount.usage_limit_total !== null">
                                    از {{ faDigits(discount.usage_limit_total) }}
                                </template>
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <button
                                type="button"
                                class="glass-flat cursor-pointer rounded-xl px-4 py-2 text-sm transition hover:bg-white/10 disabled:opacity-40"
                                :disabled="busyId === discount.id"
                                @click="toggle(discount)"
                            >
                                {{ discount.is_active ? 'توقف' : 'فعال‌سازی' }}
                            </button>
                            <button
                                v-if="discount.is_editable"
                                type="button"
                                class="glass-flat cursor-pointer rounded-xl px-4 py-2 text-sm transition hover:bg-white/10"
                                @click="startEdit(discount)"
                            >
                                ویرایش
                            </button>
                            <button
                                v-if="discount.is_deletable"
                                type="button"
                                class="cursor-pointer rounded-xl px-3 py-2 text-sm text-red-500 transition hover:bg-red-500/10 disabled:opacity-40"
                                :disabled="busyId === discount.id"
                                @click="remove(discount)"
                            >
                                حذف
                            </button>
                        </div>
                    </div>

                    <!-- Inline edit -->
                    <div
                        v-if="editingId === discount.id"
                        class="mt-4 space-y-2 border-t border-white/10 pt-4"
                    >
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                            <input
                                v-model="editForm.name"
                                type="text"
                                required
                                placeholder="نام تخفیف"
                                class="glass-flat rounded-xl px-3 py-2 text-sm outline-none"
                            >
                            <input
                                v-model="editForm.code"
                                type="text"
                                placeholder="کد کوپن (اختیاری)"
                                class="glass-flat rounded-xl px-3 py-2 text-sm outline-none"
                                dir="ltr"
                            >
                            <select
                                v-model="editForm.type"
                                class="glass-flat cursor-pointer rounded-xl px-3 py-2 text-sm outline-none"
                                aria-label="نوع تخفیف"
                            >
                                <option v-for="type in types" :key="type.value" :value="type.value">
                                    {{ type.label }}
                                </option>
                            </select>
                            <input
                                v-model="editForm.value"
                                type="number"
                                min="0"
                                required
                                placeholder="مقدار"
                                class="glass-flat rounded-xl px-3 py-2 text-sm outline-none"
                                dir="ltr"
                            >
                            <input
                                v-model="editForm.min_order_total"
                                type="number"
                                min="0"
                                placeholder="حداقل سفارش (تومان)"
                                class="glass-flat rounded-xl px-3 py-2 text-sm outline-none"
                                dir="ltr"
                            >
                            <input
                                v-model="editForm.usage_limit_total"
                                type="number"
                                min="1"
                                placeholder="سقف کل (اختیاری)"
                                class="glass-flat rounded-xl px-3 py-2 text-sm outline-none"
                                dir="ltr"
                            >
                            <input
                                v-model="editForm.usage_limit_per_user"
                                type="number"
                                min="1"
                                placeholder="سقف هر مشتری (اختیاری)"
                                class="glass-flat rounded-xl px-3 py-2 text-sm outline-none"
                                dir="ltr"
                            >
                        </div>

                        <div class="flex flex-wrap gap-2 pt-1">
                            <button
                                type="button"
                                class="cursor-pointer rounded-xl bg-pistachio-500 px-4 py-2 text-sm font-bold text-white transition hover:bg-pistachio-600 disabled:opacity-40"
                                :disabled="busyId === discount.id"
                                @click="saveEdit(discount)"
                            >
                                ذخیره
                            </button>
                            <button
                                type="button"
                                class="cursor-pointer px-3 py-2 text-sm opacity-60 transition hover:opacity-90"
                                @click="editingId = null"
                            >
                                انصراف
                            </button>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </AppLayout>
</template>
