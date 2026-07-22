# ATRILAK POS v1.0.0 Production Checklist

Release: `v1.0.0` at `30714f163fd0423f3d6a1ae54ff7d9caf47eea77`
Deployment date/time: ____________________  Operator: ____________________  Owner: ____________________

## Pre-deployment checklist

- [ ] **SAFE — read-only.** Confirm branch/package identity is `release/v1.0.0`, tag `v1.0.0`, and commit `30714f163fd0423f3d6a1ae54ff7d9caf47eea77`.
- [ ] **SAFE — read-only.** Maintenance window, Owner approval, rollback owner, and operator communications are recorded.
- [ ] **SAFE — read-only.** Production PC meets PHP 8.3, Composer, the PostgreSQL version approved from the PostgreSQL 17 production rehearsal, Node/npm for Vite 8, and Laragon/Apache prerequisites.
- [ ] **SAFE — read-only.** The recorded web document root is `<application root>\public` and the web-server account can write `storage\` and `bootstrap\cache`.
- [ ] **SAFE — read-only.** `.env` is production-specific: `APP_ENV=production`, `APP_DEBUG=false`, `APP_TIMEZONE=Asia/Bangkok`, `DB_CONNECTION=pgsql`, and a stable existing `APP_KEY`.
- [ ] **SAFE — read-only.** `PG_DUMP_PATH` and `PSQL_PATH` are the actual absolute executable paths; `BACKUP_RESTORE_ENABLED=false`.
- [ ] **SAFE — read-only.** Confirm the intended PostgreSQL database name with the Owner before any database-changing command.

## Database and backup checklist

- [ ] **CHANGES FILES / CHANGES DATABASE.** Run the approved pre-deployment backup: `php artisan atrilak:backup`.
- [ ] **SAFE — read-only.** Confirm the resulting SQL backup has a current timestamp, non-zero expected size, and a successful command result.
- [ ] **SAFE — read-only.** Preserve recovery copies of the SQL backup, `.env`, and `storage\app\public` outside the production PC.
- [ ] **SAFE — read-only.** Identify whether this release has pending migrations; do not run migration commands until backup and database target confirmation are complete.
- [ ] **CHANGES DATABASE — approval required.** If pending migrations exist, run exactly `php artisan migrate --force`; record its output and timestamp.
- [ ] **SAFE — read-only.** Do not run `migrate:fresh`, `migrate:refresh`, `db:wipe`, seeders, direct destructive SQL, or restore as an installation shortcut.

## Deployment checklist

- [ ] **CHANGES FILES — approval required.** Put the existing production app into maintenance mode with `php artisan down`, if the Owner chose maintenance mode.
- [ ] **CHANGES FILES.** Deploy only the verified release files; preserve production `.env`, `storage`, and approved uploaded files.
- [ ] **CHANGES FILES — EVERY RELEASE.** Run `composer install --no-dev --prefer-dist --optimize-autoloader`.
- [ ] **CHANGES FILES.** Run `npm ci --ignore-scripts` and `npm run build` unless an approved, matching Vite artifact is supplied. Confirm `public\build\manifest.json` exists. Node/npm may be removed from runtime operation after the approved build completes.
- [ ] **CHANGES FILES — ONE-TIME SETUP.** On a clean first install only, create `.env` from the example and run `php artisan key:generate`. Never regenerate an existing production key.
- [ ] **CHANGES FILES — ONE-TIME SETUP.** Run `php artisan storage:link` if public logo/QR uploads are required and the link is absent.
- [ ] **CHANGES DATABASE — ONE-TIME SETUP.** On first install, after migrations, run `php artisan atrilak:owner:create` with the Owner present.
- [ ] **CHANGES FILES.** Run `php artisan optimize:clear`, then `php artisan config:cache` and `php artisan view:cache`.
- [ ] **SAFE — read-only.** Do not run `route:cache` or `optimize`; closure routes make them incompatible. Do not run `event:cache` for this release.

## Scheduler and queue checklist

- [ ] **ONE-TIME SETUP — CHANGES FILES.** If the Owner elects to provision the scheduler, configure Windows Task Scheduler to run `artisan schedule:run` from `<application root>` with the actual PHP executable every one minute.
- [ ] **SAFE — read-only.** Verify the scheduler task's account, working directory, cadence, and task history. Do not create or enable it without approval.
- [ ] **SAFE — read-only.** Confirm no persistent queue worker is required or running for v1.0.0; current evidence found no dispatched/queued operational jobs.

## Post-deployment verification

- [ ] **SAFE — read-only.** Confirm the approved URL serves the application from `public` and no debug details are shown.
- [ ] **SAFE — read-only.** Confirm the login page opens and the Owner can log in with expected role access.
- [ ] **SAFE — read-only.** Confirm settings load, public logo/QR images render where configured, and file uploads/log directories are writable.
- [ ] **SAFE — read-only.** Confirm the release's Vite assets load without missing-manifest or missing-asset errors.
- [ ] **SAFE — read-only.** Perform the smoke tests below using controlled sample transactions and an Owner-approved method for any operation that writes financial or stock data. Record the test identifiers and use an approved reversal/adjustment plan so test activity does not contaminate production reporting.
- [ ] **CHANGES FILES — approval required.** Run `php artisan up` only after the Owner accepts all required verification.
- [ ] **CHANGES FILES / CHANGES DATABASE.** Run and verify a post-launch `php artisan atrilak:backup`.

## Manual smoke tests (do not execute as part of preparation)

- [ ] **SAFE — read-only / operational approval required.** Login and verify Owner, Manager, and Cashier role access.
- [ ] **SAFE — read-only / operational approval required.** Open Settings and verify permitted access and existing configuration display.
- [ ] **SAFE — read-only / operational approval required.** Look up a known product by name and barcode.
- [ ] **CHANGES DATABASE — approval required.** Create/validate an approved test Purchase and confirm stock movement.
- [ ] **CHANGES DATABASE — approval required.** Create/validate an approved test Sale paid by cash.
- [ ] **CHANGES DATABASE — approval required.** Create/validate an approved test Sale paid by PromptPay.
- [ ] **CHANGES DATABASE — approval required.** Create/validate an approved test mixed-payment Sale.
- [ ] **CHANGES FILES / CHANGES DATABASE — approval required.** Open and print the resulting invoice/document using the production printer path.
- [ ] **CHANGES DATABASE — approval required.** Void an approved test sale and verify the expected stock movement/audit result.
- [ ] **SAFE — read-only / operational approval required.** Review the relevant stock-movement history for the approved test records.
- [ ] **CHANGES DATABASE — approval required.** Exercise Daily Closing only with an approved operational test plan; do not alter a real close casually.
- [ ] **CHANGES DATABASE — approval required.** Verify technician commission/payment flow only with approved test data and a reversible plan.
- [ ] **SAFE — read-only.** Open critical reports and confirm they render.
- [ ] **CHANGES FILES / CHANGES DATABASE — approval required.** Run manual backup and verify its file.
- [ ] **SAFE — read-only.** Confirm restore availability by reviewing the configured executables, disabled restore flag, rehearsal status, and `docs/runbooks/database-restore.md`; do not restore production data.

## Rollback decision points

- [ ] **SAFE — read-only.** If failure occurs before migration, keep maintenance mode on and restore the previous verified code and matching assets while preserving `.env`, `storage`, and the database.
- [ ] **SAFE — read-only.** If failure occurs after migration, stop and record the migration/log state; do not run `migrate:rollback` without a migration-specific Owner-approved recovery plan.
- [ ] **DESTRUCTIVE — approval required.** A database restore requires an approved rehearsal, maintenance mode, scheduler stopped, verified pre-deployment backup, configured `PSQL_PATH`, a temporary explicit restore decision, and exact confirmation text. Keep the app down if any partial-restore risk is reported.
- [ ] **SAFE — read-only.** Record the final rollback decision, owner, time, backup identifier, and outcome.

## Final sign-off

| Item | Name / result | Date and time | Signature / approval |
| --- | --- | --- | --- |
| Pre-deployment backup verified |  |  |  |
| Migration decision and result |  |  |  |
| Deployment and cache result |  |  |  |
| Scheduler decision/result |  |  |  |
| Smoke-test acceptance |  |  |  |
| Post-launch backup verified |  |  |  |
| Owner go-live approval |  |  |  |
| Rollback plan retained |  |  |  |
