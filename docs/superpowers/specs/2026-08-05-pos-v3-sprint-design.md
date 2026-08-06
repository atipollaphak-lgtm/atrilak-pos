# POS V3 Complete Sprint Design

**Date:** 2026-08-05
**Status:** Approved by Owner for implementation
**Scope:** POS V3 sales flow, customer/unit supporting screens, invoice document presentation, and automated contracts/tests

## Goal

Deliver the complete POS sprint request in one implementation sprint while preserving existing sale, stock, pricing, payment, reporting, and Daily Closing behavior. The user-facing delivery confirmation copy becomes `ยืนยันการจัดส่ง`; this is not a new payment status or payment method.

## Guardrails

- Do not change Average Cost, ATRILAK rounding, tier pricing, profit, Profit Guard, delivery fee, technician commission, unit conversion, sale numbering, or stock movement semantics.
- Keep payment payloads, `payment_method`, payment resolver behavior, reports, and Daily Closing unchanged.
- Keep POS V1 and POS V2 routes and payload contracts working. The approved implementation is V3-first for the new UX; shared invoice presentation changes must remain compatible with existing formats.
- Keep authoritative validation in the existing request/service/transaction path. The browser may preview a zone and price, but the server remains authoritative for the selected delivery address and zone.
- Do not add a schema migration or rewrite historical data unless implementation evidence proves it is required. The planned changes use existing fields and relationships.
- No production database, migration, reconciliation, or real customer data is used for verification.

## Approved design decisions

### 1. Unit code generation

Creating a unit no longer requires a manually entered code. A service creates the row with a short unique temporary value inside a transaction, then assigns a deterministic display code based on the new unit ID (`UNT-000001` style). Existing codes remain unchanged. Edit forms show the code as read-only and updates only change editable unit attributes.

### 2. POS customer creation and selection

The customer search modal gets an obvious “เพิ่มลูกค้าใหม่” action and an empty-result action. POS creates a customer through a JSON endpoint that reuses `CustomerService` and the existing customer validation rules. On success, the newly created customer is selected and the search modal closes. The response includes delivery addresses and zones so the POS does not need a second round trip.

Customer rows show the number of delivery addresses. One address may be selected automatically; when there are multiple addresses, the POS shows a count and requires an explicit detail/selection action. It never silently chooses the first address.

### 3. Customer/tax-invoice data

The customer field currently labelled `ชื่อ/ข้อมูลลูกค้าใบกำกับภาษี` is relabelled to `เลขประจำตัวผู้เสียภาษี`. Existing customer name, tax ID, branch type/number, and address fields are grouped and described as the invoice data used by the document snapshot. Tax-invoice printing is enabled only when the selected customer has the required invoice data; the UI lists the missing fields when disabled.

The existing customer address field is populated from the primary address when a customer is created/updated through the established service, without changing historical records. No new payment or customer status is introduced.

### 4. Fulfillment and address flow

Pickup is the default and is displayed on the left; delivery is on the right. The selected state is visually explicit and remains usable without animation. Reduced-motion preferences receive the same information without animation.

After customer selection the POS displays name, phone, selected address, customer/delivery zone, and fulfillment mode. The avatar icon is removed. Pickup is labelled with `(รับเอง)` and delivery is prominently labelled `จัดส่ง`.

Delivery requires a customer, a selected delivery address, and a usable delivery zone before confirmation. A customer with multiple addresses must choose one. The selected address determines the effective backend delivery zone. If a pre-customer delivery price zone was selected and the customer address resolves to a different zone, the POS explains the possible price change and asks for confirmation before repricing.

### 5. Delivery price-zone preview

When delivery is selected before a customer exists, a configured active zone selector appears near the keyboard shortcuts. Selecting a zone reprices all existing lines using the existing frontend/backend pricing contract; later-added products use the selected zone. Changing a zone prompts before a price-changing repricing. The preview zone is not sent as authoritative sale data; the backend continues to resolve the final zone from the selected address.

### 6. Payment summary, confirmation, and documents

The confirmation summary product table is compact and has four columns: `#`, product name, quantity with unit (for example `10 เส้น`), and line total. Unit price is not shown in this summary.

After a successful sale, only two direct document actions are shown: `พิมพ์ใบส่งของ` and `พิมพ์ใบกำกับภาษี`. The tax action is disabled with a missing-data explanation when invoice data is incomplete. Finish is a full-width primary action. Sale submission and document actions have client-side guards against accidental double activation; the server’s existing idempotency/payment protections remain unchanged.

For pickup, the existing successful-payment copy remains. For delivery, the displayed confirmation/status copy is `ยืนยันการจัดส่ง` only. The internal payment request, `payment_method`, payment reports, and Daily Closing are unchanged.

### 7. Quantity, date, and note presentation

The quantity modal is enlarged and includes product name, unit, current quantity, unit price, line total, large +/- controls, touch-friendly input, and keyboard support. Stock and existing quantity validation remain authoritative.

V3 date display/input uses `DD/MM/YYYY` (for example `05/08/2026`) while hidden/request values remain valid ISO `Y-m-d` values for the backend. The shared invoice/delivery-note date remains formatted as `d/m/Y`. Existing V1/V2 request contracts are preserved.

Saved notes show `มีหมายเหตุแล้ว (ข้อความที่กรอกไว้)` with safe truncation and a title/full-text affordance. Editing and deletion update the display immediately.

### 8. A5 documents

The existing A5 document format is retained, with larger readable typography, spacing, and table/summary sizing. Product, address, total, and customer information remain visually prominent. CSS changes include print-safe sizing and overflow-resistant layout; no business data or numbering changes are made. Manual A5 preview remains a final environment check when Laragon/browser access is available.

## Data and interaction flow

```text
Customer search/create
        -> customer + address list
        -> one address auto-selects / many require explicit selection
        -> address zone becomes effective delivery zone

Pre-customer delivery zone -> preview repricing only
Customer/address selection -> compare zones -> confirm before price change
                                      |
                                      v
                         existing SaleService transaction
                         existing pricing/payment/reporting paths
```

The browser state keeps the selected customer, address object, preview zone, effective zone, fulfillment type, ISO delivery date, note, and cart. The store request still carries the established sale/payment fields. `StoreSaleV3Request`, `SaleService`, `ZonePricingService`, `SalePaymentResolver`, and reporting/Daily Closing code remain the authority for their current responsibilities.

## Expected implementation surface

- Unit controller/service/model view and unit tests.
- Customer POS endpoint, request/response mapping, customer service primary-address behavior, customer search/create partials, and customer feature tests.
- POS V3 Blade partials, `final-pos.js`, `sale-v3.js`, `pos-date.js`, `zone-pricing.js` integration, and `sale-v3.css`.
- Existing customer form tax label/help text.
- Final payment/document UX contract tests and frontend tests.
- Shared A5 invoice CSS and document presentation tests where existing contracts cover it.

## Acceptance criteria

1. Unit create succeeds without a code input, produces a unique generated code, and edit preserves that code.
2. Empty POS customer search can create a customer; the new customer is selected automatically.
3. Tax ID label is correct; incomplete invoice data disables tax printing with an explanation.
4. Address count/details are shown; multiple addresses do not auto-select the first.
5. Selected customer/address/zone/fulfillment information is visible without an avatar.
6. Pickup-left/delivery-right controls default to pickup and switching updates the existing state/pricing flow.
7. Confirmation summary has exactly the requested four compact columns and includes quantity units.
8. Success screen has only the two direct print buttons, a guarded full-width Finish action, and no aggregate print action.
9. Pre-customer delivery zone selection and guarded repricing work using existing pricing math.
10. Delivery cannot be confirmed without customer/address/zone; selected address zone is reflected.
11. Quantity modal has the requested touch/keyboard controls and price/total context.
12. V3 visible dates are `DD/MM/YYYY`; backend/printed values remain valid.
13. Notes show saved text with truncation/full-text affordance and immediate edit/delete state.
14. Delivery confirmation text is exactly `ยืนยันการจัดส่ง`; payment internals remain unchanged.
15. A5 output is more readable and keeps content within the existing page format.

## Verification strategy

- Run focused PHP feature/unit tests for units, customers, POS V3 page/store/workflow, notes, and invoice contracts.
- Run frontend Node tests for date formatting and final POS state/document contracts.
- Run PHP syntax checks, JavaScript syntax checks, Laravel Pint on changed PHP, and `git diff --check`.
- Run the broader Laravel regression suite only against the configured test environment; never against production.
- If Laragon/Apache/PostgreSQL is not running, report manual browser/print verification as pending rather than claiming it passed.

## Known risks and mitigations

- Existing customers may lack invoice address data; the new tax-print gate intentionally keeps tax printing disabled and explains the missing data.
- Existing legacy unit codes may not follow the generated format; they are preserved and are not rewritten.
- Frontend preview and backend zone resolution can diverge if the address changes between preview and submit; the request/service validation remains authoritative and the UI forces a clear address selection.
- Print preview cannot be fully verified without the local web stack/browser; automated markup/CSS contracts cover the deterministic portion.
