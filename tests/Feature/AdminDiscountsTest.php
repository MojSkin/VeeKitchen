<?php

use App\Models\Branch;
use App\Models\Discount;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Models\User;

function discountLab(): array
{
    $branch = Branch::factory()->create();
    $admin = User::factory()->admin()->forBranch($branch)->create();

    return [$branch, $admin];
}

function discountPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'جشنوارهٔ پیتزا',
        'code' => null,
        'type' => 'percentage',
        'value' => 20,
        'applies_to' => 'entire_order',
        'menu_category_id' => null,
        'product_id' => null,
        'min_order_total' => null,
        'starts_at' => null,
        'expires_at' => null,
        'usage_limit_total' => null,
        'usage_limit_per_user' => null,
    ], $overrides);
}

test('the discounts board is admin-only and lists discounts with live state', function () {
    [$branch, $admin] = discountLab();

    $this->get(route('admin.discounts'))->assertRedirect(route('login'));

    $live = Discount::factory()->percent(15)->create(['branch_id' => $branch->id]);
    $paused = Discount::factory()->percent(30)->inactive()->create(['branch_id' => $branch->id]);
    $scheduled = Discount::factory()->percent(10)->create([
        'branch_id' => $branch->id,
        'starts_at' => now()->addDay(),
    ]);

    $response = $this->actingAs($admin)->get(route('admin.discounts'));

    $response->assertOk();
    $rows = collect($response->viewData('page')['props']['discounts']);

    expect($rows)->toHaveCount(3)
        ->and($rows->firstWhere('id', $live->id)['currently_active'])->toBeTrue()
        ->and($rows->firstWhere('id', $paused->id)['currently_active'])->toBeFalse()
        ->and($rows->firstWhere('id', $scheduled->id)['currently_active'])->toBeFalse();

    $cashier = User::factory()->cashier()->forBranch($branch)->create();
    $this->actingAs($cashier)->get(route('admin.discounts'))->assertForbidden();
});

test('an admin creates an automatic and a coupon discount from the board', function () {
    [$branch, $admin] = discountLab();

    $this->actingAs($admin)
        ->post(route('admin.discounts.store'), discountPayload())
        ->assertRedirect();

    $this->assertDatabaseHas('discounts', [
        'branch_id' => $branch->id,
        'name' => 'جشنوارهٔ پیتزا',
        'code' => null,
        'type' => 'percentage',
        'value' => 20,
        'applies_to' => 'entire_order',
        'is_active' => true,
    ]);

    // Coupon codes are stored upper-cased.
    $this->actingAs($admin)
        ->post(route('admin.discounts.store'), discountPayload([
            'name' => 'خوش‌آمدگویی',
            'code' => 'welcome10',
            'type' => 'fixed',
            'value' => 50_000,
        ]))
        ->assertRedirect();

    $this->assertDatabaseHas('discounts', [
        'branch_id' => $branch->id,
        'name' => 'خوش‌آمدگویی',
        'code' => 'WELCOME10',
        'type' => 'fixed',
        'value' => 50_000,
    ]);
});

test('scoped discounts must carry their target', function () {
    [$branch, $admin] = discountLab();

    $category = MenuCategory::factory()->create(['branch_id' => $branch->id]);
    $product = Product::factory()->create(['branch_id' => $branch->id, 'menu_category_id' => $category->id]);

    // Missing target is rejected with a field error.
    $this->actingAs($admin)
        ->post(route('admin.discounts.store'), discountPayload(['applies_to' => 'product']))
        ->assertSessionHasErrors('product_id');

    // With the target the same payload is accepted.
    $this->actingAs($admin)
        ->post(route('admin.discounts.store'), discountPayload([
            'applies_to' => 'product',
            'product_id' => $product->id,
        ]))
        ->assertRedirect();

    $this->assertDatabaseHas('discounts', [
        'product_id' => $product->id,
        'applies_to' => 'product',
    ]);
});

test('duplicate coupon codes are rejected', function () {
    [$branch, $admin] = discountLab();

    Discount::factory()->withCode('SUMMER')->create(['branch_id' => $branch->id]);

    $this->actingAs($admin)
        ->post(route('admin.discounts.store'), discountPayload(['code' => 'summer']))
        ->assertSessionHasErrors('code');
});

test('an unused discount can be edited, toggled and deleted', function () {
    [$branch, $admin] = discountLab();

    $discount = Discount::factory()->percent(10)->create(['branch_id' => $branch->id]);

    $this->actingAs($admin)
        ->put(route('admin.discounts.update', $discount), discountPayload([
            'name' => 'جشنوارهٔ بهاری',
            'value' => 25,
        ]))
        ->assertRedirect();

    $discount->refresh();

    expect($discount->name)->toBe('جشنوارهٔ بهاری')
        ->and($discount->value)->toBe(25);

    $this->actingAs($admin)->post(route('admin.discounts.toggle', $discount))->assertRedirect();
    $this->assertFalse($discount->refresh()->is_active);

    $this->actingAs($admin)->post(route('admin.discounts.toggle', $discount))->assertRedirect();
    $this->assertTrue($discount->refresh()->is_active);

    $this->actingAs($admin)->delete(route('admin.discounts.destroy', $discount))->assertRedirect();
    $this->assertModelMissing($discount);
});

test('a used discount is locked: edit and delete are refused, toggle still works', function () {
    [$branch, $admin] = discountLab();

    $discount = Discount::factory()->percent(10)->create(['branch_id' => $branch->id, 'used_count' => 4]);

    $this->actingAs($admin)
        ->put(route('admin.discounts.update', $discount), discountPayload(['value' => 99]))
        ->assertSessionHasErrors();

    $this->actingAs($admin)
        ->delete(route('admin.discounts.destroy', $discount))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertModelExists($discount);

    $this->actingAs($admin)->post(route('admin.discounts.toggle', $discount))->assertRedirect();
    $this->assertFalse($discount->refresh()->is_active);
});

test('the guest menu carries the discount banner and per-product badges', function () {
    [$branch, $admin] = discountLab();

    $category = MenuCategory::factory()->create(['branch_id' => $branch->id]);
    $margherita = Product::factory()->create([
        'branch_id' => $branch->id,
        'menu_category_id' => $category->id,
        'price' => 300_000,
    ]);
    $coke = Product::factory()->create([
        'branch_id' => $branch->id,
        'menu_category_id' => $category->id,
        'price' => 90_000,
    ]);

    $festival = Discount::factory()->percent(15)->create([
        'branch_id' => $branch->id,
        'name' => 'جشنوارهٔ هفته',
    ]);

    $margheritaDeal = Discount::factory()->percent(20)->create([
        'branch_id' => $branch->id,
        'name' => 'ویژهٔ مارگاریتا',
        'applies_to' => 'product',
        'product_id' => $margherita->id,
    ]);

    // The strongest eligible badge wins per product.
    $categoryDeal = Discount::factory()->fixed(100_000)->create([
        'branch_id' => $branch->id,
        'name' => 'دستهٔ پرطرفدار',
        'applies_to' => 'category',
        'menu_category_id' => $category->id,
    ]);

    // Not on the menu: paused, expired, coupon-only.
    Discount::factory()->percent(50)->inactive()->create(['branch_id' => $branch->id]);
    Discount::factory()->percent(60)->expired()->create(['branch_id' => $branch->id]);
    Discount::factory()->percent(70)->withCode('SECRET')->create(['branch_id' => $branch->id]);

    $props = $this->get(route('menu.public'))->assertOk()->viewData('page')['props'];
    $discounts = $props['discounts'];

    $bannerNames = collect($discounts['banner'])->pluck('name');

    // The banner carries entire-order discounts only — scoped discounts
    // speak through their cards' badges.
    expect($bannerNames)->toContain('جشنوارهٔ هفته')
        ->and($bannerNames)->not->toContain('دستهٔ پرطرفدار')
        ->and($bannerNames)->not->toContain('ویژهٔ مارگاریتا')
        ->and($discounts['banner'])->toHaveCount(1);

    // Margherita: 100k fixed beats 20% (60k) and 15% (45k).
    expect($discounts['product_badges'][$margherita->id])->toBe('۱۰۰,۰۰۰ تومان تخفیف')
        // Coke: only the category 100k and entire-order 15% reach it.
        ->and($discounts['product_badges'][$coke->id])->toBe('۱۰۰,۰۰۰ تومان تخفیف');

    // The badge labels use Persian digits and the «٪» sign for percentages.
    $props = $this->get(route('menu.public'))->viewData('page')['props'];
    expect(collect($props['discounts']['banner'])->firstWhere('id', $festival->id)['label'])
        ->toBe('۱۵٪ تخفیف');
});

test('a drained discount disappears from the menu banner', function () {
    [$branch, $admin] = discountLab();

    Discount::factory()->percent(10)->exhausted()->create([
        'branch_id' => $branch->id,
        'name' => 'تمام‌شده',
    ]);

    $discounts = $this->get(route('menu.public'))->viewData('page')['props']['discounts'];

    expect($discounts['banner'])->toHaveCount(0)
        ->and($discounts['product_badges'])->toBeEmpty();
});
