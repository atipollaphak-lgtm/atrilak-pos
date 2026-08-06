# POS V3 Complete Sprint Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the approved POS V3 sprint requirements in one sprint, covering unit-code generation, customer/address/tax UX, fulfillment and delivery-zone behavior, compact payment confirmation, date/note/quantity UX, and A5 document readability while preserving all existing payment and business rules.

**Architecture:** Keep controllers thin and route new POS customer creation through `CustomerService`. Keep unit generation in a focused `UnitCodeService`. Keep the browser’s canonical V3 draft state in `sale-v3.js`/`final-pos.js`, with a small date utility and existing zone-pricing math. Keep backend sale validation, address-zone resolution, pricing, payment resolution, reporting, and Daily Closing unchanged.

**Tech Stack:** Laravel 13, PHP 8.3, PostgreSQL-compatible Eloquent code, Blade, vanilla JavaScript modules, CSS, PHPUnit/Laravel test runner, Node test runner.

## Global Constraints

- Read and follow `PROJECT.md` and `AGENTS.md`.
- Preserve POS V1/V2 routes, request payloads, payment methods, reports, Daily Closing, protected pricing/profit/delivery/stock rules, and sale numbering.
- Do not add a `ยังไม่ชำระ` payment status or method. Delivery success copy is `ยืนยันการจัดส่ง` only.
- Do not run tests, migrations, or diagnostics against production data. Do not run migrations unless a later evidence-based decision requires a new forward-safe migration.
- Do not edit or delete the existing untracked Owner files: `design-qa.md`, `docs/superpowers/plans/2026-07-29-final-pos-hold-bill.md`, and `docs/superpowers/plans/2026-08-05-invoice-reference-css-redesign.md`.
- Do not commit, push, merge, rebase, or create a branch; the Owner approved implementation, not Git delivery actions.
- Use `apply_patch` for source edits. Before the first edit, report the intended files and reasons in commentary.

## File map

### Backend

- `app/Services/UnitCodeService.php` — transactional generated unit code.
- `app/Http/Controllers/UnitController.php` — consume the generator and ignore user-supplied edit codes.
- `app/Http/Controllers/CustomerController.php` — JSON POS customer creation response.
- `app/Http/Requests/StoreCustomerRequest.php` and/or `app/Http/Requests/StorePosCustomerRequest.php` — preserve existing customer validation for the POS endpoint.
- `app/Services/CustomerService.php` — keep the primary customer address usable for invoice data without rewriting historical rows.
- `app/Http/Controllers/SaleV3Controller.php` — load active delivery zones/address counts and expose POS customer-create data.
- `routes/web.php` — add the scoped cashier POS customer-create route.

### POS V3 UI and behavior

- `resources/views/sales-v3/index.blade.php` — expose URLs, zones, and the create-customer partial.
- `resources/views/sales-v3/partials/customer-bar.blade.php` — selected customer/address/zone/fulfillment summary without avatar.
- `resources/views/sales-v3/partials/customer-search-modal.blade.php` — address counts, empty-state create action, and detail/selection affordances.
- `resources/views/sales-v3/partials/customer-create-modal.blade.php` — small POS customer creation form.
- `resources/views/sales-v3/partials/product-navigation.blade.php` — delivery preview-zone selector near keyboard shortcuts.
- `resources/views/sales-v3/partials/cart.blade.php` — fulfillment order, display dates, note text, and compact cart metadata.
- `resources/views/sales-v3/partials/quantity-modal.blade.php` — touch/keyboard quantity controls and price context.
- `resources/views/sales-v3/partials/final-payment-modal.blade.php` — four-column summary, two direct print actions, tax explanation, guarded Finish.
- `public/js/modules/pos-date.js` — `DD/MM/YYYY` display/ISO conversion with CommonJS-compatible tests.
- `public/js/modules/sale-v3.js` — canonical fulfillment/address/zone/date/note/quantity state and guarded delivery validation.
- `public/js/modules/final-pos.js` — customer creation/search display, tax readiness, final confirmation, direct document buttons, and duplicate guards.
- `public/css/sale-v3.css` — fulfillment, zone, date, note, quantity, summary, and responsive presentation.
- `resources/views/customers/_form.blade.php` — correct tax-ID label and invoice-data guidance.
- `public/css/sales-invoice-v2.css` — readable A5 sizing without changing document data or format contracts.

### Tests

- Existing unit/feature contracts under `tests/Unit/Sales`, `tests/Feature/Sales`, and `tests/Feature/Customers` — update stale UX assertions and add acceptance coverage.
- New `tests/Unit/Services/UnitCodeServiceTest.php` or equivalent scoped unit test — generated code uniqueness/format and edit preservation.
- New `tests/Frontend/pos-date.test.mjs` — date display/parse validation.
- Existing frontend final-pos contract test(s) — direct document actions, tax gate, delivery copy, and guard behavior.

---

## Task 1: Add generated Unit codes without changing legacy codes

**Files:** Unit service/controller/view and unit tests listed above.

- [x] Write/update a failing test that creates a unit without a code and asserts a unique generated `UNT-######`-style code.
- [x] Write/update a failing test that updates a unit while sending a legacy `code` value and asserts the stored code is preserved.
- [x] Implement `UnitCodeService` with a short unique temporary value plus a transaction-safe ID-derived final code.
- [x] Change `UnitController` validation/store/update to use only editable unit attributes; preserve active/sort behavior.
- [x] Remove the create-form requirement for code and render edit code read-only.
- [x] Run the focused unit feature tests and PHP syntax check.

## Task 2: Add POS customer creation and invoice-data readiness

**Files:** Customer controller/request/service, route, POS index/customer partials, customer form, customer tests.

- [x] Add failing feature tests for the cashier JSON customer-create endpoint, selected primary address/zone response, and validation failure behavior.
- [x] Implement the scoped endpoint using the established `CustomerService` and return a JSON customer/address/zone payload.
- [x] Make the established primary address available as customer invoice address for newly created/updated records without backfilling history.
- [x] Add the create-customer modal and empty-search actions; on success select the new customer and return to POS.
- [x] Add address counts and correct tax-ID/invoice-data labels/help text.
- [x] Add/update tests for tax readiness metadata and customer search rendering.
- [x] Run focused customer/POS page tests and PHP syntax checks.

## Task 3: Implement address-aware fulfillment and guarded delivery-zone pricing

**Files:** Sale V3 controller/index/navigation/customer bar/search/cart, `sale-v3.js`, `final-pos.js`, `sale-v3.css`, tests.

- [x] Add failing page/JS contract tests for pickup-left/delivery-right, pickup default, address count, no automatic first selection for multiple addresses, and delivery prerequisites.
- [x] Load active delivery zones and address counts through the existing V3 page route.
- [x] Replace option-text-only address state with a selected address object and effective zone state; auto-select only an explicitly preferred or sole address.
- [x] Add the pre-customer delivery zone selector and use existing `ZonePricingMath` for repricing. Prompt before price-changing zone/address changes.
- [x] Ensure the selected customer/address/zone/fulfillment summary is visible, remove the avatar, and support reduced motion.
- [x] Block delivery confirmation when customer, address, or zone is missing with a clear message. Keep backend request validation authoritative.
- [x] Run the focused V3 page/workflow tests and JavaScript syntax checks.

## Task 4: Improve quantity, date, note, and compact summary UX

**Files:** Date module, cart/quantity/final partials, `sale-v3.js`, `final-pos.js`, CSS, frontend/feature tests.

- [x] Write failing date tests for ISO-to-`DD/MM/YYYY`, valid display-to-ISO, and invalid dates.
- [x] Implement `pos-date.js`; keep hidden request values as ISO while visible V3 fields use Thai date presentation.
- [x] Expand the quantity modal with +/- controls, unit/price/total context, keyboard behavior, and existing stock limits.
- [x] Render saved note text with safe truncation/title/full-text affordance and immediate update after edit/delete.
- [x] Change the final confirmation table to exactly four columns with quantity plus unit and no unit-price column.
- [x] Run frontend date/final-pos tests and focused V3 cart tests.

## Task 5: Finish confirmation, direct printing, and delivery copy without payment changes

**Files:** Final payment partial, `final-pos.js`, CSS, existing payment/sale contract tests.

- [x] Add failing contracts asserting only `พิมพ์ใบส่งของ` and `พิมพ์ใบกำกับภาษี` are available, with no aggregate print action.
- [x] Implement per-document guarded buttons, tax readiness explanation/disabled state, full-width Finish, and sale/print double-activation guards.
- [x] Set delivery success copy to exactly `ยืนยันการจัดส่ง`; leave payment fields and `payment_method` untouched.
- [x] Add regression assertions that reports/Daily Closing/payment resolver code paths are not modified by the diff.
- [x] Run focused payment/sale/frontend tests and JavaScript syntax checks.

## Task 6: Improve A5 print readability and document contracts

**Files:** `public/css/sales-invoice-v2.css`, invoice/document tests.

- [x] Add/update a deterministic CSS/document contract for larger A5 typography, spacing, readable table/summary, and overflow-safe sizing.
- [x] Adjust A5-only styles while preserving the current paper format, document route, date formatting, and data snapshot.
- [x] Run invoice feature/contract tests and `git diff --check`.
- [x] If Laragon/browser is available, manually verify A5 print preview in the current format; otherwise report it as pending.

## Task 7: Sprint verification and handoff

- [x] Run changed-file PHP syntax checks and Laravel Pint on changed PHP files.
- [x] Run all focused unit/feature/frontend tests from Tasks 1–6.
- [x] Run the broader Laravel regression suite in the configured test environment, never production.
- [x] Review `git diff --stat`, `git diff --check`, changed-file scope, and sensitive-data safety.
- [x] Report files changed, tests/results, remaining risks, manual Laragon/browser status, and uncommitted Git status.

## Completion criteria

The sprint is complete only when all 15 acceptance criteria in `docs/superpowers/specs/2026-08-05-pos-v3-sprint-design.md` are implemented, relevant automated tests pass, no protected payment/business rule was changed, and any unavailable manual A5/browser verification is explicitly reported as pending.

**Verification record:** Focused PHP suite passed 174 tests / 967 assertions; frontend Node suite passed 15 / 15; full Laravel suite ran in the test environment with 700 passed, 4 pre-existing failures, 19 pre-existing SQLite compatibility errors, 75 skipped, and 8 risky; PHP/JS syntax checks, Pint, Blade view cache, build, and `git diff --check` passed. Manual Laragon/Apache/browser A5 print preview was not run and remains pending.

**Next / upcoming task:** Manual Laragon/browser visual QA for POS V3 and A5 print preview when the local web stack is available; no further implementation task remains.
