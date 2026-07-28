# POS V3 Sales Workspace Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a new `/sales-v3` desktop sales workspace for normal sales while preserving POS V1/V2 and reusing the authoritative sale, stock, pricing, delivery, profit, payment, commission, and product-unit services.

**Architecture:** Add a dedicated `SaleV3Controller` and V3 Blade workspace. The controller will reuse `StoreSaleV2Request` and `SaleService::createSale`; V3 routes will be isolated under the existing authenticated cashier middleware. The V3 frontend will own its state and focus behavior, but will use the existing payment resolver contract and idempotent sale submission contract.

**Tech Stack:** Laravel 13, PHP 8.3, PostgreSQL, Blade/AdminLTE, vanilla JavaScript, Bootstrap modal behavior, Laravel Feature tests.

## Global Constraints

- Preserve POS V1 and POS V2 routes, views, JavaScript, CSS, request payloads, and behavior.
- Do not change Average Cost, pricing/ATRILAK rounding, tier pricing, delivery pricing, Profit Guard, technician commission, product-unit conversion, sale numbering, or stock-movement semantics.
- Treat browser totals as display-only; submit item and header inputs to the existing authoritative SaleService flow.
- Use the existing `StoreSaleV2Request` contract for server-side sale validation and existing idempotency handling.
- Do not add migrations or modify historical data.
- The V3 note is creation-only in this sprint; existing sale edit flows do not reopen or modify notes.
- Do not deploy until automated tests pass and the Owner provides/authorizes the production target and backup workflow.

---

### Task 1: Establish task branch and V3 route/controller contract

**Files:**
- Create: `app/Http/Controllers/SaleV3Controller.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Sales/SaleV3PageTest.php`

**Interfaces:**
- `GET /sales-v3` renders `sales-v3.index` with active customers, products, categories, technicians, and current sale date.
- `POST /sales-v3/store` accepts the existing `StoreSaleV2Request` payload and delegates to `SaleService::createSale`.
- `GET /sales-v3/customers/{customer}/delivery-addresses-json` reuses `CustomerDeliveryAddressController::getByCustomer` behavior under a V3 route name.

- [ ] Create branch `codex/pos-v3-sales-workspace` from the current HEAD.
- [ ] Add page and store routes inside the existing authenticated cashier middleware.
- [ ] Implement controller orchestration only; return the existing sale-created response with the V2 invoice route.
- [ ] Add page tests proving V3 is authenticated, renders, and does not replace V1/V2 routes.
- [ ] Run the focused page tests.

### Task 2: Build the V3 Blade workspace

**Files:**
- Create: `resources/views/sales-v3/index.blade.php`
- Create: `resources/views/sales-v3/partials/customer-bar.blade.php`
- Create: `resources/views/sales-v3/partials/product-navigation.blade.php`
- Create: `resources/views/sales-v3/partials/product-grid.blade.php`
- Create: `resources/views/sales-v3/partials/cart.blade.php`
- Create: `resources/views/sales-v3/partials/quantity-modal.blade.php`
- Create: `resources/views/sales-v3/partials/edit-item-modal.blade.php`
- Create: `resources/views/sales-v3/partials/note-modal.blade.php`
- Create: `resources/views/sales-v3/partials/payment-modal.blade.php`
- Test: `tests/Feature/Sales/SaleV3PageTest.php`

**Interfaces:**
- The page exposes stable IDs/data attributes consumed by `sale-v3.js`.
- Product cards expose product, category, stock, base price, product units, unit barcodes, and price tiers as JSON.
- Payment fields retain `payment_method`, `cash_amount`, `promptpay_amount`, and `received_amount` names.

- [ ] Add a single-page header/customer bar with customer search, address selection, technician, sale date, new-bill action, and current time.
- [ ] Add dynamic category, best-selling, favorite, search, stock-only, name/category, and barcode-ready product navigation.
- [ ] Add a left product grid and fixed/sticky right cart with subtotal, delivery fee, discount, total, notes, and payment action.
- [ ] Add accessible quantity, item-edit, note, and payment modal markup with focus targets and keyboard-close behavior.
- [ ] Reuse the established payment fields and do not add invoice/document redesign.
- [ ] Verify rendered markup includes the required V3 controls and no V2 asset is modified.

### Task 3: Implement isolated V3 interaction and cart state

**Files:**
- Create: `public/js/modules/sale-v3.js`
- Create: `public/css/sale-v3.css`
- Test: `tests/Feature/Sales/SaleV3CartWorkflowTest.php`

**Interfaces:**
- `window.POSV3` owns V3 state; state includes selected customer/address, filtered products, cart lines, discount, delivery fee, note, and payment guard.
- A cart line carries `product_id`, `product_unit_id`, `qty`, `selling_price`, product/unit display data, and optional note.
- Duplicate clicks merge only matching product + product-unit lines, preserving distinct unit lines.

- [ ] Implement product search by name/code/barcode/unit barcode, category tabs, stock-only toggle, best-selling/favorite filters, and barcode Enter submission.
- [ ] Implement quantity popup with immediate focus/select, Enter confirm, Escape cancel, stock-aware display validation, and focus restoration.
- [ ] Implement add/merge/edit/increase/decrease/remove/clear operations without changing V2 globals.
- [ ] Implement item edit popup for permitted quantity/unit/price changes, retaining available units and price tiers.
- [ ] Implement note popup, discount input, delivery pickup/address handling, and total display using the established fields.
- [ ] Implement F2-F9/Escape/Enter shortcuts and focus transitions across customer, barcode, quantity, edit, note, and payment controls.
- [ ] Add HTML escaping for all product/customer-derived strings inserted into the DOM.
- [ ] Run JavaScript syntax validation and focused cart contract tests.

### Task 4: Wire payment and authoritative sale submission

**Files:**
- Modify: `resources/views/sales-v3/index.blade.php`
- Modify: `public/js/modules/sale-v3.js`
- Test: `tests/Feature/Sales/SaleV3StoreTest.php`
- Test: `tests/Feature/Sales/SaleV3PaymentTest.php`

**Interfaces:**
- V3 submits JSON to `/sales-v3/store` with the existing sale payload and a UUID `idempotency_key`.
- On success it clears the V3 cart and follows the existing invoice URL; on validation/business failure it preserves state and displays the server message.

- [ ] Reuse the existing payment calculation contract for cash, PromptPay, and mixed payment.
- [ ] Focus the first payment input when the modal opens and confirm on Enter while preserving Escape cancellation.
- [ ] Recalculate display totals from current V3 state immediately before opening payment and reject stale payment totals.
- [ ] Submit only authoritative item/header/payment inputs; never submit browser-derived base quantities or change amounts.
- [ ] Add tests for normal creation, mixed payment payload, validation failure without partial writes, and duplicate idempotency submission.
- [ ] Add regression coverage for notes persistence, omitted notes, blank-to-null normalization, max length, and V1/V2 payload compatibility.
- [ ] Run the scoped sale tests, existing payment contract tests, and relevant stock/product-unit tests.

### Task 5: Regression verification and handoff gates

**Files:**
- Modify: `docs/superpowers/plans/2026-07-28-pos-v3-sales-workspace.md`

- [ ] Run PHP syntax checks, Pint on changed PHP files, JavaScript syntax checks, and `git diff --check`.
- [ ] Run the broader relevant Sales Feature suite, including concurrency, payment persistence, product-unit conversion, stock locking, Profit Guard, commission, customer/address, and daily closing dependencies.
- [ ] Inspect the final diff for scope, sensitive data, unintended V1/V2 changes, and unsafe migrations.
- [ ] Run browser smoke verification at 1366x768 and 1920x1080 using controlled test data; inform the Owner before Laragon/manual browser work.
- [ ] Commit only the scoped files with `feat: deliver POS V3 sales workspace` after verification.
- [ ] Push/merge/deploy only after explicit production target, backup, and environment checks are available; otherwise report the exact deployment blocker.
