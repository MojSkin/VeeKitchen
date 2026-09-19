<?php

use App\Models\Branch;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\Broadcaster;

/**
 * Resolve the registered handler for a concrete channel name such as
 * "private-branch.5.kitchen" or "order.3".
 */
function channelHandler(string $channel): callable
{
    // Under tests, phpunit.xml sets BROADCAST_CONNECTION=null; the routes/channels.php
    // handlers live on the Broadcaster singleton that the auth controller resolves.
    $driver = app(Broadcaster::class);

    $channels = Closure::bind(fn () => $this->channels, $driver, $driver)();

    $segments = explode('.', preg_replace('/^(private-encrypted-|private-|presence-)/', '', $channel));

    foreach ($channels as $pattern => $handler) {
        $patternSegments = explode('.', $pattern);

        if (count($patternSegments) !== count($segments)) {
            continue;
        }

        $matches = true;

        foreach ($patternSegments as $index => $patternSegment) {
            // A "{placeholder}" segment matches anything; literal ones must be equal.
            if (! str_starts_with($patternSegment, '{') && $patternSegment !== $segments[$index]) {
                $matches = false;
                break;
            }
        }

        if ($matches) {
            return $handler;
        }
    }

    throw new InvalidArgumentException("No channel pattern matches [{$channel}].");
}

/**
 * Invoke the handler with the concrete ids pulled out of the channel name.
 */
function authorizeChannel(string $channel, ?User $user, array $data = []): bool
{
    preg_match_all('/\.(\d+)\b/', $channel, $matches);

    $args = [$user, ...array_map(intval(...), $matches[1])];

    if (str_starts_with($channel, 'order.')) {
        $args[] = $data;
    }

    return (bool) channelHandler($channel)(...$args);
}

test('kitchen staff may join their own branch kitchen channel', function () {
    $branch = Branch::factory()->create();
    $kitchen = User::factory()->kitchen()->forBranch($branch)->create();

    expect(authorizeChannel("private-branch.{$branch->id}.kitchen", $kitchen))->toBeTrue();
});

test('kitchen staff of another branch are rejected', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $kitchen = User::factory()->kitchen()->forBranch($branchB)->create();

    expect(authorizeChannel("private-branch.{$branchA->id}.kitchen", $kitchen))->toBeFalse();
});

test('a cashier may join the cashier channel', function () {
    $branch = Branch::factory()->create();
    $cashier = User::factory()->cashier()->forBranch($branch)->create();

    expect(authorizeChannel("private-branch.{$branch->id}.cashier", $cashier))->toBeTrue();
});

test('a customer cannot join staff channels', function () {
    $branch = Branch::factory()->create();
    $customer = User::factory()->customer()->forBranch($branch)->create();

    expect(authorizeChannel("private-branch.{$branch->id}.kitchen", $customer))->toBeFalse();
});

test('the pickup channel allows everyone', function () {
    $branch = Branch::factory()->create();

    $handler = channelHandler("branch.{$branch->id}.pickup");

    expect($handler(null, $branch->id))->toBeTrue();
});

test('a guest with the right token may track their order', function () {
    $order = Order::factory()->create();

    $handler = channelHandler("order.{$order->id}");

    expect($handler(null, $order->id, ['guest_token' => $order->guest_token]))->toBeTrue()
        ->and($handler(null, $order->id, ['guest_token' => 'wrong-token']))->toBeFalse();
});

test('an admin may join any branch channel', function () {
    $branch = Branch::factory()->create();
    $admin = User::factory()->admin()->create();

    expect(authorizeChannel("private-branch.{$branch->id}.kitchen", $admin))->toBeTrue();
});
