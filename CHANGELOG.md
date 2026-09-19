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

### Added (Phase 2 — warehouse report date ranges)
- The warehouse report gained a date-range selector: presets «امروز» / «۷ روز گذشته» / «این هفته» (Saturday-start, Jalali standard) / «این ماه» / «بازهٔ دلخواه» with from/to date inputs. Custom ranges tolerate swapped bounds and fall back to today on garbled dates; a 92-day cap keeps a runaway range from dragging the whole ledger into memory. The page reloads the report and range props in place (Inertia partial reload) and the header/labels follow the active range.
- `WarehouseReportService::rangeByType(branch, from, to)` generalizes the today report to arbitrary inclusive day boundaries (the report payload now carries `from`/`to`); `todayByType` remains as a thin wrapper.
- 4 new feature tests (last7 span incl. day-3 vs day-8 isolation, Saturday week start, custom bounds with a 00:00 day boundary, garbled-date fallback + swapped-range tolerance) — 132 tests green overall.

### Added (Phase 2 — admin dashboard)
- Admin dashboard page (`admin/dashboard`, linked as «داشبورد» from the admin nav): a 14-day sales chart (hand-rolled SVG line/area — daily revenue + order counts, tooltip per point, zero days stay on the axis), today's KPI cards (paid revenue, placed orders, average ticket), the open-order pipeline (awaiting payment → ready), the dining-room table snapshot, and a low-stock mini-board with per-material threshold bars linking to the inventory panel.
- Revenue truth comes from `payments.paid_at` (joined through the branch's orders); the day boundary follows the app timezone, and an empty branch renders an all-zero dashboard without errors.
- 6 new feature tests (admin-only guard, today KPIs incl. yesterday isolation, exact 14-day span with zero days, low-stock filtering, pipeline/table counts, empty-branch zeroing) — 128 tests green overall.

### Added (Phase 2 — draft purchase order editing)
- Drafts are editable until submitted: the procurement board shows an edit action on drafts (`is_editable` in the payload) leading to `admin/purchase-orders/{id}/edit` (admin-only, 403 for non-drafts) with the form prefilled — supplier, notes, and every line.
- `PurchaseOrderService::updateDraft()` replaces the whole line set inside a locked transaction with a fresh status re-check, so an order submitted between page load and save can never be rewritten; supplier swap and notes update, line totals and the order total are recomputed, and omitted unit costs fall back to the material's last purchase price.
- The create and edit forms share one `usePurchaseOrderForm` composable and a `PurchaseOrderFormFields` component (duplicate-material guard, cost prefill, live total); update goes through `PUT admin/purchase-orders/{order}` with Inertia method spoofing.
- 10 new feature tests (line-set replacement + totals, supplier preservation, ordered/received/cancelled refusal incl. a stale-page scenario and a no-op integrity check, prefill payload, admin-only + 403 guards, endpoint validation, edit-then-submit flow, board flag) — 122 tests green overall.

### Added (Phase 2 — today warehouse report)
- Admin report page (`admin/inventory/report`, admin-only, linked from the nav as «گزارش انبار»): today's StockMovement ledger aggregated by movement type. A balance pipe sums inflow (purchase + returns + positive adjustments) against outflow (consumption + waste + negative adjustments) with the net day figure; each type renders its own section with movement count, signed type total, and per-material lines (sorted by magnitude) in the material's unit.
- `WarehouseReportService::todayByType()` reads the append-only ledger as the single source of truth with the app-timezone day boundary; zero-activity types still appear for a complete picture.
- 4 new feature tests (type grouping with signed per-item aggregation, yesterday isolation, admin-only guard, payload shape) — 112 tests green overall.

## [0.3.0] — 2026-09-20

### Added (Phase 2 — new purchase order form)
- The procurement board gained a «+ سفارش جدید» action leading to `admin/purchase-orders/new` (admin-only): pick an active supplier, add item lines (material, quantity, per-unit cost), and save a draft. Unit costs prefill from each material's last purchase price and stay editable; a live running total mirrors the backend arithmetic; a material used on one line is disabled on the others.
- `PurchaseOrderService::create()` builds the draft with its lines inside a transaction (line totals filled immediately, supplier must be active), so drafts show a meaningful total before submission and flow straight into the existing submit → receive machine.
- 7 new feature tests (service-level creation with cost fallback and totals, inactive-supplier refusal, admin-only form payload, endpoint validation incl. empty orders / unknown materials / zero quantities, duplicate-material rejection, draft-then-submit flow) — 108 tests green overall.

### Added (Phase 2 — recipe version history)
- Every recipe save now freezes an immutable snapshot in `product_recipe_versions`: the recipe lines (material name, per-unit amount, unit label), each material's `unit_cost` at save time, the ordered cost-component chain, and the full pricing output of `CostCalculator` (material/cost/suggested-sale) — past figures can no longer drift when stock prices change later.
- `RecipeVersionService::record()` runs in the same transaction as the recipe save; versions are numbered per product.
- Admin history page (`admin/products/{product}/recipe-versions`, admin-only): version list with material/cost/sale figures, an A/B compare mode with a line-level diff (added/removed/quantity-changed lines) and before/after price deltas.
- 6 new feature tests (snapshot immutability, per-product numbering, endpoint wiring on save, listing order, admin-only guard, empty-recipe snapshot) — 101 tests green overall.

## [0.2.0] — 2026-09-19

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

### Added (Phase 2 — low-stock alert counter)
- Admin top-bar bell (`StockAlertBell`): unread low-stock alert counter with a red badge, an expandable list of the latest alerts (title, message, relative time), «همه خوانده شد» (`POST /admin/notifications/read-all`), and **live refresh** — the bell re-pulls the shared `stockAlerts` prop whenever the branch's `branch.{id}.inventory` channel reports a stock move. The shared Inertia prop ships only to admins (count + 6 latest); everyone else gets `null`.
- 5 new feature tests (admin-only prop and endpoint, counter raise/clear, end-to-end payment → alert → read-all) — 95 tests green overall.

### Added (Phase 2 — cost display on the product form)
- The admin inventory board now shows pricing on every product card: material cost, cost price, suggested sale price (all 100-Toman-ceilinged) and the margin percentage against the real sale price (green when healthy, red when selling below cost). The recipe editor gained the full component breakdown (packaging → management profit → tax, each step's amount and running total) plus a **live material-cost preview** that recalculates as lines are edited. Board payload exposes `unit_cost` for materials and recipes; the seeder ships a worked margherita cost chain (4,000 packaging + 30% profit + 9% tax).
- 3 new feature tests (payload summary, ceiling compliance, unit-cost exposure) — 90 tests green overall.

### Added (Phase 2 — admin purchase orders board)
- Admin purchase orders page (`admin/purchase-orders`, admin-only): per-order cards with supplier, status chip and status counters, contextual actions — submit-to-supplier on drafts, «دریافت شد» on submitted orders (stock rises), cancel on draft/ordered — plus an expandable item-lines table; stale-status actions return a friendly flash error instead of an exception. The receive endpoint moved from `InventoryController` to the dedicated `PurchaseOrderController`; the `AppLayout` nav gained a «خرید» link and the seeder ships two demo orders.
- 7 new feature tests (guards, payload, submit/receive/cancel incl. stale-status refusals) — 87 tests green overall.

### Added (Phase 2 — cost pricing)
- `CostCalculator`: material cost from the recipe (quantity × the material's `unit_cost`), then the product's cost components applied in `position` order — fixed Toman or percent (basis points) on the running amount. Returns `material_cost`, `cost_price` and `suggested_sale_price`, each passing through the 100-Toman ceiling (§5) exactly once at the end.
- `inventory_items.unit_cost` (last purchase cost) — refreshed automatically when a purchase order is received, so pricing follows real supplier prices; `cost_components.is_cost` flags whether a component inflates the cost price (packaging/overhead) or only the sale price (management profit/tax).
- 7 new feature tests (material cost, component chain in position order, compounding percents on the running amount, no-recipe products, ceiling compliance, PO-received price refresh, end-to-end margherita economics) — 80 tests green overall.

### Added (Phase 2 — purchase receiving, waste & low-stock alerts)
- `PurchaseOrderService`: `submit()` (draft → ordered, refuses empty orders), `receive()` (ordered → received inside a locked transaction — stock rises per item with a `purchase` ledger row; double-receiving is impossible via the status guard), and `cancel()` (draft/ordered only).
- `InventoryService::logWaste()`: row-locked stock check (waste can never exceed stock), a `WasteLog` record with reason/expiry, a `waste` ledger row, and alert evaluation.
- **Low-stock alerts**: `LowStockAlert` database notification to all branch admins whenever stock crosses down to/below the threshold — fired from payment deduction and waste; `notifications` table migration added.
- Admin endpoints `POST /admin/inventory/items/{item}/waste` and `POST /admin/purchase-orders/{order}/receive` (role-guarded); the inventory board gained a waste form per material (amount, reason, optional expiry date).
- 10 new feature tests (receive machine + double-receive guard, waste ledger/limits, alert firing from waste and from payment deduction, endpoint guards) — 73 tests green overall.

### Added (Phase 2 — admin inventory & recipe UI)
- Admin inventory workbench (`admin/inventory`, admin-only): material board with `StockVial` glass meters — a filled tube with a dashed threshold marker and green/amber/red tone — manual stock correction (positive or negative deltas, always ledgered as `adjustment`), new-material creation, activation toggle, and a per-product recipe editor (add/remove lines, item + amount per unit, duplicate guard).
- `StockChanged` broadcast on the new private `branch.{id}.inventory` channel fires from payment deduction, cancellation returns, and manual adjustments — the board repaints the affected vial live without a refresh; `AppLayout` gained a role-aware nav bar (tables / inventory / cashier for admins).
- `InventoryController` with `role:admin` guard, branch scoping, validation, `StockMovement` records for every correction, and `StockChanged` dispatch; `Branch::inventoryItems()` relation.
- Seeder now provisions 8 Persian materials (mozzarella sits exactly on its alert threshold to demo a live low-stock vial) and a worked margherita recipe (flour 0.4 kg, mozzarella 0.15 kg, sauce 0.1 l per unit).
- 9 new feature tests (guards, listing payload, adjustment ledger + event, negative-stock rejection, recipe replace semantics, duplicate-line guard, payment → stock event) — 63 tests green overall.

### Added (Phase 2 — warehouse entities)
- Migrations: `suppliers`, `purchase_orders` (status machine draft → ordered → received/cancelled, receive metadata), `purchase_order_items` (quantity + unit cost), `waste_logs` (reason + expiry date), `cost_components` (ordered fixed-Toman or basis-point percent surcharges on material cost).
- Models `Supplier`, `PurchaseOrder` (with `recalculateTotal()`), `PurchaseOrderItem`, `WasteLog`, `CostComponent`; enums `PurchaseOrderStatus` and `CostComponentType`; factories with Persian demo data and helper states.
- Referential protection for warehouse history: supplier/material rows referenced by purchase orders or waste logs, and products carrying recipes or cost components, can no longer be hard-deleted (`restrictOnDelete`); `product_recipes.product_id` hardened from cascade to restrict accordingly.
- `Product::recipes()` and `Product::costComponents()` relations (components sorted by position).
- 9 new feature tests (PO totals, status machine, FK protection, basis-point percent round-trip, per-product grouping) — 54 tests green overall.

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

[Unreleased]: https://github.com/MojSkin/VeeKitchen/compare/v0.3.0...HEAD
[0.3.0]: https://github.com/MojSkin/VeeKitchen/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/MojSkin/VeeKitchen/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/MojSkin/VeeKitchen/commits/v0.1.0
