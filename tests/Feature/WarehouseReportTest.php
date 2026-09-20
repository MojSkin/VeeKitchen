<?php

use App\Enums\MeasurementUnit;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\WarehouseReportService;

function reportLab(): array
{
    $branch = Branch::factory()->create();
    $admin = User::factory()->admin()->forBranch($branch)->create();

    return [$branch, $admin];
}

function ledgerMaterial(Branch $branch, string $name, MeasurementUnit $unit): InventoryItem
{
    return InventoryItem::factory()->withoutQr()->create([
        'branch_id' => $branch->id,
        'name' => $name,
        'unit' => $unit,
    ]);
}

function ledger(Branch $branch, InventoryItem $item, string $type, float $quantity, ?int $unitCost = null): StockMovement
{
    return StockMovement::factory()->create([
        'inventory_item_id' => $item->id,
        'type' => $type,
        'quantity' => $quantity,
        'unit_cost_at' => $unitCost,
    ]);
}

test('the service groups today movements by type with signed totals and per-item lines', function () {
    [$branch] = reportLab();
    $service = app(WarehouseReportService::class);

    $flour = ledgerMaterial($branch, 'آرد گندم', MeasurementUnit::Kilogram);
    $cheese = ledgerMaterial($branch, 'پنیر موزارلا', MeasurementUnit::Kilogram);

    // Consumption: 0.4 + 0.15 kg → −0.55 on one material, −0.3 on the other.
    ledger($branch, $flour, 'consumption', -0.4);
    ledger($branch, $flour, 'consumption', -0.15);
    ledger($branch, $cheese, 'consumption', -0.3);
    // A purchase received today.
    ledger($branch, $flour, 'purchase', 20.0);
    // A negative adjustment (recount).
    ledger($branch, $cheese, 'adjustment', -1.0);

    $report = $service->todayByType($branch);

    $byType = collect($report['types'])->keyBy('type');

    expect($report['movement_count'])->toBe(5)
        ->and($byType['consumption']['movements'])->toBe(3)
        ->and($byType['consumption']['total'])->toEqualWithDelta(-0.85, 0.000001)
        ->and($byType['consumption']['items'][0]['name'])->toBe('آرد گندم')
        ->and($byType['consumption']['items'][0]['total'])->toBe(-0.55)
        ->and($byType['consumption']['items'][1]['total'])->toBe(-0.3)
        ->and($byType['purchase']['total'])->toBe(20.0)
        ->and($byType['purchase']['items'])->toHaveCount(1)
        ->and($byType['adjustment']['items'][0]['name'])->toBe('پنیر موزارلا')
        // Types with no movements still appear, zeroed.
        ->and($byType['waste']['movements'])->toBe(0)
        ->and($byType['return']['total'])->toBe(0.0);
});

test('rial values follow the material unit cost and are always positive', function () {
    [$branch] = reportLab();
    $service = app(WarehouseReportService::class);

    $flour = ledgerMaterial($branch, 'آرد گندم', MeasurementUnit::Kilogram);
    $flour->update(['unit_cost' => 60_000]);

    $cheese = ledgerMaterial($branch, 'پنیر موزارلا', MeasurementUnit::Kilogram);
    $cheese->update(['unit_cost' => 320_000]);

    // Freebie with no recorded cost yet — value must be zero, never negative.
    $free = ledgerMaterial($branch, 'رب گوجه', MeasurementUnit::Liter);
    $free->update(['unit_cost' => 0]);

    ledger($branch, $flour, 'consumption', -0.4);   // 24_000
    ledger($branch, $flour, 'consumption', -0.15);  // 9_000
    ledger($branch, $cheese, 'consumption', -0.3);  // 96_000
    ledger($branch, $flour, 'purchase', 20.0);      // 1_200_000
    ledger($branch, $cheese, 'adjustment', -1.0);   // 320_000
    ledger($branch, $free, 'consumption', -2.0);    // 0

    $report = $service->todayByType($branch);
    $byType = collect($report['types'])->keyBy('type');

    expect($byType['consumption']['items'][0]['name'])->toBe('پنیر موزارلا')
        ->and($byType['consumption']['items'][0]['value'])->toBe(96_000)
        ->and($byType['consumption']['items'][1]['value'])->toBe(33_000)
        ->and($byType['consumption']['items'][2]['value'])->toBe(0)
        ->and($byType['consumption']['value'])->toBe(129_000)
        ->and($byType['purchase']['value'])->toBe(1_200_000)
        ->and($byType['adjustment']['value'])->toBe(320_000)
        ->and($report['outflow_value'])->toBe(129_000)
        ->and($report['inflow_value'])->toBe(1_520_000);
});

test('yesterday rows never leak into the today report', function () {
    [$branch] = reportLab();
    $service = app(WarehouseReportService::class);

    $flour = ledgerMaterial($branch, 'آرد گندم', MeasurementUnit::Kilogram);

    ledger($branch, $flour, 'consumption', -0.4);

    // Rewrite the row's timestamp at the query level: the model is not
    // mass-assignable for timestamps by design.
    StockMovement::query()->latest('id')->first()->forceFill(['created_at' => now()->subDay()])->save();

    $report = $service->todayByType($branch);

    expect($report['movement_count'])->toBe(0)
        ->and(collect($report['types'])->sum(fn ($type) => $type['movements']))->toBe(0);
});

test('the report page is admin-only', function () {
    [$branch, $admin] = reportLab();

    $this->get(route('admin.inventory.report'))->assertRedirect(route('login'));
    $this->actingAs($admin)->get(route('admin.inventory.report'))->assertOk();

    $cashier = User::factory()->cashier()->forBranch($branch)->create();
    $this->actingAs($cashier)->get(route('admin.inventory.report'))->assertForbidden();
});

test('the page payload carries the branch, type summary and generated timestamp', function () {
    [$branch, $admin] = reportLab();

    $flour = ledgerMaterial($branch, 'آرد گندم', MeasurementUnit::Kilogram);
    ledger($branch, $flour, 'waste', -2.0);

    $props = $this->actingAs($admin)
        ->get(route('admin.inventory.report'))
        ->viewData('page')['props'];

    expect($props['branch']['name'])->toBe($branch->name)
        ->and($props['report']['movement_count'])->toBe(1)
        ->and($props['report']['generated_at'])->toBeString()
        ->and($props['range']['preset'])->toBe('today')
        ->and(collect($props['report']['types'])->firstWhere('type', 'waste')['items'][0]['total'])->toBe(-2.0);
});

test('the last7 preset spans seven days including older rows', function () {
    [$branch, $admin] = reportLab();

    $flour = ledgerMaterial($branch, 'آرد گندم', MeasurementUnit::Kilogram);

    // Three days ago: inside last7 but outside today.
    ledger($branch, $flour, 'consumption', -1.5);
    StockMovement::query()->latest('id')->first()->forceFill(['created_at' => now()->subDays(3)])->save();
    // Eight days ago: outside last7.
    ledger($branch, $flour, 'waste', -9.0);
    StockMovement::query()->latest('id')->first()->forceFill(['created_at' => now()->subDays(8)])->save();

    $props = $this->actingAs($admin)
        ->get(route('admin.inventory.report', ['range' => 'last7']))
        ->viewData('page')['props'];

    expect($props['range']['preset'])->toBe('last7')
        ->and($props['report']['movement_count'])->toBe(1)
        ->and(collect($props['report']['types'])->firstWhere('type', 'consumption')['movements'])->toBe(1);
});

test('the week preset starts on Saturday', function () {
    [$branch, $admin] = reportLab();

    $props = $this->actingAs($admin)
        ->get(route('admin.inventory.report', ['range' => 'week']))
        ->viewData('page')['props'];

    $from = new DateTime($props['range']['from']);
    $to = new DateTime($props['range']['to']);

    expect((int) $from->format('N'))->toBe(6) // ISO: Saturday
        ->and($props['range']['preset'])->toBe('week')
        ->and($to->getTimestamp())->toBeGreaterThanOrEqual($from->getTimestamp());
});

test('the custom preset bounds the report to the requested days', function () {
    [$branch, $admin] = reportLab();

    $flour = ledgerMaterial($branch, 'آرد گندم', MeasurementUnit::Kilogram);

    // Inside the requested window (yesterday).
    ledger($branch, $flour, 'purchase', 12.0);
    StockMovement::query()->latest('id')->first()->forceFill(['created_at' => now()->subDay()])->save();
    // Outside (four days ago).
    ledger($branch, $flour, 'consumption', -3.0);
    StockMovement::query()->latest('id')->first()->forceFill(['created_at' => now()->subDays(4)])->save();

    $from = now()->subDays(2)->toDateString();
    $to = now()->toDateString();

    $props = $this->actingAs($admin)
        ->get(route('admin.inventory.report', ['range' => 'custom', 'from' => $from, 'to' => $to]))
        ->viewData('page')['props'];

    $fromProp = new DateTime($props['range']['from']);

    expect($props['range']['preset'])->toBe('custom')
        ->and($props['range']['from'])->toContain($from)
        ->and($props['report']['movement_count'])->toBe(1)
        ->and(collect($props['report']['types'])->firstWhere('type', 'purchase')['items'][0]['total'])->toBe(12.0)
        // Day boundary: the from date starts at 00:00 local.
        ->and($fromProp->format('H:i'))->toBe('00:00');
});

test('garbled custom dates fall back to today and swapped bounds are tolerated', function () {
    [$branch, $admin] = reportLab();

    $flour = ledgerMaterial($branch, 'آرد گندم', MeasurementUnit::Kilogram);
    ledger($branch, $flour, 'consumption', -0.5);

    // Garbled from-date → today fallback.
    $props = $this->actingAs($admin)
        ->get(route('admin.inventory.report', ['range' => 'custom', 'from' => 'not-a-date', 'to' => '2026-13-99']))
        ->viewData('page')['props'];

    expect($props['range']['preset'])->toBe('today')
        ->and($props['report']['movement_count'])->toBe(1);

    // Swapped bounds (to < from) are tolerated, not rejected.
    $props = $this->actingAs($admin)
        ->get(route('admin.inventory.report', [
            'range' => 'custom',
            'from' => now()->toDateString(),
            'to' => now()->subDays(2)->toDateString(),
        ]))
        ->viewData('page')['props'];

    expect($props['range']['preset'])->toBe('custom')
        ->and($props['report']['movement_count'])->toBe(1);
});
