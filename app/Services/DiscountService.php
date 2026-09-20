<?php

namespace App\Services;

use App\Enums\DiscountScope;
use App\Models\Discount;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\DiscountLimitReached;
use App\Support\Money;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;

/**
 * Chooses and applies branch discounts.
 *
 * The business rule: a cart always gets the BEST eligible discount. An
 * automatic one (no code) wins by itself; a coupon code only participates
 * if it is not worse than the best automatic offer — a worse code is
 * rejected outright with a Persian message so the cashier can drop it.
 *
 * @phpstan-type AppliedDiscount array{discount: Discount, amount: Money}
 */
class DiscountService
{
    /**
     * The best discount for a priced cart, optionally nudged by a coupon code.
     *
     * @param  Collection<int, array{product: Product, quantity: int, line_total: Money}>  $lines
     * @return array{discount: Discount|null, amount: Money}
     *
     * @throws InvalidArgumentException when the code does not exist or is inactive/expired
     */
    public function bestFor(int $branchId, Collection $lines, Money $subtotal, ?string $code = null, ?int $userId = null): array
    {
        $now = now();

        $discounts = Discount::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', $now))
            ->get();

        if ($code !== null) {
            $trimmed = mb_strtoupper(trim($code));

            $coupon = $discounts->firstWhere('code', $trimmed);

            if ($coupon === null) {
                throw new InvalidArgumentException('کد تخفیف یافت نشد یا فعال نیست.');
            }
        }

        $eligible = $discounts
            ->filter(fn (Discount $discount) => $discount->meetsMinOrder($subtotal->toman))
            ->filter(fn (Discount $discount) => $discount->hasTotalHeadroom())
            ->filter(fn (Discount $discount) => $discount->hasHeadroomFor($userId))
            ->mapWithKeys(fn (Discount $discount) => [
                $discount->id => $this->amountFor($discount, $lines, $subtotal),
            ])
            ->filter(fn (int $amount) => $amount > 0);

        $bestAmount = $eligible->max();
        $bestId = $bestAmount !== null
            ? $eligible->filter(fn (int $amount) => $amount === $bestAmount)->keys()->first()
            : null;
        $best = $bestId !== null ? $discounts->find($bestId) : null;

        // A coupon that loses to the best automatic offer is rejected, not
        // silently downgraded — the guest must know the code does nothing.
        if ($code !== null) {
            $couponAmount = $coupon->id !== $bestId ? ($eligible[$coupon->id] ?? 0) : $bestAmount;

            if ($couponAmount === null || $couponAmount < (int) $bestAmount) {
                throw new RuntimeException('این کد تخفیف کمتر از تخفیف خودکار فعلی است و اعمال نشد.');
            }
        }

        return [
            'discount' => $best,
            'amount' => $best === null ? Money::zero() : Money::of($bestAmount),
        ];
    }

    /**
     * Recompute the discount amount of a placed order (used at payment time
     * so the numbers on the receipt are the numbers charged).
     */
    public function amountForOrder(Order $order): Money
    {
        $discount = $order->discount;

        if ($discount === null) {
            return Money::zero();
        }

        $lines = $order->items->map(fn ($item) => [
            'product' => $item->product,
            'quantity' => (int) $item->quantity,
            'line_total' => Money::of((int) $item->line_total),
        ]);

        return Money::of($this->amountFor($discount, $lines, Money::of((int) $order->subtotal)));
    }

    /**
     * Atomically consume one usage of a discount (called once per placed order).
     */
    public function recordUsage(Discount $discount, ?int $userId): void
    {
        $limit = $discount->usage_limit_total;

        $discount->forceFill([
            'used_count' => $limit !== null
                ? min($discount->used_count + 1, $limit)
                : $discount->used_count + 1,
        ])->save();

        if ($limit !== null && $discount->used_count >= $limit) {
            $this->notifyLimitReached($discount);
        }
    }

    /**
     * Warn branch admins once the global usage ceiling is reached.
     */
    protected function notifyLimitReached(Discount $discount): void
    {
        $discount->branch->staff
            ->where('role', 'admin')
            ->each(fn (User $admin) => $admin->notify(new DiscountLimitReached($discount)));
    }

    /**
     * The amount a discount takes off the given cart.
     *
     * Eligibility for scoped discounts is line-based: the discount only
     * applies to the lines its scope covers, and never exceeds what those
     * lines are worth.
     *
     * @param  iterable<int, array{product: Product, quantity: int, line_total: Money}>  $lines
     */
    protected function amountFor(Discount $discount, iterable $lines, Money $subtotal): int
    {
        $base = match ($discount->applies_to) {
            DiscountScope::EntireOrder => $subtotal->toman,
            DiscountScope::Category, DiscountScope::Product => collect($lines)
                ->filter(fn (array $line) => $discount->applies_to->canApplyTo($discount, $line['product']))
                ->sum(fn (array $line) => $line['line_total']->toman),
        };

        if ($base <= 0) {
            return 0;
        }

        return min(
            $discount->type->amountFor($base, $discount->value),
            $base,
        );
    }
}
