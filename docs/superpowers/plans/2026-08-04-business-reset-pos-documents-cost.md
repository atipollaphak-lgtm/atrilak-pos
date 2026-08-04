# Business Reset, POS Display, Delivery Note, and Cost Adjustment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete the four approved ATRILAK POS sprint improvements while preserving the existing dirty Reset implementation and all protected POS business rules.

**Architecture:** Extend the existing Reset allowlist/service rather than replacing it. Normalize product image URLs at the Product model boundary, keep POS fulfillment presentation derived from the existing `state.deliveryType`, scope delivery-note layout changes to its Blade/CSS presentation layer, and add a transaction-scoped product cost adjustment service with a Manager-protected endpoint and existing price-history table.

**Tech Stack:** Laravel 13, PHP 8.3, PostgreSQL, Blade, AdminLTE/Bootstrap, vanilla JavaScript modules, PHPUnit, Node `node:test`, Brick Math Decimal, Laravel Cache locks.

## Global Constraints

- Never run Reset, migrations, diagnostics, or destructive tests against `atrilak_pos_production`.
- Preserve the existing dirty Reset files and intent; do not use `git checkout --`, `git restore`, `git reset --hard`, or equivalent destructive commands.
- Preserve POS V1/V2, sale pricing, stock, payment, delivery fee, Profit Guard, Zone Pricing, Hold Bill, Sale Edit, Average Cost, and commission behavior.
- Do not change protected business formulas or historical data.
- Do not create a new storage link if `public/storage` is already the valid junction.
- Do not add a migration for cost history because existing `product_price_histories` fields already represent the required manual adjustment audit data; add only the Reset audit migration if the existing schema has no durable audit table.
- Do not merge, push, deploy, or execute the Production reset during this implementation.

---

### Task 1: Complete the shared Business Reset workflow safely

**Files:**
- Modify: `app/Services/BusinessDataResetService.php` — retain the existing allowlist/delete order/sequence checks and add shared production identity, backup verification, lock, audit, and workflow orchestration.
- Modify: `app/Console/Commands/ResetBusinessDataCommand.php` — call the shared service workflow and preserve CLI preflight/summary behavior.
- Modify: `app/Http/Controllers/BackupController.php` — validate acknowledgement, exact phrase, and current Owner password, then call the shared service directly.
- Modify: `resources/views/backups/index.blade.php` — add the non-dismissible confirmation modal, protected/business lists, disabled-running state, and result metadata.
- Modify: `routes/web.php` — keep the Owner-only POST route, add a bounded throttle, and do not add a GET reset route.
- Create: `database/migrations/2026_08_04_000001_create_business_reset_audits_table.php` — preserve successful and failed reset metadata without credentials.
- Create: `app/Models/BusinessResetAudit.php` — represent durable audit records if the migration is needed.
- Modify: `tests/Feature/Backup/BusinessDataResetWebTest.php` — prove UI fields, permissions, CSRF, validation, password checking, shared service invocation, and double-submit behavior.
- Modify: `tests/Feature/Console/BusinessDataResetServiceTest.php` — preserve existing reset integration tests and add audit/protected-data assertions where safe.
- Modify: `tests/Feature/Console/ResetBusinessDataCommandTest.php` — prove identity/confirmation/backup gating and shared service invocation without real Production access.

**Interfaces:**
- Consumes: existing `BusinessDataResetService::reset()`, `DatabaseBackupService::create()`, `DatabaseBackupResult`, authenticated Owner, and existing business/protected table allowlists.
- Produces: `BusinessDataResetService::run(DatabaseBackupService $backupService, ?int $actorId = null): array` returning identity, verified backup metadata, reset counts, protected counts, and sequence states; no password or session data.

- [ ] **Step 1: Write failing Reset web tests.**

Add tests for the requested contract before changing the controller/view:

```php
public function test_owner_reset_requires_acknowledgement_phrase_and_current_password(): void
{
    $owner = User::factory()->create([
        'role' => 'owner',
        'password' => Hash::make('owner-password'),
    ]);

    $this->actingAs($owner)
        ->post(route('backups.reset-business-data'), [
            'acknowledged' => '1',
            'confirmation' => ResetBusinessDataCommand::CONFIRMATION,
            'password' => 'wrong-password',
        ])
        ->assertSessionHasErrors('password');
}

public function test_owner_reset_calls_business_reset_service_after_backend_validation(): void
{
    $owner = User::factory()->create([
        'role' => 'owner',
        'password' => Hash::make('owner-password'),
    ]);
    $service = Mockery::mock(BusinessDataResetService::class);
    $service->shouldReceive('run')->once()->andReturn([
        'backup' => ['file_name' => 'reset.sql', 'sha256' => str_repeat('a', 64), 'bytes' => 10],
        'reset' => ['protected_after' => ['users' => 1, 'settings' => 1]],
    ]);
    $this->app->instance(BusinessDataResetService::class, $service);

    $this->actingAs($owner)
        ->post(route('backups.reset-business-data'), [
            'acknowledged' => '1',
            'confirmation' => ResetBusinessDataCommand::CONFIRMATION,
            'password' => 'owner-password',
        ])
        ->assertRedirect(route('backups.index'))
        ->assertSessionHas('success');
}
```

- [ ] **Step 2: Run the focused web tests and verify a meaningful red failure.**

Run:

```text
php artisan test tests/Feature/Backup/BusinessDataResetWebTest.php
```

Expected: the new validation/service-invocation assertions fail because the current web form only accepts `confirmation` and calls Artisan.

- [ ] **Step 3: Write failing Reset workflow/command tests.**

Add assertions that a failed backup prevents `reset()` and that the command delegates through `BusinessDataResetService::run()`; add a durable audit assertion that the saved row contains no password/session field and records actor/database/status. Keep the existing PostgreSQL integration guard that requires `atrilak_pos_test`.

- [ ] **Step 4: Run the focused command/service tests and verify the red signal.**

Run:

```text
php artisan test tests/Feature/Console/BusinessDataResetServiceTest.php tests/Feature/Console/ResetBusinessDataCommandTest.php
```

Expected: new shared-workflow/audit assertions fail for the current command and schema.

- [ ] **Step 5: Implement the audit migration/model and shared service workflow.**

Create `business_reset_audits` with `user_id`, `database_name`, JSON counts, backup path/checksum, status, error code, and timestamps. Add it to the protected tables, never to delete/sequence lists. In `BusinessDataResetService::run()`:

1. Acquire `Cache::lock('atrilak:business-reset', config('backup.lock_seconds'))`; fail without mutation if unavailable.
2. Assert `APP_ENV=production`, PostgreSQL, and `current_database()=atrilak_pos_production` before backup/reset.
3. Capture preflight counts.
4. Call the existing `DatabaseBackupService::create()`, verify filename, non-zero SQL/manifest, database identity, and SHA-256.
5. Call the existing `reset()` only after backup verification.
6. Record success/failure audit data outside the deletion transaction, never including request credentials.
7. Release the lock in `finally`.

Retain `reset()` as the existing lower-level transaction-tested method so current service tests continue to exercise rollback and sequence postconditions.

- [ ] **Step 6: Implement direct web/CLI integration and modal behavior.**

Change the controller to use `password` with Laravel `current_password`, `acknowledged` with `accepted`, and exact `ResetBusinessDataCommand::CONFIRMATION`; call `$businessDataResetService->run($this->databaseBackupService, $request->user()->id)` directly. Keep the Owner route and add `throttle:3,1`. Update the command to call the same `run()` method and render returned backup/count metadata. Add a Bootstrap modal with `data-backdrop="static" data-keyboard="false"`, disabled submit until all three inputs are valid, and a submit handler that disables controls and displays `กำลังสำรองและล้างข้อมูล กรุณาอย่าปิดหน้านี้`.

- [ ] **Step 7: Run the focused Reset tests and confirm green.**

Run:

```text
php artisan test tests/Feature/Backup/BusinessDataResetWebTest.php tests/Feature/Console/BusinessDataResetServiceTest.php tests/Feature/Console/ResetBusinessDataCommandTest.php
```

Expected: all focused Reset tests pass; no test invokes the Production reset. Record any PostgreSQL integration skip if `atrilak_pos_test` is unavailable.

---

### Task 2: Normalize product image URLs in Product List and POS V3

**Files:**
- Modify: `app/Models/Product.php` — add the single `image_url` accessor/normalizer.
- Modify: `resources/views/products/index.blade.php` — use `image_url` and placeholder fallback in Product List data/markup.
- Modify: `resources/views/products/partials/_product_modal.blade.php` — preserve image box and use the normalized URL on details.
- Modify: `public/js/modules/product-management.js` — use `image_url` and fallback on load errors.
- Modify: `resources/views/sales-v3/partials/product-grid.blade.php` — use the normalized URL, render placeholder images, and reserve the card image box.
- Modify: `public/css/sale-v3.css` and `public/css/products.css` only if needed to preserve fixed image dimensions.
- Create/Modify: `tests/Unit/Products/ProductImageUrlTest.php` and `tests/Feature/Products/ProductManagementTest.php` — cover relative, prefixed, full URL, missing, and fallback behavior.

**Interfaces:**
- Consumes: `Product::$image_path` values stored on the `public` disk and the existing `public/storage` junction.
- Produces: `Product::$image_url` as either a safe browser URL or `null`, with no Windows filesystem path or duplicate `/storage` segment.

- [ ] **Step 1: Write failing image URL and rendering tests.**

Add tests that create products with `products/test.jpg`, `storage/products/test.jpg`, `/storage/products/test.jpg`, and no path, then assert `image_url` is normalized and Product List/POS HTML uses the same URL source plus placeholder fallback.

- [ ] **Step 2: Run the focused image tests and verify red.**

Run:

```text
php artisan test tests/Unit/Products/ProductImageUrlTest.php tests/Feature/Products/ProductManagementTest.php tests/Feature/Sales/SaleV3PageTest.php
```

Expected: the new accessor/markup assertions fail because `image_url` does not exist and POS uses the raw path.

- [ ] **Step 3: Implement the Product accessor and both view paths.**

Normalize slash direction and leading `storage/` prefixes; return full URLs unchanged; convert public-disk paths with `Storage::disk('public')->url($path)`; return `null` for empty or unsafe absolute filesystem paths. Use `asset('images/product-placeholder.svg')` for no path and an `onerror` fallback for missing files. Keep the existing Product Card dimensions and upload disk unchanged.

- [ ] **Step 4: Run focused image tests and JavaScript syntax checks.**

Run:

```text
php artisan test tests/Unit/Products/ProductImageUrlTest.php tests/Feature/Products/ProductManagementTest.php tests/Feature/Sales/SaleV3PageTest.php
node --check public/js/modules/product-management.js
```

Expected: PASS with no duplicate storage URL assertions.

---

### Task 3: Render one truthful fulfillment selection in POS V3

**Files:**
- Modify: `resources/views/sales-v3/partials/cart.blade.php` — add check-mark and label spans with initial `aria-pressed` attributes.
- Modify: `public/js/modules/sale-v3.js` — make `syncFulfillmentUi()` derive check mark, class, and `aria-pressed` from `state.deliveryType`; use state for the summary label.
- Modify: `public/css/sale-v3.css` — style selected/unselected buttons and visible focus without changing layout size.
- Modify: `tests/Unit/Sales/SaleV3BrowserStateContractTest.php` and/or `tests/Frontend/final-pos.test.mjs` — cover UI/state/payload contracts.

**Interfaces:**
- Consumes: existing `state.deliveryType`, checkbox event flow, Hold Bill/Resume state, payment payload, and delivery fee logic.
- Produces: exactly one selected button with `✓`, `.active`/`.is-selected`, and `aria-pressed="true"`; no selected button when state is absent is allowed because the state always defaults to `pickup`.

- [ ] **Step 1: Write failing fulfillment contract tests.**

Extend the source contract tests with assertions for `syncFulfillmentUi`, `aria-pressed`, `.fulfillment-check`, and a state-derived summary; add a browser harness assertion that Resume Hold Bill calls the same state sync path without changing payload semantics.

- [ ] **Step 2: Run the focused frontend tests and verify red.**

Run:

```text
php artisan test tests/Unit/Sales/SaleV3BrowserStateContractTest.php tests/Feature/Sales/SaleV3CartWorkflowTest.php
node --test tests/Frontend/final-pos.test.mjs
```

Expected: the new check-mark/ARIA assertions fail against the current color-only implementation.

- [ ] **Step 3: Implement the shared fulfillment presentation sync.**

Add hidden check spans to both buttons. In `syncFulfillmentUi`, set `hidden`, `aria-pressed`, `active`, and `is-selected` from `state.deliveryType`; retain existing Bootstrap button color toggles and delivery fee/address visibility. Keep `buildPayload()` sourced from `state.deliveryType` and ensure new bill/Resume/payment flows call `render()` so the presentation is refreshed.

- [ ] **Step 4: Run POS focused tests and syntax checks.**

Run:

```text
php artisan test tests/Unit/Sales/SaleV3BrowserStateContractTest.php tests/Feature/Sales/SaleV3CartWorkflowTest.php tests/Feature/Sales/HoldBillWorkflowTest.php
node --test tests/Frontend/final-pos.test.mjs
node --check public/js/modules/sale-v3.js
```

Expected: PASS with existing delivery fee, Hold Bill, Resume, and payment assertions unchanged.

---

### Task 4: Adjust only the Invoice V2 delivery-note print layout

**Files:**
- Modify: `resources/views/sales/invoice_v2/delivery-note.blade.php` — use type-aware delivery-note column widths and keep five columns/long-name markup.
- Modify: `public/css/sales-invoice-v2.css` — increase delivery-note A4/A5 typography, set print-safe columns/alignment/wrapping, logo/QR limits, and scoped tax-invoice rules.
- Modify: `tests/Feature/Sales/SaleInvoiceV2FulfillmentTest.php` and/or `tests/Feature/Sales/SalePaymentDisplayTest.php` — assert A4/A5, widths, long names, QR/logo hooks, and tax-invoice preservation.

**Interfaces:**
- Consumes: existing `sales.invoice_v2` rendering, `paper=a4|a5` query, document snapshots, logo/QR settings, and existing `$minimumRows` behavior.
- Produces: delivery-note presentation only; no controller/service/database/total changes.

- [ ] **Step 1: Write failing delivery-note layout assertions.**

Add an HTML-render test with a 100-character product name and assert delivery-note headers contain `6%`, `54%`, `12%`, `13%`, `15%`, the long name appears without an ellipsis, A4 is default, and A5 uses `paper-a5`. Assert tax invoice remains marked `data-document-type="tax-invoice"` and keeps its existing compact blank-row budget.

- [ ] **Step 2: Run the focused document tests and verify red.**

Run:

```text
php artisan test tests/Feature/Sales/SaleInvoiceV2FulfillmentTest.php tests/Feature/Sales/SalePaymentDisplayTest.php
```

Expected: the new width/long-name assertions fail against the current delivery-note markup/CSS.

- [ ] **Step 3: Implement scoped delivery-note CSS/markup.**

Use delivery-note-specific selectors and type-aware Blade widths so tax-invoice layout remains compatible. Set A4 general text around 12–13pt-equivalent, A5 around 10.5–11.5pt-equivalent, keep `overflow-wrap:anywhere`, remove ellipsis behavior, set numeric `white-space:nowrap`, and retain 1-page row budgets without changing totals, QR source, logo source, or tax data.

- [ ] **Step 4: Render-focused verification.**

Run:

```text
php artisan test tests/Feature/Sales/SaleInvoiceV2FulfillmentTest.php tests/Feature/Sales/SalePaymentDisplayTest.php
php artisan view:cache
```

Expected: PASS; no change to tax invoice/quotation tests. Browser print preview remains a manual local step if Laragon is opened.

---

### Task 5: Add safe manual product cost adjustment

**Files:**
- Create: `app/Http/Requests/Products/UpdateProductCostRequest.php` — validate current snapshot, decimal(12,2) cost, and trimmed reason.
- Create: `app/Exceptions/StaleProductCostException.php` — identify lost-update rejection.
- Create: `app/Services/Products/ProductCostAdjustmentService.php` — transaction/row lock/Decimal comparison/product update/history write.
- Modify: `app/Http/Controllers/ProductController.php` — add `updateCost()` orchestration only.
- Modify: `routes/web.php` — add `PUT /products/{product}/cost` inside the existing `role:manager` group.
- Create: `resources/views/products/partials/_product_cost_modal.blade.php` — separate cost-edit modal form.
- Modify: `resources/views/products/index.blade.php` and `resources/views/products/partials/_product_modal.blade.php` — expose the cost action and include the modal.
- Modify: `public/js/modules/product-management.js` and `public/css/products.css` — populate live before/after profit fields and confirmation without changing the main product form.
- Create: `tests/Feature/Products/ProductCostAdjustmentTest.php` — permissions, validation, history, stale update, rollback, and invariant coverage.

**Interfaces:**
- Consumes: `Product` row, `cost_price` snapshot, authenticated Manager/Owner, existing `product_price_histories`, and PricingService status conventions.
- Produces: `ProductCostAdjustmentService::adjust(Product $product, string $newCost, string $expectedCost, string $reason, int $userId): array` returning old/new cost, delta, and refreshed product; unchanged cost returns `changed=false` without history.

- [ ] **Step 1: Write failing cost-adjustment feature tests.**

Create a Product with cost `112.00`, selling price `150.00`, stock `7.0000`, price lock/unit/barcode data, and an authenticated Owner/Manager. Add tests for:

```php
public function test_manager_changes_only_cost_and_records_manual_history(): void
{
    $response = $this->actingAs($manager)->put(route('products.cost.update', $product), [
        'current_cost_price' => '112.00',
        'cost_price' => '120.25',
        'reason' => 'Supplier cost correction',
    ]);

    $response->assertRedirect(route('products.index'));
    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'cost_price' => '120.25',
        'selling_price' => '150.00',
        'stock_qty' => '7.0000',
    ]);
    $this->assertDatabaseHas('product_price_histories', [
        'product_id' => $product->id,
        'old_cost_price' => '112.00',
        'new_cost_price' => '120.25',
        'created_from' => 'manual_cost_adjustment',
        'user_id' => $manager->id,
        'remark' => 'Supplier cost correction',
    ]);
}
```

Also cover Cashier 403/hidden action, Guest redirect, negative/non-numeric/three-decimal/blank-reason rejection, unchanged-cost no-op, stale snapshot rejection, transaction rollback, no Purchase/StockMovement rows, and pending-review visibility.

- [ ] **Step 2: Run the focused cost tests and verify red.**

Run:

```text
php artisan test tests/Feature/Products/ProductCostAdjustmentTest.php
```

Expected: the route/service/modal assertions fail because no cost-adjustment endpoint exists.

- [ ] **Step 3: Implement the request, exception, and transaction service.**

Use `prepareForValidation()` to trim the reason and validate `cost_price`/`current_cost_price` with `required`, `numeric`, `decimal:0,2`, `min:0`, and the `decimal(12,2)` maximum. In the service, lock the Product row, compare `expectedCost` and locked `cost_price` with `Brick\Math\BigDecimal`, throw `StaleProductCostException` on mismatch, return a no-op on equal values, update only `cost_price` (and the existing `pricing_reviewed_cost` marker convention when needed), and create one history row containing old/new cost, old/new selling price unchanged, old/new average cost, `created_from=manual_cost_adjustment`, `user_id`, reason, delta/profit fields, and no stock/purchase writes. Wrap all operations in `DB::transaction`.

- [ ] **Step 4: Implement the Manager-protected route and cost modal.**

Add `ProductController::updateCost()` that catches stale updates with a refresh message and returns a redirect success/no-op message. Add a separate modal form so the existing profile-update form is not nested. Show product code/name/stock/old cost/selling price/profit before and after, reason, and a browser confirmation message that selling price stays unchanged and Pricing Management should be reviewed. Use the existing product detail modal to pass the selected product snapshot to the cost modal; Cashier has no Product route/button under the existing permission structure.

- [ ] **Step 5: Run focused cost tests and syntax checks.**

Run:

```text
php artisan test tests/Feature/Products/ProductCostAdjustmentTest.php tests/Feature/Pricing/PricingWorkflowTest.php tests/Feature/Purchases/PurchaseIntegrityTest.php
node --check public/js/modules/product-management.js
```

Expected: PASS; selling price, stock, price lock, product units, purchases, and stock movement counts remain unchanged.

---

### Task 6: Integrated verification and handoff

**Files:**
- Modify: only files already listed in Tasks 1–5; do not include backups, test artifacts, `.env`, or unrelated dirty files.

- [ ] **Step 1: Run the complete targeted suite.**

Run:

```text
php artisan test tests/Feature/Backup/BusinessDataResetWebTest.php tests/Feature/Console/BusinessDataResetServiceTest.php tests/Feature/Console/ResetBusinessDataCommandTest.php tests/Feature/Products/ProductImageUrlTest.php tests/Feature/Products/ProductManagementTest.php tests/Feature/Products/ProductCostAdjustmentTest.php tests/Feature/Sales/SaleV3PageTest.php tests/Feature/Sales/SaleV3CartWorkflowTest.php tests/Feature/Sales/HoldBillWorkflowTest.php tests/Feature/Sales/SaleInvoiceV2FulfillmentTest.php tests/Feature/Sales/SalePaymentDisplayTest.php tests/Unit/Sales/SaleV3BrowserStateContractTest.php
node --test tests/Frontend/final-pos.test.mjs tests/Frontend/pos-payment.test.mjs tests/Frontend/pos-payment-integration.test.mjs tests/Frontend/pricing-rounding.test.mjs tests/Frontend/zone-pricing.test.mjs
```

- [ ] **Step 2: Run formatters, syntax checks, and diff validation.**

Run `vendor/bin/pint` only for changed PHP files, `node --check` for every changed JavaScript file, `php artisan view:cache`, and `git diff --check`. If a failure is unrelated to this scope, record the exact command/output and leave the unrelated dirty file untouched.

- [ ] **Step 3: Run the broader regression suite without Production access.**

Run the project’s relevant Laravel regression groups for sales, pricing, products, purchases, backup, and documents. Confirm test environment/database identity before any PostgreSQL integration command. Do not run `atrilak:reset-business-data` in this environment.

- [ ] **Step 4: Review diff and stage only approved files.**

Compare `git diff HEAD`, `git status --short`, and the planned file list. Preserve existing dirty files `design-qa.md`, `docs/superpowers/plans/2026-07-29-final-pos-hold-bill.md`, and any unrelated changes without staging them unless the user separately requests them.

- [ ] **Step 5: Commit the completed sprint without merge/push/deploy.**

After all evidence passes, stage only the Reset/POS/document/cost files and tests from this plan and commit with:

```text
fix: improve reset workflow POS display invoices and product costs
```

Report both the design commit and implementation commit, changed-file ownership (pre-existing versus added this round), test results, unverified browser/manual checks, risks, and the final Git status.
