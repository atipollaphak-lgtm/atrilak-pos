# ATRILAK POS V3 UX Improvement Sprint — Design Spec

**Date:** 2026-08-03
**Scope:** POS V3 only, plus the authoritative Sale/Payment persistence points required to preserve V3 behavior.

## Goal

Make POS V3 faster for counter sales by defaulting new bills to pickup, allowing a bill-only unit-price override, simplifying cash payment confirmation, and exposing immediate post-sale document actions without changing the existing pricing engine or product price data.

## Constraints

- Do not change Average Cost, ATRILAK rounding, Tier Pricing, Profit Guard, delivery-fee formulas, commissions, product selling prices, category pricing, or product price history.
- Do not backfill historical `sale_items`.
- Keep POS V1 and POS V2 behavior operational.
- Use the existing Sale transaction, stock locking, commission, payment, idempotency, and document flows.
- `selling_price` on a persisted Sale Item remains the authoritative price actually sold and printed.

## Data Model

Add a forward-safe migration to `sale_items`:

- `original_price DECIMAL(15,2) NULL`: the system-calculated unit price before a bill-only override.
- `price_override_flag BOOLEAN NOT NULL DEFAULT FALSE`: whether the sold unit price was overridden for this item.

Existing rows are not rewritten. PostgreSQL supplies `NULL` for the new nullable column and `false` for the boolean default. The Eloquent model casts and fillable attributes will be updated.

Add the same two snapshot columns to `hold_bill_items` in a separate forward-safe migration. Existing held rows retain `original_price = NULL` and `price_override_flag = false`; no historical hold is backfilled.

For new Sale Items:

- No override: `original_price = NULL`, `price_override_flag = false`, `selling_price = system price`.
- Override: `original_price = system price`, `price_override_flag = true`, `selling_price = submitted sale price`.

The V3 create contract sends `items.*.selling_price` and `items.*.price_was_edited` only. `price_was_edited` is intent, not trusted metadata. The backend computes the system price and persists the two snapshot columns.

Hold Bill Items persist all three price values: `selling_price`, `original_price`, and `price_override_flag`. The Hold create contract also receives only `selling_price` and `price_was_edited`; `HoldBillService` calculates and stores the snapshots. Resume restores all three values and an internal `price_was_edited` state exactly. When a Sale is created from a Hold Bill, an untouched line copies the held metadata and does not compare the held price with the current pricing context. If the cashier edits the price after Resume, that line is processed as a fresh override decision while untouched lines retain their held metadata.

## Price Override Boundary

The browser sends only the desired `selling_price` and an explicit price intent/edited marker; it never sends or controls `original_price` or `price_override_flag`. The backend validates the desired price as positive with at most two decimal places and decides the persisted metadata. The existing pricing pipeline remains the source of the system price. For a non-edited line, the backend uses the freshly resolved system price. For an explicit override, the backend accepts the validated desired sale price, stores the freshly resolved system price in `original_price`, and sets `price_override_flag = true`. Metadata is never inferred solely from a numeric comparison and never writes to Product, Product Unit, Category, scheduled pricing, or price history.

Totals, delivery-fee calculation, Profit Guard, commission, and profit use the final `selling_price` persisted on the Sale Item. Invoice, delivery note, and tax invoice continue to render that persisted price.

## POS V3 State and UI

- The canonical draft state starts with `deliveryType: "pickup"`.
- Pickup and delivery controls visibly expose the active state. Switching to pickup clears the address/zone context and forces delivery fee to `0.00`; switching to delivery reveals the existing address, zone, and fee controls.
- Cart unit price is editable inline or through the existing edit affordance. Enter/Save validates a positive two-decimal price, recalculates line total and all dependent totals immediately, and marks the item as overridden in draft state. A per-line restore action returns to the system price.
- When fulfillment context changes (`pickup ↔ delivery`, address change, or zone change), non-overridden lines reprice to the latest system price. Overridden lines retain the user's sale price, update their draft reference system price for the latest context, and remain visibly marked as overridden. Restore uses the latest system price for the current context and clears the override marker. The backend repeats the same context-aware calculation at save time.
- The confirmation view shows concise item rows (quantity, product, line total) and a right-side summary containing net total, fulfillment, and payment method. Unit-price detail is omitted from this pre-payment summary.

## Payment Flow

- Opening payment confirmation initializes cash, cash amount equal to net total, received amount equal to net total, and change `0.00`.
- The primary confirmation action submits the default cash payment directly through the existing Sale request. It does not open a second data-entry popup.
- A secondary “change payment method” action opens the existing payment controller for PromptPay or Mixed Payment.
- Existing payment validation and persistence remain authoritative for non-cash payments.
- The success state is shown only after the Sale transaction has committed successfully, including payment creation, stock deduction, commission, and idempotency persistence. Validation or transaction failure keeps the summary and draft intact; it must not clear the cart or show success.

## Success and Documents

After a successful Sale response, the confirmation modal remains open and changes into a success panel showing the sale number. Delivery note and tax invoice actions are immediately available in the side panel, using the existing document routes and persisted Sale Item price snapshots. Finishing resets the next draft to pickup and clears draft-only state.

## Hold and Edit Behavior

- Hold Bill creation stores the current bill's actual unit prices.
- Resume restores delivery type, address context, quantities, and actual held prices without repricing those lines in the browser.
- Edit Sale uses an explicit per-line `price_action` contract: `preserve` for an existing line the user did not edit, `override` when the user entered a new price, and `system` when the user chose “restore normal price.” `preserve` copies the existing `selling_price`, `original_price`, and `price_override_flag` without comparing them to current pricing. `override` recalculates the current system reference price and stores new override metadata. `system` stores the current system price with `original_price = NULL` and `price_override_flag = false`. Existing Sale transaction, stock, commission, and revision safeguards remain unchanged.

## Validation and Compatibility

- POS V3 always sends a non-null `delivery_type`; backend V3 validation requires `pickup` or `delivery`.
- Delivery still requires a customer address attached to an active zone. Pickup bypasses address and delivery-zone requirements and forces fee zero.
- Existing V1/V2 request contracts are preserved unless a shared persistence change is required for the new nullable Sale Item columns.

## Verification

Add focused frontend and feature coverage for pickup default/toggle, price override metadata, total/profit/document rendering, hold/resume, edit preservation, default cash, one-click sale, and success print actions. Run scoped PHP/JS tests, relevant Laravel regression tests, syntax/style checks, `git diff --check`, and browser verification against a non-production local/test environment. Do not run tests or diagnostics against production data.
