# POS V3 Pricing Zone Before Customer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let POS V3 apply an explicitly selected Pricing Zone before customer selection, including while Pickup, while keeping Delivery Fee/address/date behavior tied to fulfillment and preserving the context through customer creation and Hold/Resume.

**Architecture:** Keep the existing `ZonePricingService` as the only pricing formula source. Split the POS draft into `pricingZone` (price markup/rounding context) and `deliveryZone` (address/fulfillment context), pass `pricing_zone_id` through POS V3 and Hold APIs, and persist a nullable pricing-zone reference/snapshot separately from delivery-zone fields. Customer creation will omit an empty address record when no delivery data was supplied.

**Tech Stack:** Laravel 13, PHP 8.3, PostgreSQL, Blade, vanilla JavaScript modules, Bootstrap modal, PHPUnit, Node built-in test runner.

## Global Constraints

- Scope is POS V3, Customer creation from POS, and the Hold/Resume state required by Pricing Context; POS V1/V2 remain unchanged.
- Preserve `ZonePricingService`, Average Cost, Product Cost, Price Tier, rounding formulas, Profit Guard formulas, Delivery Fee formulas, Commission, Stock, Payment, invoice, and sale lifecycle behavior.
- Pickup remains the default and keeps zero Delivery Fee/address/date requirements; `pricingZone` may still affect item pricing during Pickup.
- Delivery validation uses the selected address and its active `deliveryZone`; an address-zone mismatch requires the Bootstrap confirmation flow.
- Never use Production DB for tests, migrations, QA, or diagnostics; `.env.testing` points to `atrilak_pos_final_test_20260729`.
- Do not touch existing unrelated untracked files: `design-qa.md`, `docs/superpowers/plans/2026-07-29-final-pos-hold-bill.md`, and `docs/superpowers/plans/2026-08-05-invoice-reference-css-redesign.md`.
- Do not merge `main`, deploy, or push unless separately instructed.

---

### Task 1: Establish red coverage for the new pricing-context contract

**Files:**
- Modify: `tests/Frontend/sale-v3-behavior.test.mjs`
- Modify: `tests/Frontend/final-pos.test.mjs`
- Modify: `tests/Unit/Sales/SaleV3BrowserStateContractTest.php`
- Modify: `tests/Feature/Sales/SaleV3PageTest.php`
- Modify: `tests/Feature/Customers/CustomerModuleTest.php`
- Modify: `tests/Feature/Sales/SaleV3PriceOverrideTest.php`
- Modify: `tests/Feature/Sales/HoldBillWorkflowTest.php`

**Interfaces:**
- The frontend harness will expose `state.pricingZone`, `state.deliveryZone`, the enabled zone selector, and a fake Bootstrap mismatch modal.
- Backend tests will send `pricing_zone_id` and assert item price, delivery fee, fulfillment, and persisted pricing context separately.

- [ ] **Step 1: Add failing frontend tests for initial selection and Pickup repricing**

Add tests with these names and assertions:

```js
test("zone selector starts enabled and reprices pickup quotes without delivery fee", async () => {
    const quoteZone = { id: 1, name: "คลองแม่ลาย", active: true,
        price_markup_percent: 10, rounding_increment: "1.00", minimum_profit: 100 };
    const harness = createHarness({ zones: [quoteZone], product: {
        id: 1, name: "Quoted Product", unit: "piece", stock_qty: 20,
        price: 205, rounding_unit: 1, rounding_direction: "nearest",
        productUnits: [{ id: 11, selling_price: 205, is_sale_unit: true,
            unit: { name: "piece" }, price_tiers: [] }],
    }});

    assert.equal(harness.priceZoneSelect.disabled, false);
    harness.priceZoneSelect.value = "1";
    harness.priceZoneSelect.selectedOptions = [harness.priceZoneSelect.options[0]];
    await harness.priceZoneSelect.dispatch("change");
    await harness.productCard.dispatch("click");
    await harness.elements.get("#v3-quantity-confirm").dispatch("click");

    assert.equal(harness.context.state.deliveryType, "pickup");
    assert.equal(harness.context.state.pricingZone.id, 1);
    assert.equal(harness.context.state.cart[0].price, 226);
    assert.equal(harness.context.state.deliveryFee, 0);
});
```

- [ ] **Step 2: Add failing frontend tests for customer preservation and mismatch decisions**

Add tests for:

- selecting Pricing Zone A, loading a customer with no addresses, and retaining Zone A/cart;
- selecting an address in the same zone without opening the modal;
- selecting an address in Zone B, cancelling the modal, and retaining Zone A/item price while keeping the address selected;
- selecting the same address again, confirming the modal, and repricing to Zone B;
- inactive selector/address zones being rejected without clearing a previously selected Pricing Zone.

- [ ] **Step 3: Add failing backend tests for nullable customer delivery data**

Add to `CustomerModuleTest`:

```php
public function test_pos_customer_create_can_omit_address_and_zone(): void
{
    $response = $this->postJson(route('sales.v3.customers.store'), [
        'name' => 'POS Name Only Customer',
        'phone' => '0800000011',
    ]);

    $response->assertCreated()
        ->assertJsonPath('customer.name', 'POS Name Only Customer')
        ->assertJsonPath('customer.delivery_addresses', []);

    $this->assertDatabaseHas('customers', ['name' => 'POS Name Only Customer']);
    $this->assertDatabaseCount('customer_delivery_addresses', 0);
}
```

Add a second test proving an address may be created with a null zone when an address value is supplied.

- [ ] **Step 4: Add failing backend tests for explicit Pickup Pricing Zone and Hold/Resume**

Add tests that create a product priced at `205.00` and an active zone with `10.00%` markup and `1.00` rounding:

- POST POS V3 Pickup with `pricing_zone_id`, `price_was_edited=false`, and requested `226.00`; assert the saved item is `226.00`, `price_override_flag=false`, `delivery_type=pickup`, `delivery_fee=0.00`, and no delivery zone.
- POST POS V3 with an inactive `pricing_zone_id`; assert `422` and no sale.
- Create a Pickup Hold with `pricing_zone_id`; assert its item snapshot is `226.00`, then resume and assert the JSON includes the pricing-zone context.

- [ ] **Step 5: Run the red checks and record meaningful failures**

Run:

```powershell
node --test tests/Frontend/sale-v3-behavior.test.mjs tests/Frontend/final-pos.test.mjs
php artisan test --filter='SaleV3PageTest|CustomerModuleTest|SaleV3PriceOverrideTest|HoldBillWorkflowTest|SaleV3BrowserStateContractTest'
```

Expected red causes are the disabled selector/old `state.zone` contract, absent pricing-zone API handling, customer service creating an empty address, and missing hold pricing context—not test setup errors.

### Task 2: Add the forward-safe pricing-context persistence and request contract

**Files:**
- Create: `database/migrations/2026_08_09_000001_add_pricing_zone_context_to_sales_and_hold_bills.php`
- Modify: `app/Models/Sale.php`
- Modify: `app/Models/HoldBill.php`
- Modify: `app/Http/Requests/Sales/StoreSaleV3Request.php`
- Modify: `app/Http/Requests/Sales/StoreHoldBillRequest.php`
- Modify: `app/Services/Sales/SaleIdempotencyService.php`

**Interfaces:**
- `pricing_zone_id` is nullable and accepts only an active `delivery_zones.id` in POS V3 and Hold requests.
- New columns are nullable, use `nullOnDelete()`, and have no backfill.

- [ ] **Step 1: Write the migration with nullable fields and no historical rewrite**

Add to both `sales` and `hold_bills`:

```php
$table->foreignId('pricing_zone_id')
    ->nullable()
    ->constrained('delivery_zones')
    ->nullOnDelete();
$table->string('pricing_zone_name_snapshot')->nullable();
$table->decimal('pricing_zone_markup_percent_snapshot', 8, 2)->nullable();
$table->decimal('pricing_zone_rounding_increment_snapshot', 8, 2)->nullable();
```

Place the fields after the existing delivery-zone snapshot fields. The `down()` method drops the foreign key/columns for both tables.

- [ ] **Step 2: Add model fillable/casts/relations**

Add the pricing fields and a `pricingZone(): BelongsTo` relation to `Sale` and `HoldBill`. Cast the snapshot percentages/increments as `decimal:2`.

- [ ] **Step 3: Add active-zone validation**

Add `pricing_zone_id` to `StoreSaleV3Request` and `StoreHoldBillRequest` with:

```php
['nullable', 'integer', Rule::exists('delivery_zones', 'id')->where('active', true)]
```

Import `Illuminate\Validation\Rule` where needed. Keep all V1/V2 request contracts unchanged.

- [ ] **Step 4: Include Pricing Zone in idempotency hashing**

Add normalized `pricing_zone_id` to `SaleIdempotencyService::payloadHash()` before the items list so a retry key cannot replay a different pricing context.

- [ ] **Step 5: Run migration-focused tests on the test database**

Run:

```powershell
php artisan test --filter='SaleV3PriceOverrideTest|HoldBillWorkflowTest'
```

For the migration gate, run `php artisan migrate:fresh --env=testing` only against `.env.testing`, then run the scoped tests again. Record the test database name and never use `.env`.

### Task 3: Implement authoritative backend pricing separation

**Files:**
- Modify: `app/Http/Controllers/SaleV3Controller.php`
- Modify: `app/Services/SaleService.php`
- Modify: `app/Services/HoldBillService.php`

**Interfaces:**
- POS V3 passes `pricing_zone_id` to `SaleService::createSale()`.
- Sale persistence stores actual delivery fields from the address separately from pricing-zone fields.
- `ZonePricingService::priceLine()` remains unchanged and is invoked with `pickup=false` only when an explicit/fallback Pricing Zone exists; `deliveryFee()` still receives actual delivery context and the real Pickup flag.

- [ ] **Step 1: Pass `pricing_zone_id` through the POS V3 controller**

Add `'pricing_zone_id' => $validated['pricing_zone_id'] ?? null` to the existing `SaleService::createSale()` array. Do not alter V1/V2 controllers.

- [ ] **Step 2: Split `SaleService` zone resolution**

Replace the single delivery-only pricing context with two local values:

```php
[$deliveryZone, $address] = $this->resolveDeliveryContext($data);
$pricingZone = $this->resolvePricingZone($data, $deliveryZone);
$pickup = ($data['delivery_type'] ?? 'delivery') === 'pickup';
$pricingPickup = $pricingZone === null;
```

`resolveDeliveryContext()` retains the current address/active-zone checks only for Delivery. `resolvePricingZone()` loads and verifies the explicit active `pricing_zone_id`, falling back to `$deliveryZone` for Delivery and returning null for Pickup without an explicit zone.

- [ ] **Step 3: Use the existing pricing engine for item prices**

Pass `$pricingZone` and `$pricingPickup` to both price-line branches. This makes an explicit Pickup quote use the existing Zone markup/rounding pipeline without changing `ZonePricingService`.

- [ ] **Step 4: Keep Delivery Fee and fulfillment snapshots on the delivery context**

Keep `deliveryFee($productProfitAfterDiscount, $deliveryZone, $pickup)`. Set `delivery_zone_id`, delivery-zone snapshots, minimum profit, and delivery address snapshots exactly from `$deliveryZone`/`$address`. Set the new pricing-zone fields from `$pricingZone`, without setting delivery fields for Pickup.

- [ ] **Step 5: Apply the same separation to HoldBillService**

Resolve the address zone only for Delivery. Resolve `pricing_zone_id` explicitly, fallback to the address zone for Delivery, and call `SalePriceSnapshotService::systemPrice()` with the pricing zone and `pricingPickup = ($pricingZone === null)`. Persist pricing-zone snapshots while keeping delivery date/fee behavior unchanged.

- [ ] **Step 6: Run the focused backend tests until green**

Run:

```powershell
php artisan test --filter='SaleV3PriceOverrideTest|HoldBillWorkflowTest|SaleV3StoreTest'
```

### Task 4: Make Customer creation omit empty delivery addresses

**Files:**
- Modify: `app/Services/CustomerService.php`
- Modify: `tests/Feature/Customers/CustomerModuleTest.php`

**Interfaces:**
- `CustomerService::create()` returns a customer with zero addresses when no address/zone/receiver delivery data is supplied.
- Existing address creation remains one primary address and accepts a nullable zone.

- [ ] **Step 1: Add a focused failing service/feature test for blank delivery data**

Use the POS JSON endpoint with only `name` and `phone`; assert the customer exists, `address` is null, and no `customer_delivery_addresses` row is created.

- [ ] **Step 2: Implement explicit delivery-data detection**

Before calling `savePrimaryAddress()`, consider only meaningful delivery fields (`address`, `delivery_zone_id`, `receiver_name`, `receiver_phone`, and `address_name`) after trimming. Do not treat the hidden `use_customer_phone=1` flag alone as an address.

```php
$hasDeliveryData = collect(['address', 'delivery_zone_id', 'receiver_name', 'receiver_phone', 'address_name'])
    ->contains(fn (string $key): bool => trim((string) ($data[$key] ?? '')) !== '');
```

Only create/update the customer invoice address when `$hasDeliveryData` is true. Keep code generation and transaction behavior intact.

- [ ] **Step 3: Run Customer feature tests**

```powershell
php artisan test --filter='CustomerModuleTest'
```

### Task 5: Implement the POS V3 Pricing Zone state and mismatch modal

**Files:**
- Modify: `resources/views/sales-v3/partials/customer-bar.blade.php`
- Create: `resources/views/sales-v3/partials/zone-mismatch-modal.blade.php`
- Modify: `resources/views/sales-v3/index.blade.php`
- Modify: `public/js/modules/sale-v3.js`
- Modify: `public/css/sale-v3.css` only if the existing modal/selector needs a scoped layout adjustment
- Modify: `tests/Frontend/sale-v3-behavior.test.mjs`
- Modify: `tests/Feature/Sales/SaleV3PageTest.php`
- Modify: `tests/Unit/Sales/SaleV3BrowserStateContractTest.php`

**Interfaces:**
- `state.pricingZone` is the only price-context selection.
- `state.deliveryZone` is the only address/fulfillment zone.
- `setPricingZone(zone, { reprice = true } = {})` updates the explicit Pricing Zone without touching fulfillment.
- `setDeliveryType()` preserves `pricingZone` and derives only `deliveryZone`/fee validation state.

- [ ] **Step 1: Add the failing DOM contract for an enabled selector/modal**

Update `SaleV3PageTest` and `SaleV3BrowserStateContractTest` so the expected markup includes:

```html
<select id="v3-price-zone-select" aria-label="โซนราคาสำหรับคำนวณราคา">
    <option value="">เลือกโซนราคา</option>
</select>
<div id="v3-zone-mismatch-modal" class="modal fade" data-backdrop="static" data-keyboard="false">
```

Remove assertions that require the selector to be disabled.

- [ ] **Step 2: Render the enabled selector and mismatch modal**

Keep active zones from the controller, add the clear placeholder, and include the new modal partial after the existing customer/payment modals. The modal must have buttons with stable IDs for confirm and cancel and must not use browser `confirm()`.

- [ ] **Step 3: Add explicit state and pricing helpers**

Initialize:

```js
const state = {
    cart: [], customerId: "", addressId: "", deliveryType: "pickup",
    address: null, addresses: [], addressLoading: false,
    pricingZone: null, deliveryZone: null,
    deliveryFee: 0, deliveryFeeEdited: false, discount: 0, note: "",
    holdBillId: null, activeProduct: null, filter: "all", category: "",
};
```

Change `unitPrice()` to use `state.pricingZone` regardless of `deliveryType`; keep `updateDeliveryPreview()` at zero for Pickup and derive Delivery fee from `state.deliveryZone` only.

- [ ] **Step 4: Enable zone selection and reprice through the existing path**

In `refreshPricingContext()`, keep the selector enabled, show `state.pricingZone`, and update the status text with pricing/address context. Add a selector `change` listener that parses the selected zone, rejects inactive zones, updates `pricingZone`, calls `refreshPricingContext()`, and calls `render()`.

- [ ] **Step 5: Preserve Pricing Zone during customer/address changes**

Update `loadAddresses()` to clear only address/delivery context, never `pricingZone` or cart. Update `clearCustomer()` and `resetSale()` so clearing a customer preserves Pricing Zone but starting a new bill clears it. When an address supplies the first active zone and no Pricing Zone is selected, use it as the initial Pricing Zone; never overwrite an existing explicit Pricing Zone silently.

- [ ] **Step 6: Add asynchronous Bootstrap mismatch confirmation**

Implement a Promise-backed modal decision with stable confirm/cancel handlers. `applyAddressSelection()` must:

1. Reject inactive address zones.
2. Store the selected address and its `deliveryZone`.
3. If both zones are active and differ, await the modal.
4. On Confirm, set Pricing Zone to the address zone and reprice.
5. On Cancel, keep Pricing Zone/cart prices and retain the selected address.

Do not call `window.confirm()` for this flow. Keep the address load sequence guard.

- [ ] **Step 7: Update Delivery/submit state and payload**

Make `canConfirmDelivery()` require `state.deliveryZone` only for Delivery. Keep Pickup free of address/date validation. Add `pricing_zone_id: state.pricingZone?.id || null` to `buildPayload()`.

- [ ] **Step 8: Run frontend behavior tests until green**

```powershell
node --test tests/Frontend/sale-v3-behavior.test.mjs
```

### Task 6: Preserve Pricing Zone through customer create and Hold/Resume

**Files:**
- Modify: `public/js/modules/final-pos.js`
- Modify: `tests/Frontend/final-pos.test.mjs`
- Modify: `tests/Unit/Sales/SaleV3BrowserStateContractTest.php`
- Modify: `tests/Feature/Sales/HoldBillWorkflowTest.php`

**Interfaces:**
- `FinalPos` consumes the POS context's `pricingZone`/`deliveryZone` state.
- Hold payload includes `pricing_zone_id` and pricing snapshots.
- Resume restores Pricing Zone before adding or repricing new cart lines.

- [ ] **Step 1: Add failing customer-create preservation test**

Set a Pricing Zone and cart in the final-pos harness, submit the create-customer form with no address/zone, mock a successful POS customer response with zero addresses, and assert the new customer is auto-selected while Pricing Zone/cart remain unchanged.

- [ ] **Step 2: Preserve Pricing Zone in `clearCustomer()` and `createCustomer()` flows**

Do not reset Pricing Zone when opening/resetting the modal. Keep the current `await context.setCustomer()` auto-select behavior; the updated `loadAddresses()` will preserve the Pricing Zone and cart.

- [ ] **Step 3: Add Pricing Zone to Hold creation and resume**

Include the pricing ID/name/markup/rounding snapshots in the Hold payload. On resume, reconstruct the Pricing Zone from the relation or snapshots before restoring held cart items; do not trigger a mismatch modal for the hold's own saved context.

- [ ] **Step 4: Keep New Bill reset complete**

Ensure `resetSale()` clears both pricing and delivery zones, address/customer state, cart, discounts, notes, hold ID, and date fields exactly as the existing new-bill flow requires.

- [ ] **Step 5: Run frontend Hold/Resume tests**

```powershell
node --test tests/Frontend/final-pos.test.mjs tests/Frontend/sale-v3-behavior.test.mjs
```

### Task 7: Run regression verification, browser QA, review, and commit

**Files:**
- Modify only files already listed above if a test exposes a scoped regression.
- Do not stage the pre-existing unrelated untracked files.

- [ ] **Step 1: Run scoped PHP tests**

```powershell
php artisan test --filter='SaleV3|CustomerModuleTest|HoldBillWorkflowTest|DeliveryZonePricingTest|PricingWorkflowTest|SaleV3BrowserStateContractTest'
```

Record passed, failed, skipped, and assertion counts.

- [ ] **Step 2: Run all frontend tests and JavaScript syntax checks**

```powershell
node --test tests/Frontend/*.test.mjs
node --check public/js/modules/sale-v3.js
node --check public/js/modules/final-pos.js
```

- [ ] **Step 3: Run PHP syntax, Pint, and diff checks**

```powershell
php -l app/Services/SaleService.php
php -l app/Services/HoldBillService.php
php -l app/Services/CustomerService.php
php -l app/Http/Controllers/SaleV3Controller.php
php artisan pint --test app/Services/SaleService.php app/Services/HoldBillService.php app/Services/CustomerService.php app/Http/Controllers/SaleV3Controller.php app/Http/Requests/Sales/StoreSaleV3Request.php app/Http/Requests/Sales/StoreHoldBillRequest.php app/Models/Sale.php app/Models/HoldBill.php tests/Feature/Customers/CustomerModuleTest.php tests/Feature/Sales/SaleV3PriceOverrideTest.php tests/Feature/Sales/HoldBillWorkflowTest.php
git diff --check
```

- [ ] **Step 4: Run Browser QA on the test environment**

Before starting Laragon/Apache/PostgreSQL or manual browser testing, inform the Owner. Use the browser control skill and a test database only. Verify:

1. Pickup starts with enabled `เลือกโซนราคา`; selecting Zone reprices a product to the Zone price and keeps fee 0.
2. Adding a customer with only name/phone auto-selects it without losing Zone/cart/price.
3. Customer without address leaves Pricing Zone usable.
4. Same address Zone does not open confirmation.
5. Different address Zone opens modal; Cancel preserves old Zone/price; Confirm changes Zone/reprices.
6. Pickup and Delivery payment/success/print flows remain intact.

Capture console/network errors and note any unverified scenario.

- [ ] **Step 5: Review diff and run the change-review gate**

Check branch, base commit, staged file list, working tree, migration scope, and that no `.env`, data dump, log, cache, or unrelated untracked file is included. Use `keystone:change-review` for this user-impacting behavior and migration change; resolve blockers before commit.

- [ ] **Step 6: Commit only after all gates pass**

Stage only the scoped files and commit on `codex/pos-zone-before-customer`:

```powershell
git add -- docs/superpowers/specs/2026-08-09-pos-zone-before-customer-design.md docs/superpowers/plans/2026-08-09-pos-zone-before-customer.md database/migrations/2026_08_09_000001_add_pricing_zone_context_to_sales_and_hold_bills.php app/Models/Sale.php app/Models/HoldBill.php app/Http/Requests/Sales/StoreSaleV3Request.php app/Http/Requests/Sales/StoreHoldBillRequest.php app/Http/Controllers/SaleV3Controller.php app/Services/SaleService.php app/Services/HoldBillService.php app/Services/CustomerService.php app/Services/Sales/SaleIdempotencyService.php resources/views/sales-v3/partials/customer-bar.blade.php resources/views/sales-v3/partials/zone-mismatch-modal.blade.php resources/views/sales-v3/index.blade.php public/js/modules/sale-v3.js public/js/modules/final-pos.js public/css/sale-v3.css tests/Frontend/sale-v3-behavior.test.mjs tests/Frontend/final-pos.test.mjs tests/Unit/Sales/SaleV3BrowserStateContractTest.php tests/Feature/Sales/SaleV3PageTest.php tests/Feature/Sales/SaleV3PriceOverrideTest.php tests/Feature/Sales/HoldBillWorkflowTest.php tests/Feature/Customers/CustomerModuleTest.php
git commit -m "feat: allow POS zone pricing before customer selection"
```

Do not merge `main`, push, open a PR, or deploy. Final report must say `READY FOR OWNER REVIEW` only when automated tests, Browser QA, diff review, and safety checks all have evidence; otherwise report `NOT READY` with the exact blocker.
