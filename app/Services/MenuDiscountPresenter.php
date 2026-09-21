<?php

namespace App\Services;

use App\Enums\DiscountScope;
use App\Enums\DiscountType;
use App\Models\Discount;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The menu-facing face of a branch's discounts.
 *
 * Guests never see usage ceilings or raw windows — they see what the
 * discount buys them and until when. Product lines carry their best badge
 * so the menu can decorate the cards without computing anything.
 */
class MenuDiscountPresenter
{
    /**
     * The banner chips: active *entire-order* discounts. Category and
     * product discounts speak through their cards' badges instead, so the
     * banner never turns into a product catalogue.
     *
     * @return array<int, array<string, mixed>>
     */
    public function activeForMenu(int $branchId): array
    {
        return Discount::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereNull('code')
            ->where('applies_to', DiscountScope::EntireOrder->value)
            ->orderByDesc('value')
            ->get()
            ->filter(fn (Discount $discount) => $discount->isActiveAt())
            ->filter(fn (Discount $discount) => $discount->hasTotalHeadroom())
            ->values()
            ->map(fn (Discount $discount) => $this->presentForMenu($discount))
            ->all();
    }

    /**
     * The best automatic discount badge label for each menu product, e.g.
     * «۲۰٪ تخفیف». The server renders the label so percentage vs fixed can
     * never be confused client-side.
     *
     * @param  array<int, int>  $productIds
     * @return array<int, string>
     */
    public function bestBadgesByProduct(int $branchId, array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $entireOrder = $this->activeAutomatic($branchId)
            ->filter(fn (Discount $discount) => $discount->applies_to === DiscountScope::EntireOrder);

        $categoryTargets = $this->activeAutomatic($branchId)
            ->filter(fn (Discount $discount) => $discount->applies_to === DiscountScope::Category)
            ->keyBy('menu_category_id');

        $productTargets = $this->activeAutomatic($branchId)
            ->filter(fn (Discount $discount) => $discount->applies_to === DiscountScope::Product)
            ->keyBy('product_id');

        $categories = Product::query()
            ->whereIn('id', $productIds)
            ->pluck('menu_category_id', 'id');

        $productBadges = [];

        foreach ($productIds as $productId) {
            $candidates = $entireOrder->values();

            $menuCategoryId = $categories[$productId] ?? null;

            if ($menuCategoryId !== null && $categoryTargets->has($menuCategoryId)) {
                $candidates->push($categoryTargets->get($menuCategoryId));
            }

            if ($productTargets->has($productId)) {
                $candidates->push($productTargets->get($productId));
            }

            $best = $candidates
                ->map(fn (Discount $discount) => $this->presentForMenu($discount))
                ->sortByDesc(fn (array $badge) => $this->weight($badge))
                ->first();

            if ($best !== null) {
                $productBadges[$productId] = $best['label'];
            }
        }

        return $productBadges;
    }

    /**
     * Active, code-less discounts with a live window and headroom.
     *
     * @return Collection<int, Discount>
     */
    protected function activeAutomatic(int $branchId): Collection
    {
        return Discount::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereNull('code')
            ->get()
            ->filter(fn (Discount $discount) => $discount->isActiveAt())
            ->filter(fn (Discount $discount) => $discount->hasTotalHeadroom())
            ->values();
    }

    /**
     * A comparable weight for cross-type badge comparison (Toman saved on a
     * nominal 10_000 base keeps percentages and fixed amounts commensurable).
     */
    protected function weight(array $badge): int
    {
        return DiscountType::from($badge['type'])->amountFor(10_000, $badge['value']);
    }

    /**
     * The menu shape of one discount.
     *
     * @return array<string, mixed>
     */
    public function presentForMenu(Discount $discount): array
    {
        return [
            'id' => $discount->id,
            'name' => $discount->name,
            'type' => $discount->type->value,
            'value' => $discount->value,
            'label' => $this->label($discount),
            'scope' => $discount->applies_to->value,
            'scope_label' => $discount->applies_to->label(),
            'target_name' => $this->targetName($discount),
            'min_order_total' => $discount->min_order_total,
            'expires_at' => $discount->expires_at?->toIso8601String(),
        ];
    }

    /**
     * The chip text a guest sees, e.g. «۲۰٪ تخفیف» or «۵۰,۰۰۰ تومان تخفیف».
     */
    protected function label(Discount $discount): string
    {
        return match ($discount->type) {
            DiscountType::Percentage => $this->faDigits((string) $discount->value).'٪ تخفیف',
            DiscountType::Fixed => $this->faDigits(number_format($discount->value)).' تومان تخفیف',
        };
    }

    /**
     * Latin digits → Persian digits, matching the frontend's faDigits.
     */
    protected function faDigits(string $value): string
    {
        return strtr($value, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        ]);
    }

    /**
     * Human name of the scope target, or null for entire orders.
     */
    protected function targetName(Discount $discount): ?string
    {
        return match ($discount->applies_to) {
            DiscountScope::EntireOrder => null,
            DiscountScope::Category => $discount->category?->name,
            DiscountScope::Product => $discount->product?->name,
        };
    }

    /**
     * ISO expiry for the menu banner's «تا …» chip, or null.
     */
    public function expiryChip(?Discount $discount): ?string
    {
        if ($discount?->expires_at === null) {
            return null;
        }

        /** @var Carbon $expiresAt */
        $expiresAt = $discount->expires_at;

        return 'تا '.$expiresAt->format('Y/m/d');
    }
}
