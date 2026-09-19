<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Setting;
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
    }
}
