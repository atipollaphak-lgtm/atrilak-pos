# Category Pricing Rounding Override Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** เพิ่ม `categories.rounding_override` สำหรับการขายแบบ Delivery ให้เลือกปัดหลัง Zone Markup โดยยังคง CategoryPricingRule เดิมทุกพฤติกรรม

**Architecture:** เพิ่มค่าปัดแบบ nullable ใน Category และให้ `ZonePricingService` เลือกค่าจาก Category ก่อน Zone เฉพาะเมื่อเป็น Delivery; Pickup จะ bypass Zone Pricing ตามเดิม. Sale snapshot จะบันทึกค่าปัดสุดท้ายที่ใช้จริงโดยไม่ให้ Sale Edit คำนวณ snapshot เดิมใหม่.

**Tech Stack:** Laravel 13, PHP 8.3, PostgreSQL, Decimal service เดิม, Blade, vanilla JavaScript, PHPUnit, Node test runner.

## Global Constraints

- คง `CategoryPricingRule`, `CategoryPricingService`, `PricingService`, UI, Route, Logic และ Tests เดิมทั้งหมดไว้
- `rounding_override` ใช้เฉพาะ Zone Pricing ของ Delivery
- NULL หมายถึงใช้ Default Rounding ของ Zone
- Pickup ใช้ Global Pricing และไม่ใช้ Zone Markup หรือ Category Rounding Override
- ห้ามแก้ Production, Production `.env`, Production Database หรือสร้าง Production data
- ใช้ Migration ใหม่เท่านั้น; ห้ามแก้ Migration ที่ Deploy แล้ว
- ใช้ Decimal/Numeric และ Constraint เฉพาะค่า NULL, 0.25, 0.50, 1.00, 5.00, 10.00

---

### Task 1: Add Category Rounding Override Schema and Form Contract

**Files:**
- Create: `database/migrations/2026_07_28_000005_add_rounding_override_to_categories.php`
- Modify: `app/Models/Category.php`
- Modify: `app/Http/Controllers/CategoryController.php`
- Modify: `resources/views/categories/index.blade.php`
- Test: `tests/Feature/Categories/CategoryRoundingOverrideTest.php`

- [ ] Write failing tests for default NULL, supported values, invalid values, and Category CRUD persistence
- [ ] Run the focused Category test and confirm it fails before implementation
- [ ] Add nullable `decimal('rounding_override', 4, 2)` with PostgreSQL CHECK constraint for the five supported values
- [ ] Add model cast and controller validation with Thai validation messages
- [ ] Add the nullable select to the existing Category create/edit form without changing CategoryPricingRule UI
- [ ] Run the focused Category test and confirm it passes

### Task 2: Resolve Category Override in Zone Pricing

**Files:**
- Modify: `app/Services/Sales/ZonePricingService.php`
- Modify: `app/Services/Sales/DeliveryResolverService.php` only if the existing payload needs the category value
- Test: `tests/Unit/Services/Sales/ZonePricingServiceTest.php`
- Test: `tests/Feature/DeliveryZones/DeliveryZonePricingTest.php`

- [ ] Add failing cases for category 0.25, category NULL with zone 5.00, and Pickup bypass
- [ ] Run focused pricing tests and confirm the new cases fail
- [ ] Add a resolver that receives the product/category context and returns the effective increment: Category override first, Zone default second
- [ ] Preserve base price resolution, CategoryPricingRule behavior, Zone Markup, Minimum Profit and Delivery Fee order
- [ ] Return the effective rounding increment in the pricing result for frontend and snapshot consumers
- [ ] Run focused Zone Pricing tests and confirm the new cases pass

### Task 3: Wire Backend Sale Snapshot and Sale Edit

**Files:**
- Modify: `app/Services/SaleService.php`
- Modify: `app/Models/Sale.php` only if the existing snapshot cast/fillable contract needs the resolved value
- Test: `tests/Feature/Sales/SaleV3CartWorkflowTest.php`
- Test: `tests/Feature/Sales/SaleSnapshotPreservingUpdateTest.php`
- Test: `tests/Feature/Sales/SaleCommissionLifecycleCharacterizationTest.php`

- [ ] Add failing tests proving Sale stores the final Category-or-Zone increment and markup snapshot
- [ ] Add failing test proving Sale Edit preserves the existing snapshot instead of resolving current Category/Zone rules
- [ ] Pass the selected Product Category into authoritative backend pricing
- [ ] Store the effective increment only for Delivery and retain Pickup snapshot behavior
- [ ] Keep CategoryPricingRule as the source of base selling price and do not connect its rule record to rounding override
- [ ] Run focused Sale, Snapshot and Commission regression tests

### Task 4: Wire POS V3 Frontend Consistency

**Files:**
- Modify: `resources/views/sales-v3/partials/product-grid.blade.php`
- Modify: `public/js/modules/sale-v3.js`
- Test: `tests/Feature/Sales/SaleV3CartWorkflowTest.php`
- Test: `tests/Frontend/zone-pricing.test.mjs`

- [ ] Add failing frontend assertions for category 0.25 and category NULL falling back to zone 5.00
- [ ] Include only the category rounding context in product card data; leave existing pricing fields unchanged
- [ ] Apply Category Override after Zone Markup and before Zone Default Rounding only for Delivery
- [ ] Keep Pickup on existing product/global rounding path with zero Zone pricing
- [ ] Confirm frontend and backend produce 6.50 for 6.30 + 3% + 0.25 and 170 for 162 + 3% + zone 5.00
- [ ] Run JavaScript syntax and frontend tests

### Task 5: Full Scoped Verification and Commit

**Files:**
- No additional runtime files beyond Tasks 1-4

- [ ] Run Category, Pricing, Zone Pricing, Sale V3, Snapshot and Frontend tests
- [ ] Run the existing CategoryPricingRule/Pricing Management tests and confirm baseline behavior is unchanged
- [ ] Run `vendor/bin/pint --test`, JavaScript syntax checks, and `git diff --check`
- [ ] Create an isolated Test DB, migrate only there, and run Browser Smoke without creating Sale/Purchase/Stock Movement
- [ ] Compare Full Suite results against the known baseline and report unchanged baseline failures without fixing them
- [ ] Review the final diff for scope and sensitive files
- [ ] Commit once on `codex/category-pricing-rounding`; do not merge, push `main`, or deploy
