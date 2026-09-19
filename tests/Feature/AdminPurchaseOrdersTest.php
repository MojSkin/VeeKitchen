<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\User;

function procurementLab(): array
{
    $branch = Branch::factory()->create();
    $admin = User::factory()->admin()->forBranch($branch)->create();

    return [$branch, $admin];
}

function orderWithItem(Branch $branch, string $status, float $quantity = 10.0): PurchaseOrder
{
    $item = InventoryItem::factory()->withoutQr()->create(['branch_id' => $branch->id]);
    $factory = PurchaseOrder::factory()->for($branch);

    $order = match ($status) {
        'draft' => $factory->create(),
        'ordered' => $factory->ordered()->create(),
        'received' => $factory->received()->create(),
        default => $factory->cancelled()->create(),
    };

    PurchaseOrderItem::factory()->forOrder($order)->forItem($item, $quantity, 40_000)->create();

    return $order;
}

test('the purchase orders board is admin-only and lists orders with items', function () {
    [$branch, $admin] = procurementLab();

    $this->get(route('admin.purchase-orders'))->assertRedirect(route('login'));

    $order = orderWithItem($branch, 'draft');

    $this->actingAs($admin)->get(route('admin.purchase-orders'))->assertOk();

    $cashier = User::factory()->cashier()->forBranch($branch)->create();
    $this->actingAs($cashier)->get(route('admin.purchase-orders'))->assertForbidden();

    $order->delete();
    $this->actingAs($admin)->get(route('admin.purchase-orders'))->assertOk();
});

test('the board payload carries supplier, status, totals and item lines', function () {
    [$branch, $admin] = procurementLab();

    $order = orderWithItem($branch, 'ordered', 25.0);
    $order->recalculateTotal();

    $response = $this->actingAs($admin)->get(route('admin.purchase-orders'));
    $props = $response->viewData('page')['props'];
    $row = collect($props['orders'])->firstWhere('id', $order->id);

    expect($row['supplier_name'])->toBe($order->supplier->name)
        ->and($row['status'])->toBe('ordered')
        ->and($row['items'][0]['quantity'])->toBe(25.0)
        ->and($row['items'][0]['unit_label'])->toBeString()
        ->and($row['total'])->toBe(1_000_000);
});

test('submit sends a draft to the supplier', function () {
    [$branch, $admin] = procurementLab();

    $order = orderWithItem($branch, 'draft');

    $this->actingAs($admin)
        ->post(route('admin.purchase-orders.submit', $order))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($order->refresh()->status)->toBe(PurchaseOrderStatus::Ordered)
        ->and($order->ordered_at)->not->toBeNull();
});

test('receive lifts stock and writes purchase ledger rows', function () {
    [$branch, $admin] = procurementLab();

    $order = orderWithItem($branch, 'ordered', 12.0);
    $material = $order->items->first()->inventoryItem;
    $stockBefore = (float) $material->current_stock;

    $this->actingAs($admin)
        ->post(route('admin.purchase-orders.receive', $order))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect((float) $material->refresh()->current_stock)->toEqualWithDelta($stockBefore + 12.0, 0.000001)
        ->and($order->refresh()->status)->toBe(PurchaseOrderStatus::Received)
        ->and(StockMovement::query()->where('inventory_item_id', $material->id)->where('type', 'purchase')->count())->toBe(1);
});

test('a stale board cannot receive an already-received order', function () {
    [$branch, $admin] = procurementLab();

    $order = orderWithItem($branch, 'received');

    $this->actingAs($admin)
        ->post(route('admin.purchase-orders.receive', $order))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($order->refresh()->status)->toBe(PurchaseOrderStatus::Received);
});

test('cancelling a received order is refused with a friendly message', function () {
    [$branch, $admin] = procurementLab();

    $order = orderWithItem($branch, 'received');

    $this->actingAs($admin)
        ->post(route('admin.purchase-orders.cancel', $order))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($order->refresh()->status)->toBe(PurchaseOrderStatus::Received);
});

test('cancelling a draft works', function () {
    [$branch, $admin] = procurementLab();

    $order = orderWithItem($branch, 'draft');

    $this->actingAs($admin)
        ->post(route('admin.purchase-orders.cancel', $order))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($order->refresh()->status)->toBe(PurchaseOrderStatus::Cancelled);
});
