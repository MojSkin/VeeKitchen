<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Events\OrderPaid;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Product;
use App\Models\StaffShift;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Support\Facades\Event;

test('markPaid assigns sequential daily numbers and records the payment', function () {
    $branch = Branch::factory()->create();
    $cashier = User::factory()->cashier()->forBranch($branch)->create();
    // The phase-3 golden rule: payments hang on an open shift.
    StaffShift::factory()->openingCash(0)->create([
        'branch_id' => $branch->id,
        'user_id' => $cashier->id,
    ]);
    $product = Product::factory()->create(['branch_id' => $branch->id]);
    $service = app(OrderService::class);

    $first = $service->place($branch->id, null, null, [['product_id' => $product->id, 'quantity' => 1]]);
    $second = $service->place($branch->id, null, null, [['product_id' => $product->id, 'quantity' => 1]]);

    $service->markPaid($first, PaymentMethod::Cash, $cashier);
    $service->markPaid($second, PaymentMethod::Card, $cashier);

    expect($first->refresh()->order_number)->toBe(1)
        ->and($second->refresh()->order_number)->toBe(2)
        ->and($first->status)->toBe(OrderStatus::Queued)
        ->and($first->payments)->toHaveCount(1)
        ->and($first->payments->first()->method)->toBe(PaymentMethod::Cash)
        ->and($first->payments->first()->amount)->toBe($first->total);
});

test('markPaid on an already-paid order fails', function () {
    $branch = Branch::factory()->create();
    $order = Order::factory()->paid()->create(['branch_id' => $branch->id]);

    app(OrderService::class)->markPaid($order, PaymentMethod::Cash);
})->throws(RuntimeException::class);

test('paying an order broadcasts OrderPaid on the branch kitchen channel', function () {
    Event::fake([OrderPaid::class]);

    $branch = Branch::factory()->create();
    $product = Product::factory()->create(['branch_id' => $branch->id]);

    $order = app(OrderService::class)->place(
        $branch->id, null, null,
        [['product_id' => $product->id, 'quantity' => 1]],
    );

    app(OrderService::class)->markPaid($order, PaymentMethod::Cash);

    Event::assertDispatched(OrderPaid::class);
});

test('daily numbers reset per branch but not per day within the same test day', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $service = app(OrderService::class);

    $productA = Product::factory()->create(['branch_id' => $branchA->id]);
    $productB = Product::factory()->create(['branch_id' => $branchB->id]);

    $orderA1 = $service->place($branchA->id, null, null, [['product_id' => $productA->id, 'quantity' => 1]]);
    $orderB1 = $service->place($branchB->id, null, null, [['product_id' => $productB->id, 'quantity' => 1]]);
    $orderA2 = $service->place($branchA->id, null, null, [['product_id' => $productA->id, 'quantity' => 1]]);

    foreach ([$orderA1, $orderB1, $orderA2] as $order) {
        $service->markPaid($order, PaymentMethod::Cash);
    }

    expect($orderA1->refresh()->order_number)->toBe(1)
        ->and($orderB1->refresh()->order_number)->toBe(1)
        ->and($orderA2->refresh()->order_number)->toBe(2);
});
