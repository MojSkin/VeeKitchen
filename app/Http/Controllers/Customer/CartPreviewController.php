<?php

namespace App\Http\Controllers\Customer;

use App\Enums\DiscountScope;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Product;
use App\Services\DiscountService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * The guest cart's live quote: the same pricing pipeline the order will
 * follow (line pricing → DiscountService), exposed read-only so the menu
 * can show what the coupon actually buys before committing.
 *
 * Plain JSON by design — the menu polls it with fetch, not through the
 * Inertia router (a prop-only POST has no page component to resolve).
 */
class CartPreviewController extends Controller
{
    public function __construct(
        protected DiscountService $discounts,
    ) {}

    /**
     * Preview totals for the posted cart + optional code.
     */
    public function quote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:50'],
            // No exists rule — unknown ids are dropped below, mirroring how
            // a forged placement would be filtered, not rejected.
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'discount_code' => ['nullable', 'string', 'max:32'],
        ]);

        $branchId = $this->firstBranchId();

        $products = Product::query()
            ->where('branch_id', $branchId)
            ->whereIn('id', collect($validated['items'])->pluck('product_id'))
            ->get()
            ->keyBy('id');

        // Unknown or cross-branch products are dropped, exactly like a
        // forged POST would be at placement time.
        $lines = collect($validated['items'])
            ->filter(fn (array $line) => $products->has($line['product_id']))
            ->map(fn (array $line) => [
                'product' => $products->get($line['product_id']),
                'quantity' => (int) $line['quantity'],
                'line_total' => Money::of($products->get($line['product_id'])->price * (int) $line['quantity']),
            ]);

        if ($lines->isEmpty()) {
            return response()->json([
                'valid' => false,
                'subtotal' => 0,
                'discount_total' => 0,
                'total' => 0,
                'message' => 'سبد خرید معتبری برای پیش‌نمایش نیست.',
            ]);
        }

        $subtotal = $lines->sum(fn (array $line) => $line['line_total']->toman);

        $code = $validated['discount_code'] ?? null;

        try {
            $applied = $this->discounts->bestFor(
                $branchId,
                $lines,
                Money::of($subtotal),
                $code,
                $request->user()?->id,
            );
        } catch (Throwable $e) {
            // Unknown/inactive code or a code losing to the automatic
            // offer — the guest sees why, the cart still prices cleanly.
            return response()->json([
                'valid' => false,
                'subtotal' => $subtotal,
                'discount_total' => 0,
                'total' => $subtotal,
                'message' => $e->getMessage(),
            ]);
        }

        $total = Money::of($subtotal)->minus($applied['amount'])->roundUp();

        $discount = $applied['discount'];

        return response()->json([
            'valid' => true,
            'subtotal' => $subtotal,
            'discount_total' => $applied['amount']->toman,
            'total' => $total->toman,
            'message' => $discount === null ? null : $this->message($discount, $code),
        ]);
    }

    /**
     * The human chip under the totals: which discount won and why.
     */
    protected function message($discount, ?string $code): string
    {
        $scope = match ($discount->applies_to) {
            DiscountScope::EntireOrder => 'کل سفارش',
            default => $discount->applies_to->label(),
        };

        if ($code !== null && $discount->code !== null) {
            return "کد «{$discount->code}» اعمال شد.";
        }

        return "بهترین تخفیف فعال ({$discount->name}) اعمال شد.";
    }

    /**
     * The public menu falls back to the first active branch.
     */
    protected function firstBranchId(): int
    {
        $branch = Branch::query()->where('is_active', true)->orderBy('id')->first();

        abort_if($branch === null, 503, 'هیچ شعبه فعالی ثبت نشده است.');

        return $branch->id;
    }
}
