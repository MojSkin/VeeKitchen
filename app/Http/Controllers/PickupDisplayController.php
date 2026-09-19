<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PickupDisplayController extends Controller
{
    /**
     * The always-on "ready for pickup" screen next to the counter.
     */
    public function show(Request $request, Branch $branch): Response
    {
        $ready = Order::query()
            ->where('branch_id', $branch->id)
            ->where('status', OrderStatus::Ready)
            ->orderBy('ready_at')
            ->get(['id', 'order_number', 'restaurant_table_id', 'ready_at']);

        return Inertia::render('Pickup', [
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'readyOrders' => $ready->map(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'table_id' => $order->restaurant_table_id,
                'ready_at' => $order->ready_at?->toIso8601String(),
            ]),
        ]);
    }
}
