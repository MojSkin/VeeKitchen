# VeeKitchen — برنامه اجرایی فاز ۱ (هسته سفارش / پرداخت / KDS / Real-time)

> **وضعیت:** پیش‌نویس برای تایید کارفرما — پیاده‌سازی پس از تایید شروع می‌شود.
> **مبنای محیط:** پورت‌ها Laravel `8020` / Reverb `8080` / Vite `8120`؛ توسعه و تست روی sqlite، سازگاری مایگریشن‌ها با MySQL الزامی.

---

## ۱. دامنه فاز ۱

در این فاز **حلقه کامل سفارش از QR تا تحویل** ساخته می‌شود؛ فقط آنچه لازمهٔ همین حلقه است، نه بیشتر:

| در دامنه | خارج از دامنه (فاز بعد) |
|---|---|
| رزرو میز با QR + منوی عمومی | انبار، متریال، فرمول تولید، کسر خودکار موجودی |
| ثبت سفارش مهمان/عضو (منتظر پرداخت) | تخفیف و کوپن (ساختار جدول فقط آماده می‌شود) |
| صندوق: پرداخت، تخصیص شماره روزانه، فیش HTML | چاپ حرارتی ESC/POS |
| KDS بلادرنگ + تغییر وضعیت | تسویه روزانه صندوق، شیفت کارکنان |
| نمایشگر «آماده تحویل» + TTS | Web Push، PWA نصب‌پذیر کامل |
| رویدادهای Real-time روی Reverb | امتیازدهی، داشبورد مدیریتی |
| نقش‌ها: Admin/Cashier/Kitchen + مسیر مهمان | نقش گارسون، چندپرداختی (Split Bill) |

---

## ۲. گام‌های اجرایی

### گام ۰ — ستون فقرات اپلیکیشن (پیش‌نیاز همه گام‌ها)
1. تکمیل `HandleInertiaRequests` (اشتراک `auth.user`، فلش، برنچ فعال) و فعال‌سازی گروه `web` در `bootstrap/app.php`؛ ثبت alias میدلور `role`.
2. تکمیل میدلور `EnsureUserHasRole` (بررسی `role` از enum `UserRole`) + روت‌های auth:
   - `GET /login` (Inertia) و `POST /login` (تکمیل `LoginController` با `Auth::attempt` + هدایت بر اساس `UserRole::homeRoute()`)
   - `POST /logout`
3. فعال‌سازی `RefreshDatabase` در `tests/Pest.php` برای فیچرتست‌ها.

### گام ۱ — داده پایه (Factory و Seeder)
4. تکمیل Factoryهای خالی موجود: `Branch`، `User` (stateهای `admin/cashier/kitchen/customer` + `branch_id`)، `MenuCategory`، `Product`، `RestaurantTable` (تولید `qr_token` امن غیرقابل‌حدس)، `Order`، `OrderItem`، `Payment`.
5. `DatabaseSeeder`: یک شعبه پیش‌فرض + کاربران نمونه (admin/cashier/kitchen با رمز مشخص) + ۴ دسته و ~۱۲ محصول فارسی + ۸ میز (قابل اجرا با `php artisan db:seed` برای دموی اولیه).

### گام ۲ — مدل‌ها و قواعد کسب‌وکار (لایه دامنه)
6. تکمیل مدل‌های موجود با روابط و cast به enumها: `Order` (برنچ/میز/مشتری، آیتم‌ها، پرداخت‌ها؛ `status→OrderStatus` و ...)، `OrderItem`، `Payment` (`method→PaymentMethod`)، `Product`، `RestaurantTable` (`status→TableStatus`)، `Branch`، `MenuCategory`.
7. **سرویس `App\Services\OrderService`** — تمام تغییر وضعیت سفارش فقط از اینجا:
   - `place(...)` — ثبت سفارش مهمان/عضو با محاسبه جمع از DB و **رند سقف ۱۰۰ تومانی** با `Money`، atomic
   - `markPaid(...)` — داخل `DB::transaction` با `lockForUpdate`: تخصیص `order_number` = `max+1` برای `(branch_id, امروز)`، ثبت `Payment`، `status→queued`، `paid_at`
   - `transition(...)` — با `canTransitionTo()` موجود؛ کنسل فقط با نقش admin/cashier و ثبت دلیل
8. **سرویس `QuoteService`** — اعتبار سبد: قیمت‌گذاری ردیف‌ها فقط از DB (قیمت هرگز از کلاینت پذیرفته نمی‌شود).

### گام ۳ — رویدادها و Real-time (Reverb)
9. تکمیل سه رویداد موجود (`OrderPlaced`, `OrderPaid`, `OrderStatusChanged`) به‌صورت `ShouldBroadcast`:
   - `OrderPlaced` → کانال خصوصی `branch.{id}.cashier` (سفارش جدید منتظر پرداخت)
   - `OrderPaid` → `branch.{id}.kitchen` + `order.{id}` (ورود به صف آشپزخانه)
   - `OrderStatusChanged` → `order.{id}` (مشتری) + `branch.{id}.pickup` (نمایشگر تحویل)
10. Authorization کانال‌ها در `routes/channels.php`:
    - `branch.{id}.kitchen` → کاربر kitchen/admin همان برنچ؛ `branch.{id}.cashier` → cashier/admin
    - `branch.{id}.pickup` → کانال عمومی (نمایشگر بدون لاگین، payload حداقلی)
    - `order.{id}` → عمومی با توکن مهمان سفارش (غیرقابل‌حدس) یا مالک
11. Resourceهای سبک (`OrderResource`, `OrderItemResource`) برای هم‌شکلی payload بین Inertia و Broadcast.

### گام ۴ — جریان مشتری (QR → منو → سبد → ثبت)
12. روت‌های مهمان (throttle روی ثبت):
    - `GET /t/{qr_token}` → رزرو میز (free→ordering) + هدایت به منو با اطلاعات میز
    - `GET /menu` (لینک/QR عمومی منو) و `GET /t/{qr_token}/menu`
    - `POST /orders` → سفارش «منتظر پرداخت»؛ خروجی: پیگیری با `guest_token`
13. صفحات Vue (استاب‌های موجود تکمیل می‌شوند، با طراحی glassmorphism موجود):
    - `Customer/Menu.vue` — منو با دسته‌بندی، سبد (`lib/cart.js` + localStorage)، نام مهمان، ثبت سفارش
    - `Customer/Order.vue` — پیگیری زنده با Echo روی `order.{id}` + poll fallback هر ۱۵ ثانیه
14. آزادسازی دستی میز توسط cashier/admin (`POST /tables/{table}/release`).

### گام ۵ — صندوق
15. `GET /cashier` (auth+role):
    - لیست زنده «منتظر پرداخت» (Echo) + صف/آماده‌ها برای مرج
    - «دریافت وجه» (نقد/کارت) → `POST /orders/{order}/pay`
    - رسید HTML برای چاپ مرورگر (`GET /orders/{order}/receipt` — چیدمان ۸۰mm با CSS) + دکمه «تکرار اعلان»
16. صفحه `Cashier/Index.vue` تکمیل می‌شود.

### گام ۶ — KDS آشپزخانه
17. `GET /kitchen` (auth+role):
    - ستون‌های «صف» و «در حال آماده‌سازی» + کارت سفارش (شماره، میز، آیتم‌ها، زمان سپری‌شده با رنگ هشدار)
    - `POST /kitchen/orders/{order}/start` (queued→preparing) و `.../ready` (preparing→ready)
    - Echo روی `branch.{id}.kitchen` — سفارش جدید با انیمیشن
18. صفحه `Kitchen/Index.vue` (فونت درشت، مناسب نمایشگر آشپزخانه).

### گام ۷ — نمایشگر «آماده تحویل» + TTS
19. `GET /pickup/{branch}` — بدون لاگین، Echo روی کانال عمومی `branch.{id}.pickup`:
    - صف «آماده تحویل» با شماره درشت + متن «شماره [X] میز [Y]»
    - TTS فارسی از `lib/tts.js`: تشخیص خودکار voice فارسی؛ در نبود آن **فایل‌های صوتی پیش‌ضبط** (اعداد + «شماره/میز») طبق Plan B تحلیل ریسک — انتخاب حالت در `Setting`
    - دکمه «فعال‌سازی صدا» (سیاست autoplay) + «تکرار» + تمام‌صفحه
20. اکشن «آماده» در KDS رویداد `OrderStatusChanged` می‌فرستد → هم مشتری هم نمایشگر تحویل به‌روز می‌شود.

### گام ۸ — میزها در پنل مدیر (حداقلی)
21. `GET /admin/tables` — شبکه میزها با وضعیت زنده (Echo) + آزادسازی + تولید مجدد توکن QR + نمایش/چاپ QR (SVG).
22. صفحه `Admin/Tables.vue` تکمیل می‌شود.

### گام ۹ — تست‌ها (Feature با Factory، به‌علاوه یک Unit)
23. `MoneyTest` (Unit): رند سقف ۱۰۰ تومانی، جمع/تفریق، صفر.
24. `OrderPlacementTest`: سفارش مهمان روی میز، محاسبه جمع، وضعیت میز، throttle.
25. `PaymentFlowTest`: `markPaid` → شماره روزانه درست (سفارش‌های متوالی = ۱، ۲، ...)، ثبت Payment، وضعیت queued، broadcast روی کانال درست (fake).
26. `OrderTransitionTest`: مسیرهای مجاز/غیرمجاز `OrderStatus`، کنسل با دلیل و مجوز نقش.
27. `ChannelAuthorizationTest`: دسترسی کانال‌ها برای نقش‌ها و مهمان دارای guest_token.
28. اجرای `composer test` تا سبز شدن کامل.

### گام ۱۰ — جمع‌بندی فاز
29. `vendor/bin/pint --dirty --format agent`؛ به‌روزرسانی README (بخش دموی فاز ۱: سیدر، حساب‌های تست، مسیرهای کلیدی)؛ کامیت‌های مرحله‌ای.

---

## ۳. فایل‌های کلیدی

```
app/Services/{OrderService, QuoteService}                 ← جدید
app/Events/{OrderPlaced, OrderPaid, OrderStatusChanged}   ← تکمیل موجود
app/Http/Resources/{OrderResource, OrderItemResource}     ← جدید
app/Http/Controllers/* (تکمیل اسکلت‌های موجود)
app/Http/Middleware/EnsureUserHasRole                     ← تکمیل
routes/{web.php, channels.php}
database/factories/* (تکمیل) + DatabaseSeeder
resources/js/Pages/{Customer, Cashier, Kitchen, Admin, Auth}/*  ← تکمیل استاب‌ها
resources/js/lib/{cart, tts, format, echo}                ← تکمیل
tests/Feature/{OrderPlacement, PaymentFlow, OrderTransition, ChannelAuthorization}Test
```

## ۴. نقاط دقت
- **تخصیص شماره روزانه:** حتماً داخل تراکنش با `lockForUpdate`، وگرنه دو صندوق هم‌زمان شماره تکراری می‌گیرند (پوشش تست گام ۹).
- **قیمت از کلاینت نمی‌آید:** همه محاسبات از DB (گام ۲).
- **TTS:** Plan B فایل‌های پیش‌ضبط از روز اول در گام ۷ هست، نه بعداً.
- **کانال عمومی pickup:** فقط payload حداقلی (شماره/میز/زمان) — بدون داده مشتری.
- **sqlite/MySQL:** `DB::transaction` + `lockForUpdate` روی هر دو موتور کار می‌کند؛ هیچ نوع ستون مخصوص یک موتور استفاده نمی‌شود.

## ۵. معیار پذیرش فاز ۱
یک سناریوی کامل قابل اجرا روی دموی محلی:
اسکن QR میز → ثبت سفارش مهمان → صندوق بلادرنگ می‌بیند → پرداخت نقدی → شماره می‌گیرد → KDS بلادرنگ می‌بیند → «در حال آماده‌سازی» سپس «آماده» → پخش «شماره ۳ میز ۵» از نمایشگر تحویل → مشتری روی گوشی تغییر وضعیت را بلادرنگ می‌بیند → همه تست‌ها سبز.
