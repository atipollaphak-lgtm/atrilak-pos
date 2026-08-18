# POS V3 Product Ordering & Navigation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add per-category Product ordering to POS V3 while preserving the existing top-level Frequent, All Categories, and real Category navigation, and add the approved POS V3 dashboard exit guard.

**Architecture:** Store a forward-safe `products.sort_order` integer on the Product record. A focused `ProductOrderingService` owns append and transactional reorder rules; a manager/owner-only endpoint exposes complete active-product order updates. POS V3 keeps one product-card collection and applies a deterministic client-side comparator for Frequent, All Products, and real Category tabs, with an explicit drag reorder mode that never runs during normal selling.

**Tech Stack:** Laravel 13, PHP 8.3, PostgreSQL Test DB, Blade, vanilla JavaScript, Node test runner, Vite.

**Spec:** Owner-approved POS V3 Product Ordering & Navigation Sprint in the current task.

## Global Constraints

- Keep `ขายบ่อย` as the first/default top-level tab and use only `pos_v3_frequent_products.sort_order` there.
- Keep `ทุกหมวด` and each real Category as separate top-level tabs.
- All Products order is Category sort order → Product sort order → Product ID.
- Reorder is available only in an explicit `จัดลำดับสินค้า` mode and is not an auto-save or normal sell action.
- Remove visible F2–F8 labels only; keep existing F2/F8 keyboard behavior.
- POS V3 logo links to Dashboard and warns before leaving when the cart is non-empty.
- Do not change price, stock, cost, barcode, Customer, Sale, Payment, POS V1/V2, Production, or Production data.
- Leave pre-existing untracked files in the original checkout untouched.

---

### Task 1: Establish red tests for Product ordering contracts

**Files:**
- Create: `database/migrations/2026_08_18_000004_add_sort_order_to_products_table.php`
- Create: `app/Services/ProductOrderingService.php`
- Create: `app/Http/Controllers/ProductOrderingController.php`
- Create: `tests/Feature/Products/ProductOrderingTest.php`
- Modify: `tests/Feature/Sales/SaleV3FrequentProductsTest.php`
- Modify: `tests/Feature/Sales/SaleV3PageTest.php` if the existing page fixture needs the new contract

**Interfaces:**
- `ProductOrderingService::nextSortOrderForLockedCategory(int $categoryId): int` returns the next zero-based order while the caller owns the Category row lock.
- `ProductOrderingService::reorder(Category $category, array $productIds): void` validates the complete active set and updates only `products.sort_order` in one transaction.
- `PUT /categories/{category}/products/order` accepts `{ "product_ids": [1, 2, 3] }` and is restricted by the existing `role:manager` middleware.

- [ ] Write tests for the schema, deterministic order behavior, creation append, category-move append, authorization, complete-set validation, frequent-order isolation, and unchanged protected fields.
- [ ] Run `php artisan test --filter=ProductOrderingTest` and the new POS page assertions before implementation; record meaningful failures rather than setup failures.
- [ ] Implement only the smallest backend slice needed for the red tests.
- [ ] Re-run the focused tests green.

### Task 2: Implement POS V3 ordering and navigation behavior

**Files:**
- Modify: `app/Http/Controllers/SaleV3Controller.php`
- Modify: `resources/views/sales-v3/index.blade.php`
- Modify: `resources/views/sales-v3/partials/product-navigation.blade.php`
- Modify: `resources/views/sales-v3/partials/product-grid.blade.php`
- Modify: `resources/views/sales-v3/partials/final-sidebar.blade.php`
- Modify: `public/js/modules/sale-v3.js`
- Modify: `public/js/modules/final-pos.js`
- Modify: `public/css/sale-v3.css`
- Modify: `tests/Frontend/sale-v3-behavior.test.mjs`
- Modify: `tests/Frontend/final-pos.test.mjs`

**Interfaces:**
- `data-product-sort-order` and `data-category-sort-order` are render-only ordering metadata; no sale payload uses them.
- `data-product-order-url-template` resolves the selected real Category endpoint.
- POS state uses `productOrdering` and a local snapshot; save sends the complete active selected-category IDs and cancel restores the local snapshot.

- [ ] Add failing frontend tests for Frequent isolation, All/Category comparator order, normal-card click behavior, explicit reorder mode, and F2/F8 keyboard preservation.
- [ ] Add failing Blade/FinalPos assertions for no visible shortcut label and dashboard warning behavior.
- [ ] Implement server-rendered metadata and the minimal client comparator/mode state.
- [ ] Implement the anchor dashboard guard with `preventDefault()` only when a non-empty cart is declined.
- [ ] Run `node --test tests/Frontend/sale-v3-behavior.test.mjs tests/Frontend/final-pos.test.mjs` and keep the existing 115-test behavior suite green.

### Task 3: Test DB migration and integrated verification

**Files:**
- Modify only task files above; generated `public/build` remains ignored and is not committed.

- [ ] Verify `.env` points to `atrilak_pos_final_test_20260729` and never Production.
- [ ] Run `php artisan migrate` on the Test DB only and verify migration `2026_08_18_000004_add_sort_order_to_products_table` is `Ran`.
- [ ] Run scoped PHP/Node tests, Pint/syntax checks, Vite build, and `git diff --check`.
- [ ] Run the broader regression suite and document the two known baseline URL fixture failures if they remain unchanged.
- [ ] Perform Browser QA only against the Test environment; do not create a Sale or Customer.

### Task 4: Review and feature-branch handoff

**Files:**
- Stage and commit only feature files; never stage `.env`, vendor, build output, or pre-existing unrelated files.

- [ ] Review `git diff`, protected behavior, sensitive files, and status.
- [ ] Commit on `codex/pos-v3-product-ordering-navigation` without merge/push/main/deploy.
- [ ] Report branch, base/feature SHA, files, migration, tests, browser QA, regression, limitations, and Production status.
- [ ] Stop with the exact handoff marker `READY FOR OWNER REVIEW`.
