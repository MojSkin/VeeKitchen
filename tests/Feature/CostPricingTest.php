<?php

use App\Enums\MeasurementUnit;
use App\Models\Branch;
use App\Models\CostComponent;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Services\CostCalculator;
use App\Services\PurchaseOrderService;

function costLab(): array
{
    $branch = Branch::factory()->create();
    $product = Product::factory()->create(['branch_id' => $branch->id]);

    return [$branch, $product];
}

function material(Branch $branch, string $name, MeasurementUnit $unit, int $unitCost): InventoryItem
{
    return InventoryItem::factory()->withoutQr()->create([
        'branch_id' => $branch->id,
        'name' => $name,
        'unit' => $unit,
        'unit_cost' => $unitCost,
        'current_stock' => 999,
    ]);
}

test('material cost is the sum of recipe quantities times unit costs', function () {
    [$branch, $product] = costLab();

    $flour = material($branch, 'آرد گندم', MeasurementUnit::Kilogram, 60_000);
    $cheese = material($branch, 'پنیر موزارلا', MeasurementUnit::Kilogram, 320_000);

    // 0.4 kg flour = 24,000 + 0.15 kg cheese = 48,000 → 72,000 exactly.
    ProductRecipe::factory()->forProduct($product)->consuming($flour, 0.4)->create();
    ProductRecipe::factory()->forProduct($product)->consuming($cheese, 0.15)->create();

    $cost = app(CostCalculator::class)->forProduct($product);

    expect($cost['material_cost']->toman)->toBe(72_000)
        ->and($cost['cost_price']->toman)->toBe(72_000)
        ->and($cost['suggested_sale_price']->toman)->toBe(72_000);
});

test('fixed and percent components chain in position order and only round at the end', function () {
    [$branch, $product] = costLab();

    $chicken = material($branch, 'فیله مرغ', MeasurementUnit::Kilogram, 210_000);
    // 0.25 kg × 210,000 = 52,500 — already a 100 multiple, survives the ceiling untouched.
    ProductRecipe::factory()->forProduct($product)->consuming($chicken, 0.25)->create();

    CostComponent::factory()->forProduct($product)->costBearing()->fixed(5_000)->create(['label' => 'بسته‌بندی', 'position' => 1]);
    CostComponent::factory()->forProduct($product)->costBearing()->percent(9)->create(['label' => 'مالیات', 'position' => 2]);
    CostComponent::factory()->forProduct($product)->fixed(100_000)->create(['label' => 'سود مدیریت', 'position' => 3]);

    $cost = app(CostCalculator::class)->forProduct($product);

    // Chain: 52,500 → packaging 57,500 → tax 9% = 5,175 → 62,675 → profit 162,675 → 162,700.
    expect($cost['material_cost']->toman)->toBe(52_500)
        ->and($cost['cost_price']->toman)->toBe(62_700)
        ->and($cost['suggested_sale_price']->toman)->toBe(162_700)
        ->and(count($cost['components']))->toBe(3)
        ->and($cost['components'][2]['label'])->toBe('سود مدیریت')
        ->and($cost['components'][2]['running']->toman)->toBe(162_675);
});

test('percent components compound on the running amount, not the material base', function () {
    [$branch, $product] = costLab();

    $tea = material($branch, 'چای', MeasurementUnit::Gram, 500);
    // 20 g × 500 = 10,000.
    ProductRecipe::factory()->forProduct($product)->consuming($tea, 20)->create();

    CostComponent::factory()->forProduct($product)->percent(10)->create(['label' => 'سربار', 'position' => 1]);
    CostComponent::factory()->forProduct($product)->percent(10)->create(['label' => 'عوارض', 'position' => 2]);

    $cost = app(CostCalculator::class)->forProduct($product);

    // 10,000 → 11,000 → 12,100 (percent of running, not of the base).
    expect($cost['suggested_sale_price']->toman)->toBe(12_100);
});

test('a product without a recipe costs nothing instead of exploding', function () {
    [$branch, $product] = costLab();

    $cost = app(CostCalculator::class)->forProduct($product);

    expect($cost['material_cost']->toman)->toBe(0)
        ->and($cost['cost_price']->toman)->toBe(0)
        ->and($cost['suggested_sale_price']->toman)->toBe(0);
});

test('every figure obeys the 100-Toman ceiling from section 5', function () {
    [$branch, $product] = costLab();

    $onion = material($branch, 'پیاز', MeasurementUnit::Gram, 33);
    // 1,337 g × 33 = 44,121 → ceilings to 44,200.
    ProductRecipe::factory()->forProduct($product)->consuming($onion, 1_337)->create();

    $cost = app(CostCalculator::class)->forProduct($product);

    expect($cost['material_cost']->toman)->toBe(44_200)
        ->and($cost['cost_price']->toman)->toBe(44_200)
        ->and($cost['suggested_sale_price']->toman)->toBe(44_200)
        ->and($cost['cost_price']->toman % 100)->toBe(0);
});

test('receiving a purchase order refreshes the material unit cost for pricing', function () {
    [$branch, $product] = costLab();
    $admin = User::factory()->admin()->forBranch($branch)->create();

    $flour = material($branch, 'آرد گندم', MeasurementUnit::Kilogram, 60_000);
    ProductRecipe::factory()->forProduct($product)->consuming($flour, 0.4)->create();

    expect($product->recipes->first()->inventoryItem->unit_cost)->toBe(60_000);

    // Flour price rises: the PO buys 25 kg at 75,000 per kg.
    $order = PurchaseOrder::factory()->ordered()->for($branch)->create();
    PurchaseOrderItem::factory()->forOrder($order)->forItem($flour, 25.0, 75_000)->create();

    app(PurchaseOrderService::class)->receive($order, $admin);

    $cost = app(CostCalculator::class)->forProduct($product);

    expect($flour->refresh()->unit_cost)->toBe(75_000)
        // 0.4 kg × 75,000 = 30,000.
        ->and($cost['material_cost']->toman)->toBe(30_000);
});

test('the full margherita economics read sensibly end to end', function () {
    [$branch, $product] = costLab();

    $flour = material($branch, 'آرد', MeasurementUnit::Kilogram, 60_000);
    $cheese = material($branch, 'پنیر', MeasurementUnit::Kilogram, 320_000);
    $sauce = material($branch, 'سس', MeasurementUnit::Liter, 180_000);

    ProductRecipe::factory()->forProduct($product)->consuming($flour, 0.4)->create();
    ProductRecipe::factory()->forProduct($product)->consuming($cheese, 0.15)->create();
    ProductRecipe::factory()->forProduct($product)->consuming($sauce, 0.1)->create();

    // 24,000 + 48,000 + 18,000 = 90,000 material.
    CostComponent::factory()->forProduct($product)->costBearing()->fixed(4_000)->create(['label' => 'بسته‌بندی', 'position' => 1]);
    CostComponent::factory()->forProduct($product)->percent(30)->create(['label' => 'سود مدیریت', 'position' => 2]);

    $cost = app(CostCalculator::class)->forProduct($product);

    // 90,000 → packaging 94,000 → profit 30% = 28,200 → 122,200 (already a 100 multiple).
    expect($cost['cost_price']->toman)->toBe(94_000)
        ->and($cost['suggested_sale_price']->toman)->toBe(122_200)
        ->and($cost['suggested_sale_price']->toman % 100)->toBe(0);
});
