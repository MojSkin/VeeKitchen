<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Branch;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orders,
    ) {}

    /**
     * Place an order as guest (or as the signed-in customer).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'qr_token' => ['nullable', 'string', 'max:64'],
            'guest_name' => ['required', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.notes' => ['nullable', 'string', 'max:200'],
            'discount_code' => ['nullable', 'string', 'max:32'],
        ]);

        $table = isset($validated['qr_token'])
            ? RestaurantTable::query()->where('qr_token', $validated['qr_token'])->first()
            : null;

        try {
            $order = $this->orders->place(
                branchId: $table->branch_id ?? $this->firstBranchId(),
                table: $table,
                customer: $request->user(),
                cart: $validated['items'],
                guestName: $validated['guest_name'],
                notes: $validated['notes'] ?? null,
                discountCode: $validated['discount_code'] ?? null,
            );
        } catch (RuntimeException $e) {
            // A coupon that loses to the automatic offer (or any domain
            // refusal) surfaces as a field error, not a 500.
            return back()->withErrors(['discount_code' => $e->getMessage()])->withInput();
        }

        // Guests follow the order via an unguessable token in the URL.
        $trackUrl = route('orders.track', [$order, $order->guest_token]);

        return back()->with([
            'success' => 'سفارش شما ثبت شد؛ لطفاً برای پرداخت به صندوق مراجعه کنید.',
            'order' => [
                'id' => $order->id,
                'track_url' => $trackUrl,
                'total' => $order->total,
            ],
        ]);
    }

    /**
     * Live tracking page — guest token in the URL authorizes the visitor.
     */
    public function track(Order $order, string $guestToken): Response
    {
        abort_unless($order->isOwnedByGuest($guestToken), 403, 'لینک پیگیری معتبر نیست.');

        $order->load(['items', 'table']);

        return Inertia::render('Customer/Order', [
            'order' => new OrderResource($order),
            'guestToken' => $guestToken,
        ]);
    }

    /**
     * Fallback branch for orders without a table.
     */
    protected function firstBranchId(): int
    {
        $branch = Branch::query()->where('is_active', true)->first();

        abort_if($branch === null, 503, 'هیچ شعبه فعالی ثبت نشده است.');

        return $branch->id;
    }
}
