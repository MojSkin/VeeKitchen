# چک‌لیست کارها — VeeKitchen

> purpose: هر تسکی که انجام می‌شود اینجا ثبت می‌شود تا دوباره‌کاری اتفاق نیفتد.
> Signs: ✅ انجام‌شده · 🚧 در حال انجام · ⏳ باقی‌مانده

## زیرساخت (فاز ۰)

- [x] راه‌اندازی Laravel 13 + Vue 3 + Inertia + Tailwind + Reverb
- [x] مایگریشن‌های هسته (برنچ، کاربر، دسته، محصول، میز، سفارش، قلم سفارش، پرداخت، تنظیمات)
- [x] enumهای وضعیت (OrderStatus، PaymentMethod، TableStatus، UserRole) + کلاس Money با رند ۱۰۰ تومانی
- [x] هم‌ترازی پورت‌ها: Laravel 8020 / Reverb 8080 / Vite 8120
- [x] انتقال سند نیازمندی‌ها به docs/REQUIREMENTS.md
- [x] رفع شکستگی‌های build (ویوی welcome، import زایگی، استاب‌های Vue خالی)
- [x] کپی اسکیل‌ها: persian-writing، ui-ux-pro-max، frontend-design
- [x] ثبت قوانین گیت در .ai/rules
- [x] README فارسی + CHECKLIST + CHANGELOG
- [x] ساخت برنچ‌های development / testing / production و پوش اولیه به origin
- [x] بازبینی README و CHANGELOG با قوانین گیت و اصلاح ناهماهنگی‌ها
- [x] تگ v0.1.0 روی کامیت پایه main

## فاز ۱ — هستهٔ سفارش / پرداخت / KDS / Real-time ✅

> برنامه: docs/PHASE-1-PLAN.md — روی برنچ feature/phase1-order-core اجرا و به development مرج شد.

- [x] گام ۰ — میدلورها (role, auth, guest, Inertia sharing) + لاگین/خروج
- [x] گام ۱ — Factoryها (همه ۸ مدل) + DatabaseSeeder (شعبه، کاربران، منو، میزها)
- [x] گام ۲ — مدل‌ها با روابط/casts + OrderService (place/markPaid/transition) + QuoteService
- [x] گام ۳ — OrderPlaced/OrderPaid/OrderStatusChanged + مجوز کانال‌ها (BranchAccess helper)
- [x] گام ۴ — منوی عمومی/میز، رزرو QR، سبد localStorage، ثبت سفارش با throttle، پیگیری زنده
- [x] گام ۵ — صندوق: صف «منتظر پرداخت»، دریافت وجه، رسید ۸۰mm، تکرار اعلان، آزادسازی میز
- [x] گام ۶ — KDS دوستونه با رنگ فوریت زمانی و اکشن‌های start/ready
- [x] گام ۷ — نمایشگر تحویل عمومی + TTS (Web Speech با fallback فایل صوتی)
- [x] گام ۸ — شبکه میزها + صفحه QR + چرخش توکن
- [x] گام ۹ — ۳۶ تست (Money، ثبت سفارش، جریان پرداخت، ترنزیشن‌ها، مجوز کانال‌ها) — همه سبز
- [x] گام ۱۰ — Build سبز، Pint، سه کامیت مرحله‌ای، مرج به development، پوش

## 🐛 باگ‌های امنیتی رفع‌شده حین تست

- [x] مجوز کانال `order.{id}`: مقایسه `$user?->id === $order->customer_id` با کاربر مهمان (null) همیشه false بود ولی مقایسه null===null در مسیر دیگری true می‌داد — با چک صریح not-null رفع شد.

## ⏳ پیگیری‌های بعدی فاز ۱ (اختیاری/تزئینی)

- [ ] فایل‌های صوتی واقعی `public/audio/tts/*.mp3` (اعداد فارسی) — TTS فعلاً فقط speech mode دارد و fallback منتظر فایل‌هاست
- [ ] اتصال Echo در Cashier برای آپدیت زنده صف «منتظر پرداخت» (الان فقط `.order.placed` → reload)
- [ ] صفحه Dashboard و منوی ناوبری بین پنل‌ها
- [ ] Wake Lock برای نمایشگر تحویل (جلوگیری از خاموشی صفحه)

## انتشارها

- [x] v0.2.0 — ۲۰۲۶-۰۹-۱۹: فاز ۱ + هستهٔ فاز ۲ روی main تگ خورد (جریان کامل: development → testing → تست‌های سبز → main + production)

## فاز ۲ — انبار و فرمول تولید 🚧

> هستهٔ انبار روی برنچ feature/phase2-inventory-core پیاده شد و به development مرج شد.

- [x] مایگریشن‌های هستهٔ انبار: inventory_items (واحد، موجودی، آستانه هشدار، qr_label)، product_recipes (مقدار مصرف به‌ازای هر واحد محصول)، stock_movements (دفتر کلید لاگ‌محور با کلید یکتای payment_id + inventory_item_id)
- [x] مدل‌ها و enumها: InventoryItem، ProductRecipe، StockMovement، MeasurementUnit، StockMovementType + فکتوری و استیت‌های کمکی
- [x] کسر خودکار متریال هنگام markPaid از طریق InventoryService::deductForOrder — idempotent با کلید یکتای دفتر کل؛ کسری موجودی کل تراکنش پرداخت را rollback می‌کند
- [x] بازگشت متریال به انبار هنگام کنسل کردن سفارشِ پرداخت‌شده (returnForCancelledOrder)
- [x] ۹ تست فیچر جدید (کسر، تجمیع چند خط، idempotency، محصول بی‌فرمول، کسری موجودی، هشدار موجودی کم، بازگشت کنسلی) — مجموع ۴۵ تست سبز
- [x] مایگریشن‌های تکمیلی: suppliers، purchase_orders + purchase_order_items، waste_logs، cost_components
- [x] مدل‌ها و enumهای تکمیلی: Supplier، PurchaseOrder (دستگاه وضعیت Draft→Ordered→Received/Cancelled)، PurchaseOrderItem، WasteLog، CostComponent (درصد بر حسب بیس‌پوینت) + PurchaseOrderStatus و CostComponentType
- [x] دفاع در سطح دیتابیس: FK های restrict برای حفظ تاریخچهٔ انبار (supplier، متریال، محصول با فرمول/اجزای هزینه)
- [x] UI مدیریت انبار و فرمول تولید در پنل ادمین: صفحهٔ admin/inventory با کارت‌های StockVial (سنجاق موجودی + خط نشانگر آستانه)، اصلاح موجودی با ثبت در دفتر کل، افزودن/فعال‌سازی متریال، ویرایشگر فرمول محصول؛ به‌روزرسانی زنده با کانال branch.{id}.inventory و رویداد StockChanged (کسر پرداخت هم اکنون broadcast می‌شود)
- [x] سرویس رسید خرید: PurchaseOrderService با submit/receive/cancel — دریافت فقط از وضعیت Ordered، داخل تراکنش قفل‌شده، با ردیف Purchase در دفتر کل و broadcast
- [x] ثبت ضایعات: InventoryService::logWaste — کسر موجودی با قفل ردیف، ردیف Waste در دفتر کل، ثبت WasteLog، و رد موجودی منفی
- [x] هشدار موجودی کم: نوتیفیکیشن دیتابیسی LowStockAlert برای ادمین‌های شعبه هنگام عبور موجودی به زیر آستانه (پس از کسر پرداخت و ضایعات) + شمارندهٔ زندهٔ خوانده‌نشده‌ها در نوار بالای ادمین با زنگولهٔ بازشدنی و «همه خوانده شد» (prop اشتراکی stockAlerts + به‌روزرسانی با کانال inventory)
- [x] قیمت تمام‌شده و قیمت فروش پیشنهادی: CostCalculator — هزینهٔ متریال از فرمول × unit_cost، زنجیرهٔ اجزای هزینه به‌ترتیب position (ثابت/درصدی روی مبلغ جاری)، رند ۱۰۰ تومانی فقط در انتها؛ ستون unit_cost متریال با دریافت خرید به‌روز می‌شود و پرچم is_cost مشخص می‌کند کدام جزء به تمام‌شده می‌خورد و کدام فقط به قیمت فروش
- [x] نمایش قیمت تمام‌شده در پنل انبار: خلاصهٔ متریال/تمام‌شده/فروش پیشنهادی + حاشیهٔ سود روی هر کارت محصول، تفکیک اجزای هزینه در ویرایشگر فرمول و پیش‌نمایش زندهٔ هزینهٔ متریال هنگام ویرایش؛ Seeder زنجیرهٔ نمونهٔ بسته‌بندی + سود ۳۰٪ + مالیات ۹٪ برای مارگاریتا دارد
- [x] صفحهٔ سفارش‌های خرید در پنل ادمین: کارت‌های سفارش با چیپ وضعیت و شمارنده‌ها، دکمه‌های زمینه‌ای (ثبت نزد تامین‌کننده، دریافت، کنسل)، جدول اقلام بازشدنی و پیام خطای دوستانه برای وضعیت‌های کهنه؛ endpoint دریافت از InventoryController به PurchaseOrderController منتقل شد؛ Seeder دو سفارش نمونه دارد
- [x] تاریخچهٔ تغییرات فرمول: هر ذخیرهٔ فرمول یک snapshot غیرقابل‌تغییر در product_recipe_versions می‌سازد (خطوط + قیمت واحد لحظه‌ای + زنجیرهٔ اجزا + ارقام قیمت تمام‌شده/فروش پیشنهادی از CostCalculator)؛ صفحهٔ admin/products/{product}/recipe-versions با مقایسهٔ دو نسخه (تفاوت خطوط و ارقام قیمت) و شماره‌گذاری نسخه به‌ازای هر محصول
- [x] فرم ساخت سفارش خرید جدید: صفحهٔ admin/purchase-orders/new با انتخاب تامین‌کنندهٔ فعال، خطوط قلم (متریال + مقدار + هزینهٔ واحد با پیش‌پرشدن از آخرین قیمت خرید)، جمع کل زنده و گارد متریال تکراری؛ PurchaseOrderService::create پیش‌نویس را داخل تراکنش با جمع کل قلم‌به‌قلم می‌سازد؛ دکمهٔ «+ سفارش جدید» روی تابلو خرید
