<?php

use App\Enums\MeasurementUnit;
use App\Mail\WarehouseWeeklyReport;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\WarehouseReportService;
use Illuminate\Support\Facades\Mail;

function reportBranch(): array
{
    $branch = Branch::factory()->create();
    $admin = User::factory()->admin()->forBranch($branch)->create(['email' => 'admin@example.test']);

    return [$branch, $admin];
}

function movementOn(Branch $branch, string $day, float $quantity): void
{
    $item = InventoryItem::factory()->create([
        'branch_id' => $branch->id,
        'unit' => MeasurementUnit::Kilogram,
        'unit_cost' => 100_000,
    ]);

    // The factory does not accept timestamps; bypass Eloquent's guarded
    // fill the same way the warehouse-report tests do.
    $movement = StockMovement::factory()->consumption($quantity)->create([
        'inventory_item_id' => $item->id,
    ]);

    $movement->forceFill(['created_at' => $day.' 12:00:00'])->save();
}

test('the weekly report emails each active branch admins for the last seven completed days', function () {
    Mail::fake();
    [$branch, $admin] = reportBranch();

    // Inside the window: two completed days ago.
    movementOn($branch, now()->subDays(2)->format('Y-m-d'), 1.5);

    $this->artisan('reports:weekly-warehouse')->assertSuccessful();

    Mail::assertSent(WarehouseWeeklyReport::class, function (WarehouseWeeklyReport $mail) use ($admin, $branch) {
        return $mail->hasTo($admin->email)
            && $mail->branch->id === $branch->id
            // Window: the seven days before today, none of them today.
            && $mail->fromDay->isSameDay(now()->subDays(7))
            && $mail->toDay->isSameDay(now()->subDay())
            && $mail->report['movement_count'] === 1;
    });
});

test('movements from today and from eight days ago stay outside the window', function () {
    Mail::fake();
    [$branch] = reportBranch();

    movementOn($branch, now()->format('Y-m-d'), 3.0);
    movementOn($branch, now()->subDays(8)->format('Y-m-d'), 4.0);

    $this->artisan('reports:weekly-warehouse')->assertSuccessful();

    // Both rows are out of the window → nothing to report → no mail.
    Mail::assertNotSent(WarehouseWeeklyReport::class);
});

test('a branch with an empty window gets no email', function () {
    Mail::fake();
    reportBranch();

    $this->artisan('reports:weekly-warehouse')->assertSuccessful();

    Mail::assertNothingSent();
});

test('an inactive branch is skipped even with movements', function () {
    Mail::fake();
    [$branch] = reportBranch();
    $branch->update(['is_active' => false]);

    movementOn($branch, now()->subDay()->format('Y-m-d'), 2.0);

    $this->artisan('reports:weekly-warehouse')->assertSuccessful();

    Mail::assertNothingSent();
});

test('a branch without admins is skipped', function () {
    Mail::fake();
    $branch = Branch::factory()->create();
    User::factory()->cashier()->forBranch($branch)->create();

    movementOn($branch, now()->subDay()->format('Y-m-d'), 2.0);

    $this->artisan('reports:weekly-warehouse')->assertSuccessful();

    Mail::assertNothingSent();
});

test('cashiers and other staff never receive the report', function () {
    Mail::fake();
    [$branch] = reportBranch();
    User::factory()->cashier()->forBranch($branch)->create(['email' => 'cashier@example.test']);
    User::factory()->kitchen()->forBranch($branch)->create(['email' => 'kitchen@example.test']);

    movementOn($branch, now()->subDay()->format('Y-m-d'), 2.0);

    $this->artisan('reports:weekly-warehouse')->assertSuccessful();

    Mail::assertSent(WarehouseWeeklyReport::class, 1);
    Mail::assertSent(WarehouseWeeklyReport::class, fn (WarehouseWeeklyReport $mail) => $mail->hasTo('admin@example.test'));
    Mail::assertNotSent(fn (WarehouseWeeklyReport $mail) => $mail->hasTo('cashier@example.test'));
    Mail::assertNotSent(fn (WarehouseWeeklyReport $mail) => $mail->hasTo('kitchen@example.test'));
});

test('the window override covers exactly the given days', function () {
    Mail::fake();
    [$branch] = reportBranch();

    movementOn($branch, '2026-09-01', 2.0);
    movementOn($branch, '2026-09-03', 1.0);

    $this->artisan('reports:weekly-warehouse', ['--from' => '2026-09-01', '--to' => '2026-09-03'])->assertSuccessful();

    Mail::assertSent(WarehouseWeeklyReport::class, fn (WarehouseWeeklyReport $mail) => $mail->report['movement_count'] === 2);
});

test('a swapped override range is tolerated', function () {
    Mail::fake();
    [$branch] = reportBranch();

    movementOn($branch, '2026-09-01', 2.0);

    $this->artisan('reports:weekly-warehouse', ['--from' => '2026-09-03', '--to' => '2026-09-01'])->assertSuccessful();

    Mail::assertSent(WarehouseWeeklyReport::class, fn (WarehouseWeeklyReport $mail) => $mail->report['movement_count'] === 1);
});

test('the email renders the branch name, totals and item rows', function () {
    Mail::fake();
    [$branch] = reportBranch();

    movementOn($branch, now()->subDay()->format('Y-m-d'), 1.5);

    $this->artisan('reports:weekly-warehouse')->assertSuccessful();

    Mail::assertSent(WarehouseWeeklyReport::class, function (WarehouseWeeklyReport $mail) {
        $html = $mail->render();

        return str_contains($html, 'گزارش هفتگی انبار')
            && str_contains($html, $mail->branch->name)
            && str_contains($html, 'ارزش خروجی از انبار')
            && str_contains($html, '150,000')
            && str_contains($html, '1.5')
            && str_contains($html, 'کیلوگرم');
    });
});

test('the mailable subject carries the branch and range', function () {
    [$branch] = reportBranch();

    movementOn($branch, now()->subDay()->format('Y-m-d'), 1.5);

    $report = app(WarehouseReportService::class)->rangeByType(
        $branch,
        now()->subDays(7)->startOfDay(),
        now()->subDay()->endOfDay(),
    );

    $mail = new WarehouseWeeklyReport(
        $branch,
        $report,
        now()->subDays(7)->startOfDay(),
        now()->subDay()->endOfDay(),
    );

    expect($mail->envelope()->subject)->toContain('گزارش هفتگی انبار')
        ->and($mail->envelope()->subject)->toContain($branch->name);
});
