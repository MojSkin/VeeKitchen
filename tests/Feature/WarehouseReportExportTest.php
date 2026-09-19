<?php

use App\Enums\MeasurementUnit;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\IOFactory;

function exportLab(): array
{
    $branch = Branch::factory()->create();
    $admin = User::factory()->admin()->forBranch($branch)->create();

    return [$branch, $admin];
}

function exportMaterial(Branch $branch, string $name, MeasurementUnit $unit, int $unitCost): InventoryItem
{
    return InventoryItem::factory()->withoutQr()->create([
        'branch_id' => $branch->id,
        'name' => $name,
        'unit' => $unit,
        'unit_cost' => $unitCost,
    ]);
}

test('the xlsx export streams a readable workbook with summary and detail sheets', function () {
    [$branch, $admin] = exportLab();

    $flour = exportMaterial($branch, 'آرد گندم', MeasurementUnit::Kilogram, 60_000);
    $cheese = exportMaterial($branch, 'پنیر موزارلا', MeasurementUnit::Kilogram, 320_000);

    StockMovement::factory()->create(['inventory_item_id' => $flour->id, 'type' => 'consumption', 'quantity' => -0.4]);
    StockMovement::factory()->create(['inventory_item_id' => $cheese->id, 'type' => 'purchase', 'quantity' => 10.0]);

    $response = $this->actingAs($admin)
        ->get(route('admin.inventory.report.export', ['format' => 'xlsx', 'range' => 'today']))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $content = $response->streamedContent();
    expect($content)->not->toBe('');

    // toArray() hands numbers back as strings and pads rows to the widest
    // one with trailing nulls — normalize both. Numeric cells become ints
    // when integral (10.0 → 10), so strict comparisons use loose equality.
    $numeric = function (array $row): array {
        $row = array_map(fn ($cell) => is_numeric($cell) ? $cell + 0 : $cell, $row);

        while ($row !== [] && end($row) === null) {
            array_pop($row);
        }

        return $row;
    };

    $temp = tmpfile();
    fwrite($temp, $content);
    $workbook = IOFactory::load(stream_get_meta_data($temp)['uri']);

    $summary = array_map($numeric, $workbook->getSheet(0)->toArray());
    $detail = array_map($numeric, $workbook->getSheet(1)->toArray());

    expect($workbook->getSheetNames())->toBe(['خلاصه', 'اقلام'])
        ->and($summary[6][0])->toBe('نوع حرکت')
        ->and(collect($summary)->contains(fn ($row) => $row == ['مصرف', -0.4, 24_000]))->toBeTrue()
        ->and($detail[0])->toBe(['نوع حرکت', 'متریال', 'واحد', 'جمع مقدار', 'ارزش ریالی (تومان)'])
        ->and(collect($detail)->contains(fn ($row) => $row == ['خرید', 'پنیر موزارلا', 'کیلوگرم', 10.0, 3_200_000]))->toBeTrue();

    fclose($temp);
});

test('the print export returns self-contained html with report figures', function () {
    [$branch, $admin] = exportLab();

    $flour = exportMaterial($branch, 'آرد گندم', MeasurementUnit::Kilogram, 60_000);
    StockMovement::factory()->create(['inventory_item_id' => $flour->id, 'type' => 'consumption', 'quantity' => -0.4]);

    $response = $this->actingAs($admin)
        ->get(route('admin.inventory.report.export', ['format' => 'print', 'range' => 'today']))
        ->assertOk()
        ->assertHeader('content-type', 'text/html; charset=UTF-8');

    $html = $response->streamedContent();

    expect($html)->toContain('گزارش انبار')
        ->toContain($branch->name)
        ->toContain('آرد گندم')
        ->toContain('24,000')
        ->toContain('window.print()')
        ->toContain('dir="rtl"');
});

test('exports carry the selected custom range bounds and filename', function () {
    [$branch, $admin] = exportLab();

    $flour = exportMaterial($branch, 'آرد گندم', MeasurementUnit::Kilogram, 60_000);
    StockMovement::factory()->create(['inventory_item_id' => $flour->id, 'type' => 'consumption', 'quantity' => -0.4]);
    $movement = StockMovement::query()->latest('id')->first();
    $movement->forceFill(['created_at' => now()->subDays(5)])->save();

    $from = now()->subDays(6)->toDateString();
    $to = now()->toDateString();

    $response = $this->actingAs($admin)
        ->get(route('admin.inventory.report.export', ['format' => 'xlsx', 'range' => 'custom', 'from' => $from, 'to' => $to]))
        ->assertOk();

    $disposition = $response->headers->get('content-disposition');

    expect($disposition)->toContain('warehouse-report-')
        ->toContain($from)
        ->toContain('.xlsx');

    $temp = tmpfile();
    fwrite($temp, $response->streamedContent());
    $workbook = IOFactory::load(stream_get_meta_data($temp)['uri']);
    $summary = $workbook->getSheet(0)->toArray();

    expect($summary[1][1])->toBe($from)
        ->and($summary[1][3])->toBe($to);

    fclose($temp);
});

test('exports are admin-only', function () {
    [$branch, $admin] = exportLab();

    $this->get(route('admin.inventory.report.export', ['format' => 'xlsx']))
        ->assertRedirect(route('login'));

    $cashier = User::factory()->cashier()->forBranch($branch)->create();
    $this->actingAs($cashier)
        ->get(route('admin.inventory.report.export', ['format' => 'xlsx']))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('admin.inventory.report.export', ['format' => 'print']))
        ->assertOk();
});
