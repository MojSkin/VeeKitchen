<?php

use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\Product;
use App\Models\StaffShift;
use App\Models\User;
use App\Services\EndOfDayService;
use App\Services\OrderService;
use App\Services\ShiftService;

function eodLab(): array
{
    $branch = Branch::factory()->create();
    $cashier = User::factory()->cashier()->forBranch($branch)->create();

    return [$branch, $cashier];
}

function eodPaidOrder(Branch $branch, User $cashier, int $total): void
{
    $product = Product::factory()->create(['branch_id' => $branch->id, 'price' => $total]);
    $order = app(OrderService::class)->place($branch->id, null, null, [
        ['product_id' => $product->id, 'quantity' => 1],
    ]);

    app(OrderService::class)->markPaid($order, PaymentMethod::Cash, $cashier);
}

test('the pipeline groups open and closed shifts with their settlement numbers', function () {
    [$branch, $cashier] = eodLab();
    $service = app(ShiftService::class);
    $eod = app(EndOfDayService::class);

    $shift = $service->open($cashier, $branch->id, 200_000);
    eodPaidOrder($branch, $cashier, 150_000);
    $service->registerMovement($shift, $cashier, 'withdrawal', 50_000, 'واریز به خزانه');
    $service->close($shift, $cashier, 300_000); // Expected 300k → zero discrepancy.

    $second = $service->open($cashier, $branch->id, 100_000);
    eodPaidOrder($branch, $cashier, 80_000);

    $pipeline = $eod->pipeline($branch);

    expect($pipeline['closed_shifts'])->toBe(1)
        ->and($pipeline['open_shifts'])->toBe(1)
        ->and($pipeline['shifts'])->toHaveCount(2)
        ->and($pipeline['total_discrepancy'])->toBe(0)
        ->and($pipeline['orders']['placed'])->toBe(2)
        ->and($pipeline['orders']['paid'])->toBe(2);

    $closed = collect($pipeline['shifts'])->firstWhere('id', $shift->id);

    expect($closed['is_open'])->toBeFalse()
        ->and($closed['cash_payments'])->toBe(150_000)
        ->and($closed['movements_net'])->toBe(-50_000)
        ->and($closed['expected_cash'])->toBe(300_000)
        ->and($closed['counted_cash'])->toBe(300_000)
        ->and($closed['closed_by'])->toBe($cashier->name);

    $open = collect($pipeline['shifts'])->firstWhere('id', $second->id);

    expect($open['is_open'])->toBeTrue()
        ->and($open['counted_cash'])->toBeNull()
        ->and($open['cash_payments'])->toBe(80_000);
});

test('a midnight-spanning open shift stays on the day pipeline', function () {
    [$branch, $cashier] = eodLab();

    // Opened yesterday, still open → belongs to today's pipeline too.
    $shift = StaffShift::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $cashier->id,
        'opened_at' => now()->subDay(),
        'opening_cash' => 100_000,
    ]);

    $pipeline = app(EndOfDayService::class)->pipeline($branch);

    expect($pipeline['open_shifts'])->toBe(1)
        ->and($pipeline['shifts'][0]['id'])->toBe($shift->id);
});

test('a closed shift from yesterday drops off the day pipeline', function () {
    [$branch, $cashier] = eodLab();

    StaffShift::factory()->closed()->create([
        'branch_id' => $branch->id,
        'user_id' => $cashier->id,
        'opened_at' => now()->subDay()->startOfDay(),
        'closed_at' => now()->subDay()->endOfDay(),
    ]);

    $pipeline = app(EndOfDayService::class)->pipeline($branch);

    expect($pipeline['shifts'])->toHaveCount(0)
        ->and($pipeline['open_shifts'])->toBe(0)
        ->and($pipeline['closed_shifts'])->toBe(0);
});

test('dashboard KPIs flag discrepancy shifts and the total', function () {
    [$branch, $cashier] = eodLab();
    $service = app(ShiftService::class);

    $short = $service->open($cashier, $branch->id, 500_000);
    eodPaidOrder($branch, $cashier, 300_000);
    $service->close($short, $cashier, 750_000); // Expected 800k (500 opening + 300 cash) → 50k shortage.

    $clean = $service->open($cashier, $branch->id, 100_000);
    $service->close($clean, $cashier, 100_000);

    $kpis = app(EndOfDayService::class)->dashboardKpis($branch);

    expect($kpis['open_shifts'])->toBe(0)
        ->and($kpis['closed_shifts'])->toBe(2)
        ->and($kpis['discrepancy_shifts'])->toBe(1)
        ->and($kpis['total_discrepancy'])->toBe(-50_000);
});

test('the end-of-day page renders the pipeline for admins', function () {
    [$branch, $cashier] = eodLab();
    $admin = User::factory()->admin()->forBranch($branch)->create();

    $service = app(ShiftService::class);
    $shift = $service->open($cashier, $branch->id, 250_000);
    eodPaidOrder($branch, $cashier, 125_000);
    $service->close($shift, $cashier, 375_000);

    $this->actingAs($admin)->get(route('admin.end-of-day'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/EndOfDay')
            ->where('pipeline.closed_shifts', 1)
            ->where('pipeline.shifts.0.cash_payments', 125_000)
            ->where('pipeline.shifts.0.expected_cash', 375_000)
            ->where('pipeline.orders.paid', 1));
});

test('the end-of-day page is admin-only', function () {
    [$branch, $cashier] = eodLab();

    $this->actingAs($cashier)->get(route('admin.end-of-day'))
        ->assertForbidden();
});
