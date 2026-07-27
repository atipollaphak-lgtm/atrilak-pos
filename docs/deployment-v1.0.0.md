# ATRILAK POS v1.0.0 Production Deployment Runbook

## Scope and release identity

Preparation only. This runbook is for the approved Windows 11/Laragon production installation of ATRILAK POS.

- Release branch: `release/v1.0.0`
- Release commit and annotated tag target: `30714f163fd0423f3d6a1ae54ff7d9caf47eea77` / `v1.0.0`
- Stack: Laravel 13, PHP 8.3, PostgreSQL 17, Vite 8, Windows/Laragon
- Timezone: `Asia/Bangkok`

Do not substitute a different commit, tag, backup, or database without Owner approval. A later documentation-only commit is not part of the already-created `v1.0.0` application tag; do not move or recreate that tag to include this documentation. This document does not authorize a deployment, database connection, backup, restore, migration, push, merge, or tag change.

## Runtime prerequisites

- PHP 8.3 compatible with the application (`composer.json` requires `^8.3`). Enable PHP's PostgreSQL PDO driver (`pdo_pgsql`) before using the configured `pgsql` connection. No other PHP extension is explicitly declared by the project; validate the Laravel/PHP installation before launch.
- Composer to install production PHP dependencies. Use the lock file supplied with the release; do not use `composer update`.
- PostgreSQL 17 was the verified production-rehearsal version. The database must exist and the selected account must be able to create/alter the schema only for the approved migration step; this document does not assert an application-enforced PostgreSQL major-version requirement.
- Node.js and npm are required temporarily on the production PC when it builds its own Vite assets, because `/public/build` is ignored by Git and therefore is not supplied by the repository. `package.json` does not specify a Node/npm version; use a Node version supported by Vite 8 and record the installed versions in the deployment record. Node/npm are not otherwise required for the running PHP application after a successful approved asset build.
- `pg_dump.exe` and `psql.exe` are required for the built-in backup/restore features. Record their absolute installed paths in `PG_DUMP_PATH` and `PSQL_PATH`; this project does not provide a default path.
- The web-server virtual host document root must be `<application root>\public`, never the application root itself.
- The web-server account needs write access to `storage\` (including `storage\app\backups`, `storage\app\restore-staging`, `storage\logs`, and `storage\framework`) and `bootstrap\cache`. Ensure `storage\app\public` is retained with the deployed files.

## Folder, web root, and environment preparation

Use the production application's already approved folder as `<application root>`; do not invent a new deployment directory during this release. Configure Laragon/Apache to serve `<application root>\public` and make `APP_URL` match the approved production URL.

`storage:link` is required if public logo/QR uploads will be served through the configured public disk (`storage\app\public` maps to `public\storage`).

### Environment checklist

Create `.env` from `.env.example` only on a clean first installation. Never copy a development `.env` over production, expose its contents, or regenerate an existing `APP_KEY`.

| Category | Variables and production direction |
| --- | --- |
| Mandatory before first launch | `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` (generate once on a fresh `.env`), `APP_TIMEZONE=Asia/Bangkok`, `LOG_CHANNEL`, `LOG_STACK`, `LOG_DEPRECATIONS_CHANNEL`, `LOG_LEVEL`, `BCRYPT_ROUNDS` |
| Database | `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`; set `DB_SSLMODE` only if the PostgreSQL deployment requires it. Do not use the `.env.example` SQLite/MySQL defaults. |
| Application identity and URL | `APP_NAME`, `APP_URL`, `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE`, `VITE_APP_NAME` |
| Session, cache, queue, and mail | `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_ENCRYPT`, `SESSION_PATH`, `SESSION_DOMAIN`; `CACHE_STORE`, `CACHE_PREFIX`; `QUEUE_CONNECTION`; `BROADCAST_CONNECTION`; `FILESYSTEM_DISK`; `MAIL_MAILER`, `MAIL_SCHEME`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` |
| Backup and restore | `PG_DUMP_PATH`, `PSQL_PATH`, `BACKUP_RETENTION_DAYS`, `BACKUP_LOCK_SECONDS`, `BACKUP_PROCESS_TIMEOUT_SECONDS`, `BACKUP_RESTORE_ENABLED`, `BACKUP_RESTORE_MAX_KB`, `BACKUP_RESTORE_TIMEOUT_SECONDS` |
| Optional / only if the selected driver needs them | `APP_PREVIOUS_KEYS`, `APP_MAINTENANCE_DRIVER`, `APP_MAINTENANCE_STORE`, `PHP_CLI_SERVER_WORKERS`, `MEMCACHED_HOST`, `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT`, and the optional AWS/S3 variables (`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_USE_PATH_STYLE_ENDPOINT`). |
| Must remain disabled until explicitly needed | `BACKUP_RESTORE_ENABLED=false`; leave `APP_MAINTENANCE_STORE` unset unless a cache-backed maintenance design is approved; do not enable Redis, Memcached, AWS/S3, broadcast, or a non-default mail transport merely by copying example values. |

The project defaults sessions, cache, and queues to database-backed drivers. The first migration set includes the cache and jobs tables; this is why those configured defaults need PostgreSQL available before the application can serve production traffic.

## Installation sequence for a clean production machine

Do each state-changing action in the approved maintenance window with an Owner present. Replace only the documented placeholders with the production PC's approved paths and values.

### Before PostgreSQL is started

1. **SAFE — read-only.** Confirm the supplied package identifies `v1.0.0` at `30714f163fd0423f3d6a1ae54ff7d9caf47eea77`; do not deploy an unverified working tree.
2. **CHANGES FILES — ONE-TIME SETUP.** In `<application root>`, create the production environment file from the example only if no `.env` exists:

   ```powershell
   Copy-Item .env.example .env
   ```

3. **CHANGES FILES — ONE-TIME SETUP.** Set the reviewed production values from the environment checklist, including `DB_CONNECTION=pgsql`, database credentials, and absolute `PG_DUMP_PATH`/`PSQL_PATH`. Keep restore disabled.
4. **CHANGES FILES.** Install only locked production PHP dependencies. This may run Composer's Laravel package-discovery script but does not run migrations:

   ```powershell
   composer install --no-dev --prefer-dist --optimize-autoloader
   ```

5. **CHANGES FILES — ONE-TIME SETUP.** On a fresh `.env` only, generate the application key. Do not run this against an existing production `.env`.

   ```powershell
   php artisan key:generate
   ```

6. **CHANGES FILES.** Install frontend dependencies from the committed lock file and build the ignored Vite production output:

   ```powershell
   npm ci --ignore-scripts
   npm run build
   ```

   `public\build\manifest.json` must exist after the build. A prebuilt, approved artifact may replace this step only if it exactly matches this release; the repository alone does not contain the build output.

7. **CHANGES FILES — ONE-TIME SETUP.** Create the public-storage link if it is absent and uploaded public files are required:

   ```powershell
   php artisan storage:link
   ```

### After PostgreSQL is started

8. **SAFE — read-only.** Reconfirm the production `.env` selects `pgsql`, points to the intended database, and retains `BACKUP_RESTORE_ENABLED=false`. Have the Owner confirm the target database name before the next step.
9. **CHANGES DATABASE — ONE-TIME SETUP / EVERY RELEASE WHEN NEW MIGRATIONS EXIST.** Apply only pending migrations:

   ```powershell
   php artisan migrate --force
   ```

   This is the first database-altering command. It creates the initial schema on first install, including the database cache, session, and jobs support tables. Do not use `migrate:fresh`, `db:seed`, or any destructive migration shortcut in production.
10. **CHANGES DATABASE — ONE-TIME SETUP.** Create the first Owner account interactively after migrations and only after the Owner approves the identity to create:

   ```powershell
   php artisan atrilak:owner:create
   ```

11. **CHANGES FILES.** Build the supported production caches:

   ```powershell
   php artisan config:cache
   php artisan view:cache
   ```

12. **SAFE — read-only.** Verify the Laragon virtual host targets `public`, the writable directories are writable by the web-server account, and the app can reach its approved URL before allowing users in.

### Cache commands: approved and excluded

- `config:cache` and `view:cache` are supported production steps above.
- Do **not** run `route:cache`: `routes/web.php` and `routes/auth.php` contain closure routes, which Laravel cannot serialize for the route cache.
- Do **not** run `php artisan optimize`: it includes route caching and is therefore incompatible with this route configuration.
- Do not include `event:cache` in this release procedure. No application event registration/discovery configuration was found beyond the default application provider, so it is unnecessary and unvalidated for this release.
- **CHANGES FILES.** If a cached configuration must be deliberately rebuilt, first clear the affected cache only during maintenance, then rebuild it. Avoid routine cache clearing on a live POS because it changes the running state.

## Windows scheduler

No scheduled application jobs are currently registered, but Laravel's scheduler command is designed to run every minute. If the Owner elects to provision it for future scheduled work, create one Windows Task Scheduler task with:

- Program/script: the actual absolute path to the production PHP executable.
- Add arguments: `artisan schedule:run`
- Start in: the actual `<application root>`
- Trigger: daily, repeat every **1 minute**, indefinitely; run whether the user is logged on or not, using the service account that can read the application and write the required Laravel directories.

Equivalent command template (substitute the actual, recorded PHP executable and application root; do not use an unverified path):

```powershell
# ONE-TIME SETUP — CHANGES FILES
schtasks /Create /TN "ATRILAK POS Laravel Scheduler" /SC MINUTE /MO 1 /TR "\"<PHP_EXE>\" \"<APPLICATION_ROOT>\artisan\" schedule:run" /F
```

Do not create this task as part of this preparation phase. Its verification is to run one scheduled invocation and inspect its task history; do not start a persistent `schedule:work` process on production.

## Queue requirement

`QUEUE_CONNECTION` defaults to `database`, and the first migration set creates jobs tables, but targeted inspection found no queued job classes or dispatch calls. The Composer `dev` script starts `queue:listen` only for local development. Therefore **no persistent production queue worker is required for v1.0.0 based on current evidence**. Do not start `queue:work` or `queue:listen` unless a later approved feature introduces operational queued work.

## Backups and restore

- **CHANGES FILES / CHANGES DATABASE.** Before any deployment or migration, use the approved backup process with `php artisan atrilak:backup` (or its equivalent `backup:database`) and confirm a new SQL file exists under `storage\app\backups`. This command requires PostgreSQL and a valid `PG_DUMP_PATH`; it supplies the database password to the process without printing it.
- Keep a recovery copy of the SQL backup, `storage\app\public` uploads, and `.env` outside the production PC. Verify the backup's filename, timestamp, size, and successful command result; do not attempt a production restore merely to test it.
- **DESTRUCTIVE — approval required.** `php artisan atrilak:restore "<backup.sql>"` replaces the configured PostgreSQL database and requires `BACKUP_RESTORE_ENABLED=true` plus the exact `RESTORE <database>` confirmation. It makes a pre-restore backup and intentionally leaves the application in maintenance mode after a successful restore. Production restore remains prohibited until an Owner-approved rehearsal has passed; follow `docs/runbooks/database-restore.md`.

## First launch and every-release upgrade

### First launch

1. Take and verify the approved backup.
2. Decide and record maintenance mode. **CHANGES FILES.** Use `php artisan down` only after Owner confirmation if replacing a running installation.
3. Deploy the verified release files, preserve the production `.env`, `storage`, and approved uploads, then run the dependency, asset, migration, Owner-account, and cache steps above in order.
4. Verify login, role access, settings, public uploaded logo/QR rendering, writable directories, and the critical smoke tests in the checklist.
5. Verify the scheduler task only if it was intentionally created. Confirm no queue worker is running for this release.
6. Take and verify a second manual backup after the validated launch.
7. **CHANGES FILES.** Run `php artisan up` only after the Owner accepts the smoke-test result.

### Every release

1. **SAFE — read-only.** Verify the approved release identity and the maintenance-window/rollback plan.
2. **CHANGES FILES.** Take a verified pre-deployment backup; enter maintenance mode after approval; deploy release files while preserving `.env` and `storage`.
3. **CHANGES FILES — EVERY RELEASE.** Run `composer install --no-dev --prefer-dist --optimize-autoloader`; build Vite assets when the release does not provide an approved matching build artifact.
4. **CHANGES DATABASE — EVERY RELEASE WHEN PENDING MIGRATIONS EXIST.** Run `php artisan migrate --force` only after the Owner confirms the target database and backup.
5. **CHANGES FILES — EVERY RELEASE.** Clear stale generated caches, then rebuild only the supported caches:

   ```powershell
   php artisan optimize:clear
   php artisan config:cache
   php artisan view:cache
   ```

   Complete smoke tests and reopen the application only on approval.

## Rollback procedure

### Code-only rollback / failed deployment before migration

Keep maintenance mode enabled. Restore the previously verified application code and matching asset build while preserving the existing `.env`, `storage`, and database. Reinstall locked production dependencies for that prior code if needed, run only its compatible cache steps, smoke-test, then take Owner approval before `php artisan up`.

### Failed deployment after migration

Do not assume `migrate:rollback` is safe: migrations may be irreversible and the database may already be partially changed. Keep maintenance mode enabled, record the failed command/log output and applied migration state, and escalate to the Owner. The safe recovery decision is either a specifically approved forward fix or restoration from the verified pre-deployment backup after a successful rehearsal. Never use `migrate:fresh`, `db:wipe`, or an unreviewed rollback as an incident shortcut.

### Restore from backup

**DESTRUCTIVE — approval required.** Stop the scheduler task, keep the application down, and follow `docs/runbooks/database-restore.md`. A restore requires the approved SQL backup, preserved uploads, a safe recovery copy of `.env`, configured `PSQL_PATH`, an explicit temporary decision to enable restore, the exact confirmation phrase, and a verified rehearsal. Do not run `php artisan up` if the restore reports partial-restore risk.

## Commands that must never be run casually

- **DESTRUCTIVE — approval required:** `php artisan migrate:fresh`, `php artisan migrate:refresh`, `php artisan db:wipe`, `php artisan db:seed`, `php artisan atrilak:restore`, and any direct `psql` destructive command.
- **CHANGES DATABASE — approval required:** `php artisan migrate --force` and either backup command during a production operation.
- **CHANGES FILES — approval required on an existing system:** `php artisan key:generate`, `php artisan down`, `php artisan up`, cache clears, `storage:link`, `composer install`, and frontend builds.
- Do not run `composer update`, `npm update`, `route:cache`, or `optimize` for this release.

## Known warnings and limitations

- No production PHP, Node, npm, Apache, PostgreSQL executable, Laragon virtual-host, or filesystem permission values were inspected in this preparation phase; the operator must record and validate them on the production PC.
- The production database name, credentials, and backup destination intentionally do not appear here.
- `public/build` is ignored, so assets must be built or supplied as a separately approved matching artifact.
- Route closures block route caching and `optimize`.
- No scheduled jobs or operational queued jobs were found at release preparation time.
- Restore is deliberately disabled by default and requires a rehearsal and Owner approval.
