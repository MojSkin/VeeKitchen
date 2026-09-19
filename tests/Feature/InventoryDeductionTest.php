<?php

use App\Enums\MeasurementUnit;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\StockMovement;
use App\Services\InventoryService;
use App\Services\OrderService;

test('paying an order deducts recipe materials from stock', function () {
    $branch = Branch::factory()->create();
    $flour = InventoryItem::factory()->create([
        'branch_id' => $branch->id,
        'unit' => MeasurementUnit::Gram,
        'current_stock' => 10_000.0,
    ]);
    $product = Product::factory()->create(['branch_id' => $branch->id]);
    ProductRecipe::factory()->forProduct($product)->consuming($flour, 500.0)->create();

    $order = app(OrderService::class)->place(
        branchId: $branch->id,
        table: null,
        customer: null,
        cart: [['product_id' => $product->id, 'quantity' => 2]],
    );

    app(OrderService::class)->markPaid($order, PaymentMethod::Cash);

    expect($flour->refresh()->current_stock)->toBe('9000.000')
        ->and(StockMovement::query()->where('order_id', $order->id)->count())->toBe(1)
        ->and(StockMovement::query()->where('order_id', $order->id)->first()->type)
        ->toBe(StockMovementType::Consumption);
});

test('several order lines of one product deduct once, summed', function () {
    $branch = Branch::factory()->create();
    $cheese = InventoryItem::factory()->create(['branch_id' => $branch->id, 'current_stock' => 5_000.0]);
    $product = Product::factory()->create(['branch_id' => $branch->id]);
    ProductRecipe::factory()->forProduct($product)->consuming($cheese, 100.0)->create();

    $service = app(OrderService::class);
    $order = $service->place($branch->id, null, null, [['product_id' => $product->id, 'quantity' => 3]]);

    $service->markPaid($order, PaymentMethod::Cash);

    expect($cheese->refresh()->current_stock)->toBe('4700.000')
        ->and(StockMovement::query()->where('inventory_item_id', $cheese->id)->count())->toBe(1);
});

test('the deduction ledger is idempotent when a payment is replayed', function () {
    $branch = Branch::factory()->create();
    $beef = InventoryItem::factory()->create(['branch_id' => $branch->id, 'current_stock' => 9_000.0]);
    $product = Product::factory()->create(['branch_id' => $branch->id]);
    ProductRecipe::factory()->forProduct($product)->consuming($beef, 1_000.0)->create();

    $service = app(OrderService::class);
    $order = $service->place($branch->id, null, null, [['product_id' => $product->id, 'quantity' => 1]]);
    $service->markPaid($order, PaymentMethod::Cash);

    $payment = $order->payments()->firstOrFail();
    app(InventoryService::class)->deductForOrder($order->refresh(), $payment);

    expect($beef->refresh()->current_stock)->toBe('8000.000')
        ->and(StockMovement::query()->where('inventory_item_id', $beef->id)->count())->toBe(1);
});

test('products without a recipe deduct nothing', function () {
    $branch = Branch::factory()->create();
    $product = Product::factory()->create(['branch_id' => $branch->id]);

    $service = app(OrderService::class);
    $order = $service->place($branch->id, null, null, [['product_id' => $product->id, 'quantity' => 2]]);
    $service->markPaid($order, PaymentMethod::Cash);

    expect(StockMovement::query()->count())->toBe(0)
        ->and($order->refresh()->status->value)->toBe('queued');
});

test('an insufficient stock rolls the whole payment back', function () {
    $branch = Branch::factory()->create();
    $flour = InventoryItem::factory()->create(['branch_id' => $branch->id, 'current_stock' => 100.0]);
    $product = Product::factory()->create(['branch_id' => $branch->id]);
    ProductRecipe::factory()->forProduct($product)->consuming($flour, 500.0)->create();

    $service = app(OrderService::class);
    $order = $service->place($branch->id, null, null, [['product_id' => $product->id, 'quantity' => 1]]);

    $service->markPaid($order, PaymentMethod::Cash);
})->throws(RuntimeException::class);

test('an insufficient stock leaves the order unpaid and stock untouched', function () {
    $branch = Branch::factory()->create();
    $flour = InventoryItem::factory()->create(['branch_id' => $branch->id, 'current_stock' => 100.0]);
    $product = Product::factory()->create(['branch_id' => $branch->id]);
    ProductRecipe::factory()->forProduct($product)->consuming($flour, 500.0)->create();

    $service = app(OrderService::class);
    $order = $service->place($branch->id, null, null, [['product_id' => $product->id, 'quantity' => 1]]);

    try {
        $service->markPaid($order, PaymentMethod::Cash);
    } catch (RuntimeException) {
    }

    expect($order->refresh()->status->value)->toBe('awaiting_payment')
        ->and($order->payments()->count())->toBe(0)
        ->and($flour->refresh()->current_stock)->toBe('100.000')
        ->and(StockMovement::query()->count())->toBe(0);
});

test('low-stock items are detectable after a payment', function () {
    $branch = Branch::factory()->create();
    $item = InventoryItem::factory()->create([
        'branch_id' => $branch->id,
        'current_stock' => 600.0,
        'low_stock_threshold' => 500.0,
    ]);
    $product = Product::factory()->create(['branch_id' => $branch->id]);
    ProductRecipe::factory()->forProduct($product)->consuming($item, 500.0)->create();

    $service = app(OrderService::class);
    $order = $service->place($branch->id, null, null, [['product_id' => $product->id, 'quantity' => 1]]);
    $service->markPaid($order, PaymentMethod::Cash);

    expect($item->refresh()->isLowStock())->toBeTrue()
        ->and($item->current_stock)->toBe('100.000');
});

test('cancelling a paid order returns its materials to stock', function () {
    $branch = Branch::factory()->create();
    $flour = InventoryItem::factory()->create(['branch_id' => $branch->id, 'current_stock' => 10_000.0]);
    $product = Product::factory()->create(['branch_id' => $branch->id]);
    ProductRecipe::factory()->forProduct($product)->consuming($flour, 500.0)->create();

    $service = app(OrderService::class);
    $order = $service->place($branch->id, null, null, [['product_id' => $product->id, 'quantity' => 2]]);
    $service->markPaid($order, PaymentMethod::Cash);
    expect($flour->refresh()->current_stock)->toBe('9000.000');

    $service->transition($order, OrderStatus::Cancelled, null, 'مشتری منصرف شد');

    $return = StockMovement::query()->where('inventory_item_id', $flour->id)
        ->where('type', StockMovementType::Return->value)->first();

    expect($flour->refresh()->current_stock)->toBe('10000.000')
        ->and($return)->not->toBeNull()
        ->and((float) $return->quantity)->toBe(1000.0);
});

test('a recipe measurement unit mismatch between products is handled per item', function () {
    $branch = Branch::factory()->create();
    $water = InventoryItem::factory()->create([
        'branch_id' => $branch->id,
        'unit' => MeasurementUnit::Liter,
        'current_stock' => 5.0,
    ]);
    $product = Product::factory()->create(['branch_id' => $branch->id]);
    ProductRecipe::factory()->forProduct($product)->consuming($water, 0.25)->create();

    $service = app(OrderService::class);
    $order = $service->place($branch->id, null, null, [['product_id' => $product->id, 'quantity' => 4]]);
    $service->markPaid($order, PaymentMethod::Cash);

    expect($water->refresh()->current_stock)->toBe('4.000');
});
