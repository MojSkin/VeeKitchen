<?php

use App\Enums\CashMovementType;
use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Product;
use App\Models\StaffShift;
use App\Models\User;
use App\Services\OrderService;
use App\Services\ShiftService;

function shiftLab(): array
{
    $branch = Branch::factory()->create();
    $cashier = User::factory()->cashier()->forBranch($branch)->create();
    $service = app(ShiftService::class);

    return [$branch, $cashier, $service];
}

function shiftPaidOrder(Branch $branch, int $total = 300_000): Order
{
    $product = Product::factory()->create(['branch_id' => $branch->id, 'price' => $total]);
    $order = app(OrderService::class)->place($branch->id, null, null, [
        ['product_id' => $product->id, 'quantity' => 1],
    ]);

    return $order;
}

test('a payment without an open shift is refused with a Persian message', function () {
    [$branch, $cashier, $service] = shiftLab();

    $order = shiftPaidOrder($branch);

    $service->open($cashier, $branch->id, 100_000);
    $service->close(StaffShift::query()->latest('id')->first(), $cashier, 100_000);

    $order2 = shiftPaidOrder($branch);

    expect(fn () => app(OrderService::class)->markPaid($order2, PaymentMethod::Cash, $cashier))
        ->toThrow(RuntimeException::class, 'برای دریافت پرداخت، ابتدا شیفت خود را باز کنید.');
});

test('a payment inside an open shift is stamped with the shift id', function () {
    [$branch, $cashier] = shiftLab();

    $shift = app(ShiftService::class)->open($cashier, $branch->id, 100_000);

    $order = shiftPaidOrder($branch);

    app(OrderService::class)->markPaid($order, PaymentMethod::Cash, $cashier);

    $payment = $order->refresh()->payments->first();

    expect($payment->shift_id)->toBe($shift->id)
        ->and($payment->received_by)->toBe($cashier->id);
});

test('opening twice for the same branch and user is refused', function () {
    [$branch, $cashier, $service] = shiftLab();

    $service->open($cashier, $branch->id, 100_000);

    expect(fn () => $service->open($cashier, $branch->id, 200_000))
        ->toThrow(RuntimeException::class, 'شما یک شیفت باز دارید؛ ابتدا آن را ببندید.');
});

test('the same user may open shifts in sequence after closing', function () {
    [$branch, $cashier, $service] = shiftLab();

    $first = $service->open($cashier, $branch->id, 100_000);
    $service->close($first, $cashier, 100_000);

    $second = $service->open($cashier, $branch->id, 50_000);

    expect($second->id)->not->toBe($first->id)
        ->and($second->isOpen())->toBeTrue();
});

test('expected cash follows opening + cash payments - withdrawals + deposits', function () {
    [$branch, $cashier, $service] = shiftLab();

    $shift = $service->open($cashier, $branch->id, 500_000);

    $order = shiftPaidOrder($branch, 300_000);
    app(OrderService::class)->markPaid($order, PaymentMethod::Cash, $cashier);

    $cardOrder = shiftPaidOrder($branch, 200_000);
    app(OrderService::class)->markPaid($cardOrder, PaymentMethod::Card, $cashier);

    $service->registerMovement($shift, $cashier, 'withdrawal', 100_000, 'واریز به خزانه');
    $service->registerMovement($shift, $cashier, 'deposit', 50_000, 'شارژ صندوق');

    $shift->refresh();

    // 500k opening + 300k cash - 100k withdrawal + 50k deposit = 750k.
    // Card money never enters the drawer.
    expect($shift->computeExpectedCash())->toBe(750_000);
});

test('a withdrawal beyond the drawer is refused', function () {
    [$branch, $cashier, $service] = shiftLab();

    $shift = $service->open($cashier, $branch->id, 100_000);

    expect(fn () => $service->registerMovement($shift, $cashier, 'withdrawal', 200_000, 'خیلی زیاد'))
        ->toThrow(RuntimeException::class, 'برداشت بیشتر از موجودی فعلی صندوق ممکن نیست.');
});

test('a movement on a closed shift is refused', function () {
    [$branch, $cashier, $service] = shiftLab();

    $shift = $service->open($cashier, $branch->id, 100_000);
    $service->close($shift, $cashier, 100_000);

    expect(fn () => $service->registerMovement($shift, $cashier, 'withdrawal', 10_000, 'دیر رسید'))
        ->toThrow(RuntimeException::class, 'شیفت بسته است؛ حرکت نقدی ثبت نمی‌شود.');
});

test('closing freezes expected, counted and discrepancy', function () {
    [$branch, $cashier, $service] = shiftLab();

    $shift = $service->open($cashier, $branch->id, 500_000);

    $order = shiftPaidOrder($branch, 300_000);
    app(OrderService::class)->markPaid($order, PaymentMethod::Cash, $cashier);

    $service->registerMovement($shift, $cashier, 'withdrawal', 50_000, 'واریز به خزانه');

    // Expected: 500k + 300k - 50k = 750k; counted 720k → −30k shortage.
    $result = $service->close($shift, $cashier, 720_000);

    $shift->refresh();

    expect($result['expected'])->toBe(750_000)
        ->and($result['counted'])->toBe(720_000)
        ->and($result['discrepancy'])->toBe(-30_000)
        ->and($shift->expected_cash)->toBe(750_000)
        ->and($shift->closing_cash)->toBe(720_000)
        ->and($shift->discrepancy)->toBe(-30_000)
        ->and($shift->closed_by)->toBe($cashier->id)
        ->and($shift->closed_at)->not->toBeNull();
});

test('closing twice is refused', function () {
    [$branch, $cashier, $service] = shiftLab();

    $shift = $service->open($cashier, $branch->id, 100_000);
    $service->close($shift, $cashier, 100_000);

    expect(fn () => $service->close($shift, $cashier, 100_000))
        ->toThrow(RuntimeException::class, 'این شیفت قبلاً بسته شده است.');
});

test('the cashier shift panel opens and closes a shift through http', function () {
    [$branch, $cashier] = shiftLab();

    $this->actingAs($cashier)
        ->post(route('cashier.shift.open'), ['opening_cash' => 400_000])
        ->assertRedirect()
        ->assertSessionHas('success');

    $shift = StaffShift::query()->latest('id')->first();
    expect($shift->opening_cash)->toBe(400_000)
        ->and($shift->branch_id)->toBe($branch->id);

    $this->actingAs($cashier)
        ->post(route('cashier.shift.movement'), [
            'type' => 'withdrawal',
            'amount' => 150_000,
            'reason' => 'واریز به خزانه',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($shift->cashMovements)->toHaveCount(1);

    $this->actingAs($cashier)
        ->post(route('cashier.shift.close'), ['counted_cash' => 250_000])
        ->assertRedirect()
        ->assertSessionHas('success');

    $shift->refresh();

    expect($shift->discrepancy)->toBe(0)
        ->and($shift->closing_cash)->toBe(250_000);
});

test('the shift panel page shows the live till and the closed history', function () {
    [$branch, $cashier, $service] = shiftLab();

    $this->actingAs($cashier)->get(route('cashier.shift'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Cashier/Shift')->where('shift', null));

    $shift = $service->open($cashier, $branch->id, 300_000);
    $order = shiftPaidOrder($branch, 120_000);
    app(OrderService::class)->markPaid($order, PaymentMethod::Cash, $cashier);

    $this->actingAs($cashier)->get(route('cashier.shift'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Cashier/Shift')
            ->where('shift.opening_cash', 300_000)
            ->where('shift.cash_payments', 120_000)
            ->where('shift.expected_cash', 420_000));

    $service->close($shift, $cashier, 420_000);

    $this->actingAs($cashier)->get(route('cashier.shift'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Cashier/Shift')
            ->where('shift', null)
            ->has('history', 1));
});

test('the cashier screen carries the shift strip payload', function () {
    [$branch, $cashier, $service] = shiftLab();

    $shift = $service->open($cashier, $branch->id, 200_000);
    $order = shiftPaidOrder($branch, 90_000);
    app(OrderService::class)->markPaid($order, PaymentMethod::Cash, $cashier);

    $this->actingAs($cashier)->get(route('cashier.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Cashier/Index')
            ->where('shift.expected_cash', 290_000)
            ->where('shift.cash_payments', 90_000));
});

test('movements record their author and require a reason', function () {
    [$branch, $cashier, $service] = shiftLab();

    $shift = $service->open($cashier, $branch->id, 100_000);

    $movement = $service->registerMovement($shift, $cashier, 'deposit', 25_000, 'شارژ اول صبح');

    expect($movement->user_id)->toBe($cashier->id)
        ->and($movement->type)->toBe(CashMovementType::Deposit);

    expect(fn () => $service->registerMovement($shift, $cashier, 'deposit', 10_000, '  '))
        ->toThrow(InvalidArgumentException::class);
});
