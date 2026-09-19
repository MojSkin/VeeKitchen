<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseOrderService;

function orderFormLab(): array
{
    $branch = Branch::factory()->create();
    $admin = User::factory()->admin()->forBranch($branch)->create();

    return [$branch, $admin];
}

function materialFor(Branch $branch, string $name, int $unitCost = 60_000): InventoryItem
{
    return InventoryItem::factory()->withoutQr()->create([
        'branch_id' => $branch->id,
        'name' => $name,
        'unit_cost' => $unitCost,
    ]);
}

function draftPayload(int $supplierId, array $lines): array
{
    return [
        'supplier_id' => $supplierId,
        'notes' => 'برای پوشش هفتهٔ آینده',
        'lines' => $lines,
    ];
}

test('the service creates a draft order with lines, totals and defaults', function () {
    [$branch, $admin] = orderFormLab();
    $service = app(PurchaseOrderService::class);

    $flour = materialFor($branch, 'آرد گندم', 60_000);
    $cheese = materialFor($branch, 'پنیر موزارلا', 320_000);
    $supplier = Supplier::factory()->create(['is_active' => true]);

    $order = $service->create($branch, $supplier, [
        ['inventory_item_id' => $flour->id, 'quantity' => 30.0, 'unit_cost' => 62_000],
        ['inventory_item_id' => $cheese->id, 'quantity' => 8.0],
    ], $admin, 'یادداشت آزمایشی');

    expect($order->status)->toBe(PurchaseOrderStatus::Draft)
        ->and($order->branch_id)->toBe($branch->id)
        ->and($order->created_by)->toBe($admin->id)
        ->and($order->notes)->toBe('یادداشت آزمایشی')
        ->and($order->total)->toBe(30 * 62_000 + 8 * 320_000)
        // line without an explicit cost falls back to the material's last purchase cost
        ->and($order->items()->where('inventory_item_id', $cheese->id)->first()->unit_cost)->toBe(320_000)
        ->and($order->items()->where('inventory_item_id', $flour->id)->first()->line_total)->toBe(1_860_000);
});

test('the service refuses inactive suppliers', function () {
    [$branch] = orderFormLab();
    $service = app(PurchaseOrderService::class);

    $flour = materialFor($branch, 'آرد گندم');
    $supplier = Supplier::factory()->create(['is_active' => false]);

    $service->create($branch, $supplier, [
        ['inventory_item_id' => $flour->id, 'quantity' => 5.0],
    ]);

    throw new RuntimeException('should not reach here');
})->throws(InvalidArgumentException::class);

test('the new-order page renders the form payload for admins only', function () {
    [$branch, $admin] = orderFormLab();

    Supplier::factory()->create(['name' => 'پخش نور']);
    Supplier::factory()->create(['name' => 'پخش بسته', 'is_active' => false]);
    materialFor($branch, 'آرد گندم', 60_000);

    $this->get(route('admin.purchase-orders.create'))->assertRedirect(route('login'));
    $this->actingAs($admin)->get(route('admin.purchase-orders.create'))->assertOk();

    $cashier = User::factory()->cashier()->forBranch($branch)->create();
    $this->actingAs($cashier)->get(route('admin.purchase-orders.create'))->assertForbidden();

    $props = $this->actingAs($admin)->get(route('admin.purchase-orders.create'))->viewData('page')['props'];

    expect($props['suppliers'])->toHaveCount(1)
        ->and($props['suppliers'][0]['name'])->toBe('پخش نور')
        ->and($props['items'][0]['unit_cost'])->toBe(60_000)
        ->and($props['items'][0]['unit_label'])->toBeString();
});

test('storing through the endpoint creates a draft and redirects to the board', function () {
    [$branch, $admin] = orderFormLab();

    $flour = materialFor($branch, 'آرد گندم', 60_000);
    $supplier = Supplier::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.purchase-orders.store'), draftPayload($supplier->id, [
            ['inventory_item_id' => $flour->id, 'quantity' => 30, 'unit_cost' => 62_000],
        ]))
        ->assertRedirect(route('admin.purchase-orders'))
        ->assertSessionHas('success');

    $order = PurchaseOrder::query()->sole();

    expect($order->status)->toBe(PurchaseOrderStatus::Draft)
        ->and($order->supplier_id)->toBe($supplier->id)
        ->and($order->created_by)->toBe($admin->id)
        ->and($order->total)->toBe(1_860_000)
        ->and($order->notes)->toBe('برای پوشش هفتهٔ آینده');
});

test('the endpoint rejects empty orders, unknown materials and zero quantities', function () {
    [$branch, $admin] = orderFormLab();

    $flour = materialFor($branch, 'آرد گندم');
    $supplier = Supplier::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.purchase-orders.store'), draftPayload($supplier->id, []))
        ->assertSessionHasErrors('lines');

    $this->actingAs($admin)
        ->post(route('admin.purchase-orders.store'), draftPayload($supplier->id, [
            ['inventory_item_id' => 999_999, 'quantity' => 1],
        ]))
        ->assertSessionHasErrors();

    $this->actingAs($admin)
        ->post(route('admin.purchase-orders.store'), draftPayload($supplier->id, [
            ['inventory_item_id' => $flour->id, 'quantity' => 0],
        ]))
        ->assertSessionHasErrors();

    expect(PurchaseOrder::query()->count())->toBe(0);
});

test('the endpoint rejects duplicate materials on separate lines', function () {
    [$branch, $admin] = orderFormLab();

    $flour = materialFor($branch, 'آرد گندم');
    $supplier = Supplier::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.purchase-orders.store'), draftPayload($supplier->id, [
            ['inventory_item_id' => $flour->id, 'quantity' => 10],
            ['inventory_item_id' => $flour->id, 'quantity' => 20],
        ]))
        ->assertSessionHasErrors('lines');

    expect(PurchaseOrder::query()->count())->toBe(0);
});

test('a draft created through the form submits cleanly afterwards', function () {
    [$branch, $admin] = orderFormLab();

    $flour = materialFor($branch, 'آرد گندم', 60_000);
    $supplier = Supplier::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.purchase-orders.store'), draftPayload($supplier->id, [
            ['inventory_item_id' => $flour->id, 'quantity' => 30, 'unit_cost' => 62_000],
        ]))
        ->assertRedirect();

    $order = PurchaseOrder::query()->sole();

    $this->actingAs($admin)
        ->post(route('admin.purchase-orders.submit', $order))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($order->refresh()->status)->toBe(PurchaseOrderStatus::Ordered)
        ->and($order->total)->toBe(1_860_000);
});
