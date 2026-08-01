# ATRILAK POS Production Go-Live Readiness Final Report

วันที่: 2026-08-01  
Branch: `codex/category-pricing-rounding`  
Test Environment: `.env.testing`  
Test Database: `atrilak_pos_final_test_20260729`  
PostgreSQL: `17.10` on `127.0.0.1:5432`

## 1. Preflight

- Test runtime identity passed: `APP_ENV=testing`, PostgreSQL connection and approved database name
- Test schema: 49 tables, 101 migrations after fresh migration
- Initial business counts were zero in the approved Test Database
- Production database was identified only by a read-only default-environment inspection; no migration, reset, seed, restore or business write was executed against it
- Test Database was reset with `migrate:fresh --env=testing --force` only after identity checks
- Existing untracked files were preserved and never staged: `design-qa.md`, `docs/superpowers/plans/2026-07-29-final-pos-hold-bill.md`

## 2. Analysis and implementation

### Logo and documents

Root cause: setting replacement did not remove the old Logo, and V2 document templates could render a URL for a missing file.

Fix: validate existing image rules, delete only the replaced Logo on the public disk, preserve QR, and render Logo markup only when the file exists. A4/A5 and legacy document behavior remains compatible.

### Sale Edit and Void

Existing implementation was verified as sufficient. It already uses revision checks, product locks, transactional movement differences, canonical payment fields, commission lifecycle and idempotent void guards. No protected pricing/profit rule was changed.

### Stock Adjustment

Existing `StockCountService` was verified to create `ADJUST` movements atomically with authoritative before/after quantities and decimal validation. Added role/route regression coverage for Guest/Cashier denial and Manager/Owner access.

### Daily Closing

Existing closing services were verified for decimal-safe summary, actual cash, PromptPay, mixed payment, shortage/overage, snapshot, revision, concurrency, finalize/reopen and drift behavior.

### Purchase Receiving

Existing Purchase Service and validation were verified for purchase/own-production source rules, supplier handling, average cost, unit/quantity validation, one movement and unchanged selling price.

### Backup and Restore

Added a manifest beside each successful SQL dump containing database identity, dump SHA-256 and SHA-256/size/relative path for files under `storage/app/public` (including Logo, QR and Product images). Existing restore safety tests remain passing.

### E2E verification

Added integrated route-entry regression coverage. Browser smoke ran against an isolated Laravel Test Server on `127.0.0.1:8000` with `--env=testing`; the Apache vhost `atrilak-pos.test` was not used because it loads the default `.env` database.

POS V3 Browser blocker follow-up: the sale draft now keeps `customerId`, `addressId` and `deliveryType` in one state, builds the payment payload from that state, waits for customer/address loading before closing the search modal, and restores fulfillment before address state for Hold Bill. Backend validation and payment/pricing rules were unchanged.

## 3. Files changed

- `app/Http/Controllers/SettingController.php`
- `app/Services/Backup/DatabaseBackupService.php`
- `resources/views/sales/invoice_v2/header/company.blade.php`
- `resources/views/sales/invoice_v2/delivery-note.blade.php`
- `tests/Feature/Documents/TransactionDocumentSnapshotTest.php`
- `tests/Feature/Settings/SettingUpdateTest.php`
- `tests/Feature/Stock/StockAdjustmentPermissionTest.php`
- `tests/Feature/Backup/DatabaseBackupServiceTest.php`
- `tests/Feature/GoLive/GoLivePreflightTest.php`
- `tests/Feature/GoLive/GoLiveRegressionTest.php`
- `public/js/modules/sale-v3.js`
- `public/js/modules/final-pos.js`
- `tests/Unit/Sales/SaleV3BrowserStateContractTest.php`
- `docs/superpowers/reports/2026-08-01-go-live-preflight.md`
- `docs/superpowers/reports/2026-08-01-production-go-live-final-report.md`

No migration was necessary.

## 4. Automated tests

Passing targeted results:

- Preflight: 2 tests, 11 assertions
- Logo/Documents: 13 tests, 128 assertions
- Sale Update: 5 tests, 21 assertions
- Sale Void lifecycle: 12 tests, 70 assertions
- Sale Void presentation + payment update: 12 tests, 75 assertions
- Stock Adjustment/Count: 27 tests, 176 assertions
- Daily Closing: 32 tests, 220 assertions
- Purchase/Pricing: 37 tests, 177 assertions
- Backup/Restore: 38 tests, 37 passed, 161 assertions, 1 Windows symlink skip
- Integrated Go-Live routes: 2 tests, 16 assertions
- POS V3 state regression: 2 tests, 20 assertions
- POS V3/Sale/Hold Bill/Daily Closing related regression run: 75 tests, 473 assertions, passed, exit code `0`

Full Suite raw result from PHPUnit/JUnit: `671 tests`, `1,283 assertions`, `241 passed`, `413 errors`, `16 failures`, `1 skipped`, `0 incomplete` observed, `6 risky`, exit code `2`. PHPUnit completed the run; it did not stop early. The arithmetic is `671 - 413 - 16 - 1 = 241`.

The first missing-schema failure was `Tests\Feature\Categories\CategoryManagementTest::test_category_index_contains_single_page_management_controls_and_product_count`, after the Business Rules custom-schema tests. Dominant error groups were missing `categories` (221), `users` (38), `sales` (29), `daily_payment_closings` (22), `suppliers` (15), `customers` (15), `settings` (9), `technicians` (8), `daily_payment_closing_sales` (5), `quotations` (5), `delivery_zones` (4), `stock_count_number_counters` (3), and `quotation_items` (2), with remaining errors/failures from other missing tables and assertions.

The root cause is pre-existing test infrastructure: `tests/Support/CreatesBusinessRuleTestSchema.php`, `tests/Support/CreatesSaleTransactionTestSchema.php`, `tests/Support/CreatesCompetingStockWriterTestSchema.php`, and database migration tests intentionally drop/recreate shared PostgreSQL tables inside one PHPUnit process. The next `RefreshDatabase` test can see the static migrated state while tables are absent. This is not a migration or production-code change in this Sprint.

Baseline comparison, run in separate worktree/database (`4bed81a` / `atrilak_pos_baseline_20260801`) versus latest (`f2c23fd` / `atrilak_pos_final_test_20260729`):

- Baseline: 662 tests, 1,261 assertions, 237 passed, 409 errors, 15 failures, 1 skipped, exit code 2.
- Latest: 671 tests, 1,283 assertions, 241 passed, 413 errors, 16 failures, 1 skipped, exit code 2.
- Common tests: no Pass-to-Fail status change was observed.
- Latest added 9 tests; 4 passed and 5 were affected by the same shared-schema isolation behavior when the whole suite was combined.
- Therefore the Full Suite is evidence that the isolation defect persists, not evidence that all 671 workflows are verified.

Quality checks:

- PHP syntax: passed for `app`, `config`, `routes`, `database` and `tests`
- Pint: changed files pass; full-project Pint reports pre-existing formatting violations outside this scope
- `git diff --check`: passed

Migration verification on the isolated Test Database: fresh migration exit code `0`; immediate upgrade migration exit code `0` with `Nothing to migrate`; migration status showed all migrations `Ran`. No migration file was added by this Sprint, so the upgrade path is a no-op relative to the baseline schema.

Follow-up combined automation attempt after Browser verification: `138 tests`, `574 assertions`, `91 passed`, `4 failures`, `43 errors`, `5 risky`, exit code `1`. It was not accepted as a regression gate because the same shared-schema lifecycle defect removed migrated tables during the combined process; representative errors were missing `products`, `users`, `settings`, and `daily_payment_closing_sales`. Backup coverage also saw leftover `TEST-GOLIVE-*` storage files from the Browser fixture, confirming fixture cleanup must be isolated between suites. Existing isolated targeted results remain the valid evidence.

## 5. Browser verification

Isolated Test Server: `http://127.0.0.1:8000`  
Viewport: `1280x720`

Passed read-only pages:

- Login and Dashboard
- POS V3
- Receive Stock
- Pricing Management
- Stock Adjustment
- Daily Closing
- Settings
- Backup

Observed: no Server Error page, no horizontal overflow, and no browser console error/warning on the verified pages. A seeded `TEST-GOLIVE-` fixture was then used. Browser evidence: `/purchases` recorded purchase ID `1` with `TEST-GOLIVE-Supplier`, quantity `10`, total `1,000.00`, and the POS V3 page displayed stock `10.00` and selling price `150.00`. `/sales-v3` created hold bill `HLD-20260801-0001`, displayed the recall dialog, and opened the payment modal with change `50.00` for received cash `350.00` against `300.00`.

The Browser transaction did not complete: the selected fulfillment/customer state was not retained consistently after modal interaction, and the attempted double submit returned the visible validation alert `ข้อมูลการขายไม่ถูกต้อง` with no confirmed Sale record. Therefore sale payment, edit/void, delivery-zone sale, daily closing from the Browser, real upload, print preview, and post-transaction DB assertions are not claimed as passed. Console errors were empty during the observed POS interaction; no new Laravel error was observed in the browser session.

### POS V3 blocker follow-up

The previous paragraph records the pre-fix reproduction. After the minimal state fix, seeded Browser verification at `1280x720` passed: Pickup payment created `SAL-20260801-0001`; Delivery with `TEST-GOLIVE Address` and `TEST-GOLIVE-Zone` plus PromptPay created `SAL-20260801-0002`; Hold Bill `HLD-20260801-0001` resumed with customer/address/delivery/zone/cart retained and created `SAL-20260801-0003`. Database assertions were `sales=3`, `stock_movements=3`, `holds=0`, product stock `50 -> 47`, with unique sequential sale numbers. Browser console errors were empty during the successful flows.

The POS V3 blocker is resolved. Browser Edit/Void, Daily Closing from the newly created sales, real Logo replacement, Print Preview A4/A5, and Backup/Restore with the post-sale business-file fixture were not rerun in this follow-up; their automated targeted coverage remains passing, but these manual acceptance items remain documented limitations.

### Follow-up Browser verification

- Sale Edit: `SAL-20260801-0001` edited in Browser from quantity `2` to `3`; total changed `315.00 -> 472.50`, revision `1 -> 2`, received cash `500.00`, change `27.50`, stock `100 -> 97`, and one sale movement remained authoritative.
- Sale Void: the same sale was voided with reason `TEST-GOLIVE Browser verification`; Browser showed the voided state and removed Edit/Void actions. Database showed `status=voided`, actor/time populated, stock restored to `100`, one void movement, and zero commission rows.
- Daily Closing: Browser showed one active sale and Expected Cash `157.50`; actual cash `157.50` was saved as Draft and remained `157.50` after reload. Native confirmation interrupted the Browser control session before Finalize could be evidenced.
- Browser console had no new errors during Edit/Void/Draft flows. No HTTP 500 or unexpected Laravel error was observed in the successful requests.

Logo replacement/HTTP asset checks, Print Preview A4/A5, post-transaction Backup/Restore, and Daily Closing Finalize remain unverified in Browser in this follow-up.

## 6. Backup/Restore verification

- Database dump atomic finalization: passed
- Empty/missing/non-zero/timeout handling: passed
- Manifest and database SHA-256: passed
- Logo/QR/Product image coverage: passed
- Restore safety and CLI-only workflow: passed
- Windows symbolic-link source test: skipped because the environment does not permit symbolic links
- Skipped test: `Tests\\Feature\\Backup\\DatabaseRestoreServiceTest::test_it_rejects_a_symbolic_link_source`; it proves restore rejects a symbolic-link dump source. The Windows limitation was explicit. Replacement checks passed for missing/wrong-extension/empty/staging-path restore rejection, and the manifest test verified storage file existence, relative paths, sizes and SHA-256 values for business files.

## 7. Git

Implementation commits:

- `9d6e6fe` fix: harden document logo lifecycle
- `0cc5d84` style: format setting controller
- `9b2d1ab` test: cover stock adjustment permissions
- `98ce7b3` feat: add backup manifest coverage
- `16fd8ba` test: add integrated go-live regression
- `aed009a` fix: preserve POS V3 fulfillment state
- `de2571a` docs: record POS V3 verification

Design/plan commits:

- `cfa3e9b` docs: define production go-live readiness design
- `c5c7e81` docs: add go-live readiness implementation plan

## 8. Remaining limitations

- Full PHPUnit suite still has a pre-existing shared-schema isolation problem; targeted suites are the reliable proof for this sprint until test infrastructure is separately isolated.
- POS V3 customer/fulfillment/payment state is fixed and verified in Browser for Pickup, Delivery and Hold Bill. Remaining manual evidence is Browser Edit/Void, Browser Daily Closing, Logo replacement/Print Preview, and Backup/Restore after the final seeded transaction/file set.
- Follow-up Browser Edit/Void passed; Daily Closing Draft passed and persisted, but Finalize was not evidenced because native confirmation interrupted the Browser session.
- Real file upload and A4 print preview require a seeded Test fixture and manual browser session; they were not claimed as passed in this follow-up.
- Production deployment, Production backup, merge and push were not performed.

## 9. Final verdict

`READY WITH DOCUMENTED LIMITATIONS`

The seven scoped workflows are implemented or verified with targeted automated proof, and the Test Environment browser smoke is clean for read-only entry points. Controlled go-live should still include a separately approved Production preflight/backup and a seeded Test browser pass for transaction/upload/print flows before deployment.
