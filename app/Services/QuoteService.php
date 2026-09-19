<?php

namespace App\Services;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Turns raw guest carts into priced, server-verified line items.
 *
 * The client only sends product ids + quantities; every price is read from
 * the database at order time, so a tampered payload can never set a price.
 *
 * @phpstan-type CartLine array{product_id: int, quantity: int, notes?: string|null}
 * @phpstan-type QuotedLine array{product: Product, quantity: int, unit_price: Money, line_total: Money, notes: string|null}
 */
class QuoteService
{
    /**
     * Price a cart and return the quoted lines plus totals.
     *
     * @param  array<int, CartLine>  $cart
     * @return array{lines: Collection<int, QuotedLine>, subtotal: Money}
     */
    public function quote(int $branchId, array $cart): array
    {
        if ($cart === []) {
            throw new InvalidArgumentException('سبد خرید خالی است.');
        }

        $productIds = collect($cart)
            ->map(fn (array $line) => (int) ($line['product_id'] ?? 0))
            ->filter()
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            throw new InvalidArgumentException('هیچ محصول معتبری در سبد نیست.');
        }

        $products = Product::query()
            ->where('branch_id', $branchId)
            ->available()
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $lines = collect($cart)->map(function (array $line) use ($products): array {
            $productId = (int) ($line['product_id'] ?? 0);
            $quantity = (int) ($line['quantity'] ?? 0);

            $product = $products->get($productId);

            if ($product === null) {
                throw new InvalidArgumentException('یکی از محصولات سبد موجود نیست.');
            }

            if ($quantity < 1 || $quantity > 99) {
                throw new InvalidArgumentException('تعداد سفارش باید بین ۱ تا ۹۹ باشد.');
            }

            $unitPrice = Money::of($product->price);

            return [
                'product' => $product,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $unitPrice->multipliedBy($quantity),
                'notes' => $line['notes'] ?? null,
            ];
        });

        $subtotal = Money::zero();
        foreach ($lines as $line) {
            $subtotal = $subtotal->plus($line['line_total']);
        }

        return [
            'lines' => $lines,
            'subtotal' => $subtotal,
        ];
    }
}
