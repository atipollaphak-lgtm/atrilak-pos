# ATRILAK POS Architecture Audit

วันที่ตรวจ: 2026-07-13

## ขอบเขต

ตรวจโครงสร้างโปรเจกต์แบบ read-only ครอบคลุม routes, controllers, services,
models, migrations, Blade/JavaScript, console commands และ tests โดยไม่มีการแก้ไข
business logic

## ภาพรวม

- Laravel 13 / PHP 8.3 / PostgreSQL
- 182 routes, 40 controllers, 17 services, 29 models และ 68 migrations
- Frontend ใช้ Blade, AdminLTE และ JavaScript แบบ global functions
- โครงสร้างเป็น modular monolith แต่ business logic ยังกระจายระหว่าง Controller
  และ Service
- `products.stock_qty` เป็นยอดสต๊อกปัจจุบัน และ `stock_movements` เป็นประวัติ
  การเคลื่อนไหว

## โมดูลหลัก

- Authentication, users และ role hierarchy: cashier, manager, owner
- Categories, products, units, product units, barcodes และ price tiers
- Customers, delivery addresses และ delivery zones
- Suppliers, purchases และ automatic pricing จาก average cost
- POS เดิม, POS V2, sales, invoice และ commercial documents
- Stock movements และ stock counts
- Quotations และ conversion เป็น sale
- Technicians, commission rules และ payment batches
- Dashboard, reports, settings และ database backup/restore

Promotion, reservation และ stock page เดิมยังอยู่ในระดับโครงหรือยังไม่ได้เชื่อมกับ
business flow หลักครบถ้วน

## เส้นทางขายและสต๊อก

การสร้างบิลจาก POS ผ่าน `SaleService` ภายใน database transaction ตามลำดับ:

1. สร้างเลขบิล
2. หา delivery fee และ minimum profit
3. สร้าง sale และ sale items พร้อม snapshot ต้นทุน/กำไร
4. ตรวจ minimum profit
5. ลด `products.stock_qty` และสร้าง stock movement แบบ OUT
6. สร้าง technician commission

การรับสินค้าเพิ่ม stock, คำนวณ weighted average cost, อาจปรับราคาขายอัตโนมัติ
และสร้าง stock movement แบบ IN ส่วน stock count สร้าง ADJUST movement และเขียนยอด
ตรวจนับกลับไปยังสินค้า

เส้นทางแก้ไข/ลบ Sale และ Purchase รวมถึง Quotation conversion ยังไม่ได้ใช้
orchestration เดียวกับการสร้าง Sale ใหม่

## ความเสี่ยงสำคัญ

1. Fresh migration ล้มเหลว เพราะเพิ่ม `delivery_zones.sort_order` ซ้ำ
2. Multi-unit บันทึก `product_unit_id` แต่ยังไม่ใช้ `conversion_rate` ตอนตรวจและตัด stock
3. ราคาขายและจำนวนจาก POS ถูกส่งจาก browser โดย server ไม่คำนวณ price tier ใหม่
4. ไม่มี row lock สำหรับ stock และ running number จึงเสี่ยง race condition
5. Quotation conversion ไม่ตรวจ stock, status หรือการแปลงซ้ำ และข้าม SaleService
6. การแก้ไข/ลบ Sale และ Purchase ไม่ได้ครอบด้วย transaction ทั้งกระบวนการ
7. Commission ไม่ถูก reconcile เมื่อแก้ไขบิล
8. Profit guard ไม่หักส่วนลดท้ายบิล และ free-delivery threshold ยังไม่ถูกใช้
9. Stock count แปลงจำนวนเป็น integer แม้ schema ส่วนอื่นรองรับ decimal
10. ProductScheduledPrice model ไม่ตรงกับ columns ใน migration
11. Payment มีทั้ง direct-pay และ batch workflow และ error path หนึ่งใช้ `dd()`
12. นิยามกำไรระหว่าง Sale, dashboard และ reports ไม่เป็นฐานเดียวกัน

## โค้ดซ้ำที่พบ

- การโหลดข้อมูลและแปลงรายการระหว่าง POS เดิมกับ POS V2
- การเพิ่ม/คืน/ตัด stock ใน Sale, Purchase, Quotation และ Stock Count
- Validation และ CRUD flow ของ price tiers
- Daily/monthly/yearly reports และ CSV exports
- Backup logic ใน Controller และ console commands สองชุด
- Direct technician payment กับ payment batch
- POS V2 โหลดทั้ง modular scripts และ monolithic `pos-v2.js`
- `PurchaseService.php` และ delivery-zone form เป็นไฟล์ว่าง
- Report placeholder สองไฟล์เหมือนกันแบบ byte-for-byte

## ผลการตรวจอัตโนมัติ

- PHP syntax lint ผ่านทุกไฟล์
- PHPUnit พบ 25 tests
- ผ่าน 2 tests และ error 23 tests
- Error ทั้ง 23 เกิดระหว่าง migration จาก duplicate column `sort_order` ก่อนเข้าสู่
  business tests
- ชุด tests ปัจจุบันครอบคลุมหลัก ๆ เฉพาะ Laravel authentication/profile

## Tests ที่ควรเพิ่มก่อน

- Fresh migration บน PostgreSQL และ SQLite
- Role/authorization matrix
- Sale create/edit/delete และ transaction rollback
- Stock concurrency, duplicate product lines และ running number concurrency
- Multi-unit conversion และ server-side pricing
- Discount/minimum-profit/delivery rules
- Quotation conversion idempotency
- Purchase average cost และ edit/delete atomicity
- Stock count ที่เป็น decimal
- Commission reconciliation และ duplicate payment protection
- Report reconciliation ระหว่างยอดขาย ต้นทุน กำไร ส่วนลด และค่าส่ง

## แผนงานโดยไม่เปลี่ยน business logic

1. เขียน characterization tests เพื่อตรึงพฤติกรรมปัจจุบัน
2. ซ่อม migration chain และเพิ่ม schema smoke test
3. ยืนยัน stock, pricing, discount, delivery และ commission invariants กับเจ้าของระบบ
4. รวม stock mutation ไว้ใน service เดียวพร้อม transaction และ row locks
5. รวม Sale create/edit/delete และ Quotation conversion ให้ใช้ orchestration เดียว
6. แยก validation เป็น Form Requests และเพิ่ม nested-resource ownership checks
7. ทำ server-side pricing และ multi-unit conversion หลังยืนยัน business rules
8. รวม payment/report definitions แล้วลด dead code และโค้ดซ้ำภายใต้ regression tests

เอกสารนี้เป็นผลการตรวจและข้อเสนอเท่านั้น ไม่มีการแก้ไข business logic
