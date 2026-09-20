<?php

use App\Enums\MeasurementUnit;
use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\Discount;
use App\Models\InventoryItem;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\DiscountLimitReached;
use App\Services\DiscountService;
use App\Services\OrderService;
use App\Support\Money;
use Illuminate\Support\Facades\Notification;

function pricedLines(): array
{
    $branch = Branch::factory()->create();
    $product = Product::factory()->create(['branch_id' => $branch->id, 'price' => 300_000]);
    $another = Product::factory()->create(['branch_id' => $branch->id, 'price' => 100_000]);

    return [$branch, $product, $another];
}

test('an automatic percent discount applies to the entire order', function () {
    [$branch, $product] = pricedLines();

    Discount::factory()->percent(20)->create(['branch_id' => $branch->id]);

    $order = app(OrderService::class)->place($branch->id, null, null, [
        ['product_id' => $product->id, 'quantity' => 1],
    ]);

    expect($order->subtotal)->toBe(300_000)
        ->and($order->discount_total)->toBe(60_000)
        ->and($order->total)->toBe(240_000)
        ->and($order->discount)->not->toBeNull();
});

test('the best eligible discount wins over weaker ones', function () {
    [$branch, $product] = pricedLines();

    Discount::factory()->percent(10)->create(['branch_id' => $branch->id]);
    Discount::factory()->fixed(100_000)->create(['branch_id' => $branch->id]);

    $order = app(OrderService::class)->place($branch->id, null, null, [
        ['product_id' => $product->id, 'quantity' => 1],
    ]);

    expect($order->discount_total)->toBe(100_000)
        ->and($order->total)->toBe(200_000)
        ->and($order->discount->type->value)->toBe('fixed');
});

test('an ineligible discount never applies while a weaker eligible one does', function () {
    [$branch, $product] = pricedLines();

    Discount::factory()->percent(50)->minOrder(900_000)->create(['branch_id' => $branch->id]);
    Discount::factory()->percent(5)->create(['branch_id' => $branch->id]);

    $order = app(OrderService::class)->place($branch->id, null, null, [
        ['product_id' => $product->id, 'quantity' => 1],
    ]);

    expect($order->discount_total)->toBe(15_000)
        ->and($order->total)->toBe(285_000);
});

test('a category discount only applies to the lines of that category', function () {
    [$branch, $product, $another] = pricedLines();
    $drinks = MenuCategory::factory()->create(['branch_id' => $branch->id]);

    $drink = Product::factory()->create([
        'branch_id' => $branch->id,
        'menu_category_id' => $drinks->id,
        'price' => 100_000,
    ]);

    Discount::factory()->percent(50)->forCategory($drinks)->create(['branch_id' => $branch->id]);

    $order = app(OrderService::class)->place($branch->id, null, null, [
        ['product_id' => $product->id, 'quantity' => 1],
        ['product_id' => $drink->id, 'quantity' => 2],
    ]);

    // Only the two drinks (2 × 100k) carry the 50%: 100k off of a 500k cart.
    expect($order->subtotal)->toBe(500_000)
        ->and($order->discount_total)->toBe(100_000)
        ->and($order->total)->toBe(400_000);
});

test('a coupon code applies when it beats the automatic offer', function () {
    [$branch, $product] = pricedLines();

    Discount::factory()->percent(10)->create(['branch_id' => $branch->id]);
    $coupon = Discount::factory()->percent(30)->withCode('WELCOME')->create(['branch_id' => $branch->id]);

    $order = app(OrderService::class)->place(
        $branch->id, null, null,
        [['product_id' => $product->id, 'quantity' => 1]],
        discountCode: 'welcome', // Case-insensitive codes.
    );

    expect($order->discount_id)->toBe($coupon->id)
        ->and($order->discount_total)->toBe(90_000)
        ->and($order->total)->toBe(210_000);
});

test('a coupon worse than the automatic offer is rejected with a Persian message', function () {
    [$branch, $product] = pricedLines();

    Discount::factory()->percent(20)->create(['branch_id' => $branch->id]);
    Discount::factory()->percent(5)->withCode('WEAK')->create(['branch_id' => $branch->id]);

    app(OrderService::class)->place(
        $branch->id, null, null,
        [['product_id' => $product->id, 'quantity' => 1]],
        discountCode: 'WEAK',
    );
})->throws(RuntimeException::class, 'کمتر از تخفیف خودکار');

test('an unknown or inactive code is rejected', function () {
    [$branch, $product] = pricedLines();

    Discount::factory()->percent(20)->withCode('REAL')->inactive()->create(['branch_id' => $branch->id]);

    app(OrderService::class)->place(
        $branch->id, null, null,
        [['product_id' => $product->id, 'quantity' => 1]],
        discountCode: 'GHOST',
    );
})->throws(InvalidArgumentException::class, 'کد تخفیف یافت نشد');

test('an expired discount never applies', function () {
    [$branch, $product] = pricedLines();

    Discount::factory()->percent(20)->expired()->create(['branch_id' => $branch->id]);

    $order = app(OrderService::class)->place($branch->id, null, null, [
        ['product_id' => $product->id, 'quantity' => 1],
    ]);

    expect($order->discount_total)->toBe(0)
        ->and($order->total)->toBe(300_000)
        ->and($order->discount)->toBeNull();
});

test('an exhausted discount never applies', function () {
    [$branch, $product] = pricedLines();

    Discount::factory()->percent(20)->exhausted()->create(['branch_id' => $branch->id]);

    $order = app(OrderService::class)->place($branch->id, null, null, [
        ['product_id' => $product->id, 'quantity' => 1],
    ]);

    expect($order->discount_total)->toBe(0);
});

test('the global usage ceiling is enforced and one usage is counted per order', function () {
    [$branch, $product] = pricedLines();

    $discount = Discount::factory()->percent(20)->create([
        'branch_id' => $branch->id,
        'usage_limit_total' => 2,
    ]);

    $service = app(OrderService::class);

    $service->place($branch->id, null, null, [['product_id' => $product->id, 'quantity' => 1]]);
    $service->place($branch->id, null, null, [['product_id' => $product->id, 'quantity' => 1]]);

    expect($discount->refresh()->used_count)->toBe(2);

    $third = $service->place($branch->id, null, null, [['product_id' => $product->id, 'quantity' => 1]]);

    expect($third->discount_total)->toBe(0)
        ->and($discount->refresh()->used_count)->toBe(2);
});

test('the per-user ceiling counts only that user and ignores guests', function () {
    [$branch, $product] = pricedLines();
    $customer = User::factory()->customer()->create();

    $discount = Discount::factory()->percent(20)->create([
        'branch_id' => $branch->id,
        'usage_limit_per_user' => 1,
    ]);

    $service = app(OrderService::class);

    $first = $service->place($branch->id, null, $customer, [['product_id' => $product->id, 'quantity' => 1]]);
    expect($first->discount_total)->toBe(60_000);

    $second = $service->place($branch->id, null, $customer, [['product_id' => $product->id, 'quantity' => 1]]);
    expect($second->discount_total)->toBe(0);

    // A per-user ceiling needs an identity to count against — guests can
    // not prove who they are, so the safe answer is no discount.
    $guest = $service->place($branch->id, null, null, [['product_id' => $product->id, 'quantity' => 1]]);
    expect($guest->discount_total)->toBe(0)
        ->and($discount->refresh()->used_count)->toBe(1);
});

test('a reaching a usage ceiling notifies the branch admins', function () {
    Notification::fake();
    [$branch, $product] = pricedLines();
    $admin = User::factory()->admin()->forBranch($branch)->create();

    Discount::factory()->percent(20)->create([
        'branch_id' => $branch->id,
        'usage_limit_total' => 1,
    ]);

    app(OrderService::class)->place($branch->id, null, null, [['product_id' => $product->id, 'quantity' => 1]]);

    Notification::assertSentTo($admin, DiscountLimitReached::class);
});

test('a discount never drives a total below zero', function () {
    [$branch, $product] = pricedLines();

    Discount::factory()->fixed(500_000)->create(['branch_id' => $branch->id]);

    $order = app(OrderService::class)->place($branch->id, null, null, [
        ['product_id' => $product->id, 'quantity' => 1],
    ]);

    expect($order->discount_total)->toBe(300_000)
        ->and($order->total)->toBe(0);
});

test('a paid discounted order deducts materials scaled by the discount share', function () {
    $branch = Branch::factory()->create();
    $flour = InventoryItem::factory()->create([
        'branch_id' => $branch->id,
        'unit' => MeasurementUnit::Gram,
        'current_stock' => 10_000.0,
    ]);
    $product = Product::factory()->create(['branch_id' => $branch->id, 'price' => 300_000]);
    ProductRecipe::factory()->forProduct($product)->consuming($flour, 1_000.0)->create();

    Discount::factory()->percent(50)->create(['branch_id' => $branch->id]);

    $service = app(OrderService::class);
    $order = $service->place($branch->id, null, null, [['product_id' => $product->id, 'quantity' => 1]]);

    expect($order->discount_total)->toBe(150_000);

    $service->markPaid($order, PaymentMethod::Cash);

    // Half the raw materials for half the price: 1000g → 500g.
    expect($flour->refresh()->current_stock)->toBe('9500.000')
        ->and((float) StockMovement::query()->where('order_id', $order->id)->first()->quantity)->toBe(-500.0);
});

test('a fully discounted order still deducts nothing below zero scale', function () {
    $branch = Branch::factory()->create();
    $flour = InventoryItem::factory()->create([
        'branch_id' => $branch->id,
        'current_stock' => 10_000.0,
    ]);
    $product = Product::factory()->create(['branch_id' => $branch->id, 'price' => 300_000]);
    ProductRecipe::factory()->forProduct($product)->consuming($flour, 1_000.0)->create();

    Discount::factory()->fixed(500_000)->create(['branch_id' => $branch->id]);

    $service = app(OrderService::class);
    $order = $service->place($branch->id, null, null, [['product_id' => $product->id, 'quantity' => 1]]);

    expect($order->total)->toBe(0);

    $service->markPaid($order, PaymentMethod::Cash);

    // A zero-cost order consumes zero materials — the cart was given away.
    expect($flour->refresh()->current_stock)->toBe('10000.000');
});

test('DiscountService::bestFor returns no discount for an empty board', function () {
    [$branch, $product] = pricedLines();

    $result = app(DiscountService::class)->bestFor(
        $branch->id,
        collect([['product' => $product, 'quantity' => 1, 'line_total' => Money::of(300_000)]]),
        Money::of(300_000),
    );

    expect($result['discount'])->toBeNull()
        ->and($result['amount']->toman)->toBe(0);
});
