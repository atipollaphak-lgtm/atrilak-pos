# ATRILAK POS Business Reset, POS, Delivery Note, and Cost Design

**Date:** 2026-08-04

**Goal:** Complete the four approved sprint improvements while preserving the existing Reset implementation and ATRILAK POS business rules.

## Scope and safety

- Work only in the source repository `C:\laragon\www\atrilak-pos`.
- Never execute the reset workflow against Production during development or automated testing.
- Preserve POS V1/V2, sale pricing, stock, payment, delivery fee, Profit Guard, and commission behavior.
- Do not modify database schema unless existing history fields cannot represent manual cost changes; the current `product_price_histories` schema already has cost, price, user, reason, and source fields.
- Do not modify or delete the previously dirty Reset files without a content-preserving merge.

## Evidence-backed findings

1. The existing dirty Reset work adds `BusinessDataResetService`, `ResetBusinessDataCommand`, an Owner-only POST route, controller delegation, and baseline tests. It currently lacks the requested password/checkbox modal, and the web controller invokes Artisan instead of calling the Reset Service directly.
2. Product uploads store relative paths such as `products/example.jpg` on the `public` disk. `public/storage` is already a valid junction to `storage/app/public`. The Product List uses `/storage/<path>`, while POS V3 currently renders `<path>` directly, so the two screens do not share one URL source.
3. POS V3 stores fulfillment in `state.deliveryType`, but the two fulfillment buttons only receive color/active classes; they do not render a check mark or `aria-pressed` from the same state.
4. Invoice V2 delivery notes use a dedicated `sales-invoice-v2.css`, but the delivery-note table currently has five columns sized `8/42/18/16/16` and the item name uses wrapping CSS without the requested `6/54/12/13/15` delivery-note layout.
5. `products.cost_price` is `decimal(12,2)`. Existing `product_price_histories` includes `old_cost_price`, `new_cost_price`, `old_average_cost`, `average_cost`, `user_id`, `remark`, and `created_from`; PricingService already marks a product `pending_review` when `pricing_reviewed_cost` differs from current `cost_price`.
6. Product management is currently protected by `role:manager`, so Owner and Manager can use the product screen while Cashier receives 403. The new cost endpoint will remain inside that existing permission boundary.

## Design

### 1. Business Reset

Keep the existing allowlist, foreign-key delete order, PostgreSQL identity checks, backup verification, sequence allowlist, transaction, and postconditions. Add a shared application-level execution path that both the command and web controller call through `BusinessDataResetService`.

The web route remains POST-only, authenticated, Owner-only, CSRF-protected, and rate-limited. The request must include the exact confirmation phrase, the explicit acknowledgement checkbox, and the current Owner password. The backend validates all three values and never logs the password. A cache lock keyed to the reset operation prevents double submission; the service transaction owns all business deletion and sequence reset work. Backup creation and verification must finish before the service mutates data.

The existing decision to preserve Users, Roles, Permissions, Migrations, Settings, Pricing Settings, and Delivery Zones is retained as the current Reset intent. Any additional audit record will contain user id, database name, pre-reset counts, backup path/checksum, and success/failure metadata only.

The UI will use a non-dismissible confirmation modal. While the request is running it disables all controls and shows the warning to keep the page open. The result message will include backup metadata and protected counts when the workflow returns successfully.

### 2. POS V3 image and fulfillment display

Add one Product image URL accessor/normalizer that accepts the stored relative path and safely handles already-prefixed storage paths, leading slashes, full URLs, and Windows separators without producing duplicated `/storage` segments. Product List and POS V3 will use this source. Both views reserve the same image box; missing or failed images use `public/images/product-placeholder.svg` without changing card dimensions.

Add a small `syncFulfillmentUi` responsibility in `sale-v3.js` that is the only writer for fulfillment button presentation. It derives the selected button, `✓` label, active class, and `aria-pressed` from `state.deliveryType`. Existing checkbox, delivery fee, address/zone, Hold Bill, Resume, payment, and sale payload behavior remain unchanged; state transitions call the same sync function.

### 3. Delivery note print layout

Change only the delivery-note presentation layer. Delivery-note markup will use five columns with widths `6%, 54%, 12%, 13%, 15%`; the item name will wrap naturally with no ellipsis, while numeric cells remain right/center aligned and nowrap. CSS will use print-friendly `mm` and `pt`-appropriate sizes, scoped A4/A5 rules, bounded logo/QR dimensions, and compact spacing. Tax invoices that reuse the partial will retain their existing layout through type-specific selectors/markup.

### 4. Manual product cost adjustment

Add a narrow `UpdateProductCostRequest`, `ProductCostAdjustmentService`, and Manager-protected `PUT` endpoint. The service starts a transaction, locks the product row, compares the submitted current-cost snapshot using Brick Decimal math, rejects stale edits, updates only `cost_price`, and writes one `product_price_histories` row with old/new cost, delta, user, reason, and `manual_cost_adjustment` source. If the product has a selling price and has not been reviewed before, preserve the existing pricing-review convention by recording the old cost as `pricing_reviewed_cost`; never change selling price, stock, units, barcodes, price lock, pricing rules, purchase rows, or stock movements.

The product details modal will expose a separate cost-edit modal. It will show product identity, stock, old/new cost, selling price, before/after unit profit and margin, a trimmed reason, and a confirmation message. Successful submission refreshes the product list and Pricing Management status through the existing redirect flow; unchanged cost returns a no-op message without a history row.

## Verification strategy

- Add failing feature tests for Reset validation/permissions/shared service invocation and preserve existing Reset Service/Command tests.
- Add failing frontend/feature assertions for normalized image URLs, placeholders, fulfillment check marks, active exclusivity, `aria-pressed`, and payload/state parity.
- Add delivery-note rendering assertions for A4/A5 class, five-column widths, long-name wrapping, and no change to tax invoice presentation.
- Add cost-adjustment feature tests for permissions, validation, transaction rollback, stale updates, decimal behavior, history, unchanged sale/stock, and Pricing Management pending-review state.
- Run targeted Laravel tests, JavaScript syntax checks, relevant frontend tests, Pint on changed PHP files, `git diff --check`, and the broader regression suite without Production database access.
- Browser smoke testing is limited to a local/test environment and will not click Reset.

## Files

### Existing dirty files to preserve and extend

- `app/Http/Controllers/BackupController.php`
- `resources/views/backups/index.blade.php`
- `routes/web.php`
- `app/Console/Commands/ResetBusinessDataCommand.php`
- `app/Services/BusinessDataResetService.php`
- `tests/Feature/Backup/BusinessDataResetWebTest.php`
- `tests/Feature/Console/BusinessDataResetServiceTest.php`
- `tests/Feature/Console/ResetBusinessDataCommandTest.php`

### New or additional files expected in this sprint

- Product image accessor/normalizer and its tests, only if the existing Product model pattern does not provide one.
- `app/Http/Requests/Products/UpdateProductCostRequest.php`
- `app/Services/Products/ProductCostAdjustmentService.php`
- Product cost feature tests and any narrowly scoped frontend contract tests.
- `resources/views/products/partials/_product_cost_modal.blade.php` and corresponding Product modal/JS/CSS changes.
- Delivery-note Blade/CSS changes and focused document tests.
- Reset audit/lock support only where the existing service cannot satisfy the approved safety contract.

## Out of scope

- Production reset, deployment, migration execution, data repair, historical rewrite, schema redesign, pricing formula changes, stock movement changes, payment contract changes, or POS V1/V2 redesign.
