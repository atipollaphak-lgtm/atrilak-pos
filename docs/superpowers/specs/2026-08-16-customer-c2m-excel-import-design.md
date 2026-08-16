# Customer C2M Excel Import — Design Specification

วันที่: 2026-08-16  
สถานะ: ออกแบบสำหรับ Feature Branch `codex/customer-c2m-excel-import`

## เป้าหมายและขอบเขต

เพิ่ม workflow `ลูกค้า > นำเข้าสมาชิก Excel` สำหรับผู้ใช้ Owner และ Manager เพื่ออ่านข้อมูลจาก C2M POS หรือ ATRILAK template, ตรวจสอบและ preview ก่อนสร้าง Customer ใหม่เท่านั้น

ขอบเขต Version 1:

- รองรับไฟล์ `.xlsx` ที่เป็น Excel Workbook จริง
- รองรับ header ของ C2M ผ่าน alias/normalization และ header ของ ATRILAK template
- Preview ไม่สร้างหรือแก้ Customer, Customer Address หรือข้อมูลธุรกรรมใด ๆ
- Confirm สร้าง Customer ใหม่และ Address หลักเมื่อมี address จริง
- เก็บ external reference ของ C2M เพื่อป้องกัน import ซ้ำ
- มี batch history และรายงาน CSV ของ duplicate/review/invalid rows
- ไม่ update, merge หรือ auto-assign zone ให้ Customer เดิมหรือ Customer ใหม่

นอกขอบเขต:

- รองรับ `.xls` HTML/web export โดยตรง
- import Sale, Stock, Payment, Purchase, Closing, Outstanding balance หรือ Delivery Zone
- auto merge/update Customer เดิม
- generic column mapper หรือ address parser
- destructive undo import

## หลักฐานจากระบบปัจจุบัน

- `CustomerService` ใช้ transaction, สร้างรหัส `CUS-####`, และสร้าง primary delivery address เฉพาะเมื่อมีข้อมูล
- `CustomerDeliveryAddress` รองรับ `delivery_zone_id = null` และ `is_default`
- `customers.tax_number` ไม่มี unique constraint และ branch ถูกเก็บแยกใน `branch_type`/`branch_number`
- `role:manager` และ Gate `manager` เป็น authorization mechanism เดิมสำหรับ Manager ขึ้นไป
- `PhpSpreadsheet` 5.9.0 มีอยู่แล้วใน `composer.json`
- Product import ใช้ PhpSpreadsheet + Cache preview token + transaction confirm เป็น pattern ที่นำมาปรับใช้ได้

ข้อไม่แน่นอนที่ต้อง fail safely:

- ชื่อ sheet และตำแหน่งคอลัมน์ C2M อาจเปลี่ยนได้ จึงตรวจ header จริงแทนการยึดตำแหน่ง
- ไฟล์ `.xls` ที่พบใน Downloads เป็น HTML product export ไม่ใช่ member workbook และจะไม่ถูกใช้เป็น fixture หรือ import data
- สถานะ migration ของ PostgreSQL test environment อ่านไม่ได้ในรอบ survey เพราะ authentication ไม่พร้อม; ห้ามใช้ Production เป็นทางแก้

## Architecture

สร้างโมดูลเฉพาะ `CustomerImport` โดยแยกหน้าที่ดังนี้:

- `CustomerImportController`: HTTP orchestration, download, view และ user-facing errors
- `CustomerImportValidationService`: file/workbook/header/row validation, source detection และ row normalization
- `CustomerImportPhoneNormalizer`: normalize เบอร์และคืนผลพร้อม status/warnings
- `CustomerImportStorageService`: เก็บ preview payload แบบ server-side Cache พร้อม owner, TTL และ state
- `CustomerImportService`: duplicate re-check, batch transaction, Customer/address/reference creation และ summary
- `CustomerImportTemplateService`: สร้าง ATRILAK `.xlsx` template และ sanitized CSV report
- Data objects/config/request classes: ทำให้ row/preview/result และ input contract ชัดเจน

ไม่แก้ customer create flow เดิม ยกเว้นเพิ่ม helper สำหรับ reserve customer codes แบบ batch ถ้าจำเป็นเพื่อหลีกเลี่ยงการ query รหัสซ้ำ 652 ครั้ง

## Database design

เพิ่ม migration แบบ additive สามตาราง:

### `customer_import_batches`

- `id`
- `source_system` (`C2M` หรือ `ATRILAK_TEMPLATE`)
- `original_filename`
- `file_hash` nullable
- `created_by` foreign key ไป `users`, restrict/null-on-delete ตาม convention ที่เหมาะสม
- `total_parsed`, `ready_count`, `imported_count`, `duplicate_count`, `review_count`, `invalid_count`, `not_selected_count`
- `status` (`processing`, `completed`, `failed`)
- `failure_reason` nullable และไม่เก็บ exception trace ให้ผู้ใช้
- timestamps และ indexes สำหรับ created user/status/date

### `customer_import_rows`

เก็บเฉพาะ normalized/result metadata หลัง Confirm ไม่เก็บไฟล์ดิบ:

- `id`, `customer_import_batch_id`, `customer_id` nullable
- `row_number`, `external_id`, `name`, `phone`, `tax_number`, `branch_type`, `branch_number`, `address`, `remark`
- `status` (`imported`, `duplicate`, `review_required`, `invalid`, `not_selected`)
- `reason` nullable และ `warnings` JSON nullable
- indexes ที่ batch/status/external ID

### `customer_external_references`

- `id`, `customer_id`, `customer_import_batch_id` nullable
- `source_system`, `external_id` เป็น string
- timestamps
- unique constraint `(source_system, external_id)` และ indexes ที่ customer/batch

ไม่มีการเพิ่ม unique constraint ให้ `customers.tax_number` และไม่แก้ Sale foreign keys หรือข้อมูลย้อนหลัง

## Parse และ normalize flow

1. Form Request รับเฉพาะ file, `.xlsx`, size ตาม config และ CSRF/role เดิม
2. Validation service ตรวจ extension, MIME/content reader และ workbook structure; ต้องเป็น Xlsx reader จริง ไม่ใช่ HTML reader หรือ Xls reader
3. ตรวจ sheet ที่มี header ตรงกับ C2M alias หรือ ATRILAK template alias อย่างชัดเจน หากไม่พบ/พบหลาย sheet ที่กำกวมให้ file error
4. Normalize whitespace โดยไม่เปลี่ยน spelling ของชื่อหรือ free-text address; ค่าจาก formula cells ถูกปฏิเสธอย่างปลอดภัย
5. C2M ใช้ `ลำดับ` เป็น `external_id`; ATRILAK template ใช้ `external_id` ตาม header
6. แถวว่างถูกข้าม
7. แถว `เลขผู้เสียภาษี` ตามด้วยตัวเลขที่ไม่มี customer fields ถูกถือเป็น tax continuation และ attach ให้ customer row ก่อนหน้า; ไม่สร้าง Customer ใหม่
8. Tax ID ถูกเก็บเป็น string, ตัดเฉพาะ whitespace/hyphen ที่ปลอดภัย และต้องเป็น 13 digits หากมีค่า; continuation ที่ malformed จะทำให้ customer row เป็น `review_required` หรือ `invalid` หากไม่มี row ก่อนหน้า
9. ชื่อว่างเป็น `invalid`; ชื่อถูก trim แต่ไม่ตัดเลขหรือแก้ spelling
10. Address ถูกเก็บ raw; ไม่มีการเดาตำบล/อำเภอ/จังหวัด/postcode/zone

### Phone policy

- รับ string และ numeric Excel value และเก็บผลเป็น string เท่านั้น
- trim และตัด space/hyphen/วงเล็บที่ปลอดภัย
- 10-digit Thai mobile ที่ขึ้นต้น `06`, `08`, `09` ผ่านเป็นค่าปกติ
- 9-digit value ที่ขึ้นต้น `6`, `8`, `9` และเติม `0` แล้วเป็น Thai mobile ที่สมเหตุสมผล จึง normalize พร้อม warning ว่าเติม leading zero
- landline-like, malformed, unusual length หรือค่าที่ไม่แน่ใจไม่ถูกเปลี่ยนแบบเดา; ให้ `review_required` พร้อมค่า/เหตุผลที่ตรวจสอบได้
- phone pattern ใน name/address ถูกตรวจพบและแสดงเป็น warning; ห้ามเอามาเขียนทับ primary phone
- primary phone ว่างแต่พบ embedded phone ทำให้ `review_required`; primary phone มีอยู่แล้วให้ใช้ค่าเดิมและ warning

## Duplicate decision

Duplicate lookup ทำแบบ batch ไม่ query Customer ทีละ row:

- preload external references ตาม `(source_system, external_id)`
- preload Customer phones และ normalize ใน memory เพื่อเทียบ exact normalized value
- preload candidates ตาม tax/name และเทียบ `tax_number + normalized name + branch`
- preload name candidates เพื่อแสดง warning เท่านั้น

ผลลัพธ์:

- external reference ซ้ำ: `duplicate`, skip
- normalized phone ตรง Customer เดิม: `duplicate`, skip
- tax + name + branch ตรง: `duplicate`, skip
- tax เดิมแต่ branch ต่างกัน: ไม่ถือเป็น duplicate key; ให้ `review_required` เพื่อให้ Owner ตรวจ
- tax เดิมอย่างเดียว: ไม่ถือเป็น duplicate
- name ตรงอย่างเดียว: `ready` พร้อม warning ไม่ auto-skip
- external ID ซ้ำใน workbook: แถวที่เกี่ยวข้องเป็น `invalid` และไม่สร้าง Customer
- duplicate phone ใน workbook: แถวที่ชนกันตั้งแต่รายการที่สองเป็น `duplicate` พร้อมเหตุผล

ทุก duplicate decision ถูกทำซ้ำใน Confirm ภายใน transaction เพื่อกัน stale preview และห้าม update Customer เดิม

## Preview และ confirmation

Preview payload เก็บใน Cache key ที่ผูกกับ user และ token มี TTL จำกัด เช่นเดียวกับ Product import; ไม่มี Customer/Address/transaction write ในขั้นนี้

หน้า Preview แสดง:

- summary cards: `พร้อมนำเข้า`, `ต้องตรวจสอบ`, `มีอยู่แล้ว`, `ผิดพลาด`
- ตาราง status, C2M ID, name, phone, address, Tax ID และ warning/reason
- filter ทุก status และ ready-row checkbox ซึ่งเลือกทั้งหมดเป็นค่าเริ่มต้น
- confirm button แสดงจำนวน selected ready rows; rows review/duplicate/invalid ถูกเลือกไม่ได้
- ปุ่มยกเลิกและ download report เมื่อมีผลลัพธ์ที่ข้าม

การ submit Confirm ตรวจ token/user/state และ selected row keys จาก server ไม่เชื่อค่าที่ส่งจาก browser ว่าเป็น ready หรือเป็นข้อมูลลูกค้า

ใช้ Cache lock สำหรับ confirm และ unique DB constraint เป็น backstop. Confirm จะสร้าง batch metadata เป็น `processing`, แล้วทำ Customer, Address, External Reference และ batch rows ใน transaction เดียว; หากเกิด error ทุก business write rollback และ batch ถูกทำเครื่องหมาย `failed` ด้วยข้อความปลอดภัย

การสร้าง Customer:

- `code` ถูกจัดสรรตาม existing `CUS-####` sequence แบบ batch ภายใต้ lock
- `active = true`, name/phone/tax/branch/remark มาจาก normalized row
- สร้าง address หลักเพียงเมื่อ address ไม่ว่าง; `is_default = true`, `delivery_zone_id = null`
- ไม่แก้ Customer เดิม ไม่เขียน Sale/Stock/Payment/Purchase/Delivery transaction

หาก Confirm ซ้ำด้วย workbook เดิม external reference จะเปลี่ยน rows เป็น duplicate และจำนวน Customer ใหม่เป็นศูนย์

## History และ reports

หน้า Import มี link ไป History และ History detail แสดง filename, source, ผู้ดำเนินการ, วันที่, counts และสถานะ batch. รายละเอียดใช้ `customer_import_rows` แบบ paginate ได้

Download report ใน Version 1 ใช้ UTF-8 CSV พร้อม BOM มีคอลัมน์ C2M ID, name, phone, address, tax ID, status, reason. ทุก cell ที่ขึ้นต้นด้วย `=`, `+`, `-`, `@` จะถูก prefix เพื่อป้องกัน spreadsheet formula injection

Template `.xlsx` ใช้คอลัมน์ที่มีใน schemaจริงเท่านั้น: `external_id`, `name`, `phone`, `tax_id`, `branch_type`, `branch_number`, `address`, `remark` และมีคำแนะนำว่า external ID ต้องคงเดิมเมื่อเป็น source เดิม

## Authorization และ UI

- routes หน้า import, preview, confirm, history, detail และ report อยู่ใน `auth` + `role:manager`
- ปุ่ม `นำเข้าสมาชิก Excel` ใน customer index ใช้ `@can('manager')`; Cashier อาจเห็นหน้ารายชื่อลูกค้าแต่ไม่เห็นปุ่มและเข้า URL โดยตรงไม่ได้
- ไม่สร้าง role/permission mechanism ใหม่
- Blade output ใช้ escaping ตามปกติ; error แสดงข้อความแก้ไขได้ ไม่แสดง exception raw หรือ filesystem path

## Test strategy

Unit tests:

- header aliases, missing headers, blank rows, C2M tax continuation/malformed continuation
- numeric/string/leading-zero/landline/malformed/blank/embedded phone cases
- Thai Unicode, blank name/address, tax string preservation
- duplicate external IDs in workbook and each duplicate level
- CSV formula-injection sanitization

Feature tests:

- Preview creates zero Customer/Address and leaves sales/stock/payment/purchase counts unchanged
- ready/review/duplicate/invalid counters and selection contract
- Confirm creates Customer/address/reference correctly, preserves tax and null zone
- review/duplicate/invalid/not-selected rows are not imported
- batch history and CSV report
- transaction rollback on injected failure
- same workbook twice creates zero new Customer
- Owner/Manager allowed; Cashier/guest rejected for every endpoint
- invalid extension/MIME/malformed workbook/formula handling

Regression tests cover existing Customer module, address behavior, sales customer selection, POS V3 customer selection, delivery/pricing zones, customer creation and authorization. PostgreSQL migration/concurrency/browser checks are run only when the configured test environment is available; no Production connection is used.

## Rollback and future deployment

The feature is isolated to additive migrations and new routes/services/views. Rollback before deployment is deleting the feature branch. After a future deployment, rollback means disabling routes/UI and rolling back the new migrations only if no imported data has been accepted; otherwise retain the tables and use a separately approved data-retention plan. No production migration/import/deploy is part of this task.

Future production plan requires Owner approval, a test database migration/upgrade rehearsal, a backup/restore check, review of real C2M preview counts, manual UI review, and an explicit production migration/import window. Production data must not be used in automated tests.

## Acceptance summary

The implementation is ready for Owner review only when preview is write-free, C2M tax continuation does not create a row, phone normalization is conservative, embedded phones are warnings, no zone is auto-assigned, existing customers are never updated, import is transactional and idempotent by external reference, history/report/permissions exist, focused/regression verification passes, and no production state is touched.
