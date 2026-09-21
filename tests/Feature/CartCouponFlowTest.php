<?php

use App\Models\Branch;
use App\Models\Discount;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

function couponLab(): array
{
    $branch = Branch::factory()->create();
    $category = MenuCategory::factory()->create(['branch_id' => $branch->id]);
    $pizza = Product::factory()->create([
        'branch_id' => $branch->id,
        'menu_category_id' => $category->id,
        'price' => 300_000,
    ]);

    return [$branch, $pizza];
}

function quotePayload(int $productId, int $quantity = 1, ?string $code = null): array
{
    return [
        'items' => [['product_id' => $productId, 'quantity' => $quantity]],
        'discount_code' => $code,
    ];
}

/**
 * The decoded JSON body of a quote response.
 */
function quoteJson($response): array
{
    return $response->json();
}

test('the quote endpoint prices the cart with the best automatic discount', function () {
    [$branch, $pizza] = couponLab();

    Discount::factory()->percent(15)->create(['branch_id' => $branch->id, 'name' => 'جشنواره']);

    $quote = quoteJson($this->postJson(route('cart.quote'), quotePayload($pizza->id)));

    expect($quote['valid'])->toBeTrue()
        ->and($quote['subtotal'])->toBe(300_000)
        ->and($quote['discount_total'])->toBe(45_000)
        ->and($quote['total'])->toBe(255_000)
        ->and($quote['message'])->toContain('جشنواره');
});

test('the quote endpoint accepts a coupon better than the automatic offer', function () {
    [$branch, $pizza] = couponLab();

    Discount::factory()->percent(10)->create(['branch_id' => $branch->id]);
    Discount::factory()->percent(25)->withCode('BIG25')->create(['branch_id' => $branch->id]);

    $quote = quoteJson($this->postJson(route('cart.quote'), quotePayload($pizza->id, 1, 'big25')));

    expect($quote['valid'])->toBeTrue()
        ->and($quote['subtotal'])->toBe(300_000)
        ->and($quote['discount_total'])->toBe(75_000)
        ->and($quote['total'])->toBe(225_000)
        ->and($quote['message'])->toContain('BIG25');
});

test('the quote endpoint rejects a losing code with a Persian message', function () {
    [$branch, $pizza] = couponLab();

    Discount::factory()->percent(30)->create(['branch_id' => $branch->id]);
    Discount::factory()->percent(5)->withCode('WEAK5')->create(['branch_id' => $branch->id]);

    $quote = quoteJson($this->postJson(route('cart.quote'), quotePayload($pizza->id, 1, 'WEAK5')));

    expect($quote['valid'])->toBeFalse()
        ->and($quote['subtotal'])->toBe(300_000)
        ->and($quote['discount_total'])->toBe(0)
        ->and($quote['total'])->toBe(300_000)
        ->and($quote['message'])->toContain('کمتر از تخفیف خودکار');
});

test('the quote endpoint rejects unknown and expired codes cleanly', function () {
    [$branch, $pizza] = couponLab();

    $ghost = quoteJson($this->postJson(route('cart.quote'), quotePayload($pizza->id, 1, 'GHOST')));

    expect($ghost['valid'])->toBeFalse()
        ->and($ghost['message'])->toContain('یافت نشد');

    Discount::factory()->percent(50)->withCode('OLD')->expired()->create(['branch_id' => $branch->id]);

    $expired = quoteJson($this->postJson(route('cart.quote'), quotePayload($pizza->id, 1, 'OLD')));

    expect($expired['valid'])->toBeFalse();
});

test('the quote ignores unknown products instead of failing', function () {
    [$branch, $pizza] = couponLab();

    $quote = quoteJson($this->postJson(route('cart.quote'), [
        'items' => [
            ['product_id' => $pizza->id, 'quantity' => 1],
            ['product_id' => 999_999, 'quantity' => 3],
        ],
    ]));

    expect($quote['valid'])->toBeTrue()
        ->and($quote['subtotal'])->toBe(300_000);
});

test('a placed order carries the coupon discount and usage is recorded', function () {
    [$branch, $pizza] = couponLab();

    $coupon = Discount::factory()->percent(20)->withCode('SAVE20')->create(['branch_id' => $branch->id]);

    $this->from('/menu')
        ->post(route('orders.store'), [
            'guest_name' => 'مهمان کوپن‌دار',
            'discount_code' => 'save20',
            'items' => [['product_id' => $pizza->id, 'quantity' => 2]],
        ])
        ->assertRedirect();

    $order = Order::query()->latest('id')->first();

    expect($order->subtotal)->toBe(600_000)
        ->and($order->discount_total)->toBe(120_000)
        ->and($order->total)->toBe(480_000)
        ->and($order->discount_id)->toBe($coupon->id)
        ->and($coupon->refresh()->used_count)->toBe(1);
});

test('placing an order with a losing code fails instead of silently downgrading', function () {
    [$branch, $pizza] = couponLab();

    Discount::factory()->percent(30)->create(['branch_id' => $branch->id]);
    Discount::factory()->percent(5)->withCode('WEAK')->create(['branch_id' => $branch->id]);

    $this->from('/menu')
        ->post(route('orders.store'), [
            'guest_name' => 'مهمان',
            'discount_code' => 'WEAK',
            'items' => [['product_id' => $pizza->id, 'quantity' => 1]],
        ])
        ->assertSessionHasErrors();

    expect(Order::query()->count())->toBe(0);
});

test('the per-user ceiling blocks the second order of the same customer', function () {
    [$branch, $pizza] = couponLab();

    $customer = User::factory()->customer()->create();

    Discount::factory()->percent(50)->create([
        'branch_id' => $branch->id,
        'usage_limit_per_user' => 1,
    ]);

    $this->actingAs($customer)->post(route('orders.store'), [
        'guest_name' => 'نفر اول',
        'items' => [['product_id' => $pizza->id, 'quantity' => 1]],
    ])->assertRedirect();

    // A guest (no user) cannot be tracked against a per-user ceiling, so
    // the discount deliberately does not apply to them.
    $this->post(route('orders.store'), [
        'guest_name' => 'مهمان بی‌حساب',
        'items' => [['product_id' => $pizza->id, 'quantity' => 1]],
    ])->assertRedirect();

    $orders = Order::query()->orderBy('id')->get();

    expect($orders)->toHaveCount(2)
        ->and($orders[0]->discount_total)->toBe(150_000)
        ->and($orders[1]->discount_total)->toBe(0);

    // The same signed-in customer placing again gets no discount.
    $this->actingAs($customer)->post(route('orders.store'), [
        'guest_name' => 'نفر اول دوباره',
        'items' => [['product_id' => $pizza->id, 'quantity' => 1]],
    ])->assertRedirect();

    $third = Order::query()->latest('id')->first();

    expect($third->discount_total)->toBe(0)
        ->and($third->discount_id)->toBeNull();
});
