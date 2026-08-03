# POS V3 UX Improvement Sprint Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task with verification checkpoints.

**Goal:** Improve POS V3 counter-sale speed and preserve auditable bill-only price overrides across new bills, Hold Bills, Sale edits, payments, and commercial documents.

**Architecture:** Keep the existing authoritative \`SaleService\` transaction and \`ZonePricingService\` formulas. Add a small Sale price-snapshot boundary that resolves the current system price and applies explicit browser/user intent without trusting browser metadata. Reuse the same snapshot contract for \`sale_items\` and \`hold_bill_items\`; keep legacy V1/V2 callers backward compatible when they do not send the new intent fields.

**Tech Stack:** Laravel 13, PHP 8.3, PostgreSQL, Eloquent, PHPUnit, browser JavaScript modules, Node test runner, AdminLTE/Bootstrap modal UI.

## Global Constraints

- Work only on POS V3 behavior and the shared Sale/Payment persistence points required to support it.
- Do not change Pricing Engine formulas, Category Pricing, Product Selling Price, Product Price History, Profit Guard, delivery-fee rules, commissions, sale numbering, stock semantics, or document routes.
- Add forward-safe migrations; never modify an applied migration and never backfill historical Sale or Hold Item rows.
- Existing Sale Item rows must remain readable with \`original_price = NULL\` and \`price_override_flag = false\` when the new columns are absent from historical data.
- The browser may send desired sale price and explicit price intent only; Backend computes \`original_price\` and \`price_override_flag\`.
- A Sale success state is shown only after the complete transaction commits; failure keeps the summary and draft intact.
- Do not run tests, migrations, or diagnostics against production data.

---

### Task 1: Add forward-safe price snapshot columns and model contracts

**Files:**
- Create: \`database/migrations/2026_08_03_000001_add_price_override_snapshots_to_sale_items.php\`
- Create: \`database/migrations/2026_08_03_000002_add_price_override_snapshots_to_hold_bill_items.php\`
- Modify: \`app/Models/SaleItem.php\`
- Modify: \`app/Models/HoldBillItem.php\`
- Test: \`tests/Feature/Sales/SaleV3PriceOverrideTest.php\`
- Test: \`tests/Feature/Sales/HoldBillWorkflowTest.php\`

**Interfaces:**
- Produces nullable \`original_price\`, default-false \`price_override_flag\` on both item tables.
- Eloquent exposes both fields as fillable, \`original_price\` as \`decimal:2\`, and \`price_override_flag\` as boolean.
- No migration updates historical values.

- [ ] **Step 1: Write failing schema/model tests**

Assert that a newly created Sale Item and Hold Bill Item accept and cast:

\`\`\`php
$item->fill([
    'original_price' => '100.00',
    'price_override_flag' => true,
]);

$this->assertSame('100.00', $item->original_price);
$this->assertTrue($item->price_override_flag);
\`\`\`

Also assert a normal item persists \`original_price\` as \`null\` and \`price_override_flag\` as \`false\`.

- [ ] **Step 2: Run the focused test and confirm the red signal**

Run with a dedicated testing database:

\`\`\`powershell
php artisan test --filter='SaleV3PriceOverrideTest|HoldBillWorkflowTest'
\`\`\`

Expected before migration: the new columns are missing or the model does not cast them.

- [ ] **Step 3: Add both migrations**

Use \`Schema::table\` with \`decimal('original_price', 15, 2)->nullable()\` and \`boolean('price_override_flag')->default(false)\`. Use reversible \`down()\` methods that drop only these new columns. Do not update old migrations or issue data updates.

- [ ] **Step 4: Update both models**

Add the fields to \`$fillable\` and casts. Keep all existing fields, relationships, and historical compatibility unchanged.

- [ ] **Step 5: Run the focused test and migration checks**

Run:

\`\`\`powershell
php artisan test --filter='SaleV3PriceOverrideTest|HoldBillWorkflowTest'
php artisan migrate:fresh --env=testing
\`\`\`

Expected: schema/model assertions pass and the migration is reversible/forward-safe in the isolated testing database.

---

### Task 2: Introduce backend price-intent and snapshot resolution

**Files:**
- Create: \`app/Services/Sales/SalePriceSnapshotService.php\`
- Modify: \`app/Services/SaleService.php\`
- Modify: \`app/Services/Sales/SaleItemService.php\`
- Modify: \`app/ValueObjects/Sales/ResolvedSaleLine.php\`
- Test: \`tests/Unit/Services/Sales/SalePriceSnapshotServiceTest.php\`
- Test: \`tests/Feature/Sales/SaleV3PriceOverrideTest.php\`

**Interfaces:**
- \`SalePriceSnapshotService::systemPrice(array $item, Product $product, ?ProductUnit $unit, ?DeliveryZone $zone, bool $pickup): string\` calls existing \`ZonePricingService::priceLine()\` for both pickup and delivery without changing that service's formulas.
- \`SalePriceSnapshotService::snapshot(string $systemPrice, string $requestedPrice, bool $priceWasEdited): array\` returns \`selling_price\`, \`original_price\`, and \`price_override_flag\`.
- Internal Hold metadata is passed from the locked Hold Item, not from browser input.

- [ ] **Step 1: Write failing service tests**

Cover normal and edited lines:

\`\`\`php
$this->assertSame([
    'selling_price' => '100.00',
    'original_price' => null,
    'price_override_flag' => false,
], $service->snapshot('100.00', '100.00', false));

$this->assertSame([
    'selling_price' => '99.50',
    'original_price' => '100.00',
    'price_override_flag' => true,
], $service->snapshot('100.00', '99.50', true));
\`\`\`

Verify an invalid requested price is rejected and that browser-supplied \`original_price\` or \`price_override_flag\` is ignored by the service contract.

- [ ] **Step 2: Run the service tests to confirm red**

Run:

\`\`\`powershell
php artisan test --filter=SalePriceSnapshotServiceTest
\`\`\`

Expected: FAIL because the service does not yet exist.

- [ ] **Step 3: Implement the snapshot service**

Use \`SaleDecimalService\`/BigDecimal-compatible formatting and validate positive prices with a maximum of two decimal places. Resolve the current system price through \`ZonePricingService\`; do not duplicate rounding or tier formulas in the new service.

- [ ] **Step 4: Carry explicit intent through resolved lines**

Preserve \`price_was_edited\` and internal Hold metadata in the line source context without exposing \`original_price\` or \`price_override_flag\` as trusted request fields. Keep existing \`ResolvedSaleLine\` stock and unit snapshot behavior unchanged.

- [ ] **Step 5: Integrate create-sale price resolution**

In \`SaleService::persistResolvedSale\`, calculate system prices for pickup and delivery before totals. For V3 lines with \`price_was_edited = false\`, use the resolved system price. For \`true\`, use the validated requested price and snapshot the resolved system price. For legacy V1/V2 lines with no intent marker, preserve the existing selling-price behavior and default the new metadata to \`NULL/false\`.

- [ ] **Step 6: Persist metadata through SaleItemService**

Extend \`attributesForResolvedLine()\` and new-sale item creation to include the already backend-resolved snapshot fields. Ensure \`total\`, \`profit\`, Profit Guard, delivery fee, commission, and stock use final \`selling_price\` exactly as before.

- [ ] **Step 7: Run focused create/profit tests**

Run:

\`\`\`powershell
php artisan test --filter='SaleV3PriceOverrideTest|SaleDecimalIntegrityTest|SaleCommissionLifecycleCharacterizationTest'
\`\`\`

Expected: normal and overridden lines persist correctly; profit and totals use the final selling price; protected pricing behavior remains unchanged.

---

### Task 3: Preserve price metadata in Hold Bills and Resume

**Files:**
- Modify: \`app/Http/Requests/Sales/StoreHoldBillRequest.php\`
- Modify: \`app/Services/HoldBillService.php\`
- Modify: \`app/Http/Controllers/HoldBillController.php\`
- Modify: \`app/Models/HoldBillItem.php\`
- Modify: \`public/js/modules/final-pos.js\`
- Modify: \`public/js/modules/sale-v3.js\`
- Test: \`tests/Feature/Sales/HoldBillWorkflowTest.php\`
- Test: \`tests/Frontend/final-pos.test.mjs\`

**Interfaces:**
- Hold create input accepts \`items.*.price_was_edited\`, \`items.*.selling_price\`, quantity, product, and unit only.
- Hold Bill response includes \`selling_price\`, \`original_price\`, and \`price_override_flag\`.
- Resume state includes an internal per-line price intent/metadata marker.

- [ ] **Step 1: Add failing Hold Bill regression tests**

Create one normal line and one overridden line at the same system price, then assert the Hold Item stores:

\`\`\`php
$this->assertDatabaseHas('hold_bill_items', [
    'selling_price' => '99.50',
    'original_price' => '100.00',
    'price_override_flag' => true,
]);
\`\`\`

Resume the hold and assert the browser state restores both lines' prices and override markers. Change the product price after holding, resume without editing, and assert the stored Hold metadata remains unchanged.

- [ ] **Step 2: Run Hold tests and capture red**

Run:

\`\`\`powershell
php artisan test --filter=HoldBillWorkflowTest
node --test tests/Frontend/final-pos.test.mjs
\`\`\`

Expected: FAIL because Hold Items currently have no price metadata and Resume only restores selling price.

- [ ] **Step 3: Compute Hold snapshots server-side**

Resolve the selected address/zone and product/unit through the same existing pricing service used by Sale. Persist the calculated system price only when \`price_was_edited\` is true; otherwise persist \`original_price = NULL\` and \`price_override_flag = false\`.

- [ ] **Step 4: Restore metadata without repricing**

Update \`final-pos.js\` and \`sale-v3.js\` so Resume sets each item's actual price, system-price reference, and edited marker from the response. Do not call normal pricing recalculation for an untouched held line during Resume.

- [ ] **Step 5: Handle edited-after-resume lines**

When the cashier changes a resumed line, mark only that line as newly edited. Sale creation must preserve Hold metadata for untouched lines and recalculate metadata for changed lines.

- [ ] **Step 6: Run Hold regression tests**

Run:

\`\`\`powershell
php artisan test --filter=HoldBillWorkflowTest
node --test tests/Frontend/final-pos.test.mjs
\`\`\`

Expected: Hold/Resume retains delivery type, quantities, actual prices, and metadata across a later system-price change.

---

### Task 4: Make Edit Sale preserve, override, and restore explicit

**Files:**
- Modify: \`app/Http/Requests/Sales/UpdateSaleRequest.php\`
- Modify: \`app/Services/SaleService.php\`
- Modify: \`resources/views/sales/edit.blade.php\`
- Modify: \`public/js/modules/sale-edit.js\`
- Test: \`tests/Feature/Sales/SaleEditSafetyTest.php\`
- Test: \`tests/Feature/Sales/SaleUpdateTest.php\`

**Interfaces:**
- \`normalized_items.*.price_action\` accepts \`preserve\`, \`override\`, or \`system\`.
- \`preserve\` copies existing \`selling_price\`, \`original_price\`, and \`price_override_flag\` without comparing to current pricing.
- \`override\` accepts the validated entered selling price and recomputes \`original_price\` from current context.
- \`system\` recomputes the current system price and clears override metadata.

- [ ] **Step 1: Add failing edit tests**

Cover:

1. Existing overridden item edited only in customer/date/header retains all three price fields.
2. Existing overridden item submitted with \`price_action = preserve\` retains metadata even after Product/Zone prices change.
3. \`price_action = override\` stores a new original system price and \`price_override_flag = true\`.
4. \`price_action = system\` stores current system price, \`original_price = NULL\`, and \`price_override_flag = false\`.

- [ ] **Step 2: Run the focused edit tests and confirm red**

Run:

\`\`\`powershell
php artisan test --filter='SaleEditSafetyTest|SaleUpdateTest'
\`\`\`

Expected: FAIL because update planning currently compares only product/quantity/price and does not carry snapshot metadata.

- [ ] **Step 3: Validate \`price_action\` without breaking legacy forms**

Add the optional field to normalized items. Existing requests without the field use \`preserve\` for retained existing items and keep the legacy behavior for newly added rows.

- [ ] **Step 4: Update SaleService update planning**

Copy snapshot fields for exact/preserve lines. Call the price snapshot service only for explicit \`override\` or \`system\` actions. Keep revision checks, stock movement replacement, commission recalculation, and transaction rollback unchanged.

- [ ] **Step 5: Update edit UI contract**

Render a hidden \`price_action\` per row. Mark an existing unchanged row as \`preserve\`, mark manual price input as \`override\`, and provide a row-level restore action that sets the latest system price and \`system\`.

- [ ] **Step 6: Run edit regression tests**

Run:

\`\`\`powershell
php artisan test --filter='SaleEditSafetyTest|SaleUpdateTest|SaleSnapshotPreservingUpdateTest'
node --check public/js/modules/sale-edit.js
\`\`\`

Expected: unchanged override prices remain unchanged, explicit override/restore actions update metadata correctly, and existing stock/revision tests stay green.

---

### Task 5: Implement V3 pickup default, context-aware cart pricing, and inline unit-price editing

**Files:**
- Create: \`app/Http/Requests/Sales/StoreSaleV3Request.php\`
- Modify: \`app/Http/Controllers/SaleV3Controller.php\`
- Modify: \`resources/views/sales-v3/partials/cart.blade.php\`
- Modify: \`resources/views/sales-v3/partials/customer-bar.blade.php\`
- Modify: \`public/js/modules/sale-v3.js\`
- Modify: \`public/css/sale-v3.css\`
- Test: \`tests/Feature/Sales/SaleV3PageTest.php\`
- Test: \`tests/Feature/Sales/SaleV3CartWorkflowTest.php\`
- Test: \`tests/Unit/Sales/SaleV3BrowserStateContractTest.php\`
- Test: \`tests/Frontend/sale-v3-price-override.test.mjs\`

**Interfaces:**
- V3 request requires \`delivery_type\` in \`pickup,delivery\` and validates \`items.*.price_was_edited\` as boolean.
- Draft item state contains \`systemPrice\`, \`price\`, and \`priceWasEdited\`.
- \`buildPayload()\` sends \`selling_price\` and \`price_was_edited\`, never \`original_price\` or \`price_override_flag\`.

- [ ] **Step 1: Add failing frontend and page contract tests**

Assert the initial state is pickup, both controls expose one active state, delivery-only fields hide on pickup, and the payload contains \`delivery_type\` plus \`price_was_edited\` but not backend-owned snapshot fields. Add tests for context changes: normal lines reprice, overridden lines retain sale price and update only their system reference.

- [ ] **Step 2: Run focused frontend/page tests and confirm red**

Run:

\`\`\`powershell
node --test tests/Frontend/sale-v3-price-override.test.mjs
php artisan test --filter='SaleV3PageTest|SaleV3CartWorkflowTest|SaleV3BrowserStateContractTest'
\`\`\`

Expected: FAIL because current state starts delivery, price is recalculated without an override marker, and controls are not visibly active/inactive.

- [ ] **Step 3: Add V3-specific request and controller wiring**

Use shared sale-shape validation behavior while requiring \`delivery_type\` for V3. Pass the validated intent field to \`SaleService\`; keep V2 on its existing request contract.

- [ ] **Step 4: Update canonical draft state and delivery toggle**

Initialize pickup. Centralize toggle behavior in \`state.deliveryType\`; on pickup clear address/zone and fee; on delivery show existing address/zone/fee controls. Add active classes/ARIA state to the two visible buttons.

- [ ] **Step 5: Add inline unit-price editor and restore action**

Render a two-decimal editable control or pencil affordance per line. On Enter/Save validate positive price, set \`priceWasEdited\`, update line total, subtotal, discount, delivery fee preview, profit preview, and grand total. Restore uses the latest context \`systemPrice\` and clears the marker.

- [ ] **Step 6: Reprice on quantity and fulfillment context changes**

Use the existing frontend pricing context for immediate feedback. Reprice only non-overridden items; keep overridden sale prices while updating their reference system price. Keep delivery fee and Profit Guard formulas unchanged; backend remains authoritative on submit.

- [ ] **Step 7: Run focused V3 checks**

Run:

\`\`\`powershell
node --test tests/Frontend/sale-v3-price-override.test.mjs tests/Frontend/final-pos.test.mjs
php artisan test --filter='SaleV3PageTest|SaleV3CartWorkflowTest|SaleV3BrowserStateContractTest|SaleV3StoreTest'
node --check public/js/modules/sale-v3.js
\`\`\`

---

### Task 6: Simplify default cash payment and one-click Sale confirmation

**Files:**
- Modify: \`public/js/modules/pos-payment.js\`
- Modify: \`public/js/modules/final-pos.js\`
- Modify: \`resources/views/sales-v3/partials/final-payment-modal.blade.php\`
- Modify: \`public/css/sale-v3.css\`
- Test: \`tests/Frontend/pos-payment.test.mjs\`
- Test: \`tests/Frontend/pos-payment-integration.test.mjs\`
- Test: \`tests/Frontend/final-pos.test.mjs\`
- Test: \`tests/Unit/Sales/PosPaymentUiContractTest.php\`

**Interfaces:**
- Payment controller exposes a direct default-cash confirmation path that resolves cash with \`received_amount = total\` and \`change_amount = 0.00\`.
- Existing \`open()\` path remains available for PromptPay and Mixed Payment.
- Final confirmation calls the direct path; “change payment method” calls \`open()\`.

- [ ] **Step 1: Add failing payment tests**

Assert opening payment initializes:

\`\`\`js
assert.equal(received.value, total);
assert.equal(change.textContent, "0.00");
\`\`\`

Assert the primary V3 confirmation invokes one Sale submission without opening the payment modal, while the alternate method button opens it.

- [ ] **Step 2: Run payment tests and confirm red**

Run:

\`\`\`powershell
node --test tests/Frontend/pos-payment.test.mjs tests/Frontend/pos-payment-integration.test.mjs tests/Frontend/final-pos.test.mjs
\`\`\`

- [ ] **Step 3: Implement direct cash confirmation**

Keep \`PosPayment.resolve()\` and backend payment validation unchanged. Add only the controller method needed to build the canonical cash payload and call the existing \`onConfirm\` callback.

- [ ] **Step 4: Update confirmation UI**

Show net total, fulfillment, payment method, and a secondary change-method action. Remove redundant unit-price/detail columns from the confirmation summary while retaining product, quantity, and line total.

- [ ] **Step 5: Run payment and contract tests**

Run:

\`\`\`powershell
node --test tests/Frontend/pos-payment.test.mjs tests/Frontend/pos-payment-integration.test.mjs tests/Frontend/final-pos.test.mjs
php artisan test --filter=PosPaymentUiContractTest
\`\`\`

---

### Task 7: Keep the modal open for committed success and expose document actions

**Files:**
- Modify: \`public/js/modules/final-pos.js\`
- Modify: \`resources/views/sales-v3/partials/final-payment-modal.blade.php\`
- Modify: \`public/css/sale-v3.css\`
- Modify: \`resources/views/sales/invoice_v2/items.blade.php\`
- Test: \`tests/Frontend/final-pos.test.mjs\`
- Test: \`tests/Feature/Sales/SaleInvoiceV2FulfillmentTest.php\`
- Test: \`tests/Feature/Sales/SalePaymentDisplayTest.php\`

**Interfaces:**
- \`showSuccess()\` is called only by the successful Sale response after commit.
- Failed responses leave the confirmation modal, cart, and draft state unchanged.
- Existing delivery-note and tax-invoice routes are opened with the created Sale ID.

- [ ] **Step 1: Add failing success/rollback/document tests**

Assert success shows the sale number and both print actions immediately. Assert a rejected Sale response does not call \`resetSale()\`. Assert invoice and tax invoice render the persisted override \`selling_price\`.

- [ ] **Step 2: Run focused success/document tests and confirm red**

Run:

\`\`\`powershell
node --test tests/Frontend/final-pos.test.mjs
php artisan test --filter='SaleInvoiceV2FulfillmentTest|SalePaymentDisplayTest'
\`\`\`

- [ ] **Step 3: Implement committed-success state transition**

Keep the modal open, hide edit/confirm actions, show the large success message and Sale number, enable the document panel, and reset only after the user finishes. Preserve the draft on validation or transaction errors.

- [ ] **Step 4: Keep document item rendering authoritative**

Keep document templates reading \`sale_items.selling_price\`; add only assertions/labels needed to make the override display explicit. Do not change totals, tax formulas, or document routes.

- [ ] **Step 5: Run success/document tests**

Run:

\`\`\`powershell
node --test tests/Frontend/final-pos.test.mjs
php artisan test --filter='SaleInvoiceV2FulfillmentTest|SalePaymentDisplayTest'
\`\`\`

---

### Task 8: Full POS V3 regression, browser verification, and review checkpoint

**Files:**
- Review only: all files changed by Tasks 1–7
- Test: \`tests/Feature/Sales/SaleV3PriceOverrideTest.php\`
- Test: \`tests/Feature/Sales/HoldBillWorkflowTest.php\`
- Test: \`tests/Feature/Sales/SaleEditSafetyTest.php\`
- Test: \`tests/Feature/Sales/SalePaymentPersistenceTest.php\`
- Test: \`tests/Frontend/*.test.mjs\` relevant to POS V3

- [ ] **Step 1: Run PHP syntax and JavaScript syntax checks**

Run:

\`\`\`powershell
php -l app/Services/SaleService.php
php -l app/Services/Sales/SalePriceSnapshotService.php
php -l app/Services/HoldBillService.php
php -l app/Http/Requests/Sales/StoreSaleV3Request.php
node --check public/js/modules/sale-v3.js
node --check public/js/modules/final-pos.js
node --check public/js/modules/pos-payment.js
node --check public/js/modules/sale-edit.js
\`\`\`

- [ ] **Step 2: Run the complete scoped PHP regression suite**

Run against the isolated testing database:

\`\`\`powershell
php artisan test --filter='SaleV3|HoldBillWorkflow|SaleEditSafety|SaleUpdate|SalePayment|SaleInvoiceV2Fulfillment|SaleSnapshotPreservingUpdate|SaleValidation|SaleDecimalIntegrity|SaleCommissionLifecycleCharacterization'
\`\`\`

Expected: pickup, delivery, override, Hold/Resume, Edit Sale, payment, invoice, tax invoice, profit, stock, and legacy behavior all pass.

- [ ] **Step 3: Run relevant frontend tests**

Run:

\`\`\`powershell
node --test tests/Frontend/final-pos.test.mjs tests/Frontend/pos-payment.test.mjs tests/Frontend/pos-payment-integration.test.mjs tests/Frontend/sale-v3-price-override.test.mjs
\`\`\`

- [ ] **Step 4: Run formatting and diff checks**

Run Laravel Pint for changed PHP files and:

\`\`\`powershell
git diff --check
git status --short
\`\`\`

Confirm only approved task files are staged; leave \`design-qa.md\` and the pre-existing \`docs/superpowers/plans/2026-07-29-final-pos-hold-bill.md\` untouched.

- [ ] **Step 5: Perform browser verification**

After confirming Laragon/Apache/PostgreSQL is using a non-production test database, open \`/sales-v3\` and verify: new bill pickup default; visible toggle; delivery address/zone/fee; inline price edit and restore; context repricing; Hold/Resume metadata; default cash one-click sale; failure retains cart; success panel; delivery note and tax invoice links. Record any manual-only gaps.

- [ ] **Step 6: Review final diff and prepare delivery checkpoint**

Review migration safety, protected pricing files, request contracts, transaction rollback, sensitive data, and generated artifacts. Stage only approved files, commit implementation, push \`codex/pos-v3-ux-improvement-sprint\`, report tests and remaining risks, and wait for approval before merge/deploy.
