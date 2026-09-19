<?php

use App\Enums\UserRole;
use App\Models\Order;
use App\Support\BranchAccess;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/** Kitchen staff of the branch (admins pass everywhere). */
Broadcast::channel('branch.{branchId}.kitchen', function ($user, int $branchId) {
    return in_array($user->role, [UserRole::Kitchen, UserRole::Admin], true)
        && BranchAccess::belongsToBranch($user->role, $user->branch_id, $branchId);
});

/** Cashiers and admins of the branch. */
Broadcast::channel('branch.{branchId}.cashier', function ($user, int $branchId) {
    return in_array($user->role, [UserRole::Cashier, UserRole::Admin], true)
        && BranchAccess::belongsToBranch($user->role, $user->branch_id, $branchId);
});

/** Public pickup display — never sends an auth request for public channels. */
Broadcast::channel('branch.{branchId}.pickup', fn () => true);

/** Public order tracking, gated by the order's unguessable guest token. */
Broadcast::channel('order.{orderId}', function ($user, int $orderId, array $data = []) {
    $order = Order::find($orderId);

    if ($order === null) {
        return false;
    }

    // The registered customer of the order.
    if ($user !== null && $user->id === $order->customer_id) {
        return true;
    }

    // Guests must carry the order's unguessable token.
    $guestToken = (string) ($data['guest_token'] ?? '');

    return $guestToken !== '' && $order->isOwnedByGuest($guestToken);
});

/** Table channel for guests seated at a table. */
Broadcast::channel('table.{tableId}', fn () => true);
