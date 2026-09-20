<?php

use App\Enums\PaymentMethod;
use App\Events\StockChanged;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PurchaseOrderService;
use App\Services\UnitCostSnapshot;
use App\Services\WarehouseReportService;
use Illuminate\Support\Facades\Event;

/**
 * Cost snapshots freeze the ledger's rial values at write time so historic
 * reports never drift when today's purchase prices change.
 */
function snapshotLab(): array
{
    $branch = Branch::factory()->create();
    $admin = User::factory()->admin()->forBranch($branch)->create();

    return [$branch, $admin];
}

function snapshotMaterial(Branch $branch, string $name, int $cost): InventoryItem
{
    return InventoryItem::factory()->withoutQr()->create([
        'branch_id' => $branch->id,
        'name' => $name,
        'unit' => 'kg',
        'unit_cost' => $cost,
    ]);
}

test('purchase movements snapshot the price actually paid', function () {
    [$branch, $admin] = snapshotLab();

    $material = snapshotMaterial($branch, 'آرد گندم', 55_000);
    $material->update(['unit_cost' => 70_000]);

    StockMovement::factory()->unitCost(60_000, UnitCostSnapshot::SOURCE_PURCHASE)->create([
        'inventory_item_id' => $material->id,
        'type' => 'purchase',
        'quantity' => 20.0,
    ]);

    $movement = StockMovement::query()->latest('id')->first();

    expect($movement->unitCostAt())->toBe(60_000)
        ->and($movement->unit_cost_source)->toBe('purchase_price');
});

test('consumption movements snapshot the current cost at write time', function () {
    [$branch] = snapshotLab();

    $material = snapshotMaterial($branch, 'پنیر موزارلا', 320_000);

    $movement = StockMovement::factory()->unitCost(320_000, UnitCostSnapshot::SOURCE_CURRENT)->create([
        'inventory_item_id' => $material->id,
        'type' => 'consumption',
        'quantity' => -0.3,
    ]);

    expect($movement->unitCostAt())->toBe(320_000)
        ->and($movement->unit_cost_source)->toBe('current_cost');
});

test('rows without a snapshot fall back to the material current cost', function () {
    [$branch] = snapshotLab();

    $material = snapshotMaterial($branch, 'قارچ', 90_000);

    $legacy = StockMovement::factory()->withoutCostSnapshot()->create([
        'inventory_item_id' => $material->id,
        'type' => 'consumption',
        'quantity' => -1.0,
    ]);

    expect(UnitCostSnapshot::valuationCost($legacy))->toBe(90_000);
});

test('historic rows keep their value when today prices change', function () {
    [$branch] = snapshotLab();
    $service = app(WarehouseReportService::class);

    $material = snapshotMaterial($branch, 'روغن سرخ‌کردنی', 180_000);

    // Yesterday: oil cost 150_000/T.
    $old = StockMovement::factory()->unitCost(150_000, UnitCostSnapshot::SOURCE_CURRENT)->create([
        'inventory_item_id' => $material->id,
        'type' => 'consumption',
        'quantity' => -2.0,
        'created_at' => now()->subDay(),
    ]);

    // Today the price rose…
    $material->update(['unit_cost' => 210_000]);

    // …and a new consumption row snapshots the new price.
    StockMovement::factory()->unitCost(210_000, UnitCostSnapshot::SOURCE_CURRENT)->create([
        'inventory_item_id' => $material->id,
        'type' => 'consumption',
        'quantity' => -1.0,
    ]);

    $report = $service->rangeByType($branch, now()->subDays(7), now());
    $byType = collect($report['types'])->keyBy('type');

    // Historic row valued at 150_000 (300_000), today's at 210_000 — never
    // both at the same price.
    expect($byType['consumption']['value'])->toBe(510_000)
        ->and($report['outflow_value'])->toBe(510_000)
        ->and($old->unitCostAt())->toBe(150_000);
});

test('auto deduction stamps consumption rows with the current cost', function () {
    [$branch, $admin] = snapshotLab();

    $material = snapshotMaterial($branch, 'گوشت چرخ‌کرده', 500_000);
    $material->update(['current_stock' => 10.0]);

    $product = Product::factory()->create(['branch_id' => $branch->id]);
    ProductRecipe::factory()->forProduct($product)->consuming($material, 0.2)->create();

    $order = app(OrderService::class)->place(
        branchId: $branch->id,
        table: null,
        customer: null,
        cart: [['product_id' => $product->id, 'quantity' => 1]],
    );

    Event::fake([StockChanged::class]);

    app(OrderService::class)->markPaid($order, PaymentMethod::Cash);

    $movement = StockMovement::query()->where('type', 'consumption')->latest('id')->first();

    expect($movement)->not->toBeNull()
        ->and($movement->unitCostAt())->toBe(500_000)
        ->and($movement->unit_cost_source)->toBe('current_cost');
});

test('receiving a purchase order stamps the paid price and keeps old values frozen', function () {
    [$branch, $admin] = snapshotLab();

    $material = snapshotMaterial($branch, 'سس گوجه', 45_000);
    $material->update(['current_stock' => 5.0]);

    $order = PurchaseOrder::factory()
        ->for($branch)
        ->ordered()
        ->create();

    PurchaseOrderItem::factory()->forOrder($order)->forItem($material, 12.0, 52_000)->create();

    app(PurchaseOrderService::class)->receive($order->refresh(), $admin);

    $movement = StockMovement::query()->where('type', 'purchase')->latest('id')->first();

    expect($movement->unitCostAt())->toBe(52_000)
        ->and($movement->unit_cost_source)->toBe('purchase_price')
        // The item's rolling cost was refreshed to the paid price too.
        ->and($material->refresh()->unit_cost)->toBe(52_000);
});
