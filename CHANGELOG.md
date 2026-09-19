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

### Added
- Skills copied from sibling projects: `persian-writing`, `ui-ux-pro-max`, `frontend-design` (under `.claude/skills` and `.agents/skills`).
- Project rules in `.ai/rules/`: git workflow (no direct `main` pushes, feature branches from `development`, changelog-driven versioning) and skills policy.
- Persian README with setup guide, demo accounts, ports table, and roadmap.
- `CHECKLIST.md` to track completed/pending tasks and avoid duplicate work.
- `CHANGELOG.md` (this file) as the single source for release versioning.
- Phase 1 implementation plan: `docs/PHASE-1-PLAN.md` (order/payment/KDS/real-time core).

### Changed
- README replaced (stock Laravel → project-specific, Persian).
- README and CHANGELOG aligned with the git workflow rules (branch diagram with tag/release flow, roadmap carries version tags, demo accounts flagged as pending the seeder).

### Docs
- `README.md` — branch model section now documents the full release flow (`development → testing → main + production → tag`).

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
