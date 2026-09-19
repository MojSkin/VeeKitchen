# VeeKitchen

نرم‌افزار مدیریت رستوران چندشعبه‌ای — Laravel + Vue 3 (Inertia) + Tailwind + Reverb.

سند کامل نیازمندی‌ها و معماری: [`docs/REQUIREMENTS.md`](docs/REQUIREMENTS.md)

## پورت‌های توسعه

| سرویس | پورت |
|---|---|
| Laravel (`php artisan serve`) | `8020` |
| Reverb (WebSocket) | `8080` |
| Vite Dev Server | `8120` |

## شروع کار

```bash
composer install
cp .env.example .env   # اگر .env ندارید
php artisan key:generate
php artisan migrate
npm install
composer run dev       # هر چهار سرویس هم‌زمان روی پورت‌های بالا
```

## تست‌ها

```bash
composer test          # یا: php artisan test --compact
```

## دیتابیس

- توسعه/تست: sqlite (پیش‌فرض `.env.example`)
- پروداکشن: MySQL — مایگریشن‌ها باید با هر دو موتور سازگار بمانند
