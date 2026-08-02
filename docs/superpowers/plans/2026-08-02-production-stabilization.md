# Production Stabilization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the confirmed production routing/presentation defects, restore PostgreSQL reconciliation test isolation, and perform a controlled, reversible deployment.

**Architecture:** Keep the existing modal-based Product module and both POS interfaces intact. Apply minimal route/view changes and isolate only the reconciliation suite with Laravel's truncation test trait. Deployment copies only verified runtime files and records commit/checksum evidence.

**Tech Stack:** Laravel 13, PHP 8.3, PostgreSQL, Blade, PHPUnit, Laravel Pint, Windows/Laragon.

## Global Constraints

- Never run automated tests, migrations, reconciliation, or diagnostic writes against the production database.
- Use only `atrilak_pos_final_test_20260729` for PostgreSQL integration tests.
- Do not change protected business rules or historical production data.
- Preserve POS V1 and POS V2.
- Do not stage the user's existing untracked files.

---

### Task 1: Lock route and presentation behavior with tests

**Files:**
- Create: `tests/Feature/Auth/RootRouteTest.php`
- Modify: `tests/Feature/Products/ProductManagementTest.php`
- Create: `tests/Feature/Sales/SaleV2PageTest.php`
- Modify: `routes/web.php`
- Modify: `resources/views/sales/index_v2.blade.php`

**Interfaces:**
- Consumes: named routes `login`, `dashboard`, `products.index`, `products.edit`, and `sales.v2`.
- Produces: no `products.show` route; deterministic root redirects; POS V2 heading without development status.

- [ ] Add failing route, modal/edit, root redirect, and POS V2 presentation tests.
- [ ] Run the three focused test files and verify the new expectations fail.
- [ ] Change the product resource to `except('show')`, implement root redirects, and remove the stale POS V2 label.
- [ ] Run the focused tests and product/auth/POS V2 groups until green.

### Task 2: Repair reconciliation test isolation

**Files:**
- Modify: `tests/Feature/Reconciliation/DataReconciliationCommandTest.php`

**Interfaces:**
- Consumes: `DataReconciliationService::reconcile()` and `atrilak:reconcile-data` unchanged.
- Produces: 12 PostgreSQL reconciliation tests that exercise the existing read-only business checks without an enclosing fixture transaction.

- [ ] Replace only this suite's `RefreshDatabase` trait with `DatabaseTruncation`.
- [ ] Wrap the malformed JSON fixture in a PostgreSQL test-only text-column conversion with cleanup and JSON restoration in `finally`.
- [ ] Run all 12 reconciliation tests and preserve every assertion.
- [ ] Re-run once to prove truncation/schema restoration isolation.

### Task 3: Verify existing workspace repairs and requested regression groups

**Files:**
- Verify the six existing modified application/test files from the previous audit.
- Do not modify protected formulas unless a new failing test proves a non-business-rule defect.

**Interfaces:**
- Consumes: all Sale, Stock, Purchase, Payment, Closing, Quotation, Product, Auth, and POS V2 contracts.
- Produces: fresh proof results on the isolated PostgreSQL database.

- [ ] Run scoped tests for every changed test/application path.
- [ ] Run Reconciliation, Product, Route/Auth, POS V2, Sales, Stock Movement, Purchase, Payment, Sale Edit, Sale Void, Daily Closing, and Quotation Conversion tests.
- [ ] Run custom concurrency/schema-isolated suites separately where their test schema requires isolation.
- [ ] Run the full suite and record pass/fail/error/skip counts exactly.
- [ ] Run PHP syntax checks, Pint, JavaScript syntax/tests when relevant, and `git diff --check`.

### Task 4: Review and commit the verified change

**Files:**
- Stage only the approved runtime, test, spec, and plan files.
- Exclude `design-qa.md` and `docs/superpowers/plans/2026-07-29-final-pos-hold-bill.md`.

**Interfaces:**
- Produces: one auditable commit containing only this task's verified files.

- [ ] Review the full diff, route impact, security/permission impact, sensitive data, and test hooks.
- [ ] Verify branch, base commit, staged file list, and working-tree status.
- [ ] Commit with a clear production-stabilization message and record the full hash/file list.

### Task 5: Controlled production deployment and smoke test

**Files:**
- Deploy runtime files only to `C:\laragon\www\atrilak-pos-production`.
- Create timestamped rollback copies outside the web root or in a dedicated deployment backup directory.
- Create a deployment manifest containing source commit, file checksums, timestamp, and rollback paths.

**Interfaces:**
- Consumes: verified source commit and current Production artifact.
- Produces: reversible artifact update and version evidence without database writes.

- [ ] Resolve exact source/target paths and compare pre-deployment checksums.
- [ ] Back up every production file that will be replaced.
- [ ] Copy only verified runtime files; do not deploy test/spec/plan files.
- [ ] Clear Laravel route/view/config caches only as required.
- [ ] Confirm route list and application boot; restart Apache only if needed.
- [ ] Browser-test guest/authenticated root behavior, Product modal/edit navigation, POS V2 rendering, and read-only menu smoke paths.
- [ ] Inspect browser console/network and only new Laravel log entries.
- [ ] Record deployed checksums and rollback instructions in the manifest/report.

### Task 6: Final report

**Files:**
- No additional mutation required.

**Interfaces:**
- Produces: evidence-based `READY WITH DOCUMENTED LIMITATIONS` or `NOT READY` verdict.

- [ ] Separate browser-proven, automated-only, untested, and blocked workflows.
- [ ] Report bugs/root causes, changed files, commit, deployment, counts, logs, console/network, and remaining limitations.
- [ ] State explicitly that Production browser write workflows remain blocked by `AGENTS.md`.
