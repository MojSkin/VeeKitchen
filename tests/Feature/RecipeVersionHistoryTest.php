<?php

use App\Enums\MeasurementUnit;
use App\Models\Branch;
use App\Models\CostComponent;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\ProductRecipeVersion;
use App\Models\User;
use App\Services\RecipeVersionService;

function versionLab(): array
{
    $branch = Branch::factory()->create();
    $admin = User::factory()->admin()->forBranch($branch)->create();
    $product = Product::factory()->create(['branch_id' => $branch->id, 'price' => 150_000]);

    return [$branch, $admin, $product];
}

function recipeIngredient(Branch $branch, string $name, MeasurementUnit $unit, int $unitCost): InventoryItem
{
    return InventoryItem::factory()->withoutQr()->create([
        'branch_id' => $branch->id,
        'name' => $name,
        'unit' => $unit,
        'unit_cost' => $unitCost,
        'current_stock' => 500,
    ]);
}

test('recording a version freezes lines, unit costs, and price figures', function () {
    [$branch, $admin, $product] = versionLab();

    $flour = recipeIngredient($branch, 'آرد گندم', MeasurementUnit::Kilogram, 60_000);
    ProductRecipe::factory()->forProduct($product)->consuming($flour, 0.4)->create();

    CostComponent::factory()->forProduct($product)->costBearing()->fixed(4_000)->create(['label' => 'بسته‌بندی', 'position' => 1]);

    $version = app(RecipeVersionService::class)->record($product, $admin);

    expect($version->version_number)->toBe(1)
        ->and($version->material_cost)->toBe(24_000)
        ->and($version->cost_price)->toBe(28_000)
        ->and($version->suggested_sale_price)->toBe(28_000)
        ->and($version->lines[0]['name'])->toBe('آرد گندم')
        ->and($version->lines[0]['unit_cost'])->toBe(60_000)
        ->and($version->components)->toHaveCount(1)
        ->and($version->components[0]['label'])->toBe('بسته‌بندی')
        ->and($version->created_by)->toBe($admin->id);
});

test('each save bumps the version number per product', function () {
    [$branch, $admin, $product] = versionLab();

    $flour = recipeIngredient($branch, 'آرد گندم', MeasurementUnit::Kilogram, 60_000);
    $cheese = recipeIngredient($branch, 'پنیر موزارلا', MeasurementUnit::Kilogram, 320_000);

    ProductRecipe::factory()->forProduct($product)->consuming($flour, 0.4)->create();
    $service = app(RecipeVersionService::class);

    $first = $service->record($product, $admin);

    // Rewrite the recipe, then snapshot again.
    $product->recipes()->delete();
    ProductRecipe::factory()->forProduct($product)->consuming($cheese, 0.15)->create();
    $second = $service->record($product, $admin);

    // A different product numbers independently.
    $other = Product::factory()->create(['branch_id' => $branch->id]);
    ProductRecipe::factory()->forProduct($other)->consuming($flour, 1.0)->create();
    $otherVersion = $service->record($other, $admin);

    expect($first->version_number)->toBe(1)
        ->and($second->version_number)->toBe(2)
        ->and($otherVersion->version_number)->toBe(1)
        ->and($second->material_cost)->toBe(48_000);
});

test('an empty recipe still snapshots as a valid version', function () {
    [$branch, $admin, $product] = versionLab();

    $version = app(RecipeVersionService::class)->record($product, $admin);

    expect($version->lines)->toBe([])
        ->and($version->material_cost)->toBe(0)
        ->and($version->suggested_sale_price)->toBe(0);
});

test('saving a recipe through the endpoint creates a version automatically', function () {
    [$branch, $admin, $product] = versionLab();

    $flour = recipeIngredient($branch, 'آرد گندم', MeasurementUnit::Kilogram, 60_000);
    $cheese = recipeIngredient($branch, 'پنیر موزارلا', MeasurementUnit::Kilogram, 320_000);

    $this->actingAs($admin)
        ->post(route('admin.products.recipe.save', $product), [
            'lines' => [
                ['inventory_item_id' => $flour->id, 'quantity_per_unit' => 0.4],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->actingAs($admin)
        ->post(route('admin.products.recipe.save', $product), [
            'lines' => [
                ['inventory_item_id' => $flour->id, 'quantity_per_unit' => 0.4],
                ['inventory_item_id' => $cheese->id, 'quantity_per_unit' => 0.15],
            ],
        ])
        ->assertRedirect();

    expect(ProductRecipeVersion::query()->where('product_id', $product->id)->count())->toBe(2)
        ->and(ProductRecipeVersion::query()->where('product_id', $product->id)->orderBy('version_number')->get())
        ->sequence(
            fn ($version) => $version->lines->toHaveCount(1),
            fn ($version) => $version->lines->toHaveCount(2),
        );
});

test('the versions endpoint lists history newest first', function () {
    [$branch, $admin, $product] = versionLab();

    $flour = recipeIngredient($branch, 'آرد گندم', MeasurementUnit::Kilogram, 60_000);
    ProductRecipe::factory()->forProduct($product)->consuming($flour, 0.4)->create();

    $service = app(RecipeVersionService::class);
    $service->record($product, $admin);
    $service->record($product, $admin);

    $response = $this->actingAs($admin)->get(route('admin.products.recipe-versions', $product));
    $props = $response->viewData('page')['props'];

    expect($props['product']['name'])->toBe($product->name)
        ->and($props['versions'][0]['version_number'])->toBe(2)
        ->and($props['versions'][1]['version_number'])->toBe(1);
});

test('the versions page is admin-only', function () {
    [$branch, $admin, $product] = versionLab();

    $this->get(route('admin.products.recipe-versions', $product))->assertRedirect(route('login'));

    $this->actingAs($admin)->get(route('admin.products.recipe-versions', $product))->assertOk();

    $cashier = User::factory()->cashier()->forBranch($branch)->create();
    $this->actingAs($cashier)->get(route('admin.products.recipe-versions', $product))->assertForbidden();
});
