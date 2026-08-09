# POS V3 Pricing Zone Before Customer Design

**Date:** 2026-08-09
**Scope:** POS V3 only; POS V1 and POS V2 behavior remains unchanged.

## Goal

Allow a cashier to select a Pricing Zone and see Zone Pricing immediately, including while the POS remains in its default Pickup state, before selecting or creating a customer. Preserve the selected pricing context, cart, quantities, and price state while customer and address information is added later.

## Baseline evidence

- `resources/views/sales-v3/partials/customer-bar.blade.php` renders `#v3-price-zone-select` as disabled and uses an address-dependent placeholder.
- `public/js/modules/sale-v3.js` disables the selector in `refreshPricingContext()`, has no user-selection handler, clears zone state in `loadAddresses()`, and currently derives pricing from the delivery address.
- `public/js/modules/final-pos.js` clears the existing zone when clearing a customer and relies on the same zone state for Hold/Resume.
- `app/Http/Requests/StoreCustomerRequest.php` and the existing `customer_delivery_addresses` schema already allow nullable delivery zone/address values.
- `app/Services/CustomerService.php` always creates a primary address, even when no delivery information was supplied.
- `app/Services/Sales/ZonePricingService.php` is the authoritative price/markup/rounding engine. Its existing Pickup behavior remains unchanged; POS V3 will call it with an explicit Pricing Zone for the newly approved quote context while keeping fulfillment fee rules separate.
- `.env.testing` points to `atrilak_pos_final_test_20260729`; all automated database checks must use that test database and never Production.

## Approved behavior

### Separate contexts

The POS draft will hold two explicit zone concepts:

- `state.pricingZone`: the cashier-selected zone used by the pricing engine. It is enabled regardless of customer selection or fulfillment type.
- `state.deliveryZone`: the zone attached to the selected delivery address. It is used only for delivery-specific fee, minimum-profit, and delivery validation behavior.

`state.deliveryType` remains `pickup` by default. Selecting a Pricing Zone while Pickup:

- reprices product cards, quantity preview, cart lines, subtotal, and total using the existing Zone Pricing engine;
- does not add a delivery fee;
- does not require a customer, address, or delivery date;
- keeps the sale fulfillment type as Pickup.

When the cashier changes to Delivery, the existing Pricing Zone remains the starting pricing context. The selected address supplies `deliveryZone` and delivery validation remains address-based.

### Zone selection and address mismatch

- Active zones are selectable from the initial POS state.
- Inactive zones remain disabled/hidden in the selector and are rejected by server validation.
- Selecting a zone explicitly is an intentional cashier action and reprices immediately.
- Selecting a customer does not clear `pricingZone`, cart, quantities, price overrides, or fulfillment state.
- Selecting an address with no zone does not clear `pricingZone`; Delivery confirmation still requires a valid address zone according to the existing rule.
- Selecting an address whose active zone differs from `pricingZone` keeps the address as the selected delivery address but opens a Bootstrap confirmation modal.
  - Confirm: set `pricingZone` to the address zone and reprice.
  - Cancel: retain `pricingZone` and all existing item prices; do not use browser `confirm()`.
- Selecting an address whose zone equals `pricingZone` does not open a confirmation.

### Backend pricing contract

POS V3 sends an optional `pricing_zone_id` in addition to the existing fulfillment/customer/address fields.

For a sale:

- `pricing_zone_id` is validated as an active Delivery Zone.
- The price engine receives the explicit Pricing Zone; if none is supplied for Delivery, the address zone remains the fallback.
- Zone pricing is calculated independently of Pickup/Delivery when an explicit Pricing Zone exists.
- Delivery Fee, minimum profit, delivery zone snapshots, address requirement, and delivery date continue to use the actual Delivery Zone and existing `delivery_type` behavior.
- A Pickup sale therefore can contain Zone-priced item snapshots and zero delivery fee while retaining `delivery_type = pickup` and no delivery zone.

The idempotency payload hash includes `pricing_zone_id` so retry keys cannot silently replay a sale created with a different pricing context.

### Hold/Resume persistence

Add nullable `pricing_zone_id` and pricing-zone snapshot fields to `sales` and `hold_bills` through a forward-safe migration. Existing delivery-zone fields retain their delivery-only meaning.

Hold creation uses the Pricing Zone for item price snapshots, regardless of Pickup/Delivery, while its delivery date and fee behavior remains unchanged. Resume restores `pricingZone` before restoring the held cart so subsequent additions continue using the same quote context.

### Customer creation

`CustomerService::create()` will create a primary delivery address only when delivery data was actually supplied. A customer with only name/phone will be persisted without an address. A supplied address may still have a nullable zone. Existing customer creation with address data remains unchanged.

## UI and data flow

1. POS loads active zones into an enabled selector with `เลือกโซนราคา` placeholder.
2. A selector change parses the existing zone payload, validates active status, updates `state.pricingZone`, and calls the existing repricing path.
3. Customer selection loads addresses without resetting pricing/cart state.
4. Address selection updates `state.address`/`state.deliveryZone`, then invokes the mismatch modal only when required.
5. Customer creation appends the returned customer option and calls the existing auto-select path; that path preserves the current draft context.
6. Sale submit sends `pricing_zone_id` and the current cart prices; backend recalculates system prices from the same Zone Pricing engine and keeps fulfillment calculations separate.

## Error handling and safety

- No inactive zone may enter `pricingZone` or `deliveryZone`.
- A failed address load preserves the selected Pricing Zone while clearing only address/delivery context.
- A stale address response must not overwrite a newer customer or pricing state.
- Modal cancellation must leave both cart prices and Pricing Zone unchanged.
- No migration backfill or historical-data rewrite is required.
- No changes are made to Average Cost, Product Cost, Price Tiers, rounding formulas, Profit Guard formulas, Delivery Fee formulas, Commission, Stock, Payment, Hold Bill totals, invoice calculations, or sale lifecycle semantics beyond the explicitly approved Pricing Zone context.

## Verification design

Automated coverage will extend existing POS V3 tests rather than introduce a second test architecture:

- Blade/contract tests verify an enabled selector, placeholder, active-zone options, and mismatch modal.
- Frontend behavior tests verify initial selection, Pickup repricing with zero fee, inactive-zone protection, customer preservation, address same/different-zone behavior, modal Confirm/Cancel, and delivery validation.
- Customer feature tests verify creation with no address/zone and creation with address but no zone.
- Sale feature tests verify Pickup Zone Pricing parity, zero delivery fee, separate delivery-zone behavior, and inactive pricing-zone rejection.
- Hold/Resume tests verify Pricing Zone price snapshots and state restoration.
- Existing Pickup, Delivery, search, quantity, payment, success/print, and hold regression suites remain green.

Manual Browser QA will run only against the configured test environment after PostgreSQL/Apache or an equivalent local server is available. No Production database or real customer data will be used.
