# Product Module UX Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign Product management into a searchable, filterable workspace with shared Add/Details XL modal workflow, nullable product images, read-only current price/stock summaries, and no delete action.

**Architecture:** Keep the existing Product resource routes and ProductUpdateService transaction/locking behavior. Add server-side list query state to ProductController, use one shared Blade modal partial for create/edit/details, and add a small page-scoped JavaScript module for modal loading/submission and preserving query state. Add only the approved nullable `image_path` column; Selling Rule remains an explicitly disabled placeholder.

**Tech Stack:** Laravel 13, PHP 8.3, Blade/AdminLTE, vanilla JavaScript, PostgreSQL, PHPUnit feature tests, Laravel Storage public disk.

## Global Constraints

- Preserve POS V1/V2, stock semantics, pricing formulas, product-unit behavior, and existing route names.
- Do not add Selling Rule model, service, migration, or business logic.
- Add only a forward-safe nullable `products.image_path` migration; never rewrite historical Product data.
- Product Details must not edit current price, stock, profit, stock value, or usage counts.
- Product UI must not expose Product Delete; Active/Inactive is the lifecycle control.
- Use existing Storage public-disk conventions and validation.
- Do not add dependencies or modify unrelated modules.
- Run scoped tests, PHP syntax/Pint, JavaScript syntax/build checks, `git diff --check`, and one browser verification pass requiring Laragon/browser access.
- Commit only after verification; do not push, merge, or deploy.

---

### Task 1: Establish red tests for Product list and image behavior

**Files:**
- Create: `tests/Feature/Products/ProductManagementTest.php`
- Modify: none

**Interfaces:**
- Consumes: Existing `products.index`, `products.store`, `products.update` routes and RefreshDatabase test setup.
- Produces: Failing behavioral coverage for query state, disabled Selling Rule UI, no delete UI, image validation/storage, and read-only details.

- [ ] **Step 1: Write feature tests** for:
  - default category/name ordering;
  - name, product code, and barcode search;
  - category/status filters;
  - per-page `10/20/50/100/all` behavior and pagination visibility;
  - disabled Selling Rule placeholder;
  - no delete controls in rendered Product UI;
  - image upload stores on public disk and persists `image_path`;
  - invalid image rejects while preserving validation;
  - details markup exposes price and stock as read-only.
- [ ] **Step 2: Run the new test file** with `php artisan test tests/Feature/Products/ProductManagementTest.php`.
- [ ] **Step 3: Confirm failures are caused by missing Product redesign behavior**, not test setup or database configuration.

### Task 2: Add the approved additive image schema and model support

**Files:**
- Create: `database/migrations/2026_07_27_000001_add_image_path_to_products_table.php`
- Modify: `app/Models/Product.php`

**Interfaces:**
- Consumes: Existing `products` table.
- Produces: Nullable `image_path` attribute available to Product create/update and Blade `asset('storage/...')` rendering.

- [ ] **Step 1: Add the migration** with nullable string `image_path`; down method drops only that column.
- [ ] **Step 2: Add `image_path` to Product fillable**.
- [ ] **Step 3: Run the focused schema/model tests** and confirm the image persistence assertions can progress.

### Task 3: Implement server-side Product list query and modal data contracts

**Files:**
- Modify: `app/Http/Controllers/ProductController.php`
- Modify: `app/Models/Product.php` only if a focused relation/helper is needed
- Modify: `routes/web.php` only if an existing route cannot support the modal workflow

**Interfaces:**
- Consumes: Query keys `search`, `category_id`, `status`, `sort`, `per_page`, `page`.
- Produces: Paginated or unpaginated `products`, categories, units, list metadata, and product modal data without N+1 queries.

- [ ] **Step 1: Implement a constrained Product query** using eager-loaded category/unit data and a subquery/existence check only for supported selling-rule data; default to category then name.
- [ ] **Step 2: Add search across `name`, `product_code`, `sku`, and `barcode`; add category/status filters; keep Selling Rule filter disabled/ignored and expose its disabled state to the view.
- [ ] **Step 3: Add allow-listed sorts** for category, name, cost, selling price, profit, stock, created date, and updated date; calculate profit using the established cost/selling values without changing pricing business logic.
- [ ] **Step 4: Add per-page handling** for 10, 20, 50, 100, and all; preserve query parameters through pagination.
- [ ] **Step 5: Update store/update validation** for nullable image upload using public-disk conventions, initial prices, and existing minimum-stock rules; do not pass unvalidated request fields to Product::create/update.
- [ ] **Step 6: Preserve image when no replacement is uploaded** and delete/replace only the prior image file when a valid replacement is submitted, without affecting Product records.
- [ ] **Step 7: Run the focused Product test file** and fix only failures from this task.

### Task 4: Build the shared Product XL modal and redesigned list

**Files:**
- Modify: `resources/views/products/index.blade.php`
- Create: `resources/views/products/partials/_product_modal.blade.php`
- Create: `public/css/products.css`

**Interfaces:**
- Consumes: Controller list data, old input/errors, Product modal data, existing route names.
- Produces: Product list table with query controls and a shared Add/Details/Edit XL modal; image placeholder; disabled Selling Rule field; read-only price/stock/usage sections; only Details table action.

- [ ] **Step 1: Replace the full-page create form/list markup** with the new header, count, search/filter/sort/per-page controls, table, and pagination.
- [ ] **Step 2: Render product image or a stable placeholder; render inactive badge, stock status using existing minimum-stock threshold, and profit status without inventing new thresholds/formulas.
- [ ] **Step 3: Add the shared XL modal partial** with mode-specific title/fields, create-edit fields, read-only summaries, disabled Selling Rule copy `ยังไม่ได้กำหนด`, and Manage Price/Stock History controls disabled or linked only when an existing route is available.
- [ ] **Step 4: Remove Product Delete buttons/forms from list and modal; retain Active/Inactive as a normal editable status field.
- [ ] **Step 5: Add responsive styles in `public/css/products.css` without inline large CSS blocks.
- [ ] **Step 6: Run Blade/PHP syntax checks and the focused Product tests.

### Task 5: Add page-scoped modal behavior and query-state preservation

**Files:**
- Create: `public/js/modules/product-management.js`
- Modify: `resources/views/products/index.blade.php`

**Interfaces:**
- Consumes: Bootstrap modal events, `data-product` JSON, current URL query string, Laravel form endpoints/CSRF token.
- Produces: Add modal open, Details modal load, validation error retention, successful refresh preserving search/filter/sort/per-page/page, and optional new-row highlight.

- [ ] **Step 1: Add JavaScript tests/checkable behavior through DOM-safe functions or a syntax/build check**; keep the module dependency-free.
- [ ] **Step 2: Implement modal population and reset behavior for Add and Details modes.
- [ ] **Step 3: Submit forms through normal Laravel POST/PUT requests while preserving query state in hidden inputs or redirect query parameters; keep validation errors visible and inputs intact on failure.
- [ ] **Step 4: Add image preview/reset behavior and placeholder fallback.
- [ ] **Step 5: Load the module only on the Product page and run `node --check public/js/modules/product-management.js`.

### Task 6: Add/adjust scoped regression tests

**Files:**
- Modify: `tests/Feature/Products/ProductManagementTest.php`
- Modify: `tests/Feature/Products/ProductMinimumStockTest.php` only if the new validated payload contract requires a compatible fixture update

**Interfaces:**
- Consumes: Completed controller/view behavior.
- Produces: Coverage for create/update, image upload, validation retention, inactive state, modal read-only fields, and existing minimum-stock behavior.

- [ ] **Step 1: Add assertions that existing products without images render normally with the placeholder.
- [ ] **Step 2: Add assertions that active/inactive changes do not delete the record.
- [ ] **Step 3: Add assertions that Product Details does not expose editable current price/stock inputs.
- [ ] **Step 4: Run `php artisan test tests/Feature/Products tests/Feature/Sales/ProductUnitConversionTest.php` once after implementation.

### Task 7: Code quality, browser verification, and commit

**Files:**
- Review only the files listed above; no additional files without scope justification.

- [ ] **Step 1: Run PHP syntax checks on changed PHP files.
- [ ] **Step 2: Run Laravel Pint on the changed PHP files.
- [ ] **Step 3: Run `node --check public/js/modules/product-management.js` and `npm run build` if the project build is available.
- [ ] **Step 4: Run `git diff --check` and inspect the complete diff for scope, security, and protected business rules.
- [ ] **Step 5: Inform the Owner before starting Laragon/Apache/browser verification.
- [ ] **Step 6: Verify the Product list, query controls, Add modal, validation, image upload, Details/Edit modal, read-only summaries, Active/Inactive, responsive layout, and absence of delete controls once in the browser.
- [ ] **Step 7: Commit the scoped work with `feat: redesign product management workflow`; do not push, merge, or deploy.
- [ ] **Step 8: Verify commit hash, committed file list, and clean `git status --short`.
