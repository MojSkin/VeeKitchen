<?php

use App\Enums\CostComponentType;
use App\Enums\PurchaseOrderStatus;
use App\Models\CostComponent;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\WasteLog;

test('suppliers track purchase orders and inactive ones still own their history', function () {
    $supplier = Supplier::factory()->create();
    PurchaseOrder::factory()->for($supplier)->count(2)->create();
    PurchaseOrder::factory()->ordered()->for($supplier)->create();

    expect($supplier->refresh()->purchaseOrders)->toHaveCount(3);
});

test('a purchase order recalculates its total from the line items', function () {
    $order = PurchaseOrder::factory()->create();
    $item = InventoryItem::factory()->create(['branch_id' => $order->branch_id]);

    PurchaseOrderItem::factory()->forOrder($order)->forItem($item, 10.0, 50_000)->create();
    PurchaseOrderItem::factory()->forOrder($order)->forItem($item, 2.5, 40_000)->create();

    expect($order->recalculateTotal())->toBe(600_000)
        ->and($order->refresh()->total)->toBe(600_000);
});

test('the purchase order status machine only allows legal transitions', function () {
    $order = PurchaseOrder::factory()->create();

    expect($order->status)->toBe(PurchaseOrderStatus::Draft)
        ->and($order->status->canTransitionTo(PurchaseOrderStatus::Ordered))->toBeTrue()
        ->and($order->status->canTransitionTo(PurchaseOrderStatus::Received))->toBeFalse();

    $order->status = PurchaseOrderStatus::Ordered;
    expect($order->status->canTransitionTo(PurchaseOrderStatus::Received))->toBeTrue()
        ->and($order->status->canTransitionTo(PurchaseOrderStatus::Ordered))->toBeFalse();

    $order->status = PurchaseOrderStatus::Received;
    expect($order->status->canTransitionTo(PurchaseOrderStatus::Cancelled))->toBeFalse();
});

test('purchase order items keep their identity with the supplier chain', function () {
    $order = PurchaseOrder::factory()->create();
    $item = InventoryItem::factory()->create(['branch_id' => $order->branch_id]);
    PurchaseOrderItem::factory()->forOrder($order)->forItem($item, 1.0, 10_000)->create();

    expect(fn () => $order->supplier()->delete())->toThrow(RuntimeException::class)
        ->and(fn () => $item->delete())->toThrow(RuntimeException::class)
        ->and(PurchaseOrderItem::count())->toBe(1);
});

test('waste logs keep the ledger complete even if the material is gone', function () {
    $item = InventoryItem::factory()->create();
    WasteLog::factory()->expired()->for($item)->create(['quantity' => 3.5]);

    expect(fn () => $item->delete())->toThrow(RuntimeException::class)
        ->and(WasteLog::count())->toBe(1);
});

test('percent cost components round-trip through basis points', function () {
    $component = CostComponent::factory()->percent(9)->create();

    expect($component->type)->toBe(CostComponentType::Percent)
        ->and($component->value)->toBe(900)
        ->and($component->percent())->toBe(9.0);
});

test('a products cost components are grouped per product and ordered', function () {
    $productA = Product::factory()->create();
    $productB = Product::factory()->create();

    $taxA = CostComponent::factory()->forProduct($productA)->percent(9)->create(['position' => 1]);
    $packagingA = CostComponent::factory()->forProduct($productA)->fixed(5_000)->create(['position' => 2]);
    CostComponent::factory()->forProduct($productB)->fixed(1_000)->create();

    expect($productA->costComponents()->pluck('id')->all())->toBe([$taxA->id, $packagingA->id])
        ->and($productA->costComponents()->count())->toBe(2)
        ->and($productB->costComponents()->count())->toBe(1);
});

test('deleting a product is blocked while its cost components exist', function () {
    $product = Product::factory()->create();
    CostComponent::factory()->forProduct($product)->fixed(1_000)->create();

    expect(fn () => $product->delete())->toThrow(RuntimeException::class)
        ->and(Product::count())->toBe(1);
});

test('recipes still restrict product deletion while deduction history exists', function () {
    $product = Product::factory()->create();
    $item = InventoryItem::factory()->create(['branch_id' => $product->branch_id]);
    ProductRecipe::factory()->forProduct($product)->consuming($item, 1.0)->create();

    expect(fn () => $product->delete())->toThrow(RuntimeException::class);
});
