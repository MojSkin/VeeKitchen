<?php

use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function alertLab(): array
{
    $branch = Branch::factory()->create();
    $admin = User::factory()->admin()->forBranch($branch)->create();

    return [$branch, $admin];
}

test('the shared stockAlerts prop carries the unread count for admins', function () {
    [$branch, $admin] = alertLab();

    InventoryItem::factory()->lowStock()->withoutQr()->create([
        'branch_id' => $branch->id,
        'current_stock' => 2.0,
        'low_stock_threshold' => 5.0,
    ]);

    app(InventoryService::class)->evaluateLowStock(
        InventoryItem::query()->firstOrFail(),
    );

    $response = $this->actingAs($admin)->get(route('admin.inventory'));
    $alerts = $response->viewData('page')['props']['stockAlerts'];

    expect($alerts['count'])->toBe(1)
        ->and($alerts['items'][0]['title'])->toBe('هشدار موجودی کم')
        ->and($alerts['items'][0]['message'])->toContain('از خط هشدار گذشت');
});

test('the prop stays null for non-admin staff and guests', function () {
    [$branch, $admin] = alertLab();

    $cashier = User::factory()->cashier()->forBranch($branch)->create();
    $response = $this->actingAs($cashier)->get(route('cashier.index'));
    $props = $response->viewData('page')['props'];

    expect($props['stockAlerts'] ?? null)->toBeNull();
});

test('mark-all-read clears the counter', function () {
    [$branch, $admin] = alertLab();

    $item = InventoryItem::factory()->withoutQr()->create([
        'branch_id' => $branch->id,
        'current_stock' => 1.0,
        'low_stock_threshold' => 5.0,
    ]);

    app(InventoryService::class)->evaluateLowStock($item);

    expect($admin->unreadNotifications()->count())->toBe(1);

    $this->actingAs($admin)
        ->post(route('admin.notifications.read-all'))
        ->assertRedirect();

    expect($admin->refresh()->unreadNotifications()->count())->toBe(0);
});

test('mark-all-read is admin-only', function () {
    [$branch, $admin] = alertLab();

    $this->post(route('admin.notifications.read-all'))->assertRedirect(route('login'));

    $cashier = User::factory()->cashier()->forBranch($branch)->create();
    $this->actingAs($cashier)
        ->post(route('admin.notifications.read-all'))
        ->assertForbidden();
});

test('the full loop: paying an order raises the counter, reading clears it', function () {
    [$branch, $admin] = alertLab();

    $flour = InventoryItem::factory()->withoutQr()->create([
        'branch_id' => $branch->id,
        'current_stock' => 1.2,
        'low_stock_threshold' => 1.0,
    ]);
    $product = Product::factory()->create(['branch_id' => $branch->id]);
    ProductRecipe::factory()->forProduct($product)->consuming($flour, 0.5)->create();

    $service = app(OrderService::class);
    $order = $service->place($branch->id, null, null, [['product_id' => $product->id, 'quantity' => 1]]);
    $service->markPaid($order, PaymentMethod::Cash);

    $response = $this->actingAs($admin)->get(route('admin.inventory'));
    $alerts = $response->viewData('page')['props']['stockAlerts'];

    expect($alerts['count'])->toBe(1)
        ->and($alerts['items'][0]['message'])->toContain('از خط هشدار گذشت');

    $this->post(route('admin.notifications.read-all'));

    expect($admin->refresh()->unreadNotifications()->count())->toBe(0);
});
