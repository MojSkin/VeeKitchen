<?php

use App\Enums\PaymentMethod;
use App\Enums\PurchaseOrderStatus;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WasteLog;
use App\Notifications\LowStockAlert;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Services\PurchaseOrderService;

function warehouseStaff(): array
{
    $branch = Branch::factory()->create();
    $admin = User::factory()->admin()->forBranch($branch)->create();

    return [$branch, $admin];
}

function calmItem(Branch $branch, float $stock, float $threshold): InventoryItem
{
    // High stock so no factory randomization strays under the alert line.
    return InventoryItem::factory()->withoutQr()->create([
        'branch_id' => $branch->id,
        'current_stock' => $stock,
        'low_stock_threshold' => $threshold,
    ]);
}

test('receiving a purchase order raises stock and writes purchase ledger rows', function () {
    [$branch, $admin] = warehouseStaff();

    $flour = InventoryItem::factory()->withoutQr()->create(['branch_id' => $branch->id, 'current_stock' => 10.0]);
    $order = PurchaseOrder::factory()->ordered()->for($branch)->create();
    PurchaseOrderItem::factory()->forOrder($order)->forItem($flour, 25.0, 40_000)->create();

    app(PurchaseOrderService::class)->receive($order, $admin);

    expect($flour->refresh()->current_stock)->toBe('35.000')
        ->and($order->refresh()->status)->toBe(PurchaseOrderStatus::Received)
        ->and($order->received_at)->not->toBeNull()
        ->and($order->received_by)->toBe($admin->id)
        ->and(StockMovement::query()->where('inventory_item_id', $flour->id)->where('type', 'purchase')->count())->toBe(1);
});

test('receiving is guarded: only ordered orders can be received, only once', function () {
    [$branch, $admin] = warehouseStaff();

    $item = InventoryItem::factory()->withoutQr()->create(['branch_id' => $branch->id]);
    $order = PurchaseOrder::factory()->ordered()->for($branch)->create();
    PurchaseOrderItem::factory()->forOrder($order)->forItem($item, 5.0, 10_000)->create();

    $service = app(PurchaseOrderService::class);
    $service->receive($order, $admin);

    // Second receive attempt is rejected.
    $service->receive($order->refresh(), $admin);
})->throws(RuntimeException::class);

test('a draft purchase order cannot be received directly', function () {
    [$branch, $admin] = warehouseStaff();

    $order = PurchaseOrder::factory()->for($branch)->create();

    app(PurchaseOrderService::class)->receive($order, $admin);
})->throws(RuntimeException::class);

test('receiving a low-stock material clears the alert condition without notifying upward', function () {
    [$branch, $admin] = warehouseStaff();

    Notification::fake();

    $cheese = InventoryItem::factory()->lowStock()->withoutQr()->create([
        'branch_id' => $branch->id,
        'current_stock' => 3.0,
        'low_stock_threshold' => 4.0,
    ]);

    $order = PurchaseOrder::factory()->ordered()->for($branch)->create();
    PurchaseOrderItem::factory()->forOrder($order)->forItem($cheese, 20.0, 90_000)->create();

    app(PurchaseOrderService::class)->receive($order, $admin);

    expect($cheese->refresh()->current_stock)->toBe('23.000')
        ->and($cheese->isLowStock())->toBeFalse()
        ->and(Notification::sent($admin, LowStockAlert::class))->toHaveCount(0);
});

test('waste cuts stock, files a log, and lands in the ledger', function () {
    [$branch, $admin] = warehouseStaff();

    $milk = calmItem($branch, 8.0, 1.0);

    $waste = app(InventoryService::class)->logWaste($milk, 2.5, 'تاریخ انقضا گذشته', $admin, '2026-09-10');

    expect($milk->refresh()->current_stock)->toBe('5.500')
        ->and($waste->quantity)->toBe('2.500')
        ->and($waste->expired_on->toDateString())->toBe('2026-09-10')
        ->and(WasteLog::count())->toBe(1)
        ->and(StockMovement::query()->where('inventory_item_id', $milk->id)->where('type', 'waste')->count())->toBe(1);
});

test('waste cannot exceed the current stock', function () {
    [$branch, $admin] = warehouseStaff();

    $milk = calmItem($branch, 1.0, 0.5);

    app(InventoryService::class)->logWaste($milk, 2.0, 'شکستن', $admin);
})->throws(InvalidArgumentException::class);

test('waste that crosses the threshold notifies the branch admins', function () {
    [$branch, $admin] = warehouseStaff();

    Notification::fake();

    $ketchup = InventoryItem::factory()->withoutQr()->create([
        'branch_id' => $branch->id,
        'current_stock' => 10.0,
        'low_stock_threshold' => 4.0,
    ]);

    app(InventoryService::class)->logWaste($ketchup, 8.0, 'خرابی یخچال', $admin);

    Notification::assertSentTo($admin, LowStockAlert::class, function (LowStockAlert $notification) use ($ketchup, $admin): bool {
        $data = $notification->toArray($admin);

        return $data['inventory_item_id'] === $ketchup->id
            && $data['current_stock'] === 2.0;
    });
});

test('paying an order that empties a material below threshold fires the alert too', function () {
    [$branch, $admin] = warehouseStaff();

    Notification::fake();

    $flour = InventoryItem::factory()->withoutQr()->create([
        'branch_id' => $branch->id,
        'current_stock' => 1.2,
        'low_stock_threshold' => 1.0,
    ]);
    $product = Product::factory()->create(['branch_id' => $branch->id]);
    ProductRecipe::factory()->forProduct($product)->consuming($flour, 0.5)->create();

    $service = app(OrderService::class);
    $order = $service->place($branch->id, null, null, [['product_id' => $product->id, 'quantity' => 1]]);
    $service->markPaid($order, PaymentMethod::Cash);

    // 1.2 − 0.5 = 0.7, below the 1.0 threshold.
    Notification::assertSentTo($admin, LowStockAlert::class);
    expect($flour->refresh()->current_stock)->toBe('0.700');
});

test('a healthy stock level never notifies', function () {
    [$branch, $admin] = warehouseStaff();

    Notification::fake();

    $item = InventoryItem::factory()->withoutQr()->create([
        'branch_id' => $branch->id,
        'current_stock' => 50.0,
        'low_stock_threshold' => 4.0,
    ]);

    app(InventoryService::class)->logWaste($item, 1.0, 'ریزش جزئی', $admin);

    Notification::assertNothingSent();
});

test('the waste endpoint is wired and admin-guarded', function () {
    [$branch, $admin] = warehouseStaff();

    $item = calmItem($branch, 5.0, 1.0);

    $this->actingAs($admin)
        ->post(route('admin.inventory.items.waste', $item), [
            'quantity' => 1.0,
            'reason' => 'آسیب حین جابه‌جایی',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($item->refresh()->current_stock)->toBe('4.000')
        ->and(WasteLog::count())->toBe(1);

    $cashier = User::factory()->cashier()->forBranch($branch)->create();
    $this->actingAs($cashier)
        ->post(route('admin.inventory.items.waste', $item), [
            'quantity' => 1.0,
            'reason' => 'نه',
        ])
        ->assertForbidden();
});
