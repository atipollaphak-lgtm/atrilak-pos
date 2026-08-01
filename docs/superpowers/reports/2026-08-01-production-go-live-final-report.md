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

Full suite result: 671 tests, 241 passed, 16 baseline schema-isolation failures/errors, 1 skip and 6 risky tests. The baseline failures come from existing tests that drop/recreate the shared PostgreSQL schema during one PHPUnit process; targeted module suites pass when run serially with the required schema lifecycle.

Quality checks:

- PHP syntax: passed for `app`, `config`, `routes`, `database` and `tests`
- Pint: changed files pass; full-project Pint reports pre-existing formatting violations outside this scope
- `git diff --check`: passed

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

Observed: no Server Error page, no horizontal overflow, and no browser console error/warning on the verified Stock Adjustment page. Transaction creation, upload and print preview were not performed in Browser because the Test Database had no seeded business fixture at the time of smoke testing; corresponding service/document behavior is covered by automated tests.

## 6. Backup/Restore verification

- Database dump atomic finalization: passed
- Empty/missing/non-zero/timeout handling: passed
- Manifest and database SHA-256: passed
- Logo/QR/Product image coverage: passed
- Restore safety and CLI-only workflow: passed
- Windows symbolic-link source test: skipped because the environment does not permit symbolic links

## 7. Git

Implementation commits:

- `9d6e6fe` fix: harden document logo lifecycle
- `0cc5d84` style: format setting controller
- `9b2d1ab` test: cover stock adjustment permissions
- `98ce7b3` feat: add backup manifest coverage
- `16fd8ba` test: add integrated go-live regression

Design/plan commits:

- `cfa3e9b` docs: define production go-live readiness design
- `c5c7e81` docs: add go-live readiness implementation plan

## 8. Remaining limitations

- Full PHPUnit suite still has a pre-existing shared-schema isolation problem; targeted suites are the reliable proof for this sprint until test infrastructure is separately isolated.
- Browser transaction creation, real file upload and A4 print preview require a seeded Test fixture and manual browser session; they were not claimed as passed.
- Production deployment, Production backup, merge and push were not performed.

## 9. Final verdict

`READY WITH DOCUMENTED LIMITATIONS`

The seven scoped workflows are implemented or verified with targeted automated proof, and the Test Environment browser smoke is clean for read-only entry points. Controlled go-live should still include a separately approved Production preflight/backup and a seeded Test browser pass for transaction/upload/print flows before deployment.
