# ATRILAK POS Excel Bulk Product Import Design

## Scope

เพิ่มสินค้าใหม่จาก `.xlsx` สูงสุด 500 แถว ผ่าน Template, Upload, Preview, Row Validation, Confirm, Error Report และ Result Summary. ฟังก์ชันนี้ไม่แก้ไขสินค้าเดิมและไม่รองรับรูปภาพ, หลายหน่วย, Tier Price, CSV, Google Sheets, API, queue หรือ Production deployment.

## Existing Product Rules to Reuse

- Product routes อยู่ใน authenticated `role:manager` group; import ใช้ขอบเขตสิทธิ์เดียวกัน
- `ProductNumberService` เป็นเจ้าของการสร้าง `product_code` และ EAN-13 barcode จาก Category prefix
- `ProductUnitService::createOrUpdateBaseUnit()` เป็นเจ้าของการสร้าง base Product Unit
- Selling price ต้องเป็นค่าจาก Excel โดยตรง; ไม่เรียก Pricing Engine, Category Pricing หรือ auto pricing
- Opening stock เป็น base-unit quantity; เมื่อมากกว่า 0 ต้องสร้าง Stock Movement ก่อน/พร้อมการอัปเดต Product stock ใน transaction เดียว

## Components

### Controller and Requests

`ProductImportController` จะทำเฉพาะ HTTP orchestration: แสดงหน้า, รับไฟล์, เรียก validation/storage/import services และส่ง download response. Request classes ตรวจชนิดไฟล์, token shape และ authorization ที่ระดับ request; route ยังคงใช้ `auth` และ `role:manager` เดิม.

### Validation and Preview

`ProductImportValidationService` โหลด workbook ด้วย PhpSpreadsheet และอ่าน sheet หลักชื่อ `สินค้า`. Header ต้องตรงกับชื่อ Template หลัง trim ช่องว่าง; header ที่หาย/เกิน/ซ้ำ reject ทั้งไฟล์. Formula cells reject. Row validator จะ normalize string/decimal/boolean, resolve เฉพาะ Category และ Unit ที่ active, ตรวจ duplicate ภายในไฟล์และ duplicate กับ database ของ code/barcode/name ตาม import rule, และคืน error ที่ผูกกับ row/column.

Preview จะไม่เขียน Product data. `ProductImportStorageService` เก็บ normalized rows, original rows, errors, filename, hash, user ID, state และ expiry ใน cache key ที่มี random token. Token มีอายุ 30 นาที, ใช้ได้เฉพาะเจ้าของ, และใช้ Confirm ได้ครั้งเดียว.

### Template and Error Report

`ProductImportTemplateService` สร้าง workbook ด้วย sheets `สินค้า`, `หมวดหมู่`, `หน่วย`, `คำแนะนำ`. Sheet หลักมี headers ภาษาไทยตามลำดับที่กำหนด; barcode เป็น text. Category/Unit sheets แสดงเฉพาะ reference ที่ active. Error report ใช้ข้อมูลแถวเดิมและเพิ่มสถานะ/ข้อผิดพลาด โดย escape ค่าเริ่มต้นที่อาจถูก spreadsheet ตีความเป็นสูตร.

### Confirm Transaction

Confirm ใช้ cache lock ต่อ token เพื่อกัน double submit จาก request พร้อมกัน. หลังตรวจ owner/expiry/state จะ re-check references และ uniqueness ที่สำคัญ แล้วเปิด `DB::transaction()` เดียว. แต่ละแถวจะสร้าง Product ผ่าน number service, base unit ผ่าน unit service, default barcode ตามโครงสร้างเดิม และ opening stock movement เมื่อจำนวนมากกว่า 0; stock จะถูกอัปเดตใน transaction. ถ้ามี exception ทุก Product, Product Unit, Barcode และ Movement จะ rollback และ token จะยัง pending. Mark used ทำหลัง transaction สำเร็จเท่านั้น.

## User Flow

1. ผู้ใช้ที่มีสิทธิ์จัดการสินค้าเปิดหน้า Products และกด `นำเข้าจาก Excel`
2. ดาวน์โหลด Template หรืออัปโหลด `.xlsx`
3. ระบบตรวจไฟล์และแสดงสรุป valid/error พร้อมตาราง preview
4. ถ้ามี error ผู้ใช้ดาวน์โหลด Error Report และแก้ไฟล์
5. ถ้าทุกแถวผ่าน ผู้ใช้กด Confirm
6. ระบบแสดงจำนวน Product และ Opening Stock Movement ที่สร้าง พร้อมลิงก์กลับรายการสินค้า

## Failure and Security Handling

- Temporary workbook/payload ไม่เก็บใน public directory
- ตรวจ extension, MIME, size, sheet, row count, formula และ parse errors
- Escape ชื่อ/รายละเอียดใน HTML และป้องกัน spreadsheet formula injection ใน report/template
- Token ของ user อื่น, token หมดอายุ, token ถูกใช้แล้ว หรือ token ไม่พบ ต้องไม่สร้างข้อมูล
- ไม่แสดง stack trace หรือข้อมูลลับแก่ผู้ใช้; log เฉพาะ user/token/file hash/row count/result/duration/error summary
- ไม่สร้าง Category/Unit ใหม่ และไม่ upsert Product เดิม

## Verification

- PHPUnit Feature/Unit: template, parser/header, row validation, duplicate detection, preview no-write, valid confirm, stock movement, rollback, idempotency, authorization and route/browser contract
- PHP syntax, Pint และ `git diff --check`
- Browser smoke บน development/test เท่านั้น
- PostgreSQL verification ใช้ test database ที่ระบุชัดเจนเท่านั้น; ไม่รัน migration หรือ diagnostic write บน Production

## Explicit Non-Goals

แก้ไขสินค้าเดิมจาก Excel, images, multiple units per product, multiple barcodes, tier pricing, scheduled pricing, suppliers/purchases, new categories/units, CSV, Google Sheets, API, background queue, imports over 500 rows และ Production deploy.

