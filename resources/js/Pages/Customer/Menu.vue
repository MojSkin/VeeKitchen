<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { addToCart, cartTotal, clearCart, loadCart, saveCart, setQuantity } from '@/lib/cart';
import { formatTomanWithUnit } from '@/lib/format';
import PublicLayout from '@/Layouts/PublicLayout.vue';

const props = defineProps({
    table: { type: Object, default: null },
    categories: { type: Array, required: true },
    discounts: { type: Object, default: () => ({ banner: [], product_badges: {} }) },
});

const scope = computed(() => props.table?.qr_token ?? 'public');
const cart = ref(loadCart(scope.value));
const guestName = ref('');
const notes = ref('');
const submitting = ref(false);
const placedOrder = ref(null);
const activeCategory = ref(props.categories[0]?.id ?? null);

/* ── Coupon + live totals ─────────────────────────────────────── */

const couponCode = ref('');
const quote = ref(null);
const quoting = ref(false);
let quoteTimer = null;

/**
 * Debounced quote: any cart/typing change re-prices through the same
 * server pipeline the order will follow.
 */
watch([cart, couponCode], () => {
    if (quoteTimer) {
        clearTimeout(quoteTimer);
    }

    if (cart.value.length === 0) {
        quote.value = null;
        return;
    }

    quoteTimer = setTimeout(fetchQuote, 350);
}, { deep: true });

/**
 * Raw fetch — the quote endpoint is a partial-props service, not an
 * Inertia page, so the Inertia router's component handshake (409 on a
 * prop-only POST) does not apply here.
 */
function fetchQuote() {
    quoting.value = true;

    const xsrf = decodeURIComponent(
        (document.cookie.split(';').find((row) => row.trim().startsWith('XSRF-TOKEN=')) ?? '')
            .split('=').slice(1).join('='),
    );

    fetch(route('cart.quote'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-XSRF-TOKEN': xsrf,
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            items: cart.value.map((line) => ({
                product_id: line.productId,
                quantity: line.quantity,
            })),
            discount_code: couponCode.value.trim() || null,
        }),
    })
        .then((response) => (response.ok ? response.json() : null))
        .then((data) => {
            quote.value = data;
        })
        .catch(() => {
            quote.value = null;
        })
        .finally(() => {
            quoting.value = false;
        });
}

watch(scope, (value) => {
    cart.value = loadCart(value);
});

watch(
    cart,
    (value) => saveCart(value, scope.value),
    { deep: true },
);

const cartCount = computed(() => cart.value.reduce((sum, line) => sum + line.quantity, 0));

function add(product) {
    cart.value = addToCart(cart.value, product);
}

function changeQty(productId, quantity) {
    cart.value = setQuantity(cart.value, productId, quantity);
}

function placeOrder() {
    if (cart.value.length === 0 || submitting.value) {
        return;
    }

    submitting.value = true;

    router.post(route('orders.store'), {
        qr_token: props.table?.qr_token,
        guest_name: guestName.value,
        notes: notes.value,
        discount_code: couponCode.value.trim() || null,
        items: cart.value.map((line) => ({
            product_id: line.productId,
            quantity: line.quantity,
        })),
        preserveScroll: true,
        onSuccess: () => {
            clearCart(scope.value);
            cart.value = [];
            quote.value = null;
            couponCode.value = '';
        },
        onFinish: () => {
            submitting.value = false;
        },
    });
}
</script>

<template>
    <PublicLayout>
        <div class="mx-auto max-w-3xl px-4 pb-32">
            <!-- Table banner -->
            <div v-if="table" class="glass glass-sheen mt-4 rounded-2xl p-4 text-center">
                <p class="text-sm opacity-70">منوی</p>
                <p class="text-xl font-bold">{{ table.label }}</p>
            </div>

            <!-- Discount banner: active automatic discounts -->
            <div
                v-if="discounts.banner.length > 0"
                class="glass mt-4 flex flex-wrap items-center justify-center gap-2 rounded-2xl p-4"
            >
                <p class="text-xs font-bold text-saffron-600 dark:text-saffron-400">🎉 تخفیف‌های فعال:</p>
                <span
                    v-for="discount in discounts.banner"
                    :key="discount.id"
                    class="rounded-full bg-lajvard-600/15 px-3 py-1 text-xs font-bold text-saffron-600 dark:text-saffron-400"
                >
                    {{ discount.label }}
                    <template v-if="discount.scope === 'entire_order'">روی کل سفارش</template>
                    <template v-else-if="discount.target_name">روی {{ discount.target_name }}</template>
                </span>
            </div>

            <!-- Categories -->
            <nav class="no-scrollbar mt-4 flex gap-2 overflow-x-auto pb-1">
                <button
                    v-for="category in categories"
                    :key="category.id"
                    type="button"
                    class="glass-flat shrink-0 rounded-full px-4 py-2 text-sm transition"
                    :class="activeCategory === category.id ? 'bg-lajvard-600/80 text-white' : ''"
                    @click="activeCategory = category.id"
                >
                    {{ category.name }}
                </button>
            </nav>

            <!-- Products -->
            <section
                v-for="category in categories"
                :key="category.id"
                class="mt-6"
            >
                <h2 class="mb-3 text-lg font-bold">{{ category.name }}</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <article
                        v-for="product in category.products"
                        :key="product.id"
                        class="glass rounded-2xl p-4"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h3 class="font-semibold">{{ product.name }}</h3>
                                <p class="mt-1 text-xs opacity-70">{{ product.description }}</p>
                            </div>
                            <div class="shrink-0 text-left">
                                <span
                                    v-if="discounts.product_badges[product.id]"
                                    class="mb-1 block rounded-full bg-lajvard-600 px-2 py-0.5 text-center text-[10px] font-bold text-white"
                                >
                                    {{ discounts.product_badges[product.id] }}
                                </span>
                                <p class="text-sm font-bold text-saffron-600 dark:text-saffron-400">
                                    {{ formatTomanWithUnit(product.price) }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-3 flex items-center justify-between">
                            <div v-if="cart.find((l) => l.productId === product.id)" class="flex items-center gap-3">
                                <button
                                    type="button"
                                    class="glass-flat h-8 w-8 rounded-full text-lg leading-none"
                                    @click="changeQty(product.id, cart.find((l) => l.productId === product.id).quantity - 1)"
                                >−</button>
                                <span class="w-6 text-center font-bold">
                                    {{ cart.find((l) => l.productId === product.id).quantity }}
                                </span>
                                <button
                                    type="button"
                                    class="h-8 w-8 rounded-full bg-lajvard-600 text-lg leading-none text-white"
                                    @click="changeQty(product.id, cart.find((l) => l.productId === product.id).quantity + 1)"
                                >+</button>
                            </div>
                            <button
                                type="button"
                                class="rounded-full bg-lajvard-600 px-5 py-2 text-sm font-medium text-white transition hover:bg-lajvard-700"
                                @click="add(product)"
                            >
                                افزودن
                            </button>
                        </div>
                    </article>
                </div>
            </section>
        </div>

        <!-- Sticky cart bar -->
        <div class="safe-bottom fixed inset-x-0 bottom-0 z-10 px-4">
            <div class="glass glass-strong mx-auto max-w-3xl rounded-2xl p-4">
                <template v-if="placedOrder">
                    <p class="text-center text-sm font-medium text-pistachio-600 dark:text-pistachio-400">
                        سفارش ثبت شد! لطفاً برای پرداخت به صندوق مراجعه کنید.
                    </p>
                </template>
                <template v-else>
                    <!-- Live quote: subtotal / discount / total -->
                    <div
                        v-if="quote"
                        class="mb-2 rounded-xl bg-white/5 px-3 py-2 text-sm"
                    >
                        <div class="flex items-center justify-between">
                            <span class="opacity-60">جمع سبد</span>
                            <span :class="quote.discount_total > 0 ? 'line-through opacity-50' : ''">
                                {{ formatTomanWithUnit(quote.subtotal) }}
                            </span>
                        </div>
                        <div
                            v-if="quote.discount_total > 0"
                            class="flex items-center justify-between text-saffron-600 dark:text-saffron-400"
                        >
                            <span>تخفیف</span>
                            <span>−{{ formatTomanWithUnit(quote.discount_total) }}</span>
                        </div>
                        <div class="mt-1 flex items-center justify-between border-t border-white/10 pt-1 font-bold">
                            <span>قابل پرداخت</span>
                            <span>{{ formatTomanWithUnit(quote.total) }}</span>
                        </div>
                        <p
                            v-if="quote.message"
                            class="mt-1 text-xs"
                            :class="quote.valid ? 'text-pistachio-600 dark:text-pistachio-400' : 'text-red-500'"
                        >
                            {{ quote.message }}
                        </p>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm opacity-70">
                                {{ cartCount }} آیتم
                                <template v-if="!quote">
                                    · {{ formatTomanWithUnit(cartTotal(cart)) }}
                                </template>
                            </p>
                            <input
                                v-model="guestName"
                                type="text"
                                placeholder="نام شما"
                                class="glass-flat mt-2 w-full rounded-xl px-3 py-2 text-sm outline-none"
                            >
                            <input
                                v-model="couponCode"
                                type="text"
                                placeholder="کد تخفیف (اختیاری)"
                                class="glass-flat mt-2 w-full rounded-xl px-3 py-2 text-sm outline-none"
                                dir="ltr"
                            >
                        </div>
                        <button
                            type="button"
                            class="shrink-0 rounded-2xl bg-pistachio-600 px-6 py-3 font-bold text-white transition disabled:opacity-40"
                            :disabled="cartCount === 0 || !guestName.trim() || submitting"
                            @click="placeOrder"
                        >
                            ثبت سفارش
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </PublicLayout>
</template>
