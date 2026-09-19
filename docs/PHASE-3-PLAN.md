# VeeKitchen — برنامه اجرایی فاز ۳ (مالی، تخفیف، شیفت و تسویه صندوق)

> **وضعیت:** پیش‌نویس برای تایید کارفرما — پیاده‌سازی پس از تایید شروع می‌شود.
> **مبنای محیط:** پورت‌ها Laravel `8020` / Reverb `8080` / Vite `8120`؛ توسعه و تست روی sqlite، سازگاری مایگریشن‌ها با MySQL الزامی.
> **پیش‌نیاز:** فاز ۱ (هستهٔ سفارش/پرداخت/KDS) و فاز ۲ (انبار/فرمول/خرید) مرج و پایدار — ۱۳۷ تست سبز روی `development`.

---

## ۱. دامنه فاز ۳

در این فاز **حلقهٔ مالی رستوران** بسته می‌شود: پولی که واقعاً در صندوق می‌رود و برمی‌گردد، تخفیف‌هایی که روی آن اثر می‌گذارند، و شیفتی که همهٔ این تراکنش‌ها به آن نسبت داده می‌شوند.

| در دامنه فاز ۳ | خارج از دامنه (فاز ۴+) |
|---|---|
| مایگریشن: `discounts`، `staff_shifts`، `cash_register_sessions`، `cash_movements` | Web Push (VAPID)، PWA نصب‌پذیر کامل |
| `DiscountService::bestFor()` — بهترین تخفیف خودکار + اعتبارسنجی کد | صف انتظار میز (Waitlist) |
| اتصال تخفیف به `QuoteService`/`OrderService` (ستون `discount_total` موجود است) | تقسیم صورت‌حساب (Split Bill) |
| `DiscountForm.vue` — پنل مدیریت تخفیف‌ها با نگاشت دسته/محصول | امتیازدهی و نظر مشتری (Review) |
| `DiscountBadge.vue` روی منوی عمومی + تغییر قیمت با تخفیف در سبد | چاپ حرارتی ESC/POS شبکه‌ای |
| `Cashier` — شیفت: شروع با موجودی اولیه، پایان با شمارش + مغایرت | گزارش سود/زیان کامل با قیمت تاریخی |
| محدودسازی پرداخت/تسویه به شیفت باز | گزارش خودکار شبانه (Email/Push) |
| `CashRegisterSession` با مقایسهٔ انتظار/واقعی/مغایرت | درگاه پرداخت آنلاین |
| `CashMovement` — برداشت/واریز نقدیِ مستند در حین شیفت | چندشعبه‌ای فعال (همهٔ صفحات فعلاً تک‌شعبهٔ اول) |
| جبران خودکار مغایرت با ردیف `adjustment` نقدی (فقط ثبت دفتری) | نقش گارسون |
| نمایشگر وضعیت شیفت در Cashier + EOD summary در Dashboard | |
| شیفت برای Kitchen هم (ثبت ورود/خروج؛ تسویه فقط Cashier) | |
| گزارش XLSX/چاپ تسویهٔ هر شیفت | |
| تخفیف روی متریال مصرفی: کسر انبار بر پایهٔ جمع نهایی سفارش | |
| ~۲۵–۳۰ تست جدید (تخفیف ۱۲، شیفت/تسویه ۱۲–۱۴، خط لوله ۴) | |

**قاعدهٔ طلایی فاز ۳:** هر پرداخت و هر تسویه به یک `staff_shift` باز تعلق دارد. بدون شیفتِ باز، صندوق پرداخت نمی‌کند — این پیوند، مالی را ممیزی‌پذیر می‌کند.

**تخفیف روی کسر انبار (تصمیم طراحی):** کسر متریال با `total_after_discount ÷ subtotal` مقیاس می‌شود — کسی که ۵۰٪ تخفیف گرفته، متریالِ واقعی همانچه تولید می‌شود را مصرف می‌کند و نصفش را «مصرف تخفیفی» حساب نمی‌کنیم.

---

## ۲. گام‌های اجرایی

### گام ۱ — دیتابیس و دامنهٔ تخفیف
۱. مایگریشن `discounts`: `branch_id, name, code (nullable+unique), type (enum: Percentage/Fixed), value, applies_to (enum: EntireOrder/Category/Product) + category_id/product_id nullable، min_order_total, starts_at, expires_at, usage_limit_total, usage_limit_per_user, used_count, is_active` + FKهای کسکید.
۲. enumهای `DiscountType`، `DiscountScope`، `DiscountStatus` (لیبل فارسی + `canApplyTo`) + مدل `Discount` (کست‌ها + `isActiveAt()`) + فکتوری (استیت‌های `code()`, `percent()`, `fixed()`, `forCategory()`, `forProduct()`, `expired()`, `exhausted()`, `minOrder()`).
۳. `DiscountService::bestFor(cart, ?code)` — بدون کوئری N+1، فقط تخفیف‌های فعالِ بازه؛ خودکار = بدون کد؛ گارد سقف کل و سقف کاربر؛ خروجی `applied + amount`.
۴. سیم‌کشی `QuoteService` (کارت با تخفیف) و `OrderService::place` (`discount_total` + `discount_id` + رد کد تخفیفیِ کمتر از خودکار با پیام فارسی) + **مقیاس کسر انبار** در `InventoryService`.
۵. `Order::discount()`، شمارش `used_count` اتمیک، هشدار نزدیک‌شدن به سقف.

### گام ۲ — UI تخفیف
۶. `DiscountForm.vue` در پنل ادمین (فرم/لیست + فعال/غیرفعال‌سازی) + مسیرها و `DiscountController`.
۷. `DiscountBadge.vue` روی کارت محصول منوی عمومی (نوار زعفرانی «٪۲۰ تخفیف») + جایزهٔ تخفیف در سبد/پیگیری.
۸. Seeder: ۳ تخفیف دمو (خودکار ۱۰٪ کل سبد، کد WELCOME ۲۰٪، تخفیف دستهٔ نوشیدنی).

### گام ۳ — دیتابیس و دامنهٔ شیفت/تسویه
۹. مایگریشن‌ها:
- `staff_shifts`: `branch_id, user_id, opened_at, closed_at, opening_cash, closing_cash, expected_cash, discrepancy, closed_by` (decimal 12,0 با default صفر — سازگار sqlite/MySQL).
- `cash_register_sessions`: `shift_id, opened_at, closed_at, opening_balance, expected_balance, counted_balance, discrepancy, closed_by` (اختیاری در گام ۳، جمع‌شدن با شیفت).
- `cash_movements`: `shift_id, user_id, type (enum: Withdrawal/Deposit/Adjustment), amount, reason`.
۱۰. enumهای `ShiftStatus`، `CashMovementType` + مدل‌ها/فکتوری‌ها + `ShiftService` (فقط یک شیفت باز به‌ازای شعبه+کاربر؛ `open`/`close` داخل تراکنش قفل‌شده + `expectedCash = opening + payments - withdrawals + deposits`) + قلاب در `CashierController::pay` (پرداخت فقط با شیفت بازِ صندوق‌دار؛ `shift_id` روی `Payment`).
۱۱. UI Cashier: `ShiftPanel.vue` — فرم شروع شیفت (موجودی اولیه)، ثبت برداشت/واریز، پایان شیفت (شمارش واقعی + مغایرت). تسویهٔ XLSX/چاپ per-shift با همان الگوی `WarehouseReportExportService`.

### گام ۴ — خط لولهٔ پایان روز و Dashboard
۱۲. `EndOfDayController` — شیفت/شیفتهای امروز: فروش، تعداد سفارش، تخفیف اعطایی، ضایعات، تسویه‌ها + دکمهٔ بستن شیفت.
۱۳. ردیف KPI شیفت/مغایرت در Dashboard + لینک گزارش تسویه.

### گام ۵ — تست، کیفیت و انتشار
۱۴. ~۲۵–۳۰ تست فیچر: تخفیف (محاسبه، بهترین‌گزینی، سقف‌ها، کد، انقضا، اتحاد with-quote، مقیاس کسر انبار)؛ شیفت (یکتایی، پرداخت بدون شیفت، فرمول پولی، مغایرت، حرکات نقدی، بستن)؛ خط لوله (۲–۴).
۱۵. `Pint --dirty` + `vite build` سبز؛ به‌روزرسانی `CHECKLIST.md` و `CHANGELOG.md`.
۱۶. مرج به `development`؛ در پایان فاز: جریان انتشار کامل (development → testing → تست‌های سبز → main + production) و **تگ v0.4.0** (نسخهٔ نهایی با تایید کارفرما).

---

## ۳. ریسک‌ها و نکات
- **هم‌زمانی بستن شیفت:** گارد داخل تراکنش با `lockForUpdate` — الگوی سرویس خرید فاز ۲.
- **اثر تخفیف روی گزارش‌ها:** `discount_total` از قبل روی `orders` است؛ گزارش‌های مالی فاز ۳ باید آن را در خالص فروش لحاظ کنند.
- **سازگاری MySQL:** همهٔ decimals بدون اعشار تومانی؛ `code` unique nullable (رفتار MySQL با NULLها سالم است).

## ۴. آنچه صریحاً به فاز ۴ موکول شد
Web Push (VAPID)، Waitlist، Split Bill، چاپ حرارتی ESC/POS، امتیازدهی (Review)، گزارش خودکار شبانه، فعال‌سازی چندشعبه‌ای واقعی، نقش گارسون.
