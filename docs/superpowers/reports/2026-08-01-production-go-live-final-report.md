# ATRILAK POS Production Go-Live Readiness Final Report

Date: 2026-08-01
Branch: `codex/category-pricing-rounding`
Environment: `.env.testing`
Source Test Database: `atrilak_pos_final_test_20260729`
Restore Test Database: `atrilak_pos_restore_20260801`
PostgreSQL: `17.10` on `127.0.0.1:5432`

## 1. Environment and Git

- Source and restore databases were verified as separate Test Databases. No Production write, migration, reset, restore or seed was performed.
- Source Test Database was rebuilt with `migrate:fresh` after the prior combined test run destroyed its shared schema.
- Working tree is unstaged and contains only the two pre-existing untracked files: `design-qa.md` and `docs/superpowers/plans/2026-07-29-final-pos-hold-bill.md`.
- HEAD: `8360897baca42ebb3798f240f01ce527868b2ff4`.
- `aed009a0e1dba123aea30fcc06d0572d4d78be0a` — `fix: preserve POS V3 fulfillment state`; 4 scoped files.
- `de2571a6cc63c46f82f1a21dd14f2cd60b9d8467` — `docs: record POS V3 verification`; report only.
- `8360897baca42ebb3798f240f01ce527868b2ff4` — `docs: record follow-up verification limits`; report only.

## 2. Initial Failure and Root Cause

Before the fix, POS V3 lost customer/fulfillment state around Payment Modal interaction and Backend validation returned `ข้อมูลการขายไม่ถูกต้อง`. The cause was separate DOM reads and asynchronous customer/address updates, plus Hold Bill Resume restoring address before fulfillment.

The fix keeps `customerId`, `addressId` and `deliveryType` in the POS draft state, builds the payment payload from that state, waits for customer/address loading before closing selection, and restores fulfillment before address state. Backend validation, transaction, pricing, profit, delivery fee and payment rules were unchanged.

## 3. Latest Browser Verification

Role: Owner (`TEST-GOLIVE-OWNER`)
URL base: `http://127.0.0.1:8000`
Viewport: 1280x720
Browser console: no new errors in successful flows
Network: no HTTP 500 observed in successful flows
Laravel log: no new application error for the successful workflows

### POS, Edit, Void and Closing

- Pickup, Delivery + Zone + PromptPay, and Hold Bill Resume passed previously as `SAL-20260801-0001..0003`; state and database assertions were verified.
- Sale Edit: `SAL-20260801-0001`, quantity `2 -> 3`, total `315.00 -> 472.50`, Revision `1 -> 2`, received cash `500.00`, change `27.50`, stock `100 -> 97`.
- Sale Void: Browser reason `TEST-GOLIVE final void verification`; status `voided`, actor/time populated, Edit/Void actions removed, stock restored to `100`, one void movement, zero commission rows. Void document displayed the cancelled marker.
- Daily Closing: one active sale and one voided sale were correctly separated. Expected Cash `157.50`, Actual Cash `157.50`, Draft persisted after reload. Native confirmation was accepted through Browser automation. Finalized page showed `TEST-GOLIVE-OWNER`, close time, Revision `3`, Snapshot sale count `1`; Edit page exposed no Actual Cash or Finalize control. Print View `/daily-payment-closings/1/print` returned expected totals with no console errors.

### Logo and Documents

- Logo A upload: source `public/vendor/adminlte/dist/img/AdminLTELogo.png`, SHA-256 `B921C343846D962D04DAC6339A291E375F89E2D26E89FB3DED1F7AE830F6D456`.
- Logo B replacement: stored at `settings/zDOH1qC82W2AbmpNdw8MOPUtfJ1i2CDl2VOSsIm6.png`, SHA-256 `431CED6916A2A21A156E38701AFE55BBD7F88969FBBFC56D7FE099D47F265460`, HTTP 200.
- Logo A was removed on replacement; QR remained at `qr/TEST-GOLIVE-qr.png`, SHA-256 `431CED6916A2A21A156E38701AFE55BBD7F88969FBBFC56D7FE099D47F265460`, HTTP 200. Product image remained present.
- Sale document routes returned HTTP 200 with no new Browser errors: invoice-v2 delivery note A4/A5, tax invoice A4, legacy invoice/print, and void-sale print. Each rendered expected image elements; the void document displayed the cancelled state.

### Backup and Restore

- Backup: `storage/app/backups/atrilak_backup_20260801_132410_906868_27470d1046f5ff76.sql`, size `154,217` bytes.
- SQL SHA-256: `6b7e57e4900f21d9a93c06c760b3596c648146ef24337803a10175caf187cb4d`; manifest database SHA-256 matched exactly.
- Manifest included Product Image, QR and Logo with relative paths, size `68`, and SHA-256 values.
- Restore succeeded into `atrilak_pos_restore_20260801`; Source Database was not overwritten.
- Source and Restore counts matched: migrations `101`, products `1`, purchases `1`, purchase_items `1`, sales `2`, sale_items `2`, stock_movements `4`, daily_payment_closings `1`, settings `1`.
- Settings Logo/QR paths and file hashes matched. Sale/Items, Purchase/Items, void status/reason, closing snapshot and Product stock relations were present in Restore.

## 4. Automated Verification

All groups below were run with a fresh Test schema before each group to avoid shared-schema contamination:

| Group and command | Tests | Assertions | Passed | Skipped | Exit |
|---|---:|---:|---:|---:|---:|
| `php artisan test --env=testing tests/Feature/DailyPaymentClosings` | 32 | 220 | 32 | 0 | 0 |
| `php artisan test --env=testing tests/Feature/Settings/SettingUpdateTest.php` | 4 | 13 | 4 | 0 | 0 |
| `php artisan test --env=testing tests/Feature/Documents/TransactionDocumentSnapshotTest.php` | 9 | 115 | 9 | 0 | 0 |
| `php artisan test --env=testing tests/Feature/Backup/DatabaseBackupServiceTest.php` | 9 | 33 | 9 | 0 | 0 |
| `php artisan test --env=testing tests/Feature/GoLive` | 4 | 27 | 4 | 0 | 0 |
| POS/Sale/Void targeted group | 80 | 515 | 80 | 0 | 0 |

Additional prior targeted proof remains: POS V3 state contract 2 tests/20 assertions, and the earlier related regression run 75 tests/473 assertions passed. PHP syntax, JavaScript syntax, Pint for changed PHP test, and `git diff --check` passed.

The Full Suite is not a gate: it completed with baseline shared-schema-isolation failures. Baseline was `662 tests / 237 passed / 409 errors / 15 failures / 1 skipped`, latest was `671 tests / 241 passed / 413 errors / 16 failures / 1 skipped`, exit code `2`; no common Pass-to-Fail regression was observed.

## 5. Remaining Limitations

- Full Suite completed with baseline schema-isolation failures caused by custom schema helpers and migration tests that drop shared tables. It must not be reported as passing.
- Backup restore required explicit Test-process `PG_DUMP_PATH`/`PSQL_PATH` because `.env.testing` does not define them; no production configuration was changed.
- Browser Print Preview was verified through the application print routes and HTTP 200/DOM evidence; OS print dialog capture was not required by the route and was not persisted as a PDF artifact.
- No Merge, Push or Deploy was performed.

## 6. Final Verdict

`READY FOR MERGE AND CONTROLLED DEPLOYMENT`

This verdict means the scoped Test Environment verification is complete. Merge, Push and Deploy remain blocked pending separate Owner authorization.
