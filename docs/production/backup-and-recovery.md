# Backup & Disaster Recovery (Architecture level)

> ไม่ระบุผู้ให้บริการ — ปลายทาง backup ต้องอยู่ **นอกเครื่อง/นอก data center เดียวกับ production** และเข้ารหัส

## 1. เป้าหมาย (ระยะแรก)

| ข้อมูล | RPO (ยอมเสียข้อมูลได้สูงสุด) | RTO (กลับมาใช้งานได้ภายใน) | เหตุผล |
|---|---|---|---|
| Database | **1 ชั่วโมง** | **4 ชั่วโมง** | เช็คชื่อ/รับ-ส่ง/สุขภาพ เกิดระหว่างวัน — เสียทั้งวันไม่ได้; DB เล็ก (< 1 GB) dump รายชั่วโมงทำได้ถูก |
| ไฟล์ภาพ | **24 ชั่วโมง** | **24 ชั่วโมง** | ภาพสำคัญรองจากข้อมูลบันทึก, ระบบใช้งานได้ด้วย placeholder ระหว่าง restore ภาพ |
| Config / Secrets | ทันที (อยู่ใน password manager + Coolify) | 1 ชั่วโมง | ไม่มี secret ต้อง recreate ไม่ได้ (โดยเฉพาะ `AUTH_PEPPER`, `encryption.key`) |
| Media Server config | เมื่อเปลี่ยน (เก็บใน repo ส่วนตัว/secret store) | 4 ชั่วโมง | ไม่มี state วิดีโอ (live only) |

ทำได้เมื่อ: ระบบใช้ในเวลาเรียน (ประมาณ 07:00–17:00) — RTO 4 ชม. ยังให้กลับมาทันช่วงรับ-ส่งถ้าเกิดเหตุตอนเช้า

## 2. Database Backup

| ชนิด | ความถี่ | เก็บ | วิธี |
|---|---|---|---|
| Logical dump (`mysqldump --single-transaction --routines`) | ทุก 1 ชม. (07:00–19:00), ทุก 4 ชม. นอกเวลา | 48 ชม. | Coolify scheduled backup หรือ cron container → บีบอัด + เข้ารหัส → offsite |
| Daily | 1 ครั้ง/วัน 02:00 | 30 วัน | เหมือนข้างบน |
| Weekly | อาทิตย์ | 12 สัปดาห์ | |
| Monthly | วันที่ 1 | 12 เดือน | |
| Pre-deploy snapshot | ก่อนทุก migration | 7 วัน | step บังคับใน deploy pipeline |

FUTURE: binlog shipping สำหรับ point-in-time recovery (RPO ≤ 5 นาที) เมื่อจำนวนโรงเรียนเพิ่ม

## 3. Image Backup

### Production Storage กับ Backup เป็นคนละ Policy

| | A. Production Storage | B. Backup |
|---|---|---|
| วัตถุประสงค์ | ภาพที่ผู้ใช้เปิดดูได้ตามสิทธิ์ | สำเนากู้ภัย (disaster recovery) — ผู้ใช้เข้าถึงไม่ได้ |
| อายุไฟล์ภาพ | **ไม่เกิน 6 เดือน** แล้วถูกลบอัตโนมัติ (ADR-006) | snapshot เก็บ **30 วัน** ตาม rotation ของ backup |
| ผู้ลบ | `media:cleanup-expired` (scheduled) | backup rotation (prune) |
| เข้าถึงโดย | แอปผ่าน authorization | ops เท่านั้น (key แยก, เข้ารหัส) |

ผลที่ต้องรับรู้: ภาพที่ Production ลบตาม retention แล้ว **อาจยังอยู่ในสำเนา backup ได้สูงสุด 30 วัน** แล้วหายไปตาม rotation
— เป็น Backup Retention ซึ่งแยกจาก User-facing Image Retention 6 เดือน (ADR-006)

### วิธีทำ
- Incremental, deduplicated (เช่น restic) ของ `storage/schools/` ทุกคืน **01:00** (ก่อน retention job 03:00) → offsite, เข้ารหัสด้วย key แยก
- เก็บ snapshot 30 วัน (prune อัตโนมัติ)
- ไม่ backup thumbnail (สร้างใหม่ได้จากภาพหลัก) — ลดขนาด ~10%
- `media_files.checksum_sha256` ใช้ตรวจความถูกต้องหลัง restore

## 4. Restore Procedures (Runbook ระดับหัวข้อ)

| เหตุการณ์ | ขั้นตอนหลัก |
|---|---|
| ข้อมูลผิดจากผู้ใช้ (ลบ/แก้ผิด) | ส่วนใหญ่แก้ได้จาก soft delete / audit / ledger reversal — **ไม่ restore ทั้ง DB**; ถ้าจำเป็น restore dump ลง DB ชั่วคราวแล้วคัดเฉพาะแถว |
| DB เสีย/ข้อมูลหาย | หยุด app (maintenance) → restore dump ล่าสุด → ตรวจ `migrations` version → เปิด app → แจ้งผู้ใช้ช่วงข้อมูลที่หาย (≤ 1 ชม.) |
| Volume ภาพเสีย | app ใช้งานต่อได้ (ภาพแสดง placeholder) → restore snapshot ล่าสุด **แบบคัดกรอง** (§4.1) → `php spark media:reconcile` (เทียบ checksum, รายงานไฟล์ขาด, ลบไฟล์ที่ไม่ควรอยู่) |
| เครื่อง production ใช้ไม่ได้ทั้งเครื่อง | เครื่องใหม่ใน Coolify → deploy image จาก GitHub tag ล่าสุด → ใส่ env จาก password manager → restore DB + ภาพ → ชี้ DNS |
| Secret รั่ว | rotate secret ที่เกี่ยวข้อง (DB password, stream token secret, SMTP) → redeploy → ตรวจ audit; ถ้า `AUTH_PEPPER` รั่ว → บังคับ reset PIN ทุกคน |

### 4.1 กติกา Restore ภาพ — ห้ามนำไฟล์ที่ครบ 6 เดือนกลับเข้า Production
1. **DB เป็นตัวตัดสิน**: restore เฉพาะไฟล์ที่ `media_files.status='available'` **และ** `expires_at > NOW()` (สร้าง include-list จาก DB ก่อน restore)
2. ไม่ restore ทั้ง snapshot ทับ storage แบบไม่คัดกรอง
3. หลัง restore รัน `media:cleanup-expired` ทันที แล้ว `media:reconcile` — ไฟล์ที่ไม่ควรอยู่ (record `deleted`/ครบกำหนด/ไม่มี record) ถูกลบ
4. แม้เกิดความผิดพลาด ภาพที่ครบกำหนดก็ **ไม่ถูกแสดง** เพราะกติกา viewable (`status='available' AND expires_at > NOW()`)
5. ถ้า restore DB ย้อนเวลา (record บางตัวกลับเป็น `available` ทั้งที่ไฟล์ถูกลบแล้ว) → job รอบถัดไปพบ "ไฟล์ไม่มี" = สำเร็จ → กลับเป็น `deleted` (self-healing)
6. บันทึก audit `MEDIA_RESTORE` (จำนวนไฟล์, snapshot id)

## 5. Verification
- **Restore drill ทุกไตรมาส** บน staging: restore DB + ภาพจาก backup จริง, วัดเวลาเทียบ RTO, บันทึกผล
- Monitor: แจ้งเตือนเมื่อ backup job ล้มเหลวหรือไม่มีไฟล์ใหม่เกิน 2 ชม. (DB) / 26 ชม. (ภาพ)
- Backup ไม่ถือว่ามีจนกว่าจะ restore สำเร็จอย่างน้อย 1 ครั้ง (ก่อน go-live)

## 6. Retention ของ Backup เทียบกับ Data Retention

| ข้อมูล | ในระบบ | ใน backup |
|---|---|---|
| Business data + record ของภาพ | ไม่ลบอัตโนมัติ | dump ตามตาราง §2 (สูงสุด 12 เดือน) |
| ไฟล์ภาพ | ไม่เกิน 6 เดือน — ลบอัตโนมัติ | snapshot 30 วัน (policy แยก) |
| Audit | ไม่ลบ | ตาม dump DB |

## 7. NOW vs FUTURE

| NOW | FUTURE |
|---|---|
| Hourly logical dump + daily/weekly/monthly rotation | Binlog PITR, replica ข้าม region |
| Nightly incremental ภาพ | Object storage versioning + replication |
| Restore drill รายไตรมาส | Automated restore test รายสัปดาห์ |
