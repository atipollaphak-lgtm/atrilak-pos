# Zone Pricing Rounding Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a per-delivery-zone ceiling rounding increment to Zone Pricing without changing the central Pricing Engine or Pickup pricing.

**Architecture:** Store the allowed increment on `delivery_zones`, expose it through the existing `DeliveryZone` model and address payload, and apply it inside the existing `ZonePricingService` after zone markup. Store the applied zone markup, rounding increment, and minimum profit on the existing Sale snapshot columns; keep POS V3 preview behavior aligned with the service using integer-cent arithmetic for the zone-specific ceiling operation.

**Tech Stack:** Laravel 13, PHP 8.3, PostgreSQL, Brick\Math, Blade, vanilla JavaScript, PHPUnit/Pest project test tooling, Laravel Pint.

## Global Constraints

- Modify only files directly involved in Zone Pricing, POS V3, Sale snapshot persistence, and their tests.
- Do not modify the central Pricing Engine or existing product rounding behavior.
- Allowed zone increments are exactly `0.25`, `0.50`, `1.00`, `5.00`, and `10.00`; default is `0.25`.
- Apply zone rounding only for Delivery with an active zone; Pickup uses the base price and existing non-zone behavior.
- Backend recalculates authoritative prices before Sale persistence; browser values are not authoritative.
- Do not run migrations, tests, or diagnostics against Production; do not merge or deploy.

---

### Task 1: Add failing service and validation tests

**Files:**
- Modify: `tests/Unit/Services/Sales/ZonePricingServiceTest.php`
- Modify: `tests/Feature/DeliveryZones/DeliveryZonePricingTest.php`
- Modify: `tests/Support/CreatesSaleTransactionTestSchema.php`
- Modify: `tests/Feature/Sales/SaleV3StoreTest.php` only where existing Zone Pricing fixtures need the new column

**Interfaces:**
- Consumes: current `ZonePricingService::priceLine()` and DeliveryZone update route.
- Produces: executable expectations for the five increments, pickup bypass, allowed-value validation, and default persistence.

- [ ] **Step 1: Add service tests for all allowed increments**

Use a base price of `6.30`, markup `3.00`, and assert the Delivery result is `6.50` for `0.25`, `6.50` for `0.50`, `7.00` for `1.00`, `10.00` for `5.00`, and `10.00` for `10.00`; also assert `6.48900000` before zone rounding.

- [ ] **Step 2: Add a pickup regression test**

Use the same product and zone but pass `true` for pickup; assert the result remains the existing product price and does not use `rounding_increment`.

- [ ] **Step 3: Add feature validation/default tests**

Assert a newly created zone stores `0.25` when the field is omitted, accepts each allowed value, and rejects `0.10`, `0.30`, and negative values with a validation response.

- [ ] **Step 4: Update only the isolated transaction schema fixture**

Add the new decimal zone column and Sale snapshot column to the test-only schema so Sale tests can exercise the new fields without changing unrelated schemas.

- [ ] **Step 5: Run the new tests and confirm they fail**

Run `php artisan test --filter=ZonePricingServiceTest` and `php artisan test --filter=DeliveryZonePricingTest`.

Expected: failures identify the missing column, validation, service rounding, and snapshot behavior.

### Task 2: Add forward-safe persistence and model contracts

**Files:**
- Create: `database/migrations/2026_07_28_000004_add_zone_rounding_increment_snapshots.php`
- Modify: `app/Models/DeliveryZone.php`
- Modify: `app/Models/Sale.php` only for the existing snapshot fillable/cast contract

**Interfaces:**
- Consumes: existing `delivery_zones` and `sales` tables.
- Produces: `delivery_zones.rounding_increment` and `sales.delivery_zone_rounding_increment_snapshot`, both nullable-safe for historical rows, with new-zone default `0.25`.

- [ ] **Step 1: Write the migration**

Add `rounding_increment DECIMAL(4,2) NOT NULL DEFAULT 0.25` to `delivery_zones` and `delivery_zone_rounding_increment_snapshot DECIMAL(4,2) NULL` to `sales`; use a PostgreSQL-compatible check constraint for the five allowed values and provide a reversible `down()`.

- [ ] **Step 2: Add model fillable/casts/constants**

Expose the new field as a decimal string and define the allowed increment list in the existing Zone model or the existing Zone Pricing service without introducing a new table or global pricing abstraction.

- [ ] **Step 3: Run the migration on the local/test database only**

Run the project’s local migration/test setup and verify the new columns, default, and rollback path without referencing Production configuration.

### Task 3: Implement authoritative zone ceiling rounding and Sale snapshot

**Files:**
- Modify: `app/Services/Sales/ZonePricingService.php`
- Modify: the existing Sale creation/snapshot service file identified by the current `delivery_zone_markup_percent_snapshot` write path
- Modify: `app/Http/Controllers/SaleV3Controller.php` only if the existing orchestration must pass the new snapshot field

**Interfaces:**
- Consumes: base/tier-resolved price, active DeliveryZone, pickup flag, and existing Sale transaction flow.
- Produces: the existing price result keys plus `rounding_increment`, with Sale snapshots written atomically from the resolved zone.

- [ ] **Step 1: Implement zone-only ceiling rounding with Brick\Math**

Resolve the zone increment as `0.25` when a Delivery zone has no value, skip the zone operation for Pickup, divide the marked-up price by the increment, apply `CEILING`, then multiply back. Keep the product’s existing rounding call untouched and do not alter `PricingService` or `pricing-rounding.js`.

- [ ] **Step 2: Include the resolved increment in the service result**

Return a decimal-string `rounding_increment` so the Sale flow and any existing response context use the same authoritative value.

- [ ] **Step 3: Persist the three zone snapshot values**

Write markup, increment, and minimum profit through the existing Sale snapshot path in the same transaction; leave historical rows nullable and unchanged.

- [ ] **Step 4: Run service and snapshot tests**

Run the focused Zone Pricing and Sale snapshot tests and confirm all pass.

### Task 4: Update settings UI and POS V3 preview/repricing

**Files:**
- Modify: `app/Http/Controllers/DeliveryZoneController.php`
- Modify: `resources/views/delivery-zones/_form.blade.php`
- Modify: `resources/views/delivery-zones/index.blade.php`
- Modify: `resources/views/delivery-zones/create.blade.php` and `edit.blade.php` only if their layout does not use the shared form
- Modify: the existing Zone Pricing settings JavaScript file if present, otherwise create the smallest `public/js/modules/zone-pricing.js`
- Modify: `public/js/modules/sale-v3.js`

**Interfaces:**
- Consumes: allowed increment list and zone data already rendered/returned for POS V3.
- Produces: validated form select, live preview, visible list value, and immediate card/cart repricing on zone or pickup changes.

- [ ] **Step 1: Validate the exact increment whitelist in the controller**

Use the existing request validation style and Thai error text; preserve all existing fields and permissions.

- [ ] **Step 2: Add the rounding select and live preview**

Render the five values, default to `0.25`, show base price, markup amount, pre-round price, rounding increment, and final ceiling price, and update on markup/increment/trial-price input without submitting or persisting.

- [ ] **Step 3: Show the increment in the zone list**

Add one readable column while preserving the current table/action structure.

- [ ] **Step 4: Align POS V3 zone repricing**

Use the zone increment only when Delivery is active; recalculate cards and cart on address/zone/pickup changes. Keep Product rounding behavior unchanged and use cent-based integer arithmetic for the new zone ceiling step so browser output matches the backend for the supported increments.

- [ ] **Step 5: Run JavaScript syntax and focused browser-facing tests**

Run `node --check` on changed modules and the existing frontend test command for the changed helper/module.

### Task 5: Full scoped verification and commit

**Files:**
- Modify only files already listed above.

**Interfaces:**
- Consumes: completed implementation and tests.
- Produces: verified Feature Branch commit, with no merge or deployment.

- [ ] **Step 1: Run focused Laravel tests**

Run `php artisan test --filter=ZonePricingServiceTest`, `php artisan test --filter=DeliveryZonePricingTest`, and the relevant Sale V3/snapshot filters.

- [ ] **Step 2: Run the scoped regression suite**

Run the project’s PostgreSQL-backed Zone Pricing, Sale V3, snapshot, and document tests that directly touch changed files; report any pre-existing unrelated failures separately.

- [ ] **Step 3: Run formatting and diff checks**

Run `vendor/bin/pint --test` and `git diff --check`.

- [ ] **Step 4: Perform Browser Smoke on local/review only**

Verify Zone settings, live preview, Delivery card/cart repricing, Pickup bypass, and browser console/network behavior without creating Production data.

- [ ] **Step 5: Review, stage, and commit only scoped files**

Verify `git status --short`, stage only the approved files, and commit with `feat: add zone pricing rounding increments`. Do not merge, push, or deploy.
