# Changelog

All notable changes to **VeeKitchen** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> نسخهٔ بعدی بر اساس این فایل تگ می‌خورد. برای انتخاب نسخهٔ هر انتشار، از کارفرما پرسیده می‌شود — هرگز حدس زده نمی‌شود.

## Release workflow

A version is **released** only when this cycle completes:

1. Feature branch merged into `development` → `development` merged into `testing`.
2. Full test suite green on `testing`.
3. `testing` merged into `main` **and** `production`.
4. The `Unreleased` section below is renamed to the new version with its date, and the version is **tagged on `main`** (e.g. `v0.2.0`).

Direct pushes to `main` or `production` never happen; merges from `testing` only.

## [Unreleased]

### Added (Phase 2 — inventory & recipes core)
- Warehouse migrations: `inventory_items` (unit, current stock, low-stock threshold, unique QR label), `product_recipes` (material amount per product unit), `stock_movements` (append-only ledger with a unique `(payment_id, inventory_item_id)` consumption key).
- `App\Services\InventoryService`:
  - `deductForOrder()` — auto-deducts recipe materials when an order is paid; **idempotent via the unique ledger key**, so a replayed payment never deducts twice.
  - A stock shortfall rolls back the entire payment transaction (no payment record, no status change).
  - `returnForCancelledOrder()` — paid-then-cancelled orders return their materials to stock, also guarded against double-return.
- `OrderService::markPaid()` now records the payment and deducts materials inside the same locked transaction; `transition()` to `Cancelled` triggers the stock return.
- New enums: `MeasurementUnit` (g/kg/ml/l/piece with compatible-unit conversion helpers) and `StockMovementType` (purchase/consumption/waste/adjustment/return).
- Models `InventoryItem`, `ProductRecipe`, `StockMovement` with relations, casts, and factories (+ `lowStock`, `withoutQr`, `consumption`, `purchase` states); `Order::stockMovements()` relation.
- Phase 2 test suite: 9 new feature tests (deduction, multi-line aggregation, idempotent replay, recipe-less products, shortfall rollback, low-stock detection, cancellation return) — 45 tests green overall.

### Added (Phase 1 — order/payment/KDS/real-time core)
- Middleware stack completed: `role` guard, Inertia shared props (auth user, flash), auth/guest redirects.
- `App\Services\OrderService` — the single gateway for order state:
  - `place()`: guest/member orders with DB-priced carts and 100-Toman ceiling rounding.
  - `markPaid()`: payment confirmation with **daily order numbers allocated inside a locked transaction** per branch.
  - `transition()`: status machine via `OrderStatus::canTransitionTo()`, cancellation requires a reason and records the actor.
- `App\Services\QuoteService` — cart prices always come from the database, never the client.
- Broadcasts: `OrderPlaced` (branch cashier), `OrderPaid` (branch kitchen + order), `OrderStatusChanged` (order + public pickup + table), with minimal public payloads.
- Channel authorization (`routes/channels.php` + `App\Support\BranchAccess`): staff scoped to their branch (admins everywhere), guest order tracking gated by the unguessable `guest_token`.
- Customer flow: public/table menus, QR table reservation, localStorage cart, order placement with rate limiting, live tracking (Echo + poll fallback).
- Cashier screen: pending-payment queue, pay action (cash/card), 80mm printable receipt, repeat announcement, manual table release.
- Kitchen display (KDS): queued/preparing columns, elapsed-time urgency colors, start/ready actions.
- Public pickup display: ready-for-pickup queue with Persian TTS (Web Speech API with recorded-clip fallback mode), sound unlock gesture, repeat button.
- Admin tables grid: live statuses, printable QR page, token rotation.
- Database seeder: demo branch, staff accounts, Persian menu (5 categories / 14 products), 8 tables, TTS/print settings.
- Model relations and enum casts completed across Order, OrderItem, Payment, Product, Branch, MenuCategory, RestaurantTable, User, Setting.
- Phase 1 test suite: 36 tests (Money rounding, order placement, payment flow, transitions, channel authorization).

### Fixed (Phase 1)
- **Security**: `order.{id}` channel authorization compared `null === null` (guest user vs. order without a registered customer), letting an unauthenticated visitor join without the guest token. Now requires an explicit non-null user match or a valid guest token.
- Inertia middleware was never appended to the `web` group — shared props did not exist; fixed in `bootstrap/app.php`.
- Duplicate `PrivateChannel` import miss (events referenced `PrivateChannel` without import) broke broadcasting under tests.

### Added (tooling & docs)
- Skills copied from sibling projects: `persian-writing`, `ui-ux-pro-max`, `frontend-design` (under `.claude/skills` and `.agents/skills`).
- Project rules in `.ai/rules/`: git workflow (no direct `main` pushes, feature branches from `development`, changelog-driven versioning) and skills policy.
- Persian README with setup guide, demo accounts, ports table, and roadmap.
- `CHECKLIST.md` to track completed/pending tasks and avoid duplicate work.
- `CHANGELOG.md` (this file) as the single source for release versioning.
- Phase 1 implementation plan: `docs/PHASE-1-PLAN.md` (order/payment/KDS/real-time core).

### Changed
- README replaced (stock Laravel → project-specific, Persian).
- README and CHANGELOG aligned with the git workflow rules (branch diagram with tag/release flow, roadmap carries version tags, demo accounts flagged as pending the seeder).

## [0.1.0] — 2026-09-19 (Initial)

### Added
- Laravel 13 skeleton with Vue 3 + Inertia v3 + Tailwind CSS v4 + Laravel Reverb.
- Core migrations: `branches`, `users`, `menu_categories`, `products`, `restaurant_tables`, `orders`, `order_items`, `payments`, `settings`.
- Domain enums with Persian labels: `OrderStatus` (full state machine), `PaymentMethod`, `TableStatus`, `UserRole` (waiter-ready).
- `App\Support\Money` value object — Toman amounts with the 100-Toman ceiling rounding rule.
- Frontend scaffold: glassmorphism theme, RTL/Vazirmatn, PWA manifest hooks, Echo setup, page/component skeletons.
- Approved requirements document at `docs/REQUIREMENTS.md` (ports: Laravel 8020, Reverb 8080, Vite 8120).

### Fixed
- Route `/` now renders the Inertia `Welcome` page (the deleted `welcome` Blade view broke the app and tests).
- `ziggy-js` import moved from the removed `ziggy-js/vue` subpath to the module root (2.x exports).
- Empty `.vue` page stubs replaced with minimal valid SFCs so `vite build` passes; added `pwa.js` service-worker registrar.

[Unreleased]: https://github.com/MojSkin/VeeKitchen/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/MojSkin/VeeKitchen/commits/v0.1.0
