# Receive Stock V2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task with review checkpoints. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** เพิ่ม workflow รับสินค้าเข้าแบบ V2 ที่รองรับซื้อจาก Supplier/ผลิตเอง, Preview/Confirm, Average Cost, Base-unit Stock Movement, Idempotency และประวัติ/รายละเอียด โดยไม่เปลี่ยนราคาขายหรือกฎต้นทุนเดิม

**Architecture:** คง `/purchases` และ `PurchaseService` เดิมไว้เพื่อไม่ให้ workflow เดิม regression แล้วเพิ่ม `ReceiveStockController`, `ReceiveStockService`, validation และ preview storage สำหรับ route `receivings.*`. Confirm จะ lock idempotency key, lock Product IDs เรียงจากน้อยไปมาก, ใช้ `AverageCostService` เดิม และบันทึก Purchase/PurchaseItem/Stock Movement ใน transaction เดียว

**Tech Stack:** Laravel 13, PHP 8.3, PostgreSQL production schema, SQLite PHPUnit test database, Blade/AdminLTE, vanilla JavaScript/jQuery, Brick Math

## Global Constraints

- การรับสินค้าเข้าต้องอัปเดต Stock ผ่าน Stock Movement เท่านั้น
- ห้ามเปลี่ยน Selling Price, Price Lock หรือ Apply Pricing Engine อัตโนมัติ
- ต้อง reuse Average Cost Logic เดิมและ lock Product rows ตามลำดับ ID
- Supplier source ต้องใช้ Supplier ที่ active; production source ไม่ต้องมี Supplier
- Confirm ต้องอยู่ใน Database Transaction เดียวและ rollback ทั้งใบเมื่อผิดพลาด
- ต้องป้องกัน double click, refresh, submit ซ้ำ, idempotency และ concurrent stock update
- แก้เฉพาะ Receive Stock V2 และไฟล์ทดสอบ/เอกสารที่เกี่ยวข้อง
- ห้ามแก้ไข/ลบไฟล์ `design-qa.md` และ `docs/superpowers/plans/2026-07-29-final-pos-hold-bill.md`
- ห้ามรัน Migration หรือ diagnostic write บน Production

---

### Task 1: Add forward-safe receiving persistence and domain contracts

**Files:**
- Create: `database/migrations/<timestamp>_add_receive_stock_v2_fields.php`
- Modify: `app/Models/Purchase.php`, `app/Models/PurchaseItem.php`
- Create: `app/Data/Receivings/ReceiveStockPreviewData.php`, `app/Data/Receivings/ReceiveStockResultData.php`
- Test: `tests/Feature/Receivings/ReceiveStockMigrationContractTest.php`

- [ ] **Step 1: Write failing migration/model contract tests** for nullable self-production Supplier, source/status/creator/idempotency fields, item unit/conversion/snapshot fields, and existing legacy row compatibility.
- [ ] **Step 2: Run the focused tests** and confirm the new columns/contracts are absent.
- [ ] **Step 3: Add one forward-safe nullable migration** without backfilling historical Purchase/PurchaseItem rows; preserve existing foreign keys and unique nullable idempotency semantics.
- [ ] **Step 4: Add model fillables, casts, constants, and relationships** for source/status, creator, product unit, and movement snapshots.
- [ ] **Step 5: Run the migration contract test** on the PHPUnit database and inspect the schema diff.

### Task 2: Implement receiving validation, product search, preview storage, and preview calculations

**Files:**
- Create: `app/Services/Receivings/ReceiveStockValidationService.php`
- Create: `app/Services/Receivings/ReceiveStockPreviewStorageService.php`
- Create: `app/Http/Requests/Receivings/PreviewReceiveStockRequest.php`
- Create: `app/Http/Requests/Receivings/ConfirmReceiveStockRequest.php`
- Create: `app/Data/Receivings/ReceiveStockLineData.php`
- Create: `app/Http/Controllers/ReceiveStockController.php` (index/create/search/preview entry points)
- Test: `tests/Unit/Receivings/ReceiveStockValidationServiceTest.php`
- Test: `tests/Feature/Receivings/ReceiveStockPreviewTest.php`

- [ ] **Step 1: Write failing tests** for supplier/production source rules, inactive Product/Supplier/Unit rejection, positive quantity/cost precision, duplicate Product lines, base/non-base unit conversion, line totals, old/new Average Cost preview, owner-bound expiring token, and no writes during preview.
- [ ] **Step 2: Run the focused tests** and confirm meaningful failures caused by missing contracts/services.
- [ ] **Step 3: Implement normalization** for source, supplier document, product/unit IDs, entered quantity/cost, conversion rate, base quantity/base cost, and snapshots without changing Product state.
- [ ] **Step 4: Implement active Product search** by name/product code/Product barcode and return current stock, average cost, selling price, and usable Product Units for scanner-compatible UI selection.
- [ ] **Step 5: Implement cache preview storage** with random owner-bound token, payload hash, pending state, TTL, and delete/ownership checks.
- [ ] **Step 6: Implement preview controller/view data** and verify no Purchase, PurchaseItem, Product, or StockMovement rows are written.

### Task 3: Implement atomic Receive Stock Confirm and idempotency

**Files:**
- Create: `app/Services/Receivings/ReceiveStockService.php`
- Modify: `app/Http/Controllers/ReceiveStockController.php`
- Test: `tests/Feature/Receivings/ReceiveStockConfirmTest.php`
- Test: `tests/Feature/Receivings/ReceiveStockConcurrencyTest.php`

- [ ] **Step 1: Write failing tests** for three-line supplier receipt, self-production without Supplier, unit conversion, Average Cost edge cases, stock/movement snapshots, selling-price and Price Lock invariance, rollback, repeated confirmation, and concurrent same-product receiving.
- [ ] **Step 2: Run the tests** and verify they fail before implementation.
- [ ] **Step 3: Implement Confirm** behind an idempotency lock and a single `DB::transaction()`; revalidate references inside the transaction, lock Product rows ascending by ID, and return an existing Purchase for a repeated idempotency key.
- [ ] **Step 4: Create Purchase/PurchaseItems** with source, supplier document, creator, entered-unit values, base conversion snapshots, and receipt total.
- [ ] **Step 5: Reuse `AverageCostService`** with base quantity/base cost, update only cost/stock/review marker, create `IN` Stock Movement, and never update selling price or Price Lock.
- [ ] **Step 6: Verify rollback leaves zero partial Purchase/PurchaseItem/Movement writes and leaves the idempotency key retryable after failure.

### Task 4: Build V2 history, create, preview, confirm, and detail UI

**Files:**
- Modify: `routes/web.php`, `config/adminlte.php`
- Create: `resources/views/receivings/index.blade.php`
- Create: `resources/views/receivings/create.blade.php`
- Create: `resources/views/receivings/preview.blade.php`
- Create: `resources/views/receivings/show.blade.php`
- Create: `public/js/modules/receive-stock.js`
- Test: `tests/Feature/Receivings/ReceiveStockAuthorizationTest.php`
- Test: `tests/Feature/Receivings/ReceiveStockHistoryTest.php`
- Test: `tests/Feature/Receivings/ReceiveStockBrowserContractTest.php`

- [ ] **Step 1: Write failing browser-contract tests** for manager-only access, route ordering, history filters/pagination, source-dependent Supplier control, search/add/remove rows, preview warning, confirm guard, and detail snapshots.
- [ ] **Step 2: Add `receivings.*` routes** under the existing authenticated `role:manager` group without changing legacy purchase routes.
- [ ] **Step 3: Add history and create forms** with Supplier/production source, supplier document, date, notes, product search by name/code/barcode, unit selector, stock/cost/price read-only information, and multi-line totals.
- [ ] **Step 4: Add Preview/Confirm/Detail pages** including the explicit warning that receiving changes stock and average cost only, not selling price.
- [ ] **Step 5: Add client-side calculations and double-submit guard** while treating server validation/transaction state as authoritative.
- [ ] **Step 6: Run feature/browser contract tests** and inspect the rendered HTML for accessible controls and expected route/form actions.

### Task 5: Regression, browser smoke, PostgreSQL verification, and commit

**Files:**
- Modify only scoped Receive Stock V2 tests or code when a test exposes an in-scope defect.

- [ ] **Step 1: Run targeted Receive Stock tests plus existing Purchase, Stock, Pricing, Product, POS, and Excel Import regression suites.
- [ ] **Step 2: Run PHP syntax checks, Pint, JavaScript syntax checks, and `git diff --check`.
- [ ] **Step 3: Run Browser Smoke on a dedicated SQLite test database:** supplier scenario with three lines, production scenario, and invalid/duplicate confirmation scenario; verify Purchase, stock, average cost, movements, selling price, and Price Lock.
- [ ] **Step 4: Run migration fresh/upgrade checks and PostgreSQL verification only against an explicitly identified non-Production test database; stop if only Production credentials are available.
- [ ] **Step 5: Review the final diff for scope, protected files, Average Cost/Pricing changes, secrets, and migration safety.
- [ ] **Step 6: Commit on `codex/receive-stock-v2` with `feat: add Receive Stock V2 workflow` and report `READY FOR USER REVIEW`; do not merge or deploy.
