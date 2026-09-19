<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;

test('the happy path walks queued → preparing → ready → delivered', function () {
    $order = Order::factory()->paid()->create();
    $service = app(OrderService::class);

    $service->transition($order, OrderStatus::Preparing);
    $service->transition($order->refresh(), OrderStatus::Ready);
    $service->transition($order->refresh(), OrderStatus::Delivered);

    expect($order->refresh()->status)->toBe(OrderStatus::Delivered)
        ->and($order->delivered_at)->not->toBeNull();
});

test('illegal jumps are rejected (awaiting_payment → ready)', function () {
    $order = Order::factory()->create();

    app(OrderService::class)->transition($order, OrderStatus::Ready);
})->throws(RuntimeException::class);

test('cancelling requires a reason and records who did it', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->paid()->create();

    app(OrderService::class)->transition($order, OrderStatus::Cancelled, $admin, 'مشتری منصرف شد');

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->cancel_reason)->toBe('مشتری منصرف شد')
        ->and($order->cancelled_by)->toBe($admin->id);
});

test('cancelling without a reason is rejected', function () {
    $order = Order::factory()->paid()->create();

    app(OrderService::class)->transition($order, OrderStatus::Cancelled, null, null);
})->throws(InvalidArgumentException::class);

test('a kitchen user cannot reach the cashier screen', function () {
    $kitchen = User::factory()->kitchen()->create();

    $this->actingAs($kitchen)
        ->get(route('cashier.index'))
        ->assertForbidden();
});

test('a guest cannot reach the kitchen screen', function () {
    $this->get(route('kitchen.index'))->assertRedirect(route('login'));
});
