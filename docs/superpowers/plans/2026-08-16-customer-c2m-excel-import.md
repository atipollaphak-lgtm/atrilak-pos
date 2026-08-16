# Customer C2M Excel Import Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a safe C2M/ATRILAK customer Excel import workflow with write-free preview, conservative normalization, duplicate protection, transactional confirmation, audit history, reports, and Owner/Manager authorization.

**Architecture:** Follow the existing Product Excel import boundary: PhpSpreadsheet parses a bounded `.xlsx`, a dedicated validation/normalization service produces immutable row payloads, and Cache stores a short-lived user-owned preview token. Confirmation rechecks authoritative duplicate conditions and creates Customers, optional primary addresses, external references, and batch rows in one transaction; additive audit tables provide history/report data after confirmation.

**Tech Stack:** Laravel 13, PHP 8.3, PostgreSQL-compatible migrations, Eloquent, PhpSpreadsheet 5.9.0, AdminLTE/Bootstrap Blade views, PHPUnit 12, existing `role:manager` middleware and `manager` Gate.

## Global Constraints

- Preview must not create or modify `customers`, `customer_delivery_addresses`, `sales`, `stock_movements`, `payments`, `purchases`, delivery data, or other business transactions.
- Import creates new Customers only; it never updates or merges an existing Customer.
- C2M external references are strings and use a unique `(source_system, external_id)` constraint; `customers.tax_number` is not made unique.
- Phone normalization is conservative; embedded phones in name/address are warnings and never overwrite the primary phone.
- Imported addresses preserve raw text, create one default address only when non-blank, and never auto-assign a delivery zone.
- Only `.xlsx` Excel Workbooks are accepted. `.xls` HTML/web exports receive the specified Save As guidance.
- All multi-row writes are transactional and revalidated inside the transaction.
- No real member workbook or customer data may be committed; no Production migration, import, deploy, or database write is allowed.
- Do not modify pre-existing untracked files: `.superdesign/`, `design-qa.md`, deployment manifests, or unrelated plans.
- Do not add a Composer dependency; reuse `phpoffice/phpspreadsheet` 5.9.0.

---

## File Map

- Create `config/customer_import.php` for limits, TTL, source aliases, template headers, and labels.
- Create `app/Data/Customers/CustomerImportPhoneResultData.php`, `CustomerImportRowData.php`, `CustomerImportPreviewData.php`, and `CustomerImportResultData.php` for typed import contracts.
- Create `app/Models/CustomerImportBatch.php`, `CustomerImportRow.php`, and `CustomerExternalReference.php` for audit/reference persistence.
- Create `app/Services/Customers/CustomerImportPhoneNormalizer.php`, `CustomerImportValidationService.php`, `CustomerImportDuplicateService.php`, `CustomerImportStorageService.php`, `CustomerImportTemplateService.php`, and `CustomerImportService.php` for focused domain responsibilities.
- Create `app/Http/Controllers/CustomerImportController.php` and the two Customer import Form Requests.
- Create three additive migrations for batches, rows, and external references.
- Create upload/preview/result/history Blade views and `public/js/modules/customer-import.js` for bounded UI filtering/selection.
- Modify only `routes/web.php`, `resources/views/customers/index.blade.php`, `app/Models/Customer.php`, and `app/Services/CustomerService.php` outside the new feature files.
- Create focused Unit/Feature tests under `tests/Unit/Customers` and `tests/Feature/Customers`; generate all workbooks synthetically at runtime.

## Shared interfaces

Use these exact signatures across tasks:

```php
// CustomerImportValidationService
public function validate(string $path, ?string $originalFilename = null): array;
// ['file_errors' => string[], 'source_system' => ?string, 'rows' => array]

// CustomerImportDuplicateService
public function annotate(string $sourceSystem, array $rows): array;

// CustomerImportStorageService
public function store(int $userId, string $filename, string $fileHash, string $sourceSystem, array $rows, array $errors): CustomerImportPreviewData;
public function get(string $token, int $userId): ?CustomerImportPreviewData;
public function markUsed(string $token, int $userId): bool;
public function delete(string $token, int $userId): bool;

// CustomerImportService
public function confirm(string $token, int $userId, array $selectedRows): CustomerImportResultData;
```

Every serialized row contains `row_number`, `external_id`, `name`, `phone`, `tax_number`, `branch_type`, `branch_number`, `address`, `remark`, `status`, `reasons`, and `warnings`.

---

### Task 1: Add the contract and conservative parser (TDD)

**Files:**

- Create: `config/customer_import.php`
- Create: `app/Data/Customers/CustomerImportPhoneResultData.php`
- Create: `app/Data/Customers/CustomerImportRowData.php`
- Create: `app/Data/Customers/CustomerImportPreviewData.php`
- Create: `app/Data/Customers/CustomerImportResultData.php`
- Create: `app/Services/Customers/CustomerImportPhoneNormalizer.php`
- Create: `app/Services/Customers/CustomerImportValidationService.php`
- Test: `tests/Unit/Customers/CustomerImportPhoneNormalizerTest.php`
- Test: `tests/Unit/Customers/CustomerImportValidationServiceTest.php`

**Interfaces:** consumes synthetic PhpSpreadsheet workbooks and config aliases; produces normalized row arrays for duplicate annotation and preview storage.

- [ ] **Step 1: Write failing phone tests.** Cover blank, valid 10-digit mobile, numeric/string values, missing-leading-zero mobile, hyphen/space formatting, landline-like, malformed, unusual length, and embedded phones. Assert phone output is a string, uncertain values are `review_required`, and source name/address text is unchanged.

```php
public function test_it_adds_a_leading_zero_only_for_a_plausible_thai_mobile(): void
{
    $result = app(CustomerImportPhoneNormalizer::class)->normalize(805098556);

    $this->assertSame('0805098556', $result->phone);
    $this->assertSame('normalized', $result->status);
}
```

- [ ] **Step 2: Run `php artisan test tests/Unit/Customers/CustomerImportPhoneNormalizerTest.php` and verify FAIL because the normalizer does not exist.**

- [ ] **Step 3: Implement `normalize(mixed $value): CustomerImportPhoneResultData` and `findEmbeddedPhones(string $text): array`.** Convert Thai digits, trim, remove only safe spaces/hyphens/parentheses, accept `^0[689]\d{8}$`, add `0` only to plausible 9-digit mobile values beginning `6|8|9`, and return `review_required` for uncertain values. Never mutate name/address input.

- [ ] **Step 4: Run the phone test again; expected PASS for all specified cases.**

- [ ] **Step 5: Write failing parser tests with runtime-generated `.xlsx` files.** Cover C2M aliases, ATRILAK headers, whitespace, missing/ambiguous headers, blank rows, Thai Unicode, blank name/address, tax continuation, malformed/orphan tax rows, numeric external ID/phone, formula cells, HTML reader rejection, and the row limit. Do not add a real Downloads file.

```php
private function workbook(array $headers, array $rows, string $filename = 'members.xlsx'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'customer-import-').'.xlsx';
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getActiveSheet()->fromArray($headers, null, 'A1');
    $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A2');
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, $filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}
```

- [ ] **Step 6: Run `php artisan test tests/Unit/Customers/CustomerImportValidationServiceTest.php` and verify FAIL before implementation.**

- [ ] **Step 7: Implement config/data/parser.** Set `max_rows` to `1000`, `max_file_size_kb` to `5120`, `token_ttl_minutes` to `30`, and explicit C2M/template aliases. Accept only `.xlsx` with an actual Xlsx reader. For `.xls`, return `ไฟล์ .xls รูปแบบนี้ไม่สามารถอ่านได้โดยตรง กรุณาเปิดด้วย Microsoft Excel และ Save As เป็น Excel Workbook (.xlsx) ก่อนนำเข้า`. Reject formulas, missing/ambiguous headers, malformed workbooks, and over-limit files without raw exceptions. Use C2M `ลำดับ` as external ID; merge `เลขผู้เสียภาษี` continuation rows into the preceding row; mark blank names invalid; preserve name/address text; flag embedded phones; keep Tax ID as a validated string.

- [ ] **Step 8: Run both Unit files and verify PASS.**

```powershell
php artisan test tests/Unit/Customers/CustomerImportPhoneNormalizerTest.php tests/Unit/Customers/CustomerImportValidationServiceTest.php
```

- [ ] **Step 9: Commit only this slice.**

```powershell
git add -- config/customer_import.php app/Data/Customers app/Services/Customers/CustomerImportPhoneNormalizer.php app/Services/Customers/CustomerImportValidationService.php tests/Unit/Customers/CustomerImportPhoneNormalizerTest.php tests/Unit/Customers/CustomerImportValidationServiceTest.php
git commit -m "feat: add customer import parser and phone normalization"
```

---

### Task 2: Add additive audit/reference schema and models

**Files:**

- Create: `database/migrations/2026_08_16_000001_create_customer_import_batches_table.php`
- Create: `database/migrations/2026_08_16_000002_create_customer_import_rows_table.php`
- Create: `database/migrations/2026_08_16_000003_create_customer_external_references_table.php`
- Create: `app/Models/CustomerImportBatch.php`
- Create: `app/Models/CustomerImportRow.php`
- Create: `app/Models/CustomerExternalReference.php`
- Modify: `app/Models/Customer.php`
- Modify: `app/Services/CustomerService.php`
- Test: `tests/Feature/Customers/CustomerImportMigrationTest.php`

**Interfaces:** consumes Task 1 rows; produces batch/row/reference relationships and `CustomerService::reserveCodes(int $count): array`.

- [ ] **Step 1: Write failing migration/model tests.** Assert the three tables, non-unique tax numbers, row relationships, and unique `(source_system, external_id)` behavior. Assert code reservation returns sequential `CUS-####` values.

- [ ] **Step 2: Run `php artisan test tests/Feature/Customers/CustomerImportMigrationTest.php`; expected FAIL before migrations/models.** If PostgreSQL authentication is unavailable, record the environment failure and continue without changing `.env.testing` or using Production.

- [ ] **Step 3: Implement three additive migrations.** Create batches first, rows second, references third. Add status/count columns, JSON warnings, safe nullable historical foreign keys, and indexes on batch/status/source/external ID. Implement `down()` in reverse dependency order. Do not alter existing customer/tax columns or Sale foreign keys.

- [ ] **Step 4: Implement models/relationships with explicit fillable fields and casts.** Add `Customer::externalReferences(): HasMany`; add batch-to-user/rows/references and row/reference-to-customer/batch relations.

- [ ] **Step 5: Refactor the existing code calculation into `CustomerService::reserveCodes(int $count): array`.** Lock current customer code rows once, return ascending `CUS-####` values, return `[]` for non-positive counts, and keep existing `create()` behavior unchanged by calling the allocator for one code.

- [ ] **Step 6: Run PHP syntax and migration tests.**

```powershell
php -l app/Models/CustomerImportBatch.php
php -l app/Models/CustomerImportRow.php
php -l app/Models/CustomerExternalReference.php
php artisan test tests/Feature/Customers/CustomerImportMigrationTest.php
```

- [ ] **Step 7: Commit only the schema slice.**

```powershell
git add -- database/migrations/2026_08_16_000001_create_customer_import_batches_table.php database/migrations/2026_08_16_000002_create_customer_import_rows_table.php database/migrations/2026_08_16_000003_create_customer_external_references_table.php app/Models/Customer.php app/Models/CustomerImportBatch.php app/Models/CustomerImportRow.php app/Models/CustomerExternalReference.php app/Services/CustomerService.php tests/Feature/Customers/CustomerImportMigrationTest.php
git commit -m "feat: add customer import audit and reference schema"
```

---

### Task 3: Add duplicate annotation and Cache preview storage

**Files:**

- Create: `app/Services/Customers/CustomerImportDuplicateService.php`
- Create: `app/Services/Customers/CustomerImportStorageService.php`
- Modify: `app/Data/Customers/CustomerImportPreviewData.php`
- Test: `tests/Feature/Customers/CustomerImportDuplicateTest.php`
- Test: `tests/Feature/Customers/CustomerImportStorageTest.php`

**Interfaces:** consumes parser rows and Task 2 models; produces status-annotated rows and user-bound preview tokens.

- [ ] **Step 1: Write failing duplicate tests.** Cover external reference precedence, normalized phone match, exact tax/name/branch match, same tax/different branch as review-required (not duplicate), same name only as a ready warning, duplicate external IDs in the file, and deterministic duplicate phones in the file.

- [ ] **Step 2: Run `php artisan test tests/Feature/Customers/CustomerImportDuplicateTest.php`; expected FAIL because the service is absent.**

- [ ] **Step 3: Implement `CustomerImportDuplicateService::annotate()`.** Fetch references and customer candidates in batch queries, normalize existing phones in memory with the same normalizer, compare tax/name/branch without making tax unique, and preserve reasons/warnings. Apply precedence `invalid > duplicate > review_required > ready`.

- [ ] **Step 4: Write failing storage tests.** Assert UUID token, user/filename/hash/source/rows/errors/pending state/TTL, owner isolation, one-way `markUsed()`, and deletion.

- [ ] **Step 5: Implement Cache storage.** Use `customer_import.preview.{token}`, configured TTL, user checks, no file paths or workbook bytes, and leave tokens pending when confirm rolls back.

- [ ] **Step 6: Run both Feature tests and verify PASS.**

```powershell
php artisan test tests/Feature/Customers/CustomerImportDuplicateTest.php tests/Feature/Customers/CustomerImportStorageTest.php
```

- [ ] **Step 7: Commit the preview-domain slice.**

```powershell
git add -- app/Services/Customers/CustomerImportDuplicateService.php app/Services/Customers/CustomerImportStorageService.php app/Data/Customers/CustomerImportPreviewData.php tests/Feature/Customers/CustomerImportDuplicateTest.php tests/Feature/Customers/CustomerImportStorageTest.php
git commit -m "feat: add customer import preview storage and duplicate checks"
```

---

## Plan self-review (part 1)

- The first three tasks cover the parser, phone/tax rules, additive schema, code allocation, duplicate levels, preview state, and Cache ownership.
- All automated workbooks are synthetic and generated at runtime; no real member data enters the repository.
- Shared signatures and row fields are consistent across the parser, duplicate service, storage service, and later controller tasks.
- PostgreSQL test failures must be reported, not bypassed through Production or changes to `.env.testing`.

---

### Task 4: Add protected routes, controller, upload/template screens, and preview UI

**Files:**

- Create: `app/Http/Requests/Customers/PreviewCustomerImportRequest.php`
- Create: `app/Http/Requests/Customers/ConfirmCustomerImportRequest.php`
- Create: `app/Http/Controllers/CustomerImportController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/customers/index.blade.php`
- Create: `resources/views/customers/import/index.blade.php`
- Create: `resources/views/customers/import/preview.blade.php`
- Create: `public/js/modules/customer-import.js`
- Test: `tests/Feature/Customers/CustomerImportPreviewTest.php`
- Test: `tests/Feature/Customers/CustomerImportBrowserContractTest.php`

**Interfaces:** consumes Tasks 1–3; produces named routes `customers.import.index`, `.template`, `.preview`, `.confirm`, `.history`, `.history.show`, `.report`, `.destroy` and the manager-only preview UI.

- [ ] **Step 1: Write failing route/authorization tests.** Assert all import endpoints have `auth` and `role:manager`, `.xlsx` and size validation exists, guests redirect to login, Cashiers receive 403, and Owner/Manager see the import page/button.

- [ ] **Step 2: Run `php artisan test tests/Feature/Customers/CustomerImportBrowserContractTest.php`; expected FAIL because routes/controller/views are absent.**

- [ ] **Step 3: Implement protected routes and Form Requests.** Put the group inside the existing `role:manager` middleware group. Validate file extension/content/max size, token shape, selected row numbers, and CSRF; never trust browser row status/value fields.

- [ ] **Step 4: Implement controller preview flow.** `index()` shows help/history links; `template()` streams the template; `preview()` calls parser then duplicate service, stores only Cache payload, logs metadata without member values, and renders preview. Convert parser failures to Thai repair guidance, never raw paths/traces.

- [ ] **Step 5: Add the customer-list button and AdminLTE views.** Use `@can('manager')` beside `เพิ่มลูกค้า`. The upload page has `เลือกไฟล์ Excel`, `ตรวจสอบไฟล์`, `ดาวน์โหลด Template`, and `ประวัติการนำเข้า`. Preview has four counters, required columns, status filters, ready checkboxes, and `ยืนยันนำเข้า X ราย`. Escape all imported values.

- [ ] **Step 6: Implement bounded `customer-import.js`.** Filter rows by `data-status`, maintain ready selection/count, and disable confirmation after submit. Do not add an unbounded upload loop or live-search DOM beyond the configured maximum.

- [ ] **Step 7: Write/run no-write preview tests.** With synthetic workbooks, assert tax continuation is merged, counters are correct, customer/address counts stay unchanged, business-table counts stay unchanged, and malformed/missing-header/`.xls` uploads show actionable errors without writes.

```powershell
php artisan test tests/Feature/Customers/CustomerImportPreviewTest.php tests/Feature/Customers/CustomerImportBrowserContractTest.php
```

- [ ] **Step 8: Commit the protected-preview slice.**

```powershell
git add -- app/Http/Requests/Customers/PreviewCustomerImportRequest.php app/Http/Requests/Customers/ConfirmCustomerImportRequest.php app/Http/Controllers/CustomerImportController.php routes/web.php resources/views/customers/index.blade.php resources/views/customers/import/index.blade.php resources/views/customers/import/preview.blade.php public/js/modules/customer-import.js tests/Feature/Customers/CustomerImportPreviewTest.php tests/Feature/Customers/CustomerImportBrowserContractTest.php
git commit -m "feat: add protected customer import preview flow"
```

---

### Task 5: Implement transactional confirm, history, template, and report

**Files:**

- Create: `app/Services/Customers/CustomerImportTemplateService.php`
- Create: `app/Services/Customers/CustomerImportService.php`
- Modify: `app/Http/Controllers/CustomerImportController.php`
- Create: `resources/views/customers/import/result.blade.php`
- Create: `resources/views/customers/import/history/index.blade.php`
- Create: `resources/views/customers/import/history/show.blade.php`
- Test: `tests/Feature/Customers/CustomerImportConfirmTest.php`
- Test: `tests/Unit/Customers/CustomerImportTemplateServiceTest.php`

**Interfaces:** consumes Task 4 token/selection and Task 2/3 models/services; produces confirm result, batch history, and sanitized CSV report.

- [ ] **Step 1: Write failing template/report tests.** Assert the `.xlsx` template contains only `external_id`, `name`, `phone`, `tax_id`, `branch_type`, `branch_number`, `address`, and `remark`; assert CSV headers and neutralization of values beginning `=`, `+`, `-`, or `@`.

- [ ] **Step 2: Implement template/CSV helpers.** Use existing PhpSpreadsheet Xlsx writer; stream UTF-8 CSV with BOM; prefix formula-like values; never include raw workbook, filesystem paths, exception traces, or secrets.

- [ ] **Step 3: Write failing confirm tests.** Cover ready Customer/address/reference creation, tax/branch/remark preservation, null zone, review/duplicate/invalid/not-selected skips, counts/history/report, stale-preview recheck, same workbook twice producing zero new Customers, and rollback when the second Customer throws.

```php
public function test_confirm_creates_customer_address_and_external_reference_atomically(): void
{
    $token = $this->storeReadyPreview([
        $this->row('1001', 'บริษัททดสอบ', '0805098556', '0123456789012', 'สำนักงานใหญ่', null, 'ที่อยู่ดิบ'),
    ]);

    $this->actingAs($this->manager())
        ->post(route('customers.import.confirm'), ['token' => $token, 'selected_rows' => [2]])
        ->assertOk()
        ->assertSee('นำเข้าสมาชิกสำเร็จ');

    $this->assertDatabaseCount('customers', 1);
    $this->assertDatabaseCount('customer_delivery_addresses', 1);
    $this->assertDatabaseHas('customer_external_references', ['source_system' => 'C2M', 'external_id' => '1001']);
}
```

- [ ] **Step 4: Run `php artisan test tests/Feature/Customers/CustomerImportConfirmTest.php`; expected FAIL before the service/views exist.**

- [ ] **Step 5: Implement `CustomerImportService::confirm()`.** Acquire a Cache lock, validate token/user/state and selected rows are ready, create a `processing` batch, then run one `DB::transaction()` that reserves codes once, re-fetches external refs/phones/tax candidates, reclassifies stale duplicates, and creates Customer, optional default address (`delivery_zone_id = null`), external reference, and batch row. Persist preview-skipped and unselected rows. Use the unique reference constraint/lock as the backstop. Mark the batch completed only after commit; on failure mark it failed with a generic safe reason and leave the token pending.

```php
DB::transaction(function () use ($preview, $selectedRows, $batch): void {
    $codes = $this->customerService->reserveCodes(count($selectedRows));
    // Recheck authoritative duplicate signals once, then create only new rows.
    // Every customer/address/reference/batch-row write is inside this transaction.
});
```

- [ ] **Step 6: Implement result/history/report controller methods and views.** Show imported/skipped/review/invalid counts and batch reference. History is paginated with date, filename, user, imported, skipped/duplicate, review, invalid, and status; detail is paginated and escaped; report includes only duplicate/review/invalid rows.

- [ ] **Step 7: Run confirm/template tests and verify PASS.**

```powershell
php artisan test tests/Feature/Customers/CustomerImportConfirmTest.php tests/Unit/Customers/CustomerImportTemplateServiceTest.php
```

Rollback must leave no partial Customer, address, reference, or batch-row writes; repeat import must create zero new Customers.

- [ ] **Step 8: Commit the transactional slice.**

```powershell
git add -- app/Services/Customers/CustomerImportTemplateService.php app/Services/Customers/CustomerImportService.php app/Http/Controllers/CustomerImportController.php resources/views/customers/import/result.blade.php resources/views/customers/import/history tests/Feature/Customers/CustomerImportConfirmTest.php tests/Unit/Customers/CustomerImportTemplateServiceTest.php
git commit -m "feat: add transactional customer import and history"
```

---

### Task 6: Authorization, regression, security, and final verification

**Files:**

- Create/modify: `tests/Feature/Customers/CustomerImportAuthorizationTest.php`
- Modify only if needed for a focused regression assertion: `tests/Feature/Customers/CustomerModuleTest.php`
- No Production/environment/config changes.

- [ ] **Step 1: Test every endpoint for Owner, Manager, Cashier, and guest.** Cover index, preview, confirm, template, history, detail, report, and destroy. Owner/Manager are allowed; Cashier is 403; guest follows existing login redirect; customer index button is hidden for Cashier.

- [ ] **Step 2: Run focused customer/import regressions.**

```powershell
php artisan test tests/Unit/Customers tests/Feature/Customers tests/Feature/DeliveryZones tests/Feature/Sales/SaleV3PageTest.php tests/Feature/Sales/SaleV3CartWorkflowTest.php
```

Separate environment failures from feature regressions; do not edit unrelated baseline tests.

- [ ] **Step 3: Run syntax, Pint, diff, and security checks.**

```powershell
php -l app/Http/Controllers/CustomerImportController.php
php -l app/Services/Customers/CustomerImportService.php
php -l app/Services/Customers/CustomerImportValidationService.php
php artisan pint app/Http/Controllers/CustomerImportController.php app/Services/Customers app/Models/CustomerImportBatch.php app/Models/CustomerImportRow.php app/Models/CustomerExternalReference.php app/Data/Customers app/Http/Requests/Customers tests/Unit/Customers tests/Feature/Customers
git diff --check
rg -n --glob '!vendor/**' --glob '!node_modules/**' 'dd\(|dump\(|password|secret|hardcoded|customer_import|CustomerImport' app config database/migrations resources/views routes tests
```

Review for debug code, secrets/paths, raw exceptions, formula injection, missing authorization, N+1 duplicate queries, and accidental POS/business changes.

- [ ] **Step 4: Run `php artisan test` if the safe test DB is available.** If PostgreSQL authentication remains unavailable, run safe Unit tests and document the exact skipped PostgreSQL/full-suite commands. Do not change `.env.testing` or connect to Production to make tests green.

- [ ] **Step 5: Inspect final Git state.**

```powershell
git status --short --branch
git log -n 10 --oneline
git diff --stat b1db803..HEAD
git diff --name-only b1db803..HEAD
git diff --check b1db803..HEAD
```

Confirm only feature files plus the committed design/plan are included; pre-existing untracked files remain untouched and unstaged; no real Excel/customer data is tracked.

- [ ] **Step 6: Do Browser QA only if Laragon/Apache is available.** Tell the Owner before this step because it requires Laragon/Apache/manual browser testing. In Test DB only, verify access, upload/preview no-write counts, filters, confirmation, history/report, repeat import, and Cashier direct-URL denial. If services are unavailable, report Browser QA skipped.

- [ ] **Step 7: Stop at `READY FOR OWNER REVIEW`.** Do not merge, push `origin/main`, deploy, run Production migrations, import real members into Production, or modify Production business data. Report branch, baseline/feature HEAD, commits, files, tests, real-file result, safety proof, Browser QA result, limitations, and Owner checklist.

---

## Final plan self-review

- Spec coverage: parser, phone, tax continuation, address/zone, references, duplicate levels, preview, confirm, history, report, permissions, security, performance, transaction, migrations, tests, regression, browser, and production isolation are covered by Tasks 1–6.
- No real member workbook is placed in fixtures; all workbooks are generated at runtime.
- Row/status fields and service signatures are consistent across parser, duplicate service, storage, controller, views, and tests.
- Same-tax/different-branch remains review-required rather than a tax-only duplicate.
- No dependency install, protected business-rule change, broad refactor, Production connection, or destructive migration is planned.
- Self-review confirms there are no incomplete-marker tokens or unresolved implementation steps.
