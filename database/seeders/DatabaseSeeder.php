<?php

namespace Database\Seeders;

use App\Enums\MeasurementUnit;
use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\CostComponent;
use App\Models\InventoryItem;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\RestaurantTable;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\UnitCostSnapshot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the demo restaurant: one branch, staff accounts, a menu,
     * tables, the TTS/print defaults, and today's warehouse ledger.
     */
    public function run(): void
    {
        $branch = Branch::factory()->create([
            'name' => 'وی‌کیچن — شعبه مرکزی',
            'slug' => 'main',
            'phone' => '021-88776655',
            'address' => 'تهران، خیابان ولیعصر، پلاک ۱۲',
        ]);

        // Staff — every account uses `password`.
        User::factory()->admin()->create([
            'branch_id' => $branch->id,
            'name' => 'مدیر سیستم',
            'email' => 'admin@veekitchen.local',
        ]);

        User::factory()->cashier()->create([
            'branch_id' => $branch->id,
            'name' => 'صندوق‌دار شعبه مرکزی',
            'email' => 'cashier@veekitchen.local',
        ]);

        User::factory()->kitchen()->create([
            'branch_id' => $branch->id,
            'name' => 'آشپزخانه شعبه مرکزی',
            'email' => 'kitchen@veekitchen.local',
        ]);

        User::factory()->customer()->create([
            'name' => 'مشتری نمونه',
            'email' => 'customer@veekitchen.local',
        ]);

        // Menu — categories with products at fixed positions.
        $menu = [
            'پیتزا' => [
                ['پیتزا مارگاریتا', 320_000, 'خمیر ایتالیایی، پنیر موزارلا، سس گوجه خانگی'],
                ['پیتزا پپرونی', 450_000, 'پپرونی تند، پنیر چدار، زیتون'],
                ['پیتزا مخلوط', 620_000, 'قارچ، فلفل دلمه، سوسیس، پنیر دوبل'],
            ],
            'برگر' => [
                ['چیزبرگر', 380_000, 'برگه گوشت ۱۵۰ گرمی، پنیر چدار، نان بریوش'],
                ['دوبل برگر', 550_000, 'دو لایه گوشت، بیکن، سس مخصوص'],
                ['برگر مرغ', 340_000, 'فیله مرغ سوخاری، کاهو، سس سیر'],
            ],
            'کباب' => [
                ['کباب کوبیده', 480_000, 'یک سیخ کوبیده، برنج ایرانی، گوجه کبابی'],
                ['جوجه کباب', 420_000, 'یک سیخ جوجه زعفرانی، لیمو، سبزی'],
            ],
            'پیش‌غذا' => [
                ['سیب‌زمینی سرخ‌کرده', 180_000, 'متوسط، نمک و پودر سیر'],
                ['سالاد فصل', 220_000, 'سبزی تازه، سرو با سس لیمویی'],
            ],
            'نوشیدنی' => [
                ['نوشابه', 90_000, 'قوطی ۳۳۰ میلی‌لیتری'],
                ['دوغ', 75_000, 'خانگی، نعنا‌دار'],
                ['آب‌میوه طبیعی', 150_000, 'پرتقال تازه فشرده'],
            ],
        ];

        $position = 0;
        foreach ($menu as $categoryName => $products) {
            $category = MenuCategory::factory()->create([
                'branch_id' => $branch->id,
                'name' => $categoryName,
                'position' => $position++,
            ]);

            foreach ($products as $index => [$name, $price, $description]) {
                Product::factory()->create([
                    'branch_id' => $branch->id,
                    'menu_category_id' => $category->id,
                    'name' => $name,
                    'description' => $description,
                    'price' => $price,
                    'position' => $index,
                ]);
            }
        }

        // Tables 1–8, all free with QR tokens ready to print.
        for ($i = 1; $i <= 8; $i++) {
            RestaurantTable::factory()->create([
                'branch_id' => $branch->id,
                'label' => 'میز '.$i,
                'capacity' => $i <= 4 ? 4 : 6,
            ]);
        }

        // Branch defaults (TTS voice choice, printer wiring, order counter).
        Setting::put($branch->id, 'tts_voice', 'auto');
        Setting::put($branch->id, 'printer_connection', 'browser');
        Setting::put($branch->id, 'order_number_prefix', '');

        // Warehouse materials with realistic stock levels. The numbers below
        // already include today's demo ledger (seeded further down), so the
        // inventory board and the warehouse report never contradict each
        // other. Mozzarella lands below its threshold — a live low-stock
        // vial on the board out of the box.
        $materials = [
            ['آرد گندم', MeasurementUnit::Kilogram, 117.6, 20.0],
            ['پنیر موزارلا', MeasurementUnit::Kilogram, 3.1, 4.0],
            ['گوشت چرخ‌کرده', MeasurementUnit::Kilogram, 26.5, 10.0],
            ['فیله مرغ', MeasurementUnit::Kilogram, 18.0, 8.0],
            ['سس گوجه', MeasurementUnit::Liter, 11.9, 3.0],
            ['قارچ', MeasurementUnit::Gram, 5500.0, 2000.0],
            ['نان بریوش', MeasurementUnit::Piece, 40.0, 10.0],
            ['قوطی نوشابه', MeasurementUnit::Piece, 168.0, 24.0],
        ];

        foreach ($materials as [$name, $unit, $stock, $threshold]) {
            InventoryItem::factory()->withoutQr()->create([
                'branch_id' => $branch->id,
                'name' => $name,
                'unit' => $unit,
                'current_stock' => $stock,
                'low_stock_threshold' => $threshold,
            ]);
        }

        // One worked example: the margherita consumes flour, mozzarella, sauce.
        $margherita = Product::query()->where('name', 'پیتزا مارگاریتا')->firstOrFail();
        $recipeMap = [
            'آرد گندم' => 0.4,
            'پنیر موزارلا' => 0.15,
            'سس گوجه' => 0.1,
        ];

        foreach ($recipeMap as $materialName => $perUnit) {
            ProductRecipe::create([
                'product_id' => $margherita->id,
                'inventory_item_id' => InventoryItem::query()->where('name', $materialName)->firstOrFail()->id,
                'quantity_per_unit' => $perUnit,
            ]);
        }

        // Purchase costs for the recipe materials, then a worked cost chain:
        // packaging (cost-bearing) → 30% management profit → 9% tax.
        InventoryItem::query()->where('name', 'آرد گندم')->update(['unit_cost' => 60_000]);
        InventoryItem::query()->where('name', 'پنیر موزارلا')->update(['unit_cost' => 320_000]);
        InventoryItem::query()->where('name', 'سس گوجه')->update(['unit_cost' => 180_000]);

        CostComponent::factory()->forProduct($margherita)->costBearing()->fixed(4_000)
            ->create(['label' => 'بسته‌بندی', 'position' => 1]);
        CostComponent::factory()->forProduct($margherita)->percent(30)
            ->create(['label' => 'سود مدیریت', 'position' => 2]);
        CostComponent::factory()->forProduct($margherita)->percent(9)
            ->create(['label' => 'مالیات بر ارزش افزوده', 'position' => 3]);

        // Two purchase orders so the procurement board is alive on first run:
        // one draft awaiting submission, one already with the supplier.
        $flour = InventoryItem::query()->where('name', 'آرد گندم')->firstOrFail();
        $cheese = InventoryItem::query()->where('name', 'پنیر موزارلا')->firstOrFail();
        $supplier = Supplier::factory()->create(['name' => 'پخش مواد غذایی نور']);

        $draft = PurchaseOrder::factory()->for($branch)->for($supplier)->create([
            'created_by' => User::query()->where('email', 'admin@veekitchen.local')->firstOrFail(),
            'notes' => 'برای پوشش هفتهٔ آینده',
        ]);
        PurchaseOrderItem::factory()->forOrder($draft)->forItem($flour, 30.0, 62_000)->create();
        PurchaseOrderItem::factory()->forOrder($draft)->forItem($cheese, 8.0, 340_000)->create();
        $draft->recalculateTotal();

        $ordered = PurchaseOrder::factory()->ordered()->for($branch)->for($supplier)->create([
            'created_by' => User::query()->where('email', 'admin@veekitchen.local')->firstOrFail(),
        ]);
        PurchaseOrderItem::factory()->forOrder($ordered)->forItem($cheese, 10.0, 330_000)->create();
        $ordered->recalculateTotal();

        // Today's warehouse ledger so the report page is alive on first run:
        // every movement type carries at least one realistic line, valued
        // with the same unit-cost snapshots the real pipeline writes.
        $admin = User::query()->where('email', 'admin@veekitchen.local')->firstOrFail();

        InventoryItem::query()->where('name', 'قوطی نوشابه')->update(['unit_cost' => 35_000]);
        InventoryItem::query()->where('name', 'قارچ')->update(['unit_cost' => 180]);
        InventoryItem::query()->where('name', 'گوشت چرخ‌کرده')->update(['unit_cost' => 850_000]);

        // Stamps spread across the day; a seeder run right after midnight
        // clamps back to 00:00 so every row stays inside "today".
        $today = now()->startOfDay();
        $stampFor = function (int $minutesAgo) use ($today): Carbon {
            $stamp = now()->subMinutes($minutesAgo);

            return $stamp->lt($today) ? $today->copy() : $stamp;
        };

        $ledger = function (
            InventoryItem $item,
            StockMovementType $type,
            float $quantity,
            int $unitCost,
            string $source,
            string $reason,
            int $minutesAgo,
        ) use ($admin, $stampFor): void {
            StockMovement::create([
                'inventory_item_id' => $item->id,
                'type' => $type,
                'quantity' => $quantity,
                'unit_cost_at' => $unitCost,
                'unit_cost_source' => $source,
                'reason' => $reason,
                'user_id' => $admin->id,
            ])->forceFill([
                'created_at' => $stampFor($minutesAgo),
                'updated_at' => $stampFor($minutesAgo),
            ])->save();
        };

        $item = fn (string $name): InventoryItem => InventoryItem::query()->where('name', $name)->firstOrFail();

        // Purchases in — flour at the same 62k the draft order quotes.
        $ledger($item('آرد گندم'), StockMovementType::Purchase, 40.0, 62_000, UnitCostSnapshot::SOURCE_PURCHASE, 'خرید هفتگی — پخش نور', 300);
        $ledger($item('قوطی نوشابه'), StockMovementType::Purchase, 48.0, 35_000, UnitCostSnapshot::SOURCE_PURCHASE, 'شارژ یخچال نوشیدنی', 260);

        // Consumption out — exactly the margherita recipe times six.
        $consumptionReason = 'مصرف فرمول — ۶ پیتزا مارگاریتا';
        $ledger($item('آرد گندم'), StockMovementType::Consumption, -2.4, 60_000, UnitCostSnapshot::SOURCE_CURRENT, $consumptionReason, 180);
        $ledger($item('پنیر موزارلا'), StockMovementType::Consumption, -0.9, 320_000, UnitCostSnapshot::SOURCE_CURRENT, $consumptionReason, 180);
        $ledger($item('سس گوجه'), StockMovementType::Consumption, -0.6, 180_000, UnitCostSnapshot::SOURCE_CURRENT, $consumptionReason, 179);

        $ledger($item('قارچ'), StockMovementType::Waste, -500.0, 180, UnitCostSnapshot::SOURCE_CURRENT, 'ضایعات تاریخ‌گذشته', 120);
        $ledger($item('گوشت چرخ‌کرده'), StockMovementType::Adjustment, 1.5, 850_000, UnitCostSnapshot::SOURCE_CURRENT, 'اصلاح شمارش دوره‌ای', 90);
        $ledger($item('سس گوجه'), StockMovementType::Return, 0.5, 180_000, UnitCostSnapshot::SOURCE_CURRENT, 'بازگشت سفارش لغوشده', 45);

        // Demo discounts: automatic banner, coupon, and a product badge.
        $this->call(DiscountSeeder::class);
    }
}
