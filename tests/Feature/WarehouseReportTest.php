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

function ledger(Branch $branch, InventoryItem $item, string $type, float $quantity): StockMovement
{
    return StockMovement::factory()->create([
        'inventory_item_id' => $item->id,
        'type' => $type,
        'quantity' => $quantity,
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
        ->and(collect($props['report']['types'])->firstWhere('type', 'waste')['items'][0]['total'])->toBe(-2.0);
});
