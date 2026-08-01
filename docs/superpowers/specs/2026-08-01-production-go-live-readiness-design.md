# ATRILAK POS Production Go-Live Readiness Design

วันที่: 2026-08-01  
ขอบเขต: Production Go-Live Readiness Sprint ทั้ง 7 โมดูล  
Environment สำหรับทดสอบ: `.env.testing` / PostgreSQL database `atrilak_pos_final_test_20260729`

## เป้าหมาย

ทำให้ workflow ที่กระทบยอดขาย เงิน สต็อก เอกสาร การปิดยอด และการสำรองข้อมูลพร้อมสำหรับ controlled go-live โดยใช้โครงสร้างเดิมของระบบและรักษา backward compatibility ของ POS V1/V2, POS V3, routes, request payloads และเอกสารเดิมเท่าที่ไม่จำเป็นต้องเปลี่ยน

ข้อมูลปัจจุบันเป็นข้อมูลทดสอบ จึงอนุญาตให้ reset หรือสร้าง fixture ใหม่ได้เฉพาะหลังยืนยันว่า runtime ใช้ Test Environment และ Test Database จริงเท่านั้น ห้ามกระทบ Production หรือ database อื่น

## หลักการที่ยึดถือ

- Backend เป็น source of truth; frontend ใช้สำหรับ preview และ UX เท่านั้น
- Sale, Payment, Purchase, Stock และ Daily Closing ใช้ transaction และ row locking ตามความเสี่ยง
- Stock เปลี่ยนผ่าน Stock Movement/Service ไม่แก้ balance โดยตรง
- ธุรกรรมที่ต้องตรวจสอบย้อนหลังใช้ status, reversal หรือ adjustment แทน hard delete
- ป้องกัน double submit และ duplicate transaction ด้วย revision/idempotency ที่มีอยู่หรือเติมเฉพาะจุดจำเป็น
- ไม่เปลี่ยน Average Cost, pricing/rounding, tier pricing, Profit Guard, delivery fee, technician commission, unit conversion, sale numbering หรือ stock movement semantics โดยไม่มีการอนุมัติกฎใหม่
- Purchase ต้องไม่เปลี่ยนราคาขายอัตโนมัติ
- Migration ใหม่ต้อง forward-safe และไม่แก้ migration เก่าที่อาจถูกรันแล้ว

## ขอบเขตและแนวทางแต่ละโมดูล

### 1. Logo และเอกสาร

ตรวจ `Setting`, upload/storage path, public URL, document service และ Blade templates ที่เกี่ยวข้องกับ A4/A5, invoice, delivery note, print และ reprint

แก้ให้ไฟล์โลโก้ถูกตรวจ existence, MIME และขนาดก่อนบันทึก; URL อิง disk/storage ที่ถูกต้อง; การไม่มีไฟล์ไม่สร้าง broken image; การเปลี่ยนโลโก้ลบเฉพาะไฟล์เก่าที่ไม่ถูกใช้งานแล้ว; ชื่อไฟล์พิเศษไม่ทำให้ path traversal หรือชนกับ QR/ไฟล์อื่น ระบบ QR เดิมต้องคงพฤติกรรมเดิมและต้องมี regression check

### 2. Sale Edit และ Sale Void

ใช้ `SaleService`, payment/stock services และ lifecycle fields เดิมเป็นจุดศูนย์กลาง ไม่ย้าย business logic ไป Controller หรือ JavaScript

การแก้บิลต้อง lock sale และ product rows ตามลำดับ Product ID ที่เรียงแล้ว, ตรวจ revision และคำนวณความต่างของสินค้า/หน่วย/ราคา/ส่วนลด/ค่าส่ง/โซนจาก snapshot เดิมอย่างชัดเจน Stock ต้องสร้าง movement ที่ตรวจสอบย้อนหลังได้และไม่ตัด/คืนซ้ำ

ยอดใหม่ที่สูงขึ้นต้องบันทึก payment เพิ่มอย่างชัดเจน ยอดใหม่ที่ต่ำลงต้องใช้ refund/adjustment ที่ระบบรองรับ หรือจำกัด workflow ไปเป็น void แล้วออกบิลใหม่ หากยังไม่มีโครงสร้าง refund ที่ปลอดภัย ห้ามลด payment เดิมแบบทำให้ประวัติหาย

การ void ต้อง idempotent, ต้องมีเหตุผล/ผู้ทำ/เวลา, คืน stock ครั้งเดียว, กลับ payment ตาม lifecycle เดิม, ไม่รวมใน report/profit/commission/closing และไม่อนุญาตแก้หรือ void ซ้ำ

### 3. Stock Adjustment

สำรวจ StockCount/StockMovement ที่มีอยู่และต่อยอด service/route/permission เดิมก่อนสร้างสิ่งใหม่

Workflow ต้องค้นหา product และ unit, แสดง system quantity, รับ actual quantity หรือ delta, แสดง before/after, บังคับเหตุผล/หมายเหตุที่เหมาะสม, บันทึกผู้ทำและเวลา, ตรวจ conversion/decimal/negative-stock policy และสร้าง movement ที่มี reference/audit trail เดียวต่อการบันทึกหนึ่งครั้ง

กำหนดสิทธิ์ตาม role เดิม โดยอย่างน้อย Cashier/Guest ถูกปฏิเสธ และ Manager/Owner ใช้งานได้ หากพบ policy เดิมที่ชัดเจนให้ยึด policy นั้น

### 4. Daily Closing

ใช้ `DailyPaymentClosing` และ relation เดิมเป็นฐาน ตรวจยอดจาก Payment/Sale ที่ไม่ voided และคงกฎเดิมเรื่องวันที่/ช่องทางชำระเงิน

เพิ่มหรือแก้การคำนวณ expected cash, actual cash, shortage/overage, cash change และ mixed payment ให้ใช้ decimal-safe arithmetic; ใช้ transaction/lock สำหรับ draft/finalize/reopen; กัน finalize ซ้ำและเก็บ revision/reopen audit ตาม schema เดิม

### 5. Purchase Receiving

ใช้ `PurchaseService` เดิม ตรวจ source เป็นผลิตเองหรือซื้อมา: supplier บังคับเฉพาะซื้อมา, unit conversion และ quantity ต้องถูกต้อง, stock movement ต้องสร้างครั้งเดียว, average cost ต้องรักษาตามกฎเดิม และ selling price ต้องไม่ถูกเขียนทับ

### 6. Backup และ Restore Readiness

ตรวจ `BackupController`, command/config/storage และ workflow สิทธิ์เดิม เพิ่มการตรวจ database identity, non-empty dump, checksum/manifest และ coverage ของ logo, QR, product images และไฟล์ธุรกิจที่ระบบใช้จริง

การ restore ต้องลง Test Database แยกเท่านั้น ใช้ข้อมูลทดสอบ และตรวจ schema/migration, counts และ sample references หลัง restore โดยไม่ restore ทับ source database

### 7. End-to-End Go-Live Verification

เพิ่มหรือปรับ regression tests สำหรับ purchase source/average cost/selling price, stock adjustment service/permission, sale edit stock/payment difference, void/payment idempotency, daily closing cash/shortage/overage/drift, logo fallback, backup coverage และ role access

ทดสอบ browser บน Test Environment ที่ 1280x720 ครอบคลุม Receive Stock, Pricing, POS pickup/delivery, mixed payment, hold bill, sale edit/void, stock adjustment, daily closing, documents, logo/QR พร้อมตรวจ console, network 500 และ Laravel log

## Data flow และความปลอดภัย

คำขอจาก UI ผ่าน Form Request/validation ที่มีอยู่ → Controller ทำ orchestration → Service ตรวจ business invariant ซ้ำภายใน transaction → lock rows ที่เกี่ยวข้อง → เขียน domain records และ audit movements/payments → commit → redirect/JSON response และ refresh UI

เมื่อพบ validation หรือ concurrency conflict ให้ rollback ทั้งชุดและตอบข้อความภาษาไทยที่เข้าใจได้ ห้ามบันทึก partial sale, payment, purchase, stock movement หรือ closing

## Migration และ compatibility

สร้าง migration ใหม่เฉพาะเมื่อ schema ที่มีอยู่ไม่พอ ใช้ nullable/default/backfill ที่ปลอดภัยกับข้อมูลเดิม และทดสอบทั้ง fresh และ upgrade path เมื่อมี migration ใน scope ไม่เปลี่ยนชื่อ route/field หรือรูปแบบเอกสารเดิมโดยไม่จำเป็น

## Verification และเกณฑ์สำเร็จ

ลำดับการพิสูจน์: targeted tests → related Feature tests → full suite ตามที่ runtime รองรับ → PHP syntax → Pint → `git diff --check` → browser smoke → backup/restore test บนฐานข้อมูลแยก

รายงาน baseline failures แยกจาก regression ใหม่ ห้ามอ้างว่างานผ่านจากการตรวจด้วยสายตาอย่างเดียว ต้องมีผลคำสั่งหรือหลักฐานแต่ละส่วน หาก Laragon/Apache/PostgreSQL หรือ browser test ยังไม่ทำงาน จะรายงานเป็นข้อจำกัดพร้อมคำสั่ง/ขั้นตอนที่ค้างอยู่

## ขอบเขตไฟล์และ Git

จะเปลี่ยนเฉพาะไฟล์ที่เกี่ยวข้องกับ 7 โมดูล, tests, migrations, configuration หรือเอกสาร verification ที่จำเป็น งาน untracked เดิม `design-qa.md` และ `docs/superpowers/plans/2026-07-29-final-pos-hold-bill.md` ต้องคงเดิมและไม่ถูก stage

เมื่อ implementation และ verification เสร็จ จะตรวจ branch/status/diff แล้ว commit งานใน scope ตามกติกาโปรเจกต์ โดยไม่ push, merge หรือเปลี่ยน branch เว้นแต่ได้รับคำสั่งแยก
