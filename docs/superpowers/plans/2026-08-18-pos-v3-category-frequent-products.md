# POS V3 Improvement Sprint Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the approved POS V3 sprint while preserving all existing customer, sales, stock, pricing, payment, and production safety invariants.

**Architecture:** Extend the existing Laravel controllers, models, Blade partials, and plain JavaScript modules. Persist Category order in `categories.sort_order`; persist frequent Products in a unique Product-to-mapping table; keep Customer Zone on the primary delivery address; pass print mode through a query flag. Avoid new frontend frameworks and avoid changing the SaleService or pricing/stock pipeline.

**Tech Stack:** Laravel 13, PHP 8.3, PostgreSQL in the test environment, Blade, plain JavaScript modules, PHPUnit, Node `--test`, Vite, Laravel Pint.

**Spec:** `docs/superpowers/specs/2026-08-18-pos-v3-improvement-sprint-design.md`

## Global Constraints

- Development/Test only; `Production Migration: NOT RUN`.
- Do not touch `C:\laragon\www\atrilak-pos-production` or its database.
- Do not stage, edit, delete, or cleanup the pre-existing untracked files.
- Do not merge `main`, push `main`, deploy Production, change Production `.env`, or import customers.
- Do not delete, reset, cleanup, or bulk-update existing Customers, Addresses, External References, Import Batches, Import Rows, Sales, Payments, or Stock Movements.
- Preserve POS V1 and POS V2 and all protected Average Cost, Pricing, Tier Pricing, Profit Guard, Delivery Fee, Commission, Product Unit, Sale numbering, and Stock Movement behavior.
- Use transactions for multi-row order/mapping updates and import confirmation.
- New Customers require an active Zone; legacy Customer edits remain compatible when their existing Zone is null.

---

### Task 1: Baseline proof and scoped test fixtures

**Files:**
- Create: `tests/Feature/Categories/CategoryOrderingTest.php`
- Create: `tests/Feature/FrequentProducts/FrequentProductManagementTest.php`
- Create: `tests/Feature/Customers/CustomerZoneMandatoryTest.php`
- Modify: `tests/Feature/Customers/CustomerModuleTest.php`
- Modify: `tests/Unit/Customers/CustomerImportValidationServiceTest.php`
- Modify: `tests/Feature/Customers/CustomerImportConfirmTest.php`
- Modify: `tests/Frontend/final-pos.test.mjs`
- Modify: `tests/Frontend/sale-v3-behavior.test.mjs`

**Interfaces:**
- Tests consume the existing `CategoryController`, `CustomerController`, `CustomerImportValidationService`, `CustomerImportService`, `final-pos.js`, and `sale-v3.js` contracts.
- Later tasks must make these tests pass without weakening the assertions.

- [ ] **Step 1: Run the baseline status and focused existing tests without editing project files**

Run:

```powershell
git status --short --branch
php artisan test tests/Feature/Categories/CategoryManagementTest.php tests/Feature/Sales/SaleV3PageTest.php tests/Feature/Customers/CustomerModuleTest.php
node --test tests/Frontend/final-pos.test.mjs tests/Frontend/sale-v3-behavior.test.mjs
```

Record pass/fail counts and any baseline failures separately from feature failures.

- [ ] **Step 2: Add the smallest failing Category-order assertions**

Assert that two old Categories render in deterministic `sort_order, id` order, that `PUT /categories/order` persists the complete list, and that a new Category receives a greater order than existing rows.

- [ ] **Step 3: Add the smallest failing Frequent Product assertions**

Assert pinning creates one mapping, repeating pinning does not duplicate it, unpinning leaves the Product and its Category intact, and reorder persists mapping order.

- [ ] **Step 4: Add the smallest failing Customer Zone assertions**

Assert management and POS creation without a Zone return 422/session validation with `กรุณาเลือกโซนลูกค้า`, invalid/inactive Zone is rejected, and a valid Zone creates a primary address linked to that Zone.

- [ ] **Step 5: Add the smallest failing Import and print contract assertions**

Assert an import row without a resolvable Zone is `review_required`, a row with an active Zone is ready/importable, and POS print URLs include `auto_print=1` while the invoice template only installs the close handler in that mode.

- [ ] **Step 6: Run every new focused test and confirm each fails for the missing behavior rather than setup noise**

Expected result: meaningful RED failures identifying missing columns/routes/validation/markers. If a test errors because its isolated fixture is incomplete, fix the fixture and rerun until the feature assertion is the failure.

### Task 2: Category persistence, edit compatibility, and management ordering

**Files:**
- Create: `database/migrations/2026_08_18_000001_add_sort_order_to_categories_table.php`
- Modify: `app/Models/Category.php`
- Modify: `app/Http/Controllers/CategoryController.php`
- Modify: `resources/views/categories/index.blade.php`
- Modify: `public/js/modules/category-management.js`
- Modify: `public/css/categories.css`
- Test: `tests/Feature/Categories/CategoryManagementTest.php`
- Test: `tests/Feature/Categories/CategoryOrderingTest.php`

**Interfaces:**
- `CategoryController::updateOrder(Request $request)` accepts `{category_ids: number[]}` containing every existing Category ID exactly once and returns JSON `{message: string}`.
- Category lists expose `sort_order` and use `sort_order ASC, id ASC`.

- [ ] **Step 1: Write and run the migration red test**

Use the Category ordering test to assert the `sort_order` column exists after the test schema is prepared and that old rows receive `id`-based initial order. Run the focused test and confirm it fails before the migration/model change.

- [ ] **Step 2: Add the forward-safe Category migration**

Add an integer `sort_order` with default `0` only when absent. After the column exists, update existing rows in ascending ID order using a deterministic increment. In `down`, drop only the new column when it exists; never delete Category rows or Product relations.

- [ ] **Step 3: Add model fillable/cast support and deterministic queries**

Add `sort_order` to `Category::$fillable` and cast it to integer. Change `CategoryController::index()` to `withCount('products')->orderBy('sort_order')->orderBy('id')`.

- [ ] **Step 4: Make Category creation append in the existing write path**

Inside the create operation, assign `max('sort_order') + 1` when the request does not provide a value. Keep `sort_order` out of the user form so users cannot submit arbitrary order numbers.

- [ ] **Step 5: Add the complete-list order endpoint in a transaction**

Validate `category_ids` as a required array of distinct existing IDs. Compare the submitted sorted ID set with the current Category ID set; reject any incomplete or unknown list with 422. Update each row to its zero-based submitted position inside `DB::transaction()`.

- [ ] **Step 6: Add drag handles and explicit save behavior to the Blade/JS UI**

Render a `sort_order` column, a draggable row handle, `data-category-id`, and a `บันทึกลำดับ` button. In `category-management.js`, use native drag events to move rows in the table body, collect all row IDs in DOM order, submit JSON to `categories.order`, and reload only after a successful response. Do not send a request on drag.

- [ ] **Step 7: Run Category tests and diff checks**

Run:

```powershell
php artisan test tests/Feature/Categories/CategoryManagementTest.php tests/Feature/Categories/CategoryOrderingTest.php
php -l app/Models/Category.php
php -l app/Http/Controllers/CategoryController.php
node --check public/js/modules/category-management.js
git diff --check
```

Expected result: Category edit/update, Delete Guard, order persistence, append behavior, duplicate-order tie-break, and validation tests pass.

### Task 3: Frequent Product mapping and management page

**Files:**
- Create: `database/migrations/2026_08_18_000002_create_pos_v3_frequent_products_table.php`
- Create: `app/Models/FrequentProduct.php`
- Create: `app/Http/Controllers/FrequentProductController.php`
- Create: `resources/views/frequent-products/index.blade.php`
- Create: `public/js/modules/frequent-product-management.js`
- Create: `public/css/frequent-products.css`
- Modify: `app/Models/Product.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/FrequentProducts/FrequentProductManagementTest.php`

**Interfaces:**
- `FrequentProduct` belongs to `Product`; `Product::frequentProduct()` is a `hasOne` mapping.
- `POST /frequent-products/{product}` pins an active Product idempotently and appends its order.
- `DELETE /frequent-products/{frequentProduct}` removes only the mapping.
- `PUT /frequent-products/order` accepts every mapping ID exactly once and persists submitted order.

- [ ] **Step 1: Run the mapping RED tests**

Run the new management test before adding the migration and controller. Confirm missing route/table behavior is the failure.

- [ ] **Step 2: Add the mapping migration and model**

Create `pos_v3_frequent_products` with `id`, unique `product_id` foreign key, integer `sort_order` default `0`, timestamps, and an index on `sort_order`. `down` drops only this new table.

- [ ] **Step 3: Implement idempotent pin/unpin/reorder in a narrow controller**

Use `DB::transaction()` for pin and reorder. Pin only `Product::where('active', true)`, use `firstOrCreate` with a computed append order, and return the mapping. Reject inactive or missing Products. Reorder validates the complete mapping ID set and writes zero-based positions.

- [ ] **Step 4: Build the simple Manager/Owner page**

Render an active Product search list, pinned mapping rows sorted by `sort_order,id`, Product name, Category, inactive status, drag handles, add/remove controls, and `บันทึกลำดับ`. Use the same native drag/save pattern as Category management. Preserve inactive mappings in the management list so no cleanup is performed.

- [ ] **Step 5: Add route access and navigation links**

Register the management routes inside the existing Manager-level middleware group. Add a link from Category management and the POS V3 More menu only for roles already allowed to manage Products.

- [ ] **Step 6: Run mapping tests and syntax checks**

Run:

```powershell
php artisan test tests/Feature/FrequentProducts/FrequentProductManagementTest.php
php -l app/Models/FrequentProduct.php
php -l app/Http/Controllers/FrequentProductController.php
node --check public/js/modules/frequent-product-management.js
git diff --check
```

### Task 4: POS V3 shared Category order and virtual Frequent tab

**Files:**
- Modify: `app/Http/Controllers/SaleV3Controller.php`
- Modify: `resources/views/sales-v3/partials/product-navigation.blade.php`
- Modify: `resources/views/sales-v3/partials/product-grid.blade.php`
- Modify: `resources/views/sales-v3/index.blade.php`
- Modify: `public/js/modules/sale-v3.js`
- Modify: `public/css/sale-v3.css`
- Modify: `tests/Feature/Sales/SaleV3PageTest.php`
- Modify: `tests/Unit/Sales/SaleV3BrowserStateContractTest.php`
- Modify: `tests/Frontend/sale-v3-behavior.test.mjs`

**Interfaces:**
- `SaleV3Controller::index()` passes active Categories ordered by `sort_order,id` and active Products with `frequentProduct` mapping data.
- Product card data includes a nullable `frequent_sort_order` without changing Product identity or sale payload shape.
- `state.category === 'frequent'` is the virtual collection selector; all other values keep existing category filtering.

- [ ] **Step 1: Run the POS RED checks**

Add assertions that inactive Categories are absent, custom Category order is present, the first tab is `ขายบ่อย`, and frequent cards render in persisted mapping order. Run the focused tests and confirm current name-order/all-category behavior fails.

- [ ] **Step 2: Change the controller query sources**

Filter Categories with `where('active', true)->orderBy('sort_order')->orderBy('id')`. Eager-load `frequentProduct` for active Products and keep the existing Product Unit, barcode, price-tier, and Category eager loads.

- [ ] **Step 3: Render the virtual tab and frequent metadata**

Render `ขายบ่อย` before `ทุกหมวด` and Category buttons. Add `data-frequent-order` to each Product Card and include the Product's existing JSON payload unchanged except for the non-authoritative frequent order metadata.

- [ ] **Step 4: Extend `filterProducts()` without touching sale logic**

When `state.category === 'frequent'`, require a numeric `data-frequent-order`; otherwise use the existing category match. Sort visible Product Card nodes by frequent order then Product ID when in the virtual collection. Keep barcode search, stock-only filter, Product Unit resolution, price calculation, cart, and submission payload untouched.

- [ ] **Step 5: Make Frequent the initial state and preserve active button classes**

Initialize `state.category = 'frequent'`, mark the virtual tab active, and call `filterProducts()` after all existing event binding. Selecting a normal Category or `ทุกหมวด` must clear the virtual filter.

- [ ] **Step 6: Enlarge the Product Card image area**

Increase the base image height and card height in `sale-v3.css`, add centered padding and `object-fit: contain`, and add a max-390px rule that keeps grid columns within the viewport. Do not change image URL generation or storage.

- [ ] **Step 7: Run POS V3 tests and Frontend tests**

Run:

```powershell
php artisan test tests/Feature/Sales/SaleV3PageTest.php tests/Unit/Sales/SaleV3BrowserStateContractTest.php
node --test tests/Frontend/sale-v3-behavior.test.mjs
node --check public/js/modules/sale-v3.js
git diff --check
```

### Task 5: Print-mode URL and popup auto-close

**Files:**
- Modify: `public/js/modules/final-pos.js`
- Modify: `resources/views/sales/invoice_v2.blade.php`
- Modify: `tests/Frontend/final-pos.test.mjs`
- Modify: `tests/Unit/Sales/PosV3BrowserStateContractTest.php`
- Create: `tests/Feature/Sales/SalePrintModeContractTest.php`

**Interfaces:**
- `final-pos.js` opens `${base}?document_type=${type}&auto_print=1` for both delivery-note and tax-invoice buttons.
- `invoice_v2.blade.php` uses `request()->boolean('auto_print')` to conditionally render the print script and `afterprint` close handler.

- [ ] **Step 1: Run the print RED checks**

Assert the current Frontend test does not include `auto_print=1` and the view does not contain a conditional `afterprint` handler. Confirm the failures are directly caused by the missing print mode.

- [ ] **Step 2: Add `auto_print=1` to the existing popup URL**

Keep `window.open`, popup blocking feedback, duplicate-print protection, and document type values unchanged. Only append the mode query parameter.

- [ ] **Step 3: Make invoice auto-print and auto-close conditional**

Render the existing `พิมพ์อีกครั้ง` button and layout unchanged. Add a script only when `auto_print=1` that calls `window.print()` after the current short load delay and registers `window.addEventListener('afterprint', () => window.close())` only when `window.opener` exists.

- [ ] **Step 4: Run document and Frontend tests**

Run:

```powershell
php artisan test tests/Feature/Sales/SalePrintModeContractTest.php tests/Feature/Sales/SaleInvoiceV2FulfillmentTest.php
node --test tests/Frontend/final-pos.test.mjs
node --check public/js/modules/final-pos.js
git diff --check
```

### Task 6: Mandatory Zone for new Customers

**Files:**
- Modify: `app/Http/Requests/StoreCustomerRequest.php`
- Modify: `app/Services/CustomerService.php`
- Modify: `resources/views/customers/_form.blade.php`
- Modify: `resources/views/sales-v3/partials/customer-create-modal.blade.php`
- Modify: `public/js/modules/customer-form.js`
- Modify: `public/js/modules/final-pos.js`
- Create: `tests/Feature/Customers/CustomerZoneMandatoryTest.php`
- Modify: `tests/Feature/Customers/CustomerModuleTest.php`

**Interfaces:**
- Store requests require `delivery_zone_id` and use the exact missing-zone message `กรุณาเลือกโซนลูกค้า`.
- `CustomerService::create(array $data)` rejects missing/inactive Zone before creating a Customer and stores the valid Zone on the primary address.
- Update requests remain nullable for legacy Customers.

- [ ] **Step 1: Run Customer RED tests**

Run the new tests against current code and confirm missing-zone requests currently succeed, proving the test detects the behavior change.

- [ ] **Step 2: Add Thai FormRequest validation**

Change only `StoreCustomerRequest` `delivery_zone_id` to `required`, integer, and active `exists` rule. Add messages for `required`, `exists`, and invalid values with `กรุณาเลือกโซนลูกค้า`.

- [ ] **Step 3: Add service-level active Zone validation**

At the start of the create transaction, load the active `DeliveryZone` by the submitted ID and throw a validation error with the same field/message if missing. Pass the resolved ID to `savePrimaryAddress`. Keep the existing customer code allocator and transaction.

- [ ] **Step 4: Preserve empty-address new Customers safely**

When a new Customer has a valid Zone but no address text, create the primary address with `address => ''`, `delivery_zone_id => resolved id`, and existing receiver-phone behavior. Do not modify old Customers or backfill legacy addresses.

- [ ] **Step 5: Update management and POS forms**

Mark the Zone labels with `*`, add `required` to the select, retain a clear server-side invalid-feedback area, and in `final-pos.js` show `กรุณาเลือกโซนลูกค้า` before fetch when the POS modal select is empty. Map server `errors.delivery_zone_id` into the same visible message.

- [ ] **Step 6: Run Customer tests and syntax checks**

Run:

```powershell
php artisan test tests/Feature/Customers/CustomerZoneMandatoryTest.php tests/Feature/Customers/CustomerModuleTest.php
php -l app/Http/Requests/StoreCustomerRequest.php
php -l app/Services/CustomerService.php
node --check public/js/modules/customer-form.js
node --check public/js/modules/final-pos.js
git diff --check
```

### Task 7: Import Zone preview and confirmation safeguards

**Files:**
- Create: `database/migrations/2026_08_18_000003_add_delivery_zone_id_to_customer_import_rows_table.php`
- Modify: `config/customer_import.php`
- Modify: `app/Models/CustomerImportRow.php`
- Modify: `app/Services/Customers/CustomerImportValidationService.php`
- Modify: `app/Services/Customers/CustomerImportService.php`
- Modify: `app/Services/Customers/CustomerImportTemplateService.php`
- Modify: `resources/views/customers/import/preview.blade.php`
- Modify: `tests/Unit/Customers/CustomerImportValidationServiceTest.php`
- Modify: `tests/Feature/Customers/CustomerImportConfirmTest.php`
- Test: `tests/Feature/Customers/CustomerImportPreviewTest.php`

**Interfaces:**
- Normalized import rows contain `delivery_zone_id: int|null`.
- Missing or unresolved Zone yields `status = review_required` and `reasons` containing `ไม่พบโซนลูกค้า`.
- `CustomerImportService::importWithinTransaction()` creates the primary address only for a rechecked ready row with an active `delivery_zone_id` and writes that ID to `customer_import_rows`.

- [ ] **Step 1: Run import RED tests**

Add a workbook row with an empty Zone and one with an active Zone. Run the validation test before the config/service changes and confirm current normalization marks both rows ready or omits Zone handling.

- [ ] **Step 2: Add the import-row migration and model field**

Add nullable `delivery_zone_id` with `nullOnDelete`, an index, and a reversible `down`. Add the field to `CustomerImportRow::$fillable` and a `belongsTo(DeliveryZone::class)` relation if history views need it.

- [ ] **Step 3: Extend source headers and generated template**

Add aliases `delivery_zone`, `delivery_zone_id`, `โซน`, and `โซนจัดส่ง` to both source definitions. Add `delivery_zone_id` to the ATRILAK template headers, width, and instructions: new rows must contain an active Zone ID or exact Zone name.

- [ ] **Step 4: Resolve Zone during preview**

Normalize the field as text. If numeric, resolve active Zone by ID; otherwise resolve active Zone by exact name. If empty or unresolved, add `ไม่พบโซนลูกค้า`; keep the row `review_required` unless another required-field error makes it invalid. Store the resolved integer ID in the row.

- [ ] **Step 5: Recheck Zone during confirmation**

Before reserving/importing each selected row, re-resolve the active `delivery_zone_id` inside the existing transaction. If it is absent or inactive, change the row to `review_required` and do not create a Customer. For valid rows, create the address with the resolved Zone and persist the same ID in `customer_import_rows`.

- [ ] **Step 6: Show the Zone in Preview and reports without changing legacy history**

Add a Zone column and reason display to the Preview table. Do not alter or delete existing Import Batch/Row records; the new nullable field remains null for old rows.

- [ ] **Step 7: Run import tests and syntax checks**

Run:

```powershell
php artisan test tests/Unit/Customers/CustomerImportValidationServiceTest.php tests/Feature/Customers/CustomerImportPreviewTest.php tests/Feature/Customers/CustomerImportConfirmTest.php
php -l app/Services/Customers/CustomerImportValidationService.php
php -l app/Services/Customers/CustomerImportService.php
git diff --check
```

### Task 8: Migration rehearsal, full verification, Browser QA, and Owner handoff

**Files:**
- Modify only tests or project files identified by a failing scoped check; do not touch any pre-existing untracked path.

**Interfaces:**
- The branch must contain only the approved implementation, migrations, tests, and the two approved Superpowers artifacts.
- Production remains untouched and no production migration/import/browser sale is performed.

- [ ] **Step 1: Run the test-database migration rehearsal**

Confirm the configured test database name and environment are non-production. Run the project-supported fresh migration and upgrade-path commands against that test database only. Record migration status and any skipped PostgreSQL-only rehearsal reason.

- [ ] **Step 2: Run focused and regression automated suites**

Run:

```powershell
php artisan test tests/Feature/Categories tests/Feature/FrequentProducts tests/Feature/Customers tests/Feature/Sales/SaleV3PageTest.php tests/Feature/Sales/SalePrintModeContractTest.php
node --test tests/Frontend/final-pos.test.mjs tests/Frontend/sale-v3-behavior.test.mjs tests/Frontend/pos-payment.test.mjs tests/Frontend/pos-payment-integration.test.mjs
```

Then run the broader project suite that is safe for the configured test database. Record baseline failures separately and do not claim them as regressions without comparison.

- [ ] **Step 3: Run quality gates**

Run PHP syntax on changed PHP files, `php artisan pint` scoped to changed PHP files, JavaScript `node --check` on changed modules, `npm run build` when dependencies are available, `php artisan route:list --except-vendor`, view compilation/cache checks without Production, and `git diff --check`.

- [ ] **Step 4: Inform Owner before environment-dependent Browser QA**

Before opening Laragon/Apache/PostgreSQL or using a browser, state that the next step requires the test environment and confirm the active database is not Production. If the environment is unavailable, record Browser QA as skipped with exact manual steps and do not substitute Production.

- [ ] **Step 5: Execute Browser QA on test data only**

Verify Category edit, order persistence, POS order parity, five Frequent Products pin/reorder/unpin flow, Quantity Modal/Unit/Pricing/Stock behavior, desktop and 390px Product Card layout, Customer Zone rejection/success in both creation flows, print popup auto-close, direct document no auto-close, A4/A5/QR/customer/address/table/summary, and no console errors.

- [ ] **Step 6: Review final diff and safety state**

Run:

```powershell
git status --short --branch
git diff --stat
git diff --check
git diff --name-only
```

Verify no `.env`, secrets, logs, cache, backups, Production files, or pre-existing untracked files are staged or changed. Confirm no Customer/Sale/Stock/Payment deletion or cleanup command was run.

- [ ] **Step 7: Commit only after tests pass and report the full SHA**

Stage only approved tracked files, including the two approved spec/plan artifacts, then create one intentional feature-branch commit after automated and available Browser QA checks pass. Do not push or merge. Report commit SHA and committed file list.

- [ ] **Step 8: Produce the Owner Report and stop**

Report baseline, branch/base/feature SHAs, changed files, migrations, test counts and baseline failures, Browser QA PASS/FAIL/SKIPPED, customer-safety confirmation, print limitation, production deployment plan only, and final status exactly:

```text
READY FOR OWNER REVIEW
```
