# Production Go-Live Readiness Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** ทำให้ ATRILAK POS พร้อมสำหรับ controlled go-live ครบทั้ง 7 โมดูล โดยพิสูจน์ความถูกต้องของยอดขาย เงิน สต็อก เอกสาร Closing และ Backup บน Test Environment

**Architecture:** ต่อยอด Service, Form Request, Model, route และ view ที่มีอยู่ โดยให้ Controller ทำ orchestration เท่านั้น และให้ transaction/lock/idempotency อยู่ใน Service ที่เป็นเจ้าของ business invariant ใช้ Stock Movement และ Payment history เป็น audit trail ไม่แก้ข้อมูลธุรกรรมย้อนหลังแบบสูญประวัติ

**Tech Stack:** Laravel 13, PHP 8.3, PostgreSQL, PHPUnit, Laravel Pint, Vite/JavaScript เดิม, Laragon สำหรับ browser smoke test

## Global Constraints

- ใช้ `.env.testing` และฐานข้อมูล `atrilak_pos_final_test_20260729` เท่านั้นสำหรับ destructive test/reset/fixture
- ห้ามแตะไฟล์ untracked เดิม `design-qa.md` และ `docs/superpowers/plans/2026-07-29-final-pos-hold-bill.md`
- ห้ามเปลี่ยน Average Cost, pricing/rounding, tier pricing, Profit Guard, delivery fee, technician commission, unit conversion, sale numbering หรือ stock movement semantics
- ห้ามแก้ migration เก่า; schema change ใช้ migration ใหม่เท่านั้น
- ห้าม hard delete Sale, Payment, Stock Movement หรือ Daily Closing ที่ต้องตรวจสอบย้อนหลัง
- Product row locks ต้องใช้ Product IDs ที่เรียงจากน้อยไปมาก
- เงินใช้ decimal-safe arithmetic และ backend เป็น source of truth
- รักษา POS V1/V2/V3, routes, request payloads และเอกสารเดิม

---

### Task 1: Test preflight and baseline characterization

**Files:**
- Create: `tests/Feature/GoLive/GoLivePreflightTest.php`
- Create: `docs/superpowers/reports/2026-08-01-go-live-preflight.md`
- Inspect only: `.env.testing`, `config/database.php`, `routes/web.php`, `composer.json`, relevant migrations and existing feature tests

**Interfaces:**
- Consumes: `.env.testing`, Laravel application config, PostgreSQL test connection
- Produces: verified test database identity, migration state, baseline table counts and a list of existing regression coverage

- [ ] **Step 1: Write the failing preflight assertions**

  Add tests that assert `app()->environment('testing')`, the configured database name is `atrilak_pos_final_test_20260729`, and required tables (`products`, `sales`, `payments`, `stock_movements`, `daily_payment_closings`, `settings`) exist.

- [ ] **Step 2: Run the preflight test**

  Run: `php artisan test --env=testing tests/Feature/GoLive/GoLivePreflightTest.php`

  Expected: the test either passes against the named test database or fails before any mutation with an explicit identity/setup error.

- [ ] **Step 3: Record read-only baseline data**

  Capture migration status, database identity, storage link state, application URL, PHP/Laravel versions and counts for users, roles, products, product units, categories, customers, suppliers, purchases, purchase items, sales, sale items, payments, stock movements, daily closings, price histories and settings.

- [ ] **Step 4: Run the preflight test again and review the report**

  Run: `php artisan test --env=testing tests/Feature/GoLive/GoLivePreflightTest.php`

  Expected: PASS with no writes to business tables; report contains masked/non-sensitive baseline references only.

- [ ] **Step 5: Commit the preflight artifact**

  Run: `git add -- tests/Feature/GoLive/GoLivePreflightTest.php docs/superpowers/reports/2026-08-01-go-live-preflight.md && git commit -m "test: add go-live test preflight"`

### Task 2: Logo lifecycle and document fallback

**Files:**
- Modify: `app/Http/Controllers/SettingController.php`
- Modify: `app/Http/Requests/UpdateSettingRequest.php`
- Modify: `resources/views/settings/index.blade.php`
- Modify: `resources/views/sales/invoice.blade.php`
- Modify: `resources/views/sales/print.blade.php`
- Modify: `resources/views/sales/invoice_v2/header/company.blade.php`
- Modify: `resources/views/sales/invoice_v2/header.blade.php`
- Modify: `resources/views/sales/invoice_v2/delivery-note.blade.php`
- Modify: `resources/views/quotations/print.blade.php`
- Test: `tests/Feature/Settings/SettingUpdateTest.php`
- Test: `tests/Feature/Documents/TransactionDocumentSnapshotTest.php`

**Interfaces:**
- Consumes: `Setting::logo_image`, `Storage::disk('public')`, existing document view data
- Produces: validated logo replacement/deletion behavior and a render-safe logo URL or null fallback

- [ ] **Step 1: Add failing tests**

  Cover valid image upload, invalid MIME/size rejection, missing stored file fallback, replacement without deleting QR, and document rendering with and without a logo.

- [ ] **Step 2: Run the focused tests**

  Run: `php artisan test --env=testing tests/Feature/Settings/SettingUpdateTest.php tests/Feature/Documents/TransactionDocumentSnapshotTest.php`

  Expected: new lifecycle/fallback assertions fail while existing behavior remains characterized.

- [ ] **Step 3: Implement minimal lifecycle protection**

  In `SettingController`, capture the old logo path before update, store the new validated image on the public disk, update the setting, then delete only the old logo when it differs from the new path and is not the QR path. Pass a null/empty-safe logo value to document views and use `Storage::disk('public')->exists()` before generating URLs.

- [ ] **Step 4: Update document templates**

  Render the logo block only when the resolved file exists; preserve QR markup, A4/A5 layout, snapshots and existing text positioning.

- [ ] **Step 5: Run focused tests and syntax checks**

  Run: `php artisan test --env=testing tests/Feature/Settings/SettingUpdateTest.php tests/Feature/Documents/TransactionDocumentSnapshotTest.php`; `php -l app/Http/Controllers/SettingController.php`; `git diff --check`

  Expected: PASS and no broken-image output path is generated for a missing file.

### Task 3: Sale edit and void safety

**Files:**
- Inspect/modify: `app/Services/SaleService.php`
- Inspect/modify: `app/Services/Sales/StockService.php`
- Inspect/modify: `app/Services/Sales/SalePaymentResolver.php`
- Inspect/modify: `app/Http/Controllers/SaleController.php`
- Inspect/modify: `app/Http/Requests/Sales/UpdateSaleRequest.php`
- Inspect/modify: `app/Http/Requests/Sales/VoidSaleRequest.php`
- Inspect/modify: `resources/views/sales/edit.blade.php`, `resources/views/sales/partials/void-sale-modal.blade.php`, `resources/views/sales/partials/void-document-marker.blade.php`
- Test: `tests/Feature/Sales/SaleEditSafetyTest.php`
- Test: `tests/Feature/Sales/SaleUpdateTest.php`
- Test: `tests/Feature/Sales/SaleVoidLifecycleTest.php`
- Test: `tests/Feature/Sales/SaleVoidPresentationTest.php`
- Test: `tests/Feature/Sales/SalePaymentUpdateTest.php`

**Interfaces:**
- Consumes: existing sale revision, sale idempotency, stock lock, commission lifecycle and payment snapshot services
- Produces: one transactional edit/void path with authoritative stock/payment outcomes and safe error messages

- [ ] **Step 1: Add or strengthen failing regression tests**

  Cover add/remove/quantity change, unit change, pickup/delivery zone change, total increase requiring an additional payment, total decrease restriction or safe adjustment, void idempotency, stock restoration once, commission/report exclusion, and edit/void permission boundaries.

- [ ] **Step 2: Run the sale regression set**

  Run: `php artisan test --env=testing tests/Feature/Sales/SaleEditSafetyTest.php tests/Feature/Sales/SaleUpdateTest.php tests/Feature/Sales/SaleVoidLifecycleTest.php tests/Feature/Sales/SaleVoidPresentationTest.php tests/Feature/Sales/SalePaymentUpdateTest.php`

  Expected: only the newly exposed gaps fail; failures must identify the violated invariant rather than setup noise.

- [ ] **Step 3: Implement the smallest service-level fix**

  Keep product locks sorted, lock the sale and items, validate revision before writes, route stock differences through `StockService`, preserve original payment records, and reject unsafe downward-total edits with a Thai explanation if no supported refund workflow exists.

- [ ] **Step 4: Make the void path idempotent and permission-safe**

  Keep the existing `voided` lifecycle, reason, actor and timestamp; ensure repeated requests do not create movement/payment/commission effects and that already-voided sales cannot be edited.

- [ ] **Step 5: Run related sales and reporting tests**

  Run: `php artisan test --env=testing tests/Feature/Sales tests/Feature/Reports tests/Feature/DailyPaymentClosings`; `php -l app/Services/SaleService.php`; `git diff --check`

  Expected: PASS or clearly isolated baseline failures, with no change to protected pricing/profit rules.

### Task 4: Stock Adjustment workflow

**Files:**
- Inspect/modify: `app/Services/StockCountService.php`
- Inspect/modify: `app/Http/Controllers/StockCountController.php`
- Inspect/modify: `app/Http/Requests/StockCounts/StoreStockCountRequest.php`
- Inspect/modify: `app/Models/StockCount.php`, `app/Models/StockCountItem.php`, `app/Models/StockMovement.php`
- Inspect/modify: `routes/web.php`
- Modify: `resources/views/stock-counts/index.blade.php`
- Test: `tests/Feature/Stock/StockCountIntegrityTest.php`
- Test: `tests/Feature/Stock/StockCountNumberTest.php`
- Create: `tests/Feature/Stock/StockAdjustmentPermissionTest.php`

**Interfaces:**
- Consumes: `StockLockService::lockProducts`, `StockCountNumberService`, existing `ADJUST` movement type and role middleware
- Produces: one atomic count/adjustment operation with before/after quantities and role enforcement

- [ ] **Step 1: Add failing service and permission tests**

  Cover increase, decrease, decimal quantity, zero quantity, duplicate product rejection, negative quantity rejection, movement before/after, double submit behavior, guest/cashier rejection and manager/owner access.

- [ ] **Step 2: Run the focused stock tests**

  Run: `php artisan test --env=testing tests/Feature/Stock/StockCountIntegrityTest.php tests/Feature/Stock/StockCountNumberTest.php tests/Feature/Stock/StockAdjustmentPermissionTest.php`

  Expected: the missing workflow/permission assertions fail before implementation.

- [ ] **Step 3: Implement service invariants**

  Lock products in ascending ID order, derive system quantity from the locked Product, normalize decimal quantity with `BigDecimal`, create the StockCount/Item and one `ADJUST` movement inside one transaction, then update authoritative product stock only after movement values are known.

- [ ] **Step 4: Wire role and UI behavior**

  Match the existing route role groups, display product code/barcode/category/base unit/system quantity and computed difference, and prevent repeat submission with the established loading state.

- [ ] **Step 5: Run stock regression and syntax checks**

  Run: `php artisan test --env=testing tests/Feature/Stock tests/Feature/Database/StockCountDecimalMigrationTest.php`; `php -l app/Services/StockCountService.php`; `git diff --check`

  Expected: PASS with no direct stock writes outside the transaction/service.

### Task 5: Daily Closing production workflow

**Files:**
- Inspect/modify: `app/Services/Sales/DailyPaymentClosingService.php`
- Inspect/modify: `app/Services/Sales/DailyPaymentSummaryService.php`
- Inspect/modify: `app/Services/Sales/DailyPaymentClosingDriftService.php`
- Inspect/modify: `app/Http/Controllers/DailyPaymentClosingController.php`
- Inspect/modify: `app/Http/Requests/DailyPaymentClosings/*.php`
- Modify: `resources/views/daily-payment-closings/form.blade.php`, `show.blade.php`, `print.blade.php`
- Test: `tests/Feature/DailyPaymentClosings/DailyPaymentClosingWorkflowTest.php`
- Test: `tests/Feature/DailyPaymentClosings/DailyPaymentClosingConcurrencyTest.php`
- Test: `tests/Feature/DailyPaymentClosings/DailyPaymentClosingDriftTest.php`
- Test: `tests/Feature/DailyPaymentClosings/DailyPaymentClosingPresentationTest.php`

**Interfaces:**
- Consumes: `DailyPaymentSummaryService`, `SalePaymentResolver`, decimal service, closing revision and snapshot relation
- Produces: decimal-safe open/update/finalize/reopen workflow with variance and drift evidence

- [ ] **Step 1: Add failing coverage for cash/mixed/shortage/overage/drift**

  Assert expected cash, actual cash, change, cash variance, PromptPay variance, mixed-payment totals, void exclusion, revision conflict, finalize idempotency and post-finalize immutability.

- [ ] **Step 2: Run the focused closing suite**

  Run: `php artisan test --env=testing tests/Feature/DailyPaymentClosings`

  Expected: new assertions fail only where current behavior does not meet the Spec.

- [ ] **Step 3: Implement the minimal service fix**

  Keep all calculations in `DailyPaymentSummaryService`/`SaleDecimalService`, lock the closing during update/finalize/reopen, write immutable sale snapshots at finalize, and derive shortage/overage as actual minus expected without floating point arithmetic.

- [ ] **Step 4: Update presentation and authorization**

  Show expected, actual, variance and drift status in Thai; preserve manager/owner route boundaries and disable actions after finalize unless the existing reopen permission applies.

- [ ] **Step 5: Run related reports and closing tests**

  Run: `php artisan test --env=testing tests/Feature/DailyPaymentClosings tests/Feature/Reports`; `php -l app/Services/Sales/DailyPaymentClosingService.php`; `git diff --check`

  Expected: PASS and no change to protected business formulas.

### Task 6: Purchase receiving source validation

**Files:**
- Inspect/modify: `app/Services/PurchaseService.php`
- Inspect/modify: `app/Services/Purchases/PurchaseValidationService.php`
- Inspect/modify: `app/Http/Requests/Purchases/StorePurchaseRequest.php`, `UpdatePurchaseRequest.php`
- Inspect/modify: `app/Http/Controllers/PurchaseController.php`
- Modify: `resources/views/purchases/index.blade.php`, `edit.blade.php`
- Test: `tests/Feature/Purchases/PurchaseIntegrityTest.php`
- Test: `tests/Feature/Purchases/PurchaseValidationTest.php`

**Interfaces:**
- Consumes: existing Purchase Service, AverageCostService, StockLockService and purchase source fields
- Produces: validated own-production/purchased receiving with one stock movement and unchanged selling price

- [ ] **Step 1: Add failing tests**

  Cover supplier required for purchased source, supplier optional for own production, invalid unit/product/quantity rejection, average cost update, selling price unchanged, movement count exactly once and double-submit protection.

- [ ] **Step 2: Run purchase tests**

  Run: `php artisan test --env=testing tests/Feature/Purchases/PurchaseIntegrityTest.php tests/Feature/Purchases/PurchaseValidationTest.php`

  Expected: source-specific gaps fail with validation assertions.

- [ ] **Step 3: Implement source validation without changing cost rules**

  Normalize source in the Form Request/validation service, require Supplier only for purchased source, keep AverageCostService as the sole cost calculator, preserve existing selling price and create movement inside the same transaction.

- [ ] **Step 4: Run purchase and pricing regression tests**

  Run: `php artisan test --env=testing tests/Feature/Purchases tests/Feature/Pricing`; `php -l app/Services/PurchaseService.php`; `git diff --check`

  Expected: PASS with no selling-price mutation.

### Task 7: Backup manifest, file coverage and restore safety

**Files:**
- Inspect/modify: `app/Services/Backup/DatabaseBackupService.php`
- Inspect/modify: `app/Services/Backup/DatabaseBackupResult.php`
- Inspect/modify: `app/Services/Backup/DatabaseRestoreService.php`
- Inspect/modify: `app/Http/Controllers/BackupController.php`
- Inspect/modify: `resources/views/backups/index.blade.php`
- Inspect/modify: backup configuration/commands discovered in `app/Console`, `config` and `routes/web.php`
- Test: `tests/Feature/Backup/DatabaseBackupServiceTest.php`
- Test: `tests/Feature/Backup/DatabaseRestoreServiceTest.php`
- Test: `tests/Feature/Backup/BackupEntryPointTest.php`
- Test: `tests/Feature/Backup/RestoreDatabaseCommandTest.php`
- Create: `tests/Feature/Backup/BusinessFileCoverageTest.php`

**Interfaces:**
- Consumes: existing PostgreSQL backup/restore services, storage paths and test database connection
- Produces: non-empty database backup plus manifest/checksum/file coverage evidence and restore validation against a separate test target

- [ ] **Step 1: Add failing backup coverage assertions**

  Assert backup file exists and is non-empty, database identity is recorded, checksum is stable, business file paths include logo/QR/product images, and restore refuses a source/target identity mismatch.

- [ ] **Step 2: Run backup tests without restoring the application database**

  Run: `php artisan test --env=testing tests/Feature/Backup`

  Expected: failures identify missing manifest/file coverage or unsafe target handling; no Production connection is attempted.

- [ ] **Step 3: Implement manifest and safe target checks**

  Extend the existing result/manifest path rather than duplicating backup logic; collect only relative business-file paths, compute SHA-256, verify non-empty dump, and require an explicitly different Test Database for restore verification.

- [ ] **Step 4: Verify restore on isolated test database**

  Create/use a separately named PostgreSQL test database, restore there, compare migration status/counts/sample references, then remove only that temporary test database if the environment permits. Do not call `migrate:fresh`, `db:wipe` or restore against the source database.

- [ ] **Step 5: Run backup tests and syntax checks**

  Run: `php artisan test --env=testing tests/Feature/Backup`; `php -l app/Services/Backup/DatabaseBackupService.php`; `git diff --check`

  Expected: PASS with a recorded backup filename, size, SHA-256, coverage list and restore result.

### Task 8: Integrated E2E verification and handoff

**Files:**
- Create/modify: `tests/Feature/GoLive/GoLiveRegressionTest.php`
- Create: `docs/superpowers/reports/2026-08-01-production-go-live-final-report.md`
- Inspect only during verification: `routes/web.php`, `storage/logs/laravel.log`, browser console/network, rendered document previews

**Interfaces:**
- Consumes: all completed module behavior and Test Database fixtures prefixed `TEST-GOLIVE-`
- Produces: automated suite results, browser smoke evidence, final diff review and one implementation commit containing only in-scope files

- [ ] **Step 1: Add integrated fixture and regression scenarios**

  Use `TEST-GOLIVE-` product/customer/supplier names and exercise purchase source flows, pickup/delivery sale, mixed payment, hold bill, sale edit/void, stock adjustment, daily closing, documents and backup.

- [ ] **Step 2: Run targeted and related tests**

  Run the task-specific commands above, then: `php artisan test --env=testing tests/Feature/GoLive tests/Feature/Sales tests/Feature/Purchases tests/Feature/Stock tests/Feature/DailyPaymentClosings tests/Feature/Backup tests/Feature/Documents tests/Feature/Settings`

  Expected: all new tests pass; existing failures are named and classified as baseline or regression.

- [ ] **Step 3: Run quality checks**

  Run: `Get-ChildItem app,config,routes,database,tests -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }`; `vendor/bin/pint --test`; `git diff --check`; `npm run build` if frontend assets changed.

- [ ] **Step 4: Perform browser smoke test on Test Environment**

  With Laragon/Apache/PostgreSQL running in the Test Environment, verify at 1280×720: no page/modal overflow, validation/loading/success states, refresh after save, no new console errors, no network 500, A4/A5 logo/QR rendering, Receive Stock, Stock Adjustment and Daily Closing. Record exact failures; do not claim browser pass without the browser run.

- [ ] **Step 5: Review scope and commit implementation**

  Run: `git status --short`; `git diff --stat`; `git diff --check`; verify no `.env`, backup, dump, personal data or unrelated untracked file is staged; then `git add -- <only in-scope files> && git commit -m "feat: complete production go-live readiness sprint"`.

- [ ] **Step 6: Complete final report**

  Record preflight identity/counts, root causes, files changed, tests and counts, browser verification, backup/restore evidence, Git commit, remaining limitations and final verdict (`READY FOR CONTROLLED GO-LIVE`, `READY WITH DOCUMENTED LIMITATIONS`, or `NOT READY`).
