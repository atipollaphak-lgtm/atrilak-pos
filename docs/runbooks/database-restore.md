# Database Restore Runbook

## 1. Preconditions

- ใช้เฉพาะ Owner บนเครื่องร้าน Windows/Laragon เท่านั้น
- ปิด POS และ Browser ทุกเครื่องที่เชื่อมระบบ
- หยุด Windows Task Scheduler task ที่เรียก `php artisan schedule:run`
- ตรวจพื้นที่ว่างให้เพียงพอสำหรับไฟล์ SQL, pre-restore backup และ staging file
- ตรวจไฟล์ SQL backup ที่จะใช้ และสำรอง `storage/app/public` ออกนอกเครื่อง
- เก็บ recovery copy ของ `.env` และ `APP_KEY` นอกเครื่องอย่างปลอดภัย
- ห้ามทดลองกับฐานข้อมูลจริง `atrilak_pos`

## 2. Rehearsal database

1. สร้างฐานทดสอบชื่อชัดเจน เช่น `atrilak_pos_restore_rehearsal_YYYYMMDD`
2. ตั้ง `.env` ชั่วคราวให้ `DB_DATABASE` ชี้ไปยังฐาน rehearsal เท่านั้น
3. ยืนยันว่าชื่อฐานไม่ใช่ `atrilak_pos`
4. ล้าง config cache ตามขั้นตอนของ Laravel ก่อนรันคำสั่ง
5. ตั้ง `BACKUP_RESTORE_ENABLED=true` ชั่วคราวเฉพาะ rehearsal

## 3. Restore command

คำสั่ง interactive:

```powershell
php artisan atrilak:restore "C:\path\to\backup.sql"
```

คำสั่ง non-interactive:

```powershell
php artisan atrilak:restore "C:\path\to\backup.sql" --confirm="RESTORE <database>"
```

คำสั่งสร้าง pre-restore backup อัตโนมัติทุกครั้ง และไม่มี option สำหรับข้ามขั้นตอนนี้

## 4. After success

1. ระบบยังอยู่ใน maintenance mode โดยตั้งใจ
2. ตรวจ `storage/logs` และจดชื่อ pre-restore backup
3. ตรวจฐานด้วยวิธี read-only และทำ login/smoke test เฉพาะ rehearsal environment
4. ตรวจ settings, products, stock และ sales ตัวอย่าง
5. ตรวจ logo และ QR จาก `storage/app/public`
6. เมื่อผ่านแล้วจึงรัน:

```powershell
php artisan up
```

7. เปิด Windows Task Scheduler task กลับ

## 5. Failure

- หากผลระบุ partial restore risk ห้ามรัน `php artisan up` ทันที
- จดชื่อ pre-restore backup และตรวจ `storage/logs`
- ใช้ pre-restore backup เป็น rollback source
- หาก PC ดับหรือ command timeout ให้ถือว่าฐานอาจถูกแก้ไขบางส่วน
- ห้ามรัน restore ซ้ำทันทีโดยไม่ตรวจสถานะและคู่มือ recovery

## 6. Live production policy

- ต้อง rehearsal ผ่านก่อนเสมอ
- ทำเฉพาะ maintenance window โดย Owner
- ต้องมี recovery copy ของ database backup, uploads และ `.env` นอกเครื่อง
- ต้องหยุด Scheduler และทราบขั้นตอน rollback
- Sprint 19B2B ยังไม่อนุมัติให้ restore ฐานจริงจนกว่า Sprint 19B3 rehearsal ผ่าน
