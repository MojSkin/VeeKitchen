<?php

namespace Database\Seeders;

use App\Enums\MeasurementUnit;
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
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the demo restaurant: one branch, staff accounts, a menu,
     * tables, and the TTS/print defaults.
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

        // Warehouse materials with realistic stock levels; the second entry
        // sits right on its alert threshold so the inventory board shows a
        // live low-stock vial out of the box.
        $materials = [
            ['آرد گندم', MeasurementUnit::Kilogram, 80.0, 20.0],
            ['پنیر موزارلا', MeasurementUnit::Kilogram, 4.0, 4.0],
            ['گوشت چرخ‌کرده', MeasurementUnit::Kilogram, 25.0, 10.0],
            ['فیله مرغ', MeasurementUnit::Kilogram, 18.0, 8.0],
            ['سس گوجه', MeasurementUnit::Liter, 12.0, 3.0],
            ['قارچ', MeasurementUnit::Gram, 6000.0, 2000.0],
            ['نان بریوش', MeasurementUnit::Piece, 40.0, 10.0],
            ['قوطی نوشابه', MeasurementUnit::Piece, 120.0, 24.0],
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
    }
}
