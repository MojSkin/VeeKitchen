<?php

use App\Enums\MeasurementUnit;
use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Services\OrderService;
use PhpOffice\PhpSpreadsheet\IOFactory;

function dashboardExportLab(): array
{
    $branch = Branch::factory()->create();
    $admin = User::factory()->admin()->forBranch($branch)->create();

    return [$branch, $admin];
}

function loadWorkbook(string $content): array
{
    // toArray() hands numbers back as strings — cast them, keep nulls (empty
    // cells), and pad every row to the sheet's width so indices are stable.
    $numeric = fn ($cell) => is_numeric($cell) ? $cell + 0 : $cell;

    $temp = tmpfile();
    fwrite($temp, $content);
    $workbook = IOFactory::load(stream_get_meta_data($temp)['uri']);

    return collect($workbook->getWorksheetIterator())
        ->mapWithKeys(function ($sheet) use ($numeric) {
            $rows = $sheet->toArray();
            $width = collect($rows)->map(fn (array $row) => count($row))->max() ?? 0;

            return [$sheet->getTitle() => array_map(function (array $row) use ($numeric, $width) {
                return array_map($numeric, array_pad($row, $width, null));
            }, $rows)];
        })
        ->all();
}

test('admins download a readable dashboard workbook with KPI and chart sheets', function () {
    [$branch, $admin] = dashboardExportLab();

    $response = $this->actingAs($admin)
        ->get(route('admin.dashboard.export'))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $sheets = loadWorkbook($response->streamedContent());

    expect(array_keys($sheets))->toBe(['KPIها', 'نمودار فروش']);
});

test('the KPI sheet mirrors the on-screen headline numbers', function () {
    [$branch, $admin] = dashboardExportLab();

    $service = app(OrderService::class);
    $order = $service->place($branch->id, null, null, [
        ['product_id' => Product::factory()->create(['branch_id' => $branch->id, 'price' => 450_000])->id, 'quantity' => 1],
    ]);
    $service->markPaid($order, PaymentMethod::Cash);

    $sheets = loadWorkbook(
        $this->actingAs($admin)->get(route('admin.dashboard.export'))->streamedContent(),
    );

    $rows = $sheets['KPIها'];

    expect($rows[0][0])->toBe('داشبورد مدیریتی — '.$branch->name)
        ->and(array_slice($rows[4], 0, 2))->toBe(['فروش امروز (تومان)', 450_000])
        ->and(array_slice($rows[5], 0, 2))->toBe(['تعداد سفارش‌های امروز', 1])
        ->and(array_slice($rows[6], 0, 2))->toBe(['میانگین سبد (تومان)', 450_000]);
});

test('the pipeline, dining room and low-stock boards all land on the KPI sheet', function () {
    [$branch, $admin] = dashboardExportLab();

    Order::factory()->create(['branch_id' => $branch->id, 'status' => 'awaiting_payment']);
    RestaurantTable::factory()->create(['branch_id' => $branch->id, 'status' => 'free']);
    InventoryItem::factory()->withoutQr()->create([
        'branch_id' => $branch->id,
        'unit' => MeasurementUnit::Kilogram,
        'current_stock' => 2.0,
        'low_stock_threshold' => 5.0,
        'name' => 'آرد گندم',
    ]);

    $sheets = loadWorkbook(
        $this->actingAs($admin)->get(route('admin.dashboard.export'))->streamedContent(),
    );

    $flat = collect($sheets['KPIها'])->flatten(1);

    expect($flat)->toContain('منتظر پرداخت')
        ->and($flat)->toContain('آزاد')
        ->and($flat)->toContain('آرد گندم')
        ->and($flat)->toContain(2)
        ->and($flat)->toContain(5);
});

test('the chart sheet carries exactly fourteen daily rows plus the period totals', function () {
    [$branch, $admin] = dashboardExportLab();

    $product = Product::factory()->create(['branch_id' => $branch->id, 'price' => 300_000]);
    $service = app(OrderService::class);
    $order = $service->place($branch->id, null, null, [['product_id' => $product->id, 'quantity' => 2]]);
    $service->markPaid($order, PaymentMethod::Cash);

    $sheets = loadWorkbook(
        $this->actingAs($admin)->get(route('admin.dashboard.export'))->streamedContent(),
    );

    $rows = $sheets['نمودار فروش'];

    // 3 header rows + 14 day rows + totals row + best-day row = 19.
    expect(count($rows))->toBe(19)
        // Today is the last day row (offset 13): [date, label, revenue, orders].
        // The date is asserted relative to `now` so the test survives
        // midnight and timezones alike.
        ->and($rows[16][0])->toBe(now()->toDateString())
        ->and($rows[16][2])->toBe(600_000)
        ->and($rows[16][3])->toBe(1)
        // Zeros for empty days keep the chart axis honest.
        ->and($rows[5][2])->toBe(0)
        ->and($rows[17])->toBe(['جمع دوره', null, 600_000, 1])
        ->and($rows[18][0])->toBe('بهترین روز')
        ->and($rows[18][1])->toBe($rows[16][1]);
});

test('a branch with no sales exports all-zero KPIs and an empty best day', function () {
    [$branch, $admin] = dashboardExportLab();

    $sheets = loadWorkbook(
        $this->actingAs($admin)->get(route('admin.dashboard.export'))->streamedContent(),
    );

    $rows = $sheets['KPIها'];

    expect($rows[4][1])->toBe(0)
        ->and($rows[5][1])->toBe(0)
        ->and($rows[6][1])->toBe(0)
        ->and($sheets['نمودار فروش'][17][2])->toBe(0)
        ->and($sheets['نمودار فروش'][18][1])->toBeNull();
});

test('the export is admin-only', function () {
    [$branch, $admin] = dashboardExportLab();
    $cashier = User::factory()->cashier()->forBranch($branch)->create();

    $this->get(route('admin.dashboard.export'))->assertRedirect();
    $this->actingAs($cashier)->get(route('admin.dashboard.export'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.dashboard.export'))->assertOk();
});

test('payments land on their paid day inside the chart window', function () {
    [$branch, $admin] = dashboardExportLab();

    $product = Product::factory()->create(['branch_id' => $branch->id, 'price' => 200_000]);
    $service = app(OrderService::class);
    $order = $service->place($branch->id, null, null, [['product_id' => $product->id, 'quantity' => 1]]);

    // Simulate a payment completed yesterday, not today.
    $service->markPaid($order, PaymentMethod::Cash);
    Payment::query()->where('order_id', $order->id)->update(['paid_at' => now()->subDay()]);
    Order::query()->whereKey($order->id)->update(['paid_at' => now()->subDay()]);

    $sheets = loadWorkbook(
        $this->actingAs($admin)->get(route('admin.dashboard.export'))->streamedContent(),
    );

    $rows = $sheets['نمودار فروش'];

    // Yesterday is day offset 12 → sheet row 15; today (row 16) stays zero.
    expect($rows[15][2])->toBe(200_000)
        ->and($rows[15][3])->toBe(1)
        ->and($rows[16][2])->toBe(0);
});
