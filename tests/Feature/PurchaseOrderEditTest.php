<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseOrderService;

function editLab(): array
{
    $branch = Branch::factory()->create();
    $admin = User::factory()->admin()->forBranch($branch)->create();

    return [$branch, $admin];
}

function draftWithLine(Branch $branch, InventoryItem $item, float $quantity, int $unitCost): PurchaseOrder
{
    $order = PurchaseOrder::factory()->for($branch)->create();
    PurchaseOrderItem::factory()->forOrder($order)->forItem($item, $quantity, $unitCost)->create();
    $order->recalculateTotal();

    return $order;
}

function editPayload(int $supplierId, array $lines, string $notes = 'یادداشت ویرایش‌شده'): array
{
    return [
        'supplier_id' => $supplierId,
        'notes' => $notes,
        'lines' => $lines,
    ];
}

test('the service replaces a draft line set and recomputes totals', function () {
    [$branch, $admin] = editLab();
    $service = app(PurchaseOrderService::class);

    $flour = InventoryItem::factory()->withoutQr()->create(['branch_id' => $branch->id, 'unit_cost' => 60_000]);
    $cheese = InventoryItem::factory()->withoutQr()->create(['branch_id' => $branch->id, 'unit_cost' => 320_000]);
    $sauce = InventoryItem::factory()->withoutQr()->create(['branch_id' => $branch->id, 'unit_cost' => 180_000]);
    $supplier = Supplier::factory()->create();

    $order = draftWithLine($branch, $flour, 30.0, 62_000);

    $service->updateDraft($order, [
        ['inventory_item_id' => $cheese->id, 'quantity' => 8.0, 'unit_cost' => 330_000],
        ['inventory_item_id' => $sauce->id, 'quantity' => 5.0],
    ], $supplier, 'همه چیز عوض شد');

    expect($order->refresh()->items()->count())->toBe(2)
        ->and($order->items()->where('inventory_item_id', $flour->id)->exists())->toBeFalse()
        ->and($order->items()->where('inventory_item_id', $cheese->id)->first()->unit_cost)->toBe(330_000)
        // Line without explicit cost falls back to the material's last purchase price.
        ->and($order->items()->where('inventory_item_id', $sauce->id)->first()->unit_cost)->toBe(180_000)
        ->and($order->total)->toBe(8 * 330_000 + 5 * 180_000)
        ->and($order->supplier_id)->toBe($supplier->id)
        ->and($order->notes)->toBe('همه چیز عوض شد')
        ->and($order->status)->toBe(PurchaseOrderStatus::Draft);
});

test('updating only quantities keeps the supplier untouched when not passed', function () {
    [$branch] = editLab();
    $service = app(PurchaseOrderService::class);

    $flour = InventoryItem::factory()->withoutQr()->create(['branch_id' => $branch->id, 'unit_cost' => 60_000]);
    $originalSupplier = Supplier::factory()->create();

    $order = PurchaseOrder::factory()->for($branch)->for($originalSupplier)->create();
    PurchaseOrderItem::factory()->forOrder($order)->forItem($flour, 10.0, 60_000)->create();

    $service->updateDraft($order, [
        ['inventory_item_id' => $flour->id, 'quantity' => 25.0, 'unit_cost' => 60_000],
    ]);

    expect($order->refresh()->supplier_id)->toBe($originalSupplier->id)
        ->and($order->items()->first()->quantity)->toBe('25.000');
});

test('the service refuses to touch an ordered or received order even after a stale page load', function () {
    [$branch] = editLab();
    $service = app(PurchaseOrderService::class);

    $flour = InventoryItem::factory()->withoutQr()->create(['branch_id' => $branch->id, 'unit_cost' => 60_000]);

    foreach (['ordered', 'received', 'cancelled'] as $state) {
        $factory = PurchaseOrder::factory()->for($branch);
        $order = match ($state) {
            'ordered' => $factory->ordered()->create(),
            'received' => $factory->received()->create(),
            default => $factory->cancelled()->create(),
        };
        PurchaseOrderItem::factory()->forOrder($order)->forItem($flour, 10.0, 60_000)->create();

        $service->updateDraft($order, [
            ['inventory_item_id' => $flour->id, 'quantity' => 99.0, 'unit_cost' => 1],
        ]);

        throw new RuntimeException("should not reach here for {$state}");
    }
})->throws(RuntimeException::class);

test('an ordered order survives an edit attempt unchanged', function () {
    [$branch] = editLab();
    $service = app(PurchaseOrderService::class);

    $flour = InventoryItem::factory()->withoutQr()->create(['branch_id' => $branch->id, 'unit_cost' => 60_000]);

    $order = PurchaseOrder::factory()->ordered()->for($branch)->create();
    PurchaseOrderItem::factory()->forOrder($order)->forItem($flour, 10.0, 60_000)->create();
    $order->recalculateTotal();

    try {
        $service->updateDraft($order, [
            ['inventory_item_id' => $flour->id, 'quantity' => 99.0, 'unit_cost' => 1],
        ]);
    } catch (RuntimeException) {
        // expected
    }

    expect($order->refresh()->status)->toBe(PurchaseOrderStatus::Ordered)
        ->and($order->total)->toBe(600_000)
        ->and($order->items()->count())->toBe(1);
});

test('the edit page prefills supplier, notes and lines for admins only', function () {
    [$branch, $admin] = editLab();

    $flour = InventoryItem::factory()->withoutQr()->create(['branch_id' => $branch->id, 'unit_cost' => 60_000]);
    $supplier = Supplier::factory()->create();
    $order = PurchaseOrder::factory()->for($branch)->for($supplier)->create(['notes' => 'یادداشت اولیه']);
    PurchaseOrderItem::factory()->forOrder($order)->forItem($flour, 12.5, 61_000)->create();

    $this->get(route('admin.purchase-orders.edit', $order))->assertRedirect(route('login'));
    $this->actingAs($admin)->get(route('admin.purchase-orders.edit', $order))->assertOk();

    $cashier = User::factory()->cashier()->forBranch($branch)->create();
    $this->actingAs($cashier)->get(route('admin.purchase-orders.edit', $order))->assertForbidden();

    $props = $this->actingAs($admin)->get(route('admin.purchase-orders.edit', $order))->viewData('page')['props'];

    expect($props['order']['id'])->toBe($order->id)
        ->and($props['order']['supplier_id'])->toBe($supplier->id)
        ->and($props['order']['notes'])->toBe('یادداشت اولیه')
        ->and($props['order']['items'][0]['inventory_item_id'])->toBe($flour->id)
        ->and($props['order']['items'][0]['quantity'])->toBe(12.5)
        ->and($props['order']['items'][0]['unit_cost'])->toBe(61_000);
});

test('the edit page refuses non-draft orders with 403', function () {
    [$branch, $admin] = editLab();

    $flour = InventoryItem::factory()->withoutQr()->create(['branch_id' => $branch->id]);
    $order = PurchaseOrder::factory()->ordered()->for($branch)->create();
    PurchaseOrderItem::factory()->forOrder($order)->forItem($flour, 10.0, 60_000)->create();

    $this->actingAs($admin)->get(route('admin.purchase-orders.edit', $order))->assertForbidden();
});

test('updating through the endpoint replaces lines and redirects to the board', function () {
    [$branch, $admin] = editLab();

    $flour = InventoryItem::factory()->withoutQr()->create(['branch_id' => $branch->id, 'unit_cost' => 60_000]);
    $cheese = InventoryItem::factory()->withoutQr()->create(['branch_id' => $branch->id, 'unit_cost' => 320_000]);
    $supplier = Supplier::factory()->create();

    $order = draftWithLine($branch, $flour, 30.0, 62_000);

    $this->actingAs($admin)
        ->put(route('admin.purchase-orders.update', $order), editPayload($supplier->id, [
            ['inventory_item_id' => $cheese->id, 'quantity' => 4, 'unit_cost' => 315_000],
        ]))
        ->assertRedirect(route('admin.purchase-orders'))
        ->assertSessionHas('success');

    expect($order->refresh()->items()->count())->toBe(1)
        ->and($order->items()->where('inventory_item_id', $cheese->id)->exists())->toBeTrue()
        ->and($order->total)->toBe(1_260_000);
});

test('the endpoint rejects empty lines, unknown materials, duplicates and bad suppliers', function () {
    [$branch, $admin] = editLab();

    $flour = InventoryItem::factory()->withoutQr()->create(['branch_id' => $branch->id]);
    $supplier = Supplier::factory()->create();
    $order = draftWithLine($branch, $flour, 30.0, 62_000);

    $this->actingAs($admin)
        ->put(route('admin.purchase-orders.update', $order), editPayload($supplier->id, []))
        ->assertSessionHasErrors('lines');

    $this->actingAs($admin)
        ->put(route('admin.purchase-orders.update', $order), editPayload($supplier->id, [
            ['inventory_item_id' => 999_999, 'quantity' => 1],
        ]))
        ->assertSessionHasErrors();

    $this->actingAs($admin)
        ->put(route('admin.purchase-orders.update', $order), editPayload($supplier->id, [
            ['inventory_item_id' => $flour->id, 'quantity' => 10],
            ['inventory_item_id' => $flour->id, 'quantity' => 20],
        ]))
        ->assertSessionHasErrors('lines');

    $this->actingAs($admin)
        ->put(route('admin.purchase-orders.update', $order), editPayload(424_242, [
            ['inventory_item_id' => $flour->id, 'quantity' => 10],
        ]))
        ->assertSessionHasErrors();

    expect($order->refresh()->items()->count())->toBe(1);
});

test('an edited draft still submits cleanly with the new total', function () {
    [$branch, $admin] = editLab();

    $flour = InventoryItem::factory()->withoutQr()->create(['branch_id' => $branch->id, 'unit_cost' => 60_000]);
    $supplier = Supplier::factory()->create();
    $order = draftWithLine($branch, $flour, 30.0, 62_000);

    $this->actingAs($admin)
        ->put(route('admin.purchase-orders.update', $order), editPayload($supplier->id, [
            ['inventory_item_id' => $flour->id, 'quantity' => 40, 'unit_cost' => 63_000],
        ]))
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('admin.purchase-orders.submit', $order))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($order->refresh()->status)->toBe(PurchaseOrderStatus::Ordered)
        ->and($order->total)->toBe(2_520_000);
});

test('the board payload flags drafts as editable', function () {
    [$branch, $admin] = editLab();

    $flour = InventoryItem::factory()->withoutQr()->create(['branch_id' => $branch->id]);
    $draft = draftWithLine($branch, $flour, 10.0, 60_000);
    $ordered = PurchaseOrder::factory()->ordered()->for($branch)->create();
    PurchaseOrderItem::factory()->forOrder($ordered)->forItem($flour, 5.0, 60_000)->create();

    $props = $this->actingAs($admin)->get(route('admin.purchase-orders'))->viewData('page')['props'];
    $rows = collect($props['orders'])->keyBy('id');

    expect($rows[$draft->id]['is_editable'])->toBeTrue()
        ->and($rows[$ordered->id]['is_editable'])->toBeFalse();
});
