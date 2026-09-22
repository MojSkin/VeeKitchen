<?php

use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\Product;
use App\Models\StaffShift;
use App\Models\User;
use App\Services\OrderService;
use App\Services\ShiftService;
use PhpOffice\PhpSpreadsheet\IOFactory;

function settlementLab(): array
{
    $branch = Branch::factory()->create();
    $cashier = User::factory()->cashier()->forBranch($branch)->create();
    $service = app(ShiftService::class);

    return [$branch, $cashier, $service];
}

function settlementPaidOrder(Branch $branch, User $cashier, int $total): void
{
    $product = Product::factory()->create(['branch_id' => $branch->id, 'price' => $total]);
    $order = app(OrderService::class)->place($branch->id, null, null, [
        ['product_id' => $product->id, 'quantity' => 1],
    ]);

    app(OrderService::class)->markPaid($order, PaymentMethod::Cash, $cashier);
}

/* ── Settlement exports ─────────────────────────────────────────── */

test('the shift settlement xlsx streams a readable workbook with both sheets', function () {
    [$branch, $cashier, $service] = settlementLab();
    $admin = User::factory()->admin()->forBranch($branch)->create();

    $shift = $service->open($cashier, $branch->id, 500_000);
    settlementPaidOrder($branch, $cashier, 300_000);
    $service->registerMovement($shift, $cashier, 'withdrawal', 50_000, 'واریز به خزانه');
    $service->close($shift, $cashier, 750_000);

    $response = $this->actingAs($admin)
        ->get(route('admin.shift-settlement.export', ['shift' => $shift->id, 'format' => 'xlsx']))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $disposition = $response->headers->get('content-disposition');
    expect($disposition)->toContain('shift-settlement-'.$shift->id)->toContain('.xlsx');

    $temp = tmpfile();
    fwrite($temp, $response->streamedContent());
    $workbook = IOFactory::load(stream_get_meta_data($temp)['uri']);

    expect($workbook->getSheetNames())->toBe(['تسویه', 'حرکات نقدی']);

    $numeric = function (array $row): array {
        $row = array_map(fn ($cell) => is_numeric($cell) ? $cell + 0 : $cell, $row);

        while ($row !== [] && end($row) === null) {
            array_pop($row);
        }

        return $row;
    };

    $summary = array_map($numeric, $workbook->getSheet(0)->toArray());
    $movements = array_map($numeric, $workbook->getSheet(1)->toArray());

    // Opening 500k + cash 300k − 50k withdrawal = 750k expected, 750k counted, zero gap.
    expect(collect($summary)->contains(fn ($row) => ($row[0] ?? null) === 'مغایرت (تومان)' && ($row[1] ?? null) === 0))->toBeTrue()
        ->and(collect($summary)->contains(fn ($row) => ($row[0] ?? null) === 'صندوق مورد انتظار (تومان)' && ($row[1] ?? null) === 750_000))->toBeTrue()
        ->and($movements[0])->toBe(['نوع', 'مبلغ (تومان)', 'دلیل', 'ثبت توسط', 'زمان'])
        ->and(collect($movements)->contains(fn ($row) => ($row[0] ?? null) === 'برداشت نقدی'
            && ($row[1] ?? null) === 50_000
            && ($row[2] ?? null) === 'واریز به خزانه'
            && ($row[3] ?? null) === $cashier->name))->toBeTrue();

    fclose($temp);
});

test('the shift settlement print export renders a self-contained rtl page', function () {
    [$branch, $cashier, $service] = settlementLab();
    $admin = User::factory()->admin()->forBranch($branch)->create();

    $shift = $service->open($cashier, $branch->id, 200_000);
    settlementPaidOrder($branch, $cashier, 120_000);
    $service->close($shift, $cashier, 320_000);

    $response = $this->actingAs($admin)
        ->get(route('admin.shift-settlement.export', ['shift' => $shift->id, 'format' => 'print']))
        ->assertOk()
        ->assertHeader('content-type', 'text/html; charset=UTF-8');

    $html = $response->streamedContent();

    expect($html)->toContain('تسویهٔ شیفت شمارهٔ '.$shift->id)
        ->toContain($cashier->name)
        ->toContain('320,000')
        ->toContain('صفر ✓')
        ->toContain('window.print()')
        ->toContain('dir="rtl"');
});

test('the settlement export refuses a shift from another branch and non-admins', function () {
    [$branch, $cashier, $service] = settlementLab();

    $shift = $service->open($cashier, $branch->id, 100_000);

    // Guest first (before actingAs lingers), then the wrong role, then a
    // shift belonging to another active branch. The foreign-branch probe
    // uses a branch-scoped admin, so the 403 vs 404 split is deterministic.
    $this->get(route('admin.shift-settlement.export', ['shift' => $shift->id]))
        ->assertRedirect(route('login'));

    $this->actingAs($cashier)
        ->get(route('admin.shift-settlement.export', ['shift' => $shift->id]))
        ->assertForbidden();

    $otherBranch = Branch::factory()->create();
    $otherCashier = User::factory()->cashier()->forBranch($otherBranch)->create();
    $foreignShift = $service->open($otherCashier, $otherBranch->id, 100_000);

    $scopedAdmin = User::factory()->admin()->forBranch($branch)->create();

    $this->actingAs($scopedAdmin)
        ->get(route('admin.shift-settlement.export', ['shift' => $foreignShift->id]))
        ->assertNotFound();
});

/* ── Discrepancy compensation ───────────────────────────────────── */

test('closing with book compensation reconciles the expectation to the count', function () {
    [$branch, $cashier, $service] = settlementLab();

    $shift = $service->open($cashier, $branch->id, 500_000);
    settlementPaidOrder($branch, $cashier, 300_000);

    // Expected 800k, counted 750k → 50k shortage; book it as an adjustment.
    $result = $service->close($shift, $cashier, 750_000, true, 'کسری آبنبات — ثبت دفتری');

    $shift->refresh();

    expect($result['discrepancy'])->toBe(0)
        ->and($shift->discrepancy)->toBe(0)
        ->and($shift->expected_cash)->toBe(750_000)
        ->and($shift->closing_cash)->toBe(750_000)
        ->and($shift->cashMovements()->where('type', 'adjustment')->count())->toBe(1);

    $adjustment = $shift->cashMovements()->where('type', 'adjustment')->first();
    expect($adjustment->amount)->toBe(-50_000)
        ->and($adjustment->reason)->toBe('کسری آبنبات — ثبت دفتری')
        ->and($adjustment->user_id)->toBe($cashier->id);
});

test('a surplus close compensates with a positive adjustment', function () {
    [$branch, $cashier, $service] = settlementLab();

    $shift = $service->open($cashier, $branch->id, 100_000);

    // Expected 100k, counted 120k → 20k surplus.
    $service->close($shift, $cashier, 120_000, true, 'پول اضافهٔ شمارش');

    $shift->refresh();

    expect($shift->discrepancy)->toBe(0)
        ->and($shift->expected_cash)->toBe(120_000);

    $adjustment = $shift->cashMovements()->where('type', 'adjustment')->first();
    expect($adjustment->amount)->toBe(20_000);
});

test('compensation without a reason is refused', function () {
    [$branch, $cashier, $service] = settlementLab();

    $shift = $service->open($cashier, $branch->id, 100_000);

    expect(fn () => $service->close($shift, $cashier, 90_000, true, '   '))
        ->toThrow(InvalidArgumentException::class, 'ثبت دلیل جبران مغایرت الزامی است.');

    // The shift stayed open — nothing was frozen.
    expect($shift->refresh()->isOpen())->toBeTrue();
});

test('closing without compensation keeps the raw discrepancy', function () {
    [$branch, $cashier, $service] = settlementLab();

    $shift = $service->open($cashier, $branch->id, 100_000);

    $result = $service->close($shift, $cashier, 90_000);

    expect($result['discrepancy'])->toBe(-10_000)
        ->and($shift->refresh()->expected_cash)->toBe(100_000)
        ->and($shift->cashMovements()->count())->toBe(0);
});

test('the close http endpoint carries the compensation flag', function () {
    [$branch, $cashier, $service] = settlementLab();

    $shift = $service->open($cashier, $branch->id, 200_000);

    $this->actingAs($cashier)
        ->post(route('cashier.shift.close'), [
            'counted_cash' => 180_000,
            'compensate' => 1,
            'compensation_reason' => 'کسری اعلامی مدیر',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $shift->refresh();

    expect($shift->discrepancy)->toBe(0)
        ->and($shift->cashMovements()->where('type', 'adjustment')->where('amount', -20_000)->exists())->toBeTrue();

    $flash = session('success');
    expect($flash)->toContain('جبران دفتری');
});

test('the close endpoint demands a reason when compensating', function () {
    [$branch, $cashier, $service] = settlementLab();

    $shift = $service->open($cashier, $branch->id, 100_000);

    $this->actingAs($cashier)
        ->from(route('cashier.shift'))
        ->post(route('cashier.shift.close'), [
            'counted_cash' => 90_000,
            'compensate' => 1,
            'compensation_reason' => '',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('compensation_reason');

    expect($shift->refresh()->isOpen())->toBeTrue();
});

/* ── Kitchen presence shifts ────────────────────────────────────── */

test('a kitchen shift opens with a zero float and is marked by station', function () {
    [$branch, $cashier, $service] = settlementLab();
    $cook = User::factory()->kitchen()->forBranch($branch)->create();

    $shift = $service->open($cook, $branch->id, 0, 'kitchen');

    expect($shift->station)->toBe('kitchen')
        ->and($shift->settlesCash())->toBeFalse()
        ->and($shift->opening_cash)->toBe(0)
        ->and($shift->isOpen())->toBeTrue();

    $closed = $service->close($shift, $cook, 0);

    expect($closed['discrepancy'])->toBe(0)
        ->and($shift->refresh()->settlesCash())->toBeFalse();
});

test('a kitchen shift refuses an opening float', function () {
    [$branch] = settlementLab();
    $cook = User::factory()->kitchen()->forBranch($branch)->create();

    expect(fn () => app(ShiftService::class)->open($cook, $branch->id, 50_000, 'kitchen'))
        ->toThrow(InvalidArgumentException::class, 'شیفت آشپزخانه صندوق ندارد؛ موجودی اولیه باید صفر باشد.');
});

test('kitchen shifts refuse cash movements', function () {
    [$branch] = settlementLab();
    $cook = User::factory()->kitchen()->forBranch($branch)->create();

    $shift = app(ShiftService::class)->open($cook, $branch->id, 0, 'kitchen');

    expect(fn () => app(ShiftService::class)->registerMovement($shift, $cook, 'withdrawal', 10_000, 'امتحان'))
        ->toThrow(RuntimeException::class, 'شیفت آشپزخانه صندوق ندارد؛ حرکت نقدی فقط در شیفت صندوق ثبت می‌شود.');
});

test('payments refuse to stamp a kitchen shift', function () {
    [$branch] = settlementLab();
    $cook = User::factory()->kitchen()->forBranch($branch)->create();

    app(ShiftService::class)->open($cook, $branch->id, 0, 'kitchen');

    $product = Product::factory()->create(['branch_id' => $branch->id, 'price' => 50_000]);
    $order = app(OrderService::class)->place($branch->id, null, null, [
        ['product_id' => $product->id, 'quantity' => 1],
    ]);

    expect(fn () => app(OrderService::class)->markPaid($order, PaymentMethod::Cash, $cook))
        ->toThrow(RuntimeException::class, 'شیفت باز شما آشپزخانه است؛ پرداخت فقط با شیفت صندوق ثبت می‌شود.');
});

test('the kitchen http endpoints open and close a presence shift', function () {
    [$branch] = settlementLab();
    $cook = User::factory()->kitchen()->forBranch($branch)->create();

    $this->actingAs($cook)
        ->post(route('kitchen.shift.open'))
        ->assertRedirect()
        ->assertSessionHas('success');

    $shift = StaffShift::query()->latest('id')->first();
    expect($shift->station)->toBe('kitchen')
        ->and($shift->opening_cash)->toBe(0)
        ->and($shift->user_id)->toBe($cook->id);

    $this->actingAs($cook)
        ->post(route('kitchen.shift.close'))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($shift->refresh()->isOpen())->toBeFalse();
});

test('the kitchen queue page carries the presence shift payload', function () {
    [$branch] = settlementLab();
    $cook = User::factory()->kitchen()->forBranch($branch)->create();

    $this->actingAs($cook)->get(route('kitchen.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Kitchen/Index')->where('shift', null));

    app(ShiftService::class)->open($cook, $branch->id, 0, 'kitchen');

    $this->actingAs($cook)->get(route('kitchen.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Kitchen/Index')
            ->where('shift.station', 'kitchen'));
});

test('the eod board labels kitchen shifts without settlement numbers', function () {
    [$branch] = settlementLab();
    $service = app(ShiftService::class);
    $admin = User::factory()->admin()->forBranch($branch)->create();
    $cook = User::factory()->kitchen()->forBranch($branch)->create();
    $cashier = User::factory()->cashier()->forBranch($branch)->create();

    $kitchen = $service->open($cook, $branch->id, 0, 'kitchen');
    $service->close($kitchen, $cook, 0);

    $till = $service->open($cashier, $branch->id, 100_000);
    settlementPaidOrder($branch, $cashier, 80_000);
    $service->close($till, $cashier, 180_000);

    $this->actingAs($admin)->get(route('admin.end-of-day'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/EndOfDay')
            ->where('pipeline.closed_shifts', 2)
            // Pipeline is newest-first: the till opened after the kitchen shift.
            ->where('pipeline.shifts.0.is_kitchen', false)
            ->where('pipeline.shifts.0.cash_payments', 80_000)
            ->where('pipeline.shifts.1.is_kitchen', true));
});
