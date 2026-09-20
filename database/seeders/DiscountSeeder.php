<?php

namespace Database\Seeders;

use App\Enums\DiscountScope;
use App\Enums\DiscountType;
use App\Models\Branch;
use App\Models\Discount;
use App\Models\MenuCategory;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Demo discounts for the seeded branch: an automatic entire-order
 * festival, a coupon, and scoped category/product discounts — so the
 * admin board and the guest menu both open alive.
 */
class DiscountSeeder extends Seeder
{
    /**
     * Seed three representative discounts.
     */
    public function run(): void
    {
        $branch = Branch::query()->orderBy('id')->first();

        if ($branch === null) {
            return;
        }

        // Automatic 15% on the entire order — the banner chip.
        Discount::factory()->percent(15)->create([
            'branch_id' => $branch->id,
            'name' => 'جشنوارهٔ هفته',
            'code' => null,
            'applies_to' => DiscountScope::EntireOrder,
            'menu_category_id' => null,
            'product_id' => null,
            'min_order_total' => 200_000,
        ]);

        // Coupon FIXED 50k on drinks — shows the coupon chip on the board.
        $drinks = MenuCategory::query()
            ->where('branch_id', $branch->id)
            ->where('name', 'نوشیدنی')
            ->first();

        if ($drinks !== null) {
            Discount::factory()->fixed(50_000)->withCode('WELCOME')->create([
                'branch_id' => $branch->id,
                'name' => 'خوش‌آمدگویی',
                'applies_to' => DiscountScope::Category,
                'menu_category_id' => $drinks->id,
                'type' => DiscountType::Fixed,
                'value' => 50_000,
            ]);
        }

        // Automatic 20% on the margherita — the per-product badge.
        $margherita = Product::query()
            ->where('branch_id', $branch->id)
            ->where('name', 'پیتزا مارگاریتا')
            ->first();

        if ($margherita !== null) {
            Discount::factory()->percent(20)->create([
                'branch_id' => $branch->id,
                'name' => 'پیشنهاد ویژهٔ مارگاریتا',
                'code' => null,
                'applies_to' => DiscountScope::Product,
                'product_id' => $margherita->id,
                'expires_at' => now()->addWeek()->endOfDay(),
            ]);
        }
    }
}
