# Excel Bulk Product Import Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task with review checkpoints.

**Goal:** เพิ่ม workflow สำหรับเพิ่มสินค้าใหม่สูงสุด 500 รายการจากไฟล์ `.xlsx` ผ่าน Template, Preview, Validation, Confirm และ Error Report โดยไม่เปลี่ยน business rule เดิมของ Product/Stock/Pricing

**Architecture:** เพิ่ม Product Import controller และ services ที่แยกหน้าที่ชัดเจน: Template/Parser, Validation, Preview Storage และ Confirm Import. Preview จะเก็บ normalized payload ใน cache แบบผูกกับ user และ token อายุ 30 นาที; Confirm จะโหลด payload, ตรวจ token ownership/state ซ้ำ และบันทึก Product, Product Unit, Barcode และ Opening Stock Movement ใน transaction เดียว. ใช้ `ProductNumberService` และ `ProductUnitService` เดิมสำหรับ code/barcode sequence และ base unit.

**Tech Stack:** Laravel 13, PHP 8.3, PostgreSQL/SQLite test database, PhpSpreadsheet, Blade/AdminLTE, PHPUnit.

## Global Constraints

- Import เฉพาะสินค้าใหม่; ห้ามแก้ไขหรือ upsert สินค้าเดิม
- รับเฉพาะ `.xlsx`, สูงสุด 500 data rows และขนาดไฟล์สูงสุด 5 MB
- ต้องมี Preview ก่อน Confirm และ Preview ห้ามสร้าง Product หรือ Stock Movement
- Confirm ต้องเป็น Database Transaction เดียวและ rollback ทั้งชุดเมื่อเกิดข้อผิดพลาด
- Opening Stock ต้องบันทึกผ่าน Stock Movement แล้วจึงอัปเดต `products.stock_qty`
- ใช้ permission เดิมของ Product management (`role:manager` route scope); ไม่สร้าง permission ใหม่
- ใช้ code/barcode generation logic เดิมและห้าม apply Pricing Engine/Category Pricing อัตโนมัติ
- ไม่เพิ่ม migration หรือแตะ Production database ใน sprint นี้
- ห้ามแก้ POS, Purchase, Pricing formulas, existing product update, images, multiple units, tier price, CSV หรือ deployment
- ห้ามแตะไฟล์ที่มีอยู่ก่อนเริ่มงานและอยู่นอก scope: `design-qa.md`, `docs/superpowers/plans/2026-07-29-final-pos-hold-bill.md`

---

### Task 1: Establish import dependency, configuration, and contracts

**Files:**
- Modify: `composer.json`, `composer.lock`
- Create: `config/product_import.php`
- Create: `app/Data/Products/ProductImportRowData.php`
- Create: `app/Data/Products/ProductImportPreviewData.php`
- Create: `app/Data/Products/ProductImportResultData.php`
- Test: `tests/Unit/Products/ProductImportConfigurationTest.php`

- [ ] **Step 1: Write the failing test** asserting the import limits and token TTL are exposed by configuration.
- [ ] **Step 2: Run the focused test** and confirm it fails because the configuration/data contracts do not exist.
- [ ] **Step 3: Add PhpSpreadsheet and the configuration** with `max_rows=500`, `max_file_size_kb=5120`, `token_ttl_minutes=30`, and `allowed_extensions=['xlsx']`.
- [ ] **Step 4: Add small immutable data objects** for normalized rows, preview payload and result summary without introducing a new framework abstraction.
- [ ] **Step 5: Run the focused test** and confirm it passes.

### Task 2: Build Template and Error Report services

**Files:**
- Create: `app/Services/Products/ProductImportTemplateService.php`
- Test: `tests/Unit/Products/ProductImportTemplateServiceTest.php`

- [ ] **Step 1: Write failing tests** for the four-sheet workbook (`สินค้า`, `หมวดหมู่`, `หน่วย`, `คำแนะนำ`), exact required headers, text-formatted barcode column, and error report columns `สถานะ`/`ข้อผิดพลาด`.
- [ ] **Step 2: Run the tests** and confirm they fail because the service is absent.
- [ ] **Step 3: Implement workbook creation** with explicit Thai headers, active category/unit reference data, frozen header rows, and formula-injection-safe text cells.
- [ ] **Step 4: Implement error workbook generation** from original rows plus status/error columns.
- [ ] **Step 5: Run the tests** and inspect workbook sheet names, values, and data types.

### Task 3: Implement XLSX parsing, row validation, duplicate detection, and preview storage

**Files:**
- Create: `app/Services/Products/ProductImportValidationService.php`
- Create: `app/Services/Products/ProductImportStorageService.php`
- Create: `app/Http/Requests/Products/PreviewProductImportRequest.php`
- Create: `app/Http/Requests/Products/ConfirmProductImportRequest.php`
- Test: `tests/Feature/Products/ProductImportPreviewTest.php`
- Test: `tests/Unit/Products/ProductImportValidationServiceTest.php`

- [ ] **Step 1: Write failing tests** for valid rows, missing/unknown/duplicate headers, empty files, over-500 files, formula cells, invalid category/unit, invalid numeric/negative values, duplicate code/barcode within file, duplicate code/barcode/name in the database, and inactive references.
- [ ] **Step 2: Run the focused tests** and confirm meaningful failures before implementation.
- [ ] **Step 3: Implement XLSX loading** with PhpSpreadsheet, formula rejection, exact header normalization, string-preserving barcode normalization, row limit and file constraints.
- [ ] **Step 4: Implement batch reference lookups** for active categories and active units, preserving normalized row order and collecting row/column errors instead of writing data.
- [ ] **Step 5: Implement preview token storage** in cache with random UUID, authenticated user ID, payload hash, original filename, rows, errors, expiration, pending/used state, and ownership checks.
- [ ] **Step 6: Run the focused tests** and verify Preview creates no Product, Product Unit, Barcode, or Stock Movement rows.

### Task 4: Implement atomic Confirm Import and idempotency

**Files:**
- Create: `app/Services/Products/ProductImportService.php`
- Create: `app/Http/Controllers/ProductImportController.php`
- Modify: `app/Services/ProductCreationService.php` only if a small reusable opening-stock seam is required
- Test: `tests/Feature/Products/ProductImportConfirmTest.php`
- Test: `tests/Feature/Products/ProductImportStockMovementTest.php`

- [ ] **Step 1: Write failing tests** for valid multi-row creation, generated code/barcode uniqueness, base Product Unit creation, explicit price/price lock/status/description, opening stock movement chain, zero-stock behavior, transaction rollback, expired/foreign/used token rejection, and duplicate confirm.
- [ ] **Step 2: Run the focused tests** and confirm the desired behavior is missing.
- [ ] **Step 3: Implement Confirm** behind a token lock: validate owner/state/expiry, re-check critical references and uniqueness, then use one `DB::transaction()` to create each product, base unit, default barcode, opening movement when positive, and final stock.
- [ ] **Step 4: Preserve existing creation logic** by calling `ProductNumberService` and `ProductUnitService` rather than copying generation logic; do not invoke Pricing Engine or modify existing products.
- [ ] **Step 5: Mark token used only after transaction success** and return a result summary; leave token pending after any exception.
- [ ] **Step 6: Run the focused tests** and inspect database counts and movement before/after values.

### Task 5: Add routes, UI workflow, permissions, and downloads

**Files:**
- Modify: `routes/web.php`
- Modify: `resources/views/products/index.blade.php`
- Create: `resources/views/products/import/index.blade.php`
- Create: `resources/views/products/import/preview.blade.php`
- Create: `resources/views/products/import/result.blade.php`
- Test: `tests/Feature/Products/ProductImportAuthorizationTest.php`
- Test: `tests/Feature/Products/ProductImportBrowserContractTest.php`

- [ ] **Step 1: Write failing feature tests** for route availability only to manager/owner, template download, upload-to-preview flow, row-level preview errors, confirm button behavior, result summary, error report download, cancel, and route ordering before `/products/{product}`.
- [ ] **Step 2: Run the feature tests** and confirm the routes/UI are absent.
- [ ] **Step 3: Add routes** under existing authenticated `role:manager` scope in the required order: index, template, preview, confirm, errors, delete/cancel.
- [ ] **Step 4: Add the Import button and Blade pages** with large-row preview table, error summary, confirm only when all rows are valid, CSRF, escaped output, and no public temporary file path.
- [ ] **Step 5: Add controller downloads** using streamed responses and token ownership checks.
- [ ] **Step 6: Run feature tests** and inspect rendered HTML for the expected browser contract.

### Task 6: Regression, browser smoke, and quality verification

**Files:**
- Modify only existing/new tests within `tests/Feature/Products/` and `tests/Unit/Products/` if a gap is discovered
- Add no production files unless a test exposes an in-scope defect

- [ ] **Step 1: Run the full Product and Stock scoped suites** with the test database configured by PHPUnit; confirm baseline behavior remains green.
- [ ] **Step 2: Run PHP syntax checks and Laravel Pint** on changed PHP files.
- [ ] **Step 3: Run `git diff --check`** and inspect the complete diff for scope, sensitive data, accidental migrations, and business-rule changes.
- [ ] **Step 4: Run browser smoke verification** against a local development server if available: open Products, download Template, upload valid/invalid workbook, Preview, Confirm, and observe result/duplicate protection. Do not use Production.
- [ ] **Step 5: Run a PostgreSQL test-database verification** only against the explicitly identified test database if the environment is available; never use production credentials or database.
- [ ] **Step 6: Stage only scoped files, verify branch/base/status, and commit with `feat: add Excel bulk product import`.

