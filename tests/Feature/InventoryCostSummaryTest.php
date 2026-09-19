<?php

use App\Enums\MeasurementUnit;
use App\Models\Branch;
use App\Models\CostComponent;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\User;

function costingLab(): array
{
    $branch = Branch::factory()->create();
    $admin = User::factory()->admin()->forBranch($branch)->create();
    $product = Product::factory()->create(['branch_id' => $branch->id, 'price' => 200_000]);

    return [$branch, $admin, $product];
}

function ingredient(Branch $branch, string $name, MeasurementUnit $unit, int $unitCost): InventoryItem
{
    return InventoryItem::factory()->withoutQr()->create([
        'branch_id' => $branch->id,
        'name' => $name,
        'unit' => $unit,
        'unit_cost' => $unitCost,
        'current_stock' => 500,
    ]);
}

test('the inventory board carries the cost summary for every product', function () {
    [$branch, $admin, $product] = costingLab();

    $flour = ingredient($branch, 'آرد گندم', MeasurementUnit::Kilogram, 60_000);
    $cheese = ingredient($branch, 'پنیر موزارلا', MeasurementUnit::Kilogram, 320_000);

    ProductRecipe::factory()->forProduct($product)->consuming($flour, 0.4)->create();
    ProductRecipe::factory()->forProduct($product)->consuming($cheese, 0.15)->create();

    CostComponent::factory()->forProduct($product)->costBearing()->fixed(4_000)->create(['label' => 'بسته‌بندی', 'position' => 1]);
    CostComponent::factory()->forProduct($product)->percent(30)->create(['label' => 'سود مدیریت', 'position' => 2]);

    $response = $this->actingAs($admin)->get(route('admin.inventory'));
    $props = $response->viewData('page')['props'];
    $row = collect($props['products'])->firstWhere('id', $product->id);

    // Material 24,000 + 48,000 = 72,000 → packaging 76,000 → profit 30% = 22,800 → 98,800.
    expect($row['material_cost'])->toBe(72_000)
        ->and($row['cost_price'])->toBe(76_000)
        ->and($row['suggested_sale_price'])->toBe(98_800)
        ->and($row['sale_price'])->toBe(200_000)
        ->and($row['components'])->toHaveCount(2)
        ->and($row['components'][0]['label'])->toBe('بسته‌بندی')
        ->and($row['components'][0]['amount'])->toBe(4_000)
        ->and($row['components'][1]['running'])->toBe(98_800);
});

test('board cost figures respect the 100-Toman ceiling', function () {
    [$branch, $admin, $product] = costingLab();

    $saffron = ingredient($branch, 'زعفران', MeasurementUnit::Gram, 33);
    ProductRecipe::factory()->forProduct($product)->consuming($saffron, 1_337)->create();

    $response = $this->actingAs($admin)->get(route('admin.inventory'));
    $row = collect($response->viewData('page')['props']['products'])->firstWhere('id', $product->id);

    // 1,337 g × 33 = 44,121 → ceilings to 44,200.
    expect($row['material_cost'])->toBe(44_200)
        ->and($row['suggested_sale_price'])->toBe(44_200)
        ->and($row['suggested_sale_price'] % 100)->toBe(0);
});

test('products carry their recipes unit costs for the live editor preview', function () {
    [$branch, $admin, $product] = costingLab();

    $flour = ingredient($branch, 'آرد گندم', MeasurementUnit::Kilogram, 60_000);
    ProductRecipe::factory()->forProduct($product)->consuming($flour, 0.4)->create();

    $response = $this->actingAs($admin)->get(route('admin.inventory'));
    $props = $response->viewData('page')['props'];

    $recipe = collect($props['products'])->firstWhere('id', $product->id)['recipes'][0];

    expect($recipe['unit_cost'])->toBe(60_000);

    $material = collect($props['items'])->firstWhere('id', $flour->id);

    expect($material['unit_cost'])->toBe(60_000);
});
