# Delivery Note Minimal A4 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task with verification checkpoints.

**Goal:** Rework the POS V2 delivery note presentation into the approved minimal A4 portrait layout without changing business logic, stored data, or non-delivery commercial documents.

**Architecture:** Keep `SaleController`, `CommercialDocumentService`, sale calculations, snapshots, routes, and document contracts unchanged. Use the existing `sales.invoice_v2` Blade composition and its partials, adding a delivery-note-specific presentation class/section and moving print CSS into a dedicated public stylesheet.

**Tech Stack:** Laravel 13 Blade, PHP 8.3, CSS print media, existing Laravel Feature tests, Laravel Pint, `git diff --check`.

## Global Constraints

- Scope is limited to the delivery-note UI and print presentation.
- POS V1, tax invoice, quotation, sale numbering, pricing, delivery fee, profit, payment, and stock behavior must remain unchanged.
- Reuse existing transaction snapshots and stored totals; do not add migrations or production-data changes.
- Preserve the existing invoice-v2 route and query contract.
- Keep CSS in `public/css`; do not add large CSS blocks to Blade.
- Do not commit, push, merge, or alter unrelated working-tree files.

---

### Task 1: Establish rendering regression coverage

**Files:**
- Modify: `tests/Feature/Sales/SalePaymentDisplayTest.php` or the smallest existing invoice-v2 feature test that already renders the view.
- Test: the delivery-note view output.

**Interfaces:**
- Consumes the existing `Sale`, `CommercialDocumentService`, and Blade view test fixtures.
- Produces assertions that the delivery-note keeps required document data and the new presentation hooks while tax invoice payment behavior remains covered.

- [ ] **Step 1: Write the failing test**

  Add focused assertions for delivery-note output: the document contains a delivery-note marker/class, a five-column item-table structure, the notes/receiver section, and the existing sale number/total. Assert the delivery-note does not render tax-only payment details. Keep existing tax-invoice assertions intact.

- [ ] **Step 2: Run the focused test to verify the signal**

  Run `php artisan test tests/Feature/Sales/SalePaymentDisplayTest.php`.

  Expected: the new presentation assertions fail against the current six-column/old-layout markup while existing payment assertions identify any unrelated regression.

- [ ] **Step 3: Keep the test limited to observable output**

  Do not assert CSS implementation details, database writes, or controller internals. Assert only stable classes/labels and preserved business values.

### Task 2: Add the dedicated print stylesheet

**Files:**
- Create: `public/css/sales-invoice-v2.css`
- Modify: `resources/views/sales/invoice_v2.blade.php`

**Interfaces:**
- The Blade view loads the stylesheet for invoice-v2 documents.
- The stylesheet owns screen/print layout for the approved delivery-note presentation and keeps document-specific selectors scoped under `.delivery-note`.

- [ ] **Step 1: Define A4 page and document geometry**

  Add `@page { size: A4 portrait; margin: 0; }`, a `210mm` wide document, `8mm 9mm 6mm` padding, Sarabun/Noto Sans Thai/Arial fallback fonts, navy/gray variables, and print color adjustment.

- [ ] **Step 2: Implement the six presentation regions**

  Style header, customer block, five-column item table, QR/summary block, notes/receiver block, and footer. Keep table rows compact, allow long product names to wrap safely, and use `page-break-inside: avoid` for summary/signature blocks.

- [ ] **Step 3: Hide controls during print and preserve browser preview**

  Keep the print button visible on screen, hide it in `@media print`, and do not add JavaScript or route changes.

### Task 3: Adapt delivery-note Blade markup

**Files:**
- Modify: `resources/views/sales/invoice_v2.blade.php`
- Modify: `resources/views/sales/invoice_v2/header.blade.php`
- Modify: `resources/views/sales/invoice_v2/header/company.blade.php`
- Modify: `resources/views/sales/invoice_v2/header/document.blade.php`
- Modify: `resources/views/sales/invoice_v2/customer.blade.php`
- Modify: `resources/views/sales/invoice_v2/items.blade.php`
- Modify: `resources/views/sales/invoice_v2/summary.blade.php`

**Interfaces:**
- Existing variables remain available: `$sale`, `$setting`, `$document`, `$resolvedDocumentTitle`, `$resolvedDocumentNo`, `$resolvedDocumentDate`, `$resolvedCurrentPage`, `$resolvedTotalPages`, `$formatNumber`, `$subTotal`, `$deliveryFee`, `$discount`, `$grandTotal`.
- All financial values continue to come from the current view/service data; only labels, grouping, and CSS hooks change.

- [ ] **Step 1: Add delivery-note-specific root and regions**

  Render the approved document regions under a delivery-note class when `$document['type'] === 'delivery-receipt'` or the established delivery-note type returned by `CommercialDocumentService`; retain the existing shared view path for other document types.

- [ ] **Step 2: Reduce delivery-note table to five columns**

  Combine the current unit information into the quantity display so the delivery-note table is `ลำดับ`, `รายการสินค้า`, `จำนวน`, `หน่วยละ`, `ราคารวม`, while retaining the existing product/unit snapshot resolution and item totals.

- [ ] **Step 3: Add notes and receiver signature area**

  Render `$sale->notes` when present and provide ruled note space plus receiver name/date lines. Do not add a sender field or alter payment/fulfillment state.

- [ ] **Step 4: Preserve document-specific tax and payment behavior**

  Keep tax information conditional on `$document['show_tax_information']`. Keep payment detail rows conditional as currently implemented and show the QR image only when configured.

### Task 4: Verify layout and regressions

**Files:**
- Review only: all files changed above.

**Interfaces:**
- No runtime contract changes beyond the delivery-note markup and stylesheet.

- [ ] **Step 1: Run focused Laravel tests**

  Run `php artisan test tests/Feature/Sales/SalePaymentDisplayTest.php tests/Feature/Sales/SaleInvoiceV2FulfillmentTest.php tests/Feature/Documents/TransactionDocumentSnapshotTest.php`.

- [ ] **Step 2: Run syntax/style checks**

  Run `php artisan view:cache`, `vendor/bin/pint --test`, and `git diff --check`.

- [ ] **Step 3: Review the final diff for scope**

  Confirm only the approved Blade/CSS/test files changed, no business-rule code or sensitive data was added, and unrelated pre-existing files remain untouched.

- [ ] **Step 4: Report manual print-preview gap if Laragon is unavailable**

  If Laragon/Apache is not running, report that browser Print Preview at 100% and QR scan verification remain manual follow-up steps; do not claim them as passed.
