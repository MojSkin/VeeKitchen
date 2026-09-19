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
- [ ] ساخت برنچ‌های development / testing / production و تنظیم پوش اولیه ⬅️ *در همین تسک*

## فاز ۱ — هستهٔ سفارش / پرداخت / KDS / Real-time

> برنامه کامل: docs/PHASE-1-PLAN.md — پس از تایید کارفرما گام‌به‌گام تیک می‌خورد.

- [ ] گام ۰ — میدلورها، لاگین، HandleInertiaRequests
- [ ] گام ۱ — Factory و Seeder
- [ ] گام ۲ — مدل‌ها + OrderService + QuoteService
- [ ] گام ۳ — رویدادهای Broadcast + مجوز کانال‌ها
- [ ] گام ۴ — جریان مشتری (QR → منو → سبد → ثبت)
- [ ] گام ۵ — صندوق (پرداخت، شماره‌گذاری، رسید HTML)
- [ ] گام ۶ — KDS آشپزخانه
- [ ] گام ۷ — نمایشگر «آماده تحویل» + TTS
- [ ] گام ۸ — پنل میزها با QR
- [ ] گام ۹ — تست‌های Feature/Unit
- [ ] گام ۱۰ — جمع‌بندی، Pint، کامیت مرحله‌ای

## پشتیبانی از VeePanel (منبع قابل استفاده)

- [ ] تصمیم: کپی کامپوننت‌های موردنیاز از VeePanel (مثل VeeButton، VeeDialog، VeeDataTable) هنگام ساخت صفحات پنل — در گام‌های ۵ تا ۸ فاز ۱
- [ ] بررسی composableهای قابل استفاده: useVeeServerErrors، useVeeToast
