# Production Stabilization Design

## Goal

Remove the confirmed production routing errors, align the root and POS V2 presentation with the documented product behavior, restore trustworthy reconciliation coverage on PostgreSQL, and deploy the verified code as a controlled artifact without writing test data to the production database.

## Scope

### Product routes

The product index embeds the product summary in each Details button and opens the existing modal. The modal submits edits through `products.update`; there is no product details page and no internal caller for `products.show`. The resource route will therefore exclude `show`. Product index, create, edit, update, deactivate, restore, unit, barcode, and tier routes remain unchanged.

### Root route

`GET /` will redirect guests to the named `login` route and authenticated users to the named `dashboard` route. No dashboard authorization or role behavior changes.

### POS V2

POS V2 remains available through its existing route, menu entry, controller, JavaScript, and store endpoint because `PROJECT.md` defines it as the main sales interface and the test suite depends on it. Only the stale “กำลังพัฒนา” label will be removed.

### Reconciliation test isolation

Only `DataReconciliationCommandTest` will switch from `RefreshDatabase` to `DatabaseTruncation`. This keeps fixture queries outside an enclosing test transaction, allowing `DataReconciliationService` to start its PostgreSQL `REPEATABLE READ READ ONLY` transaction before any query.

PostgreSQL rejects malformed text in a JSON column before the reconciliation service can inspect it. The malformed JSON test will temporarily convert the test-database column to text, exercise the full command path, remove the malformed fixture, and restore the column to JSON in a `finally` block. Assertions and reconciliation business behavior remain unchanged.

## Safety boundaries

- All automated tests that write data use `atrilak_pos_final_test_20260729`.
- No migration, reconciliation write, fixture creation, or browser write workflow runs against the production database.
- Protected pricing, delivery, profit, stock, commission, numbering, and conversion rules do not change.
- Existing user-owned untracked files are excluded from staging and deployment.
- Production deployment occurs only after scoped tests, the requested regression groups, the full suite, formatting, syntax, diff review, and change review pass.

## Deployment and evidence

The deployment will copy only approved changed runtime files after creating timestamped backups of the corresponding production files. A deployment manifest outside Git will record the source commit, deployed file checksums, timestamp, and rollback paths. Cache clearing is limited to Laravel optimization caches when route/view/config changes require it. Apache is restarted only if verification shows it is necessary.

Post-deployment checks cover root redirects, authenticated product/POS V2 pages, product modal behavior, route list, browser console/network, and new Laravel log entries. Production write workflows remain explicitly documented as blocked by `AGENTS.md`.

## Success criteria

- `products.show` is absent from the route list and the existing modal/edit flows work.
- `/` redirects guests to login and authenticated users to dashboard.
- POS V2 no longer claims to be under development and still renders for authorized users.
- All 12 reconciliation tests pass on PostgreSQL without skipped tests or reduced assertions.
- Requested scoped and full regression checks report their actual pass/fail/error/skip counts.
- Controlled deployment is reversible and version-identifiable.
- No new production runtime error appears during post-deployment smoke testing.
