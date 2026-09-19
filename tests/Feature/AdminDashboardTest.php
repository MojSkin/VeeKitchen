<?php

use App\Enums\MeasurementUnit;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\RestaurantTable;
use App\Models\User;

function dashboardLab(): array
{
    $branch = Branch::factory()->create();
    $admin = User::factory()->admin()->forBranch($branch)->create();

    return [$branch, $admin];
}

function paidOrder(Branch $branch, int $amount, ?DateTimeInterface $paidAt = null, int $items = 1): Order
{
    $order = Order::factory()->for($branch)->create([
        'status' => 'delivered',
        'total' => $amount,
        'subtotal' => $amount,
        'placed_at' => $paidAt ?? now(),
        'paid_at' => $paidAt ?? now(),
    ]);

    OrderItem::factory()->count($items)->create([
        'order_id' => $order->id,
        'unit_price' => $amount,
        'line_total' => $amount,
    ]);

    Payment::factory()->create([
        'order_id' => $order->id,
        'amount' => $amount,
        'paid_at' => $paidAt ?? now(),
    ]);

    return $order;
}

test('the dashboard is admin-only', function () {
    [$branch, $admin] = dashboardLab();

    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

    $cashier = User::factory()->cashier()->forBranch($branch)->create();
    $this->actingAs($cashier)->get(route('admin.dashboard'))->assertForbidden();
});

test('today KPIs count paid revenue, placed orders and average ticket', function () {
    [$branch, $admin] = dashboardLab();

    paidOrder($branch, 300_000, now(), 2);
    paidOrder($branch, 450_000, now(), 1);
    // Yesterday's money must not leak into today's stats.
    paidOrder($branch, 999_000, now()->subDay());

    $props = $this->actingAs($admin)->get(route('admin.dashboard'))->viewData('page')['props'];

    expect($props['today']['revenue'])->toBe(750_000)
        ->and($props['today']['orders'])->toBe(2)
        ->and($props['today']['average_ticket'])->toBe(375_000);
});

test('the sales chart spans exactly 14 days including zero days', function () {
    [$branch, $admin] = dashboardLab();

    paidOrder($branch, 250_000, now()->subDays(2));

    $props = $this->actingAs($admin)->get(route('admin.dashboard'))->viewData('page')['props'];
    $days = collect($props['salesChart']['days']);

    expect($days)->toHaveCount(14)
        ->and($props['salesChart']['revenue_total'])->toBe(250_000)
        ->and($props['salesChart']['orders_total'])->toBe(1)
        ->and($days->firstWhere('revenue', 250_000)['date'])->toBe(now()->subDays(2)->toDateString())
        // All other days stay zero on the axis.
        ->and($days->filter(fn ($day) => $day['revenue'] === 0))->toHaveCount(13)
        ->and($props['salesChart']['best_day_label'])->toBe(now()->subDays(2)->format('m-d'));
});

test('low stock lists only materials at or below their threshold', function () {
    [$branch, $admin] = dashboardLab();

    InventoryItem::factory()->withoutQr()->create([
        'branch_id' => $branch->id,
        'name' => 'پنیر موزارلا',
        'unit' => MeasurementUnit::Kilogram,
        'current_stock' => 2.0,
        'low_stock_threshold' => 4.0,
    ]);
    InventoryItem::factory()->withoutQr()->create([
        'branch_id' => $branch->id,
        'name' => 'آرد گندم',
        'unit' => MeasurementUnit::Kilogram,
        'current_stock' => 80.0,
        'low_stock_threshold' => 20.0,
    ]);

    $props = $this->actingAs($admin)->get(route('admin.dashboard'))->viewData('page')['props'];

    expect($props['lowStock'])->toHaveCount(1)
        ->and($props['lowStock'][0]['name'])->toBe('پنیر موزارلا')
        ->and($props['lowStock'][0]['current'])->toBe(2.0);
});

test('order pipeline and tables snapshot count live statuses', function () {
    [$branch, $admin] = dashboardLab();

    Order::factory()->for($branch)->create(['status' => 'awaiting_payment']);
    Order::factory()->for($branch)->create(['status' => 'preparing']);
    RestaurantTable::factory()->create(['branch_id' => $branch->id, 'status' => 'free']);
    RestaurantTable::factory()->create(['branch_id' => $branch->id, 'status' => 'awaiting_settlement']);

    $props = $this->actingAs($admin)->get(route('admin.dashboard'))->viewData('page')['props'];

    $statuses = collect($props['orderStatuses'])->keyBy('value');
    $tables = collect($props['tables'])->keyBy('value');

    expect($statuses['awaiting_payment']['count'])->toBe(1)
        ->and($statuses['preparing']['count'])->toBe(1)
        ->and($statuses['queued']['count'])->toBe(0)
        ->and($tables['free']['count'])->toBe(1)
        ->and($tables['awaiting_settlement']['count'])->toBe(1)
        ->and($tables['reserved']['count'])->toBe(0);
});

test('an empty branch renders an all-zero dashboard without errors', function () {
    [$branch, $admin] = dashboardLab();

    $props = $this->actingAs($admin)->get(route('admin.dashboard'))->viewData('page')['props'];

    expect($props['today'])->toBe(['revenue' => 0, 'orders' => 0, 'average_ticket' => 0])
        ->and($props['salesChart']['revenue_total'])->toBe(0)
        ->and($props['salesChart']['days'])->toHaveCount(14)
        ->and($props['salesChart']['best_day_label'])->toBe('')
        ->and($props['lowStock'])->toBe([])
        ->and(collect($props['tables'])->sum('count'))->toBe(0);
});
