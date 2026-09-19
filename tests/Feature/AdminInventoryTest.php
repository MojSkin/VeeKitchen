<?php

use App\Enums\MeasurementUnit;
use App\Enums\PaymentMethod;
use App\Events\StockChanged;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Support\Facades\Event;

function inventoryBoardData(): array
{
    $branch = Branch::factory()->create();
    $admin = User::factory()->admin()->forBranch($branch)->create();

    return [$branch, $admin];
}

test('the inventory board is admin-only', function () {
    [$branch, $admin] = inventoryBoardData();

    // Guest first: actingAs sticks for the rest of the test.
    $this->get(route('admin.inventory'))->assertRedirect(route('login'));

    $this->actingAs($admin)->get(route('admin.inventory'))->assertOk();

    $cashier = User::factory()->cashier()->forBranch($branch)->create();
    $this->actingAs($cashier)->get(route('admin.inventory'))->assertForbidden();
});

test('the board lists materials with low-stock flags and products with recipes', function () {
    [$branch, $admin] = inventoryBoardData();

    InventoryItem::factory()->lowStock()->withoutQr()->create(['branch_id' => $branch->id, 'name' => 'آرد گندم']);
    $product = Product::factory()->create(['branch_id' => $branch->id]);
    ProductRecipe::factory()->forProduct($product)->consuming(
        InventoryItem::query()->where('name', 'آرد گندم')->firstOrFail(),
        0.4,
    )->create();

    $response = $this->actingAs($admin)->get(route('admin.inventory'));

    $response->assertOk();
    $props = $response->viewData('page')['props'];

    expect($props['items'][0]['name'])->toBe('آرد گندم')
        ->and($props['items'][0]['is_low'])->toBeTrue()
        ->and($props['products'][0]['recipes'])->toHaveCount(1);
});

test('an admin adjusts stock and it lands in the ledger with an event', function () {
    Event::fake([StockChanged::class]);
    [$branch, $admin] = inventoryBoardData();

    $item = InventoryItem::factory()->withoutQr()->create([
        'branch_id' => $branch->id,
        'current_stock' => 10.0,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.inventory.items.adjust', $item), [
            'delta' => 5.5,
            'reason' => 'شمردن تحویل صبح',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($item->refresh()->current_stock)->toBe('15.500')
        ->and(StockMovement::query()->where('inventory_item_id', $item->id)->latest('id')->first()->type->value)
        ->toBe('adjustment');

    Event::assertDispatched(StockChanged::class);
});

test('an adjustment cannot push stock negative', function () {
    [$branch, $admin] = inventoryBoardData();

    $item = InventoryItem::factory()->withoutQr()->create([
        'branch_id' => $branch->id,
        'current_stock' => 2.0,
    ]);

    $this->actingAs($admin)
        ->from(route('admin.inventory'))
        ->post(route('admin.inventory.items.adjust', $item), [
            'delta' => -5,
            'reason' => 'خطای شمارش',
        ])
        ->assertSessionHasErrors('delta');

    expect($item->refresh()->current_stock)->toBe('2.000');
});

test('an admin creates a new material', function () {
    [$branch, $admin] = inventoryBoardData();

    $this->actingAs($admin)
        ->post(route('admin.inventory.items.store'), [
            'name' => 'روغن زیتون',
            'unit' => MeasurementUnit::Liter->value,
            'current_stock' => 6.5,
            'low_stock_threshold' => 1,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $item = InventoryItem::query()->where('name', 'روغن زیتون')->firstOrFail();

    expect($item->unit)->toBe(MeasurementUnit::Liter)
        ->and((float) $item->current_stock)->toBe(6.5)
        ->and(StockMovement::query()->where('inventory_item_id', $item->id)->count())->toBe(1);
});

test('an admin replaces a product recipe', function () {
    [$branch, $admin] = inventoryBoardData();

    $product = Product::factory()->create(['branch_id' => $branch->id]);
    $flour = InventoryItem::factory()->withoutQr()->create(['branch_id' => $branch->id, 'name' => 'آرد گندم']);
    $cheese = InventoryItem::factory()->withoutQr()->create(['branch_id' => $branch->id, 'name' => 'پنیر موزارلا']);

    $this->actingAs($admin)
        ->post(route('admin.products.recipe.save', $product), [
            'lines' => [
                ['inventory_item_id' => $flour->id, 'quantity_per_unit' => 0.4],
                ['inventory_item_id' => $cheese->id, 'quantity_per_unit' => 0.15],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($product->refresh()->recipes)->toHaveCount(2);

    // Save again with one line — the old pair is replaced, not appended.
    $this->actingAs($admin)
        ->post(route('admin.products.recipe.save', $product), [
            'lines' => [
                ['inventory_item_id' => $cheese->id, 'quantity_per_unit' => 0.2],
            ],
        ])
        ->assertRedirect();

    expect($product->refresh()->recipes)->toHaveCount(1)
        ->and($product->recipes->first()->inventory_item_id)->toBe($cheese->id)
        ->and((float) $product->recipes->first()->quantity_per_unit)->toBe(0.2);
});

test('a recipe cannot reference the same material twice', function () {
    [$branch, $admin] = inventoryBoardData();

    $product = Product::factory()->create(['branch_id' => $branch->id]);
    $flour = InventoryItem::factory()->withoutQr()->create(['branch_id' => $branch->id]);

    $this->actingAs($admin)
        ->post(route('admin.products.recipe.save', $product), [
            'lines' => [
                ['inventory_item_id' => $flour->id, 'quantity_per_unit' => 0.4],
                ['inventory_item_id' => $flour->id, 'quantity_per_unit' => 0.1],
            ],
        ])
        ->assertSessionHasErrors('lines');

    expect(ProductRecipe::query()->where('product_id', $product->id)->count())->toBe(0);
});

test('paying an order broadcasts a stock change to the inventory channel', function () {
    Event::fake([StockChanged::class]);
    [$branch] = inventoryBoardData();

    $flour = InventoryItem::factory()->withoutQr()->create([
        'branch_id' => $branch->id,
        'current_stock' => 100.0,
    ]);
    $product = Product::factory()->create(['branch_id' => $branch->id]);
    ProductRecipe::factory()->forProduct($product)->consuming($flour, 0.5)->create();

    $order = app(OrderService::class)->place(
        branchId: $branch->id,
        table: null,
        customer: null,
        cart: [['product_id' => $product->id, 'quantity' => 2]],
    );

    app(OrderService::class)->markPaid($order, PaymentMethod::Cash);

    Event::assertDispatched(StockChanged::class);
});

test('unused payment rows are not required for the board to work', function () {
    [$branch, $admin] = inventoryBoardData();

    $item = InventoryItem::factory()->withoutQr()->create(['branch_id' => $branch->id]);
    Payment::factory()->cash()->create(['amount' => 1000]);

    $this->actingAs($admin)->get(route('admin.inventory'))->assertOk();
    expect($item->exists)->toBeTrue();
});
