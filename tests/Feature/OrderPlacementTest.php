<?php

use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Models\Branch;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Services\OrderService;

test('a guest order is placed with db-priced totals and ceiling rounding', function () {
    $branch = Branch::factory()->create();
    $product = Product::factory()->create(['branch_id' => $branch->id, 'price' => 123_456]);

    $order = app(OrderService::class)->place(
        branchId: $branch->id,
        table: null,
        customer: null,
        cart: [
            ['product_id' => $product->id, 'quantity' => 2],
        ],
        guestName: 'مهمان تست',
    );

    expect($order->status)->toBe(OrderStatus::AwaitingPayment)
        ->and($order->subtotal)->toBe(246_912)
        // 246,912 ceilings to 247,000 per the business rule
        ->and($order->total)->toBe(247_000)
        ->and($order->items)->toHaveCount(1)
        ->and($order->guest_token)->not->toBeNull();
});

test('placing an order on a free table marks the table as ordering', function () {
    $branch = Branch::factory()->create();
    $product = Product::factory()->create(['branch_id' => $branch->id]);
    $table = RestaurantTable::factory()->create(['branch_id' => $branch->id]);

    app(OrderService::class)->place(
        branchId: $branch->id,
        table: $table,
        customer: null,
        cart: [['product_id' => $product->id, 'quantity' => 1]],
    );

    expect($table->refresh()->status)->toBe(TableStatus::Ordering)
        ->and($table->occupied_at)->not->toBeNull();
});

test('unavailable products are rejected', function () {
    $branch = Branch::factory()->create();
    $product = Product::factory()->unavailable()->create(['branch_id' => $branch->id]);

    app(OrderService::class)->place(
        branchId: $branch->id,
        table: null,
        customer: null,
        cart: [['product_id' => $product->id, 'quantity' => 1]],
    );
})->throws(InvalidArgumentException::class);

test('an empty cart is rejected', function () {
    $branch = Branch::factory()->create();

    app(OrderService::class)->place(
        branchId: $branch->id,
        table: null,
        customer: null,
        cart: [],
    );
})->throws(InvalidArgumentException::class);

test('the store endpoint validates the payload shape', function () {
    $response = $this->post(route('orders.store'), [
        'items' => [],
    ]);

    $response->assertSessionHasErrors(['guest_name', 'items']);
});

test('the store endpoint throttles rapid submissions', function () {
    $product = Product::factory()->create();
    $payload = [
        'guest_name' => 'مهمان',
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ];

    foreach (range(1, 10) as $attempt) {
        $this->post(route('orders.store'), $payload);
    }

    // The 11th request inside one minute is throttled (429).
    $this->post(route('orders.store'), $payload)->assertStatus(429);
});
