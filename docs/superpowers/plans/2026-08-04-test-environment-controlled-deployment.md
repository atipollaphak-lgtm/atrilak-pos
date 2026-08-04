# ATRILAK POS — Test Environment Isolation and Controlled Deployment Plan

## Scope

- Keep the existing Reset, invoice, POS V3 image/fulfillment, and product-cost work intact.
- Move the editable Source runtime to the existing test-only PostgreSQL database `atrilak_pos_final_test_20260729`.
- Never run migrations, seeders, automated writes, browser writes, or reset flows against `atrilak_pos_production`.
- Preserve the existing untracked `design-qa.md` and `docs/superpowers/plans/2026-07-29-final-pos-hold-bill.md`; neither is part of this work.
- Prepare deployment documentation only. Do not copy files, back up Production, merge, push, or deploy.

## Tasks

### 1. Fail closed before automated database work

1. Add a reusable guard that rejects `atrilak_pos_production` and requires `APP_ENV=testing` for the PHPUnit bootstrap.
2. Invoke the guard from `tests/TestCase.php` using the actual Laravel connection database name, not only environment configuration.
3. Add unit coverage for a safe in-memory/test database, the exact Production database name, and a non-test database.
4. Update the Go-Live preflight to assert the guard and required schema while remaining safe under PHPUnit's SQLite in-memory connection.

Red signal: any automated test reports `Tests refused: database connection points to atrilak_pos_production` or a related safety failure.

### 2. Switch only Source to the approved Test DB

1. Change the Source `.env` only to `APP_ENV=local`, `APP_DEBUG=true`, `APP_URL=http://atrilak-pos.test`, and `DB_DATABASE=atrilak_pos_final_test_20260729`; preserve credentials without displaying them.
2. Leave `C:\laragon\www\atrilak-pos-production\.env` unchanged as Production configuration.
3. Clear Source config/view caches only.
4. Verify the runtime values with non-secret output: environment, URL, driver, host, port, and actual connection database name.
5. Run pending migrations only on the approved Test DB and re-check migration status.

### 3. Seed rerunnable Browser and Print fixtures

1. Add an explicit `BrowserTestSeeder` protected by the test-database guard.
2. Use fixed `BTEST-*` identifiers and `updateOrCreate`/scoped item replacement so reruns do not affect unrelated data.
3. Create Owner, Manager, and Cashier test users, settings, units, category, delivery zone, customer/address, products, sale documents for 1/5/10/15 items, and a Hold Bill.
4. Create a Source-only SVG product image under `storage/app/public/products`, plus products with no image and a deliberately missing image path.
5. Verify the public storage link, HTTP 200 for the real fixture, placeholder behavior for missing images, and normalized image URLs without `/storage/storage/`.

### 4. Browser and print verification

Run the browser checks only after the Source runtime points to the Test DB:

- Reset Owner modal, failed authentication, role restrictions/direct URL, successful test-only reset, audit/backup/hash/rollback behavior, and fixture reseeding.
- POS V3 image/placeholder, search/filter, pickup/delivery selection, payment modal state, Hold/Resume, delivery fee/address/zone, and request payload.
- Product cost permissions and validation; confirm only cost changes and no Purchase/Stock Movement side effects.
- A4/A5 delivery-note print at 100% with 1/5/10/15 items, long names, two-line address, logo/QR/footer, no overflow or blank page; confirm Tax Invoice remains unchanged.

### 5. Controlled Deployment preparation

Create a read-only deployment plan containing:

- Source manifest: approved runtime-relative path, Source absolute path, size, SHA-256, file type, and deploy yes/no.
- Production predeploy manifest: target path, existence, current size, current SHA-256, and backup destination.
- Explicit exclusions: `.env`, `.git`, `node_modules`, `vendor`, tests, test fixtures, local uploads, backups, `design-qa.md`, and the old Hold Bill plan.
- Reversible backup and post-copy verification steps, with a hard stop before any copy or Production command.

## Verification gates

- `php artisan db:show` and `migrate:status` identify only the approved Test DB during development.
- Scoped PHP tests, frontend tests, syntax checks, view cache, Pint review, and `git diff --check` are recorded with baseline failures separated from this work.
- Full regression is run only after the isolation guard is active.
- Final review lists original committed files versus files added/changed in this round.
- No Merge, Push, or Deploy occurs in this task.
