<?php

namespace App\Http\Controllers\Kitchen;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Branch;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KitchenController extends Controller
{
    public function __construct(
        protected OrderService $orders,
    ) {}

    /**
     * The kitchen display: queued and preparing columns.
     */
    public function index(Request $request): Response
    {
        $branchId = $request->user()->branch_id
            ?? Branch::query()->where('is_active', true)->orderBy('id')->value('id');

        $queue = Order::query()
            ->where('branch_id', $branchId)
            ->whereIn('status', [OrderStatus::Queued, OrderStatus::Preparing])
            ->with(['items', 'table'])
            ->orderBy('paid_at')
            ->get();

        $ready = Order::query()
            ->where('branch_id', $branchId)
            ->where('status', OrderStatus::Ready)
            ->with(['items', 'table'])
            ->orderBy('ready_at')
            ->get();

        return Inertia::render('Kitchen/Index', [
            // resolve() unwraps the resource collection — inside Inertia
            // props a collection would otherwise serialize as {data: [...]}.
            'queue' => OrderResource::collection($queue)->resolve(),
            'ready' => OrderResource::collection($ready)->resolve(),
        ]);
    }

    /**
     * Queued → Preparing.
     */
    public function start(Request $request, Order $order): RedirectResponse
    {
        $this->orders->transition($order, OrderStatus::Preparing, $request->user());

        return back();
    }

    /**
     * Preparing → Ready (fires the pickup announcement).
     */
    public function ready(Request $request, Order $order): RedirectResponse
    {
        $this->orders->transition($order, OrderStatus::Ready, $request->user());

        return back();
    }
}
