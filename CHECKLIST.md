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
- [ ] مایگریشن‌های تکمیلی: cost_components، suppliers، purchase_orders، waste_logs
- [ ] قیمت تمام‌شده (Cost Price) و قیمت فروش پیشنهادی از فرمول + اجزای هزینه
- [ ] سرویس خرید/رسید انبار و ثبت Waste
- [ ] UI مدیریت انبار و فرمول تولید در پنل ادمین
