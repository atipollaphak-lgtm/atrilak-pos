# ATRILAK POS V3 Improvement Sprint Design

**Status:** Approved by Owner on 2026-08-18

**Baseline:** `b28a6726f2d1b9900b864747eee31546f529f6c4`

**Feature branch:** `codex/pos-v3-category-frequent-products`

## Goal

Deliver the approved POS V3 improvement sprint without changing production, deleting or cleaning up legacy customer data, importing new customers, or changing protected sales, stock, pricing, delivery-fee, profit, cost, or commission rules.

## Evidence from the existing system

- Category editing already updates the existing `Category` record through `CategoryController`; the current management page and AJAX module can be extended without replacing Product relationships.
- Category management currently orders by `latest()` and POS V3 currently loads categories by name, so one persisted `sort_order` field and one shared query convention are required.
- POS V3 renders all active Products as reusable cards, and its quantity, stock, Product Unit, pricing-zone, price-override, cart, pickup, and delivery behavior lives in `sale-v3.js`.
- Customer delivery Zone is stored on `customer_delivery_addresses.delivery_zone_id`; `customers.address` is a legacy mirror maintained by `CustomerService`.
- Customer management and POS V3 creation both use `StoreCustomerRequest` and `CustomerService`.
- Customer Import Preview normalizes rows before confirmation, but it currently has no Zone field or Zone resolution and confirmation writes new addresses with a null Zone.
- POS V3 document buttons open the existing invoice V2 route. The invoice view currently calls `window.print()` on load without a print-mode flag or an `afterprint` close handler.

## Design

### Category edit and ordering

Add a nullable-safe, non-destructive `sort_order` integer column to `categories` with default `0`. The migration assigns deterministic initial values by existing Category ID in ascending order. All Category lists used by management and POS V3 use `orderBy('sort_order')->orderBy('id')`.

Category creation computes `max(sort_order) + 1` inside the existing write flow, so new Categories append automatically. Category update keeps the existing record ID and all Product foreign keys unchanged. The existing Product-count Delete Guard remains unchanged.

Add a `PUT /categories/order` endpoint. It accepts the complete ordered Category ID list, rejects duplicates, unknown IDs, or incomplete lists, and updates every Category in one transaction. The management table gets a drag handle and an explicit `บันทึกลำดับ` button; dragging alone does not persist anything.

### Frequent Products

Create `pos_v3_frequent_products` as a mapping table with a unique `product_id` and `sort_order`. The mapping is not a Category and never duplicates a Product. Pinning uses an idempotent insert, unpinning deletes only the mapping, and reordering updates mapping rows in a transaction.

Add a Manager/Owner management page with client-side Product search, active Product pinning, pinned rows, Category labels, inactive status, remove controls, drag handles, and an explicit order-save button. Inactive Products are excluded from the POS V3 virtual collection; their mapping is retained so no unrelated cleanup is performed.

POS V3 receives active Categories ordered by the Category `sort_order` query and active Products with their frequent mapping order. A virtual `ขายบ่อย` tab is rendered before Category tabs and selected by default. It filters and reorders the existing Product Cards; clicking a frequent card enters exactly the same Quantity Modal and sale state flow as any Category card.

### Product image sizing

Only POS V3 CSS changes. The image area increases to approximately 55–60% of the card's upper area, keeps `object-fit: contain`, centers the image, preserves padding, and scales the placeholder equally. Desktop is the primary target; the 390px rule keeps the grid inside the viewport without horizontal overflow.

### Print auto-close

POS V3 adds `auto_print=1` to document URLs opened by the final payment modal. The invoice V2 page invokes `window.print()` only in that mode and registers `afterprint` to close the popup when it has an opener. Direct document URLs have no auto-close behavior and keep the existing A4/A5 and document markup.

### Mandatory Zone for new Customers

`StoreCustomerRequest` requires an active `delivery_zone_id` and returns the Thai message `กรุณาเลือกโซนลูกค้า` when it is missing or invalid. `CustomerService::create` repeats the active-Zone check so non-HTTP callers cannot create a new Customer without a valid Zone. The primary delivery address always receives the selected Zone; if the new Customer has no address text, the address value is stored as an empty string to satisfy the existing non-null address schema without inventing customer data.

`UpdateCustomerRequest` remains nullable for legacy Customer safety. Existing Customers without a Zone remain viewable and editable without an automatic bulk repair or forced historical update.

### Customer Import Zone policy

Add a `delivery_zone` field to both supported import header definitions and the generated ATRILAK template. Preview accepts an active Zone ID or an exact active Zone name, stores the resolved ID in each normalized row, and marks missing/unresolvable values `review_required` with `ไม่พบโซนลูกค้า`. Confirmation rechecks the active Zone inside the transaction before creating a new Customer and primary address. A nullable `delivery_zone_id` is added to `customer_import_rows` for audit history. No import file is executed in this sprint.

## Protected invariants

- Existing Category IDs and Product `category_id` values remain unchanged.
- Existing Product records, stock, cost, selling prices, Product Units, price tiers, and pricing-zone behavior remain unchanged.
- Existing Customers, Customer Addresses, External References, Import Batches, Import Rows, Sales, Payments, and Stock Movements are not deleted, reset, cleaned up, bulk-updated, or imported.
- POS V1 and POS V2 routes and behavior remain operational.
- Production source, Production database, Production `.env`, PostgreSQL configuration, `main`, and `origin/main` are not changed, migrated, merged, pushed, or deployed.

## Verification strategy

- Use focused Laravel Feature/Unit tests for Category update/order, Frequent Product mapping and ordering, Customer Zone validation, Import Preview/confirm safeguards, and document print-mode contracts.
- Use existing Frontend behavioral tests plus new contract tests for POS V3 frequent filtering/order, customer modal validation, and print URL/auto-close behavior.
- Run migration rehearsal only against the configured test database, including fresh and upgrade-path checks where the project test harness supports them.
- Run PHP syntax, JavaScript syntax, Pint on changed PHP files, Vite build if dependencies are available, `git diff --check`, route/view checks, scoped regression suites, and Browser QA only after the test environment is confirmed safe.

## Known limitation

Browser JavaScript cannot reliably distinguish Print from Cancel after the dialog opens. The accepted behavior is to close the print popup after the print dialog closes in `auto_print=1` mode.
