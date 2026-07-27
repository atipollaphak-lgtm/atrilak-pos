# ATRILAK POS — Codex Instructions

## Scope and Authority

- Read `PROJECT.md` before working on business-sensitive modules.
- Work only on the task and module explicitly requested by the Owner.
- Do not perform broad refactors, architecture rewrites, or cross-module changes without approval.
- Preserve all working behavior unless the task explicitly requires a change.
- Keep POS V1 and POS V2 working.
- Read the smallest relevant set of files before editing.
- Reuse existing project patterns before introducing new abstractions.
- Before editing, state which files will change and why.
- If a required rule or behavior is materially unclear, report the exact blocker instead of guessing.

## Environment

- Laravel 13
- PHP 8.3
- PostgreSQL
- Windows 11 with Laragon
- Project path: `C:\laragon\www\atrilak-pos`
- Do not assume Laragon is running.
- Inform the Owner clearly before any step that requires Laragon, Apache, PostgreSQL, or manual browser testing.

Never run tests, migrations, reconciliation, or diagnostic writes against the production database.

## Architecture

- Controllers handle HTTP requests, responses, and orchestration only.
- Business logic belongs in Services.
- Request shape validation belongs in Form Requests or the established validation layer.
- Authoritative validation that may become stale must also occur inside the transaction.
- CSS belongs in `public/css`.
- JavaScript belongs in `public/js/modules`.
- Separate Blade partials when appropriate.
- Do not place large CSS or JavaScript blocks inside Blade files.
- Preserve existing routes, route names, request payloads, and response behavior unless the task requires a compatible change.

## Data Integrity

- Use database transactions for multi-step Sale, Purchase, Stock, Cost, Commission, or financial writes.
- Use row locking when concurrent requests may update stock or the same business document.
- Lock multiple Product rows using unique Product IDs sorted in ascending order.
- Never use stock values supplied by the Browser as authoritative state.
- Never allow partial Sale, Purchase, Stock Movement, Cost, or Commission writes.
- Stock is controlled in base units.
- Validate Product Unit ownership, status, conversion rate, and confirmation before changing stock.
- Preserve a continuous Stock Movement chain.
- Never silently skip malformed sale items.
- Reject empty item lists, mismatched arrays, partial rows, invalid references, and invalid quantities or prices.
- Duplicate Product lines and Product Unit lines must retain the established business behavior unless the Owner approves a change.

## Protected Business Rules

Do not change the following without explicit Owner approval:

- Average Cost
- Pricing and ATRILAK rounding
- Tier Pricing
- Profit calculations
- Profit Guard
- Delivery fee calculations
- Technician Commission
- Product Unit Conversion
- Sale numbering
- Stock Movement semantics

Delivery fee and Profit Guard calculations remain protected even if an audit reports that their current behavior may be incomplete.

Report ambiguity and request an Owner decision before changing these formulas.

Do not infer or rewrite a business rule merely because the current implementation appears unusual.

## Migration and Historical Data Safety

- Never delete an applied migration.
- Do not modify an old migration in a way that can damage an existing database.
- Use a new forward-safe migration for schema changes.
- Preserve existing integer and historical values during type changes.
- Do not backfill, rewrite, repair, or delete historical Sale, Stock Movement, Cost, Commission, or customer data without explicit approval.
- Test both `migrate:fresh` and upgrade paths when a migration is in scope.
- Run migrations only on an explicitly identified test database unless the Owner separately authorizes production work.

## Security and Sensitive Files

Never commit:

- `.env` files
- Backups
- PostgreSQL or other database dumps
- Production exports
- Customer names, phone numbers, addresses, tax data, or other real personal data
- Credentials, tokens, private keys, or connection secrets
- Reconciliation output containing production data

Mask personal and sensitive data in reports.

## Testing

- Add or update automated tests before changing high-risk behavior.
- Run scoped tests while developing.
- Run PostgreSQL integration or concurrency tests when transaction and locking behavior is involved.
- Use separate PostgreSQL test databases when test schema helpers require isolated schemas.
- Set lock and statement timeouts in concurrency tests.
- Run the broader regression suite once after scoped work is complete.
- Run Laravel Pint, JavaScript syntax checks, and `git diff --check` when relevant.
- Review the final diff for scope, test hooks, sensitive data, and unintended business-rule changes.
- Do not claim success unless the relevant tests actually pass.
- Report skipped tests and their reasons.

## Git Workflow

- Use one branch per task or sprint.
- Do not commit, push, merge, rebase, delete branches, or pop/delete stashes without explicit Owner instruction.
- Stage only files within the approved task scope.
- Do not include unrelated documentation or user files in a code commit.
- Before committing, verify the branch, base commit, staged files, and working-tree status.
- After committing, report the full commit hash and committed file list.

## Handoff

Keep reports concise unless the Owner requests additional detail. Include:

- Files changed
- What changed
- Tests run and results
- Remaining risks or blockers
- Git status when Git operations were requested
- Whether Laragon must be opened for the next manual step
