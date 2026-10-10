# Data Retention Policy

> **LOCKED REQUIREMENT (Final Decision — ADR-006)**
> **Physical image files are automatically deleted after 6 months. Database records are retained.**
> รูปภาพทุกประเภทเก็บไฟล์จริงไม่เกิน 6 เดือน เมื่อครบกำหนด **ระบบลบไฟล์จริงออกจาก Storage อัตโนมัติ**
> แต่เก็บ Database Record ของรูปภาพและรายการธุรกิจไว้ตลอด — **ห้ามลบ Database Record อัตโนมัติ**

## 1. Policy

| ข้อมูล | เก็บนานเท่าไร | ลบอย่างไร |
|---|---|---|
| Business data (นักเรียน, กิจกรรม, เมนู, เช็คชื่อ, การนอน, สุขภาพ, ผลงาน, ดาว, ปฏิทิน, แชท, แจ้งเตือน, รับ-ส่ง, กล้อง) | ตามอายุระบบ | **ไม่ลบอัตโนมัติ**; ผู้ใช้ "ลบ" = soft delete (`deleted_at`) |
| `media_files` (record ของรูปภาพ) | ตามอายุระบบ | **ไม่ลบ** — เปลี่ยน `status` เป็น `deleted` เท่านั้น |
| ไฟล์ภาพจริง + thumbnail ใน Production Storage | **สูงสุด 6 เดือน** นับจาก `uploaded_at` | **Automatic Scheduled Job** `media:cleanup-expired` (§4) |
| Audit log | ตามอายุระบบ | ไม่ลบ (FUTURE: archive รายปี) |
| Session / remember token | หมดอายุตาม config | CI4 GC + `auth:prune-tokens` (ไม่ใช่ business data) |
| Application log | 14 วัน | log rotation |
| Backup (DB / ภาพ) | **Policy แยก** — ดู [backup-and-recovery.md](backup-and-recovery.md) | backup rotation ของตัวเอง |

### รูปภาพที่อยู่ภายใต้กฎนี้
- Student Portfolio Images (`category = portfolio`)
- Activity Images (`category = activity`)
- Food Menu Images (`category = food`)
- รูปภาพประเภทอื่นที่เป็น user-generated / school-generated media ที่จะเพิ่มในอนาคต — **ค่าเริ่มต้นคืออยู่ภายใต้กฎนี้เสมอ**
- รูปโปรไฟล์นักเรียน: อยู่ภายใต้กฎนี้ (6 เดือน) จนกว่าลูกค้าจะยืนยันเป็นอย่างอื่น —
  **PENDING BUSINESS CONFIRMATION** (decision-log P7) ถ้าอนุมัติข้อยกเว้น การเปลี่ยนแปลงจำกัดอยู่ที่การคำนวณ `expires_at` ของ category นั้น

ไม่มีข้อยกเว้นที่ทำให้ไฟล์ภาพอยู่เกิน 6 เดือนใน Production Storage

## 2. Image Status Model

เลือกโมเดลที่เรียบง่าย **2 สถานะ** (ไม่มี `expired`/`purged` แยก):

| `status` | ความหมาย | ไฟล์จริง | Record |
|---|---|---|---|
| `available` | ไฟล์จริงยังอยู่ใน Storage — เปิดดูได้ตามสิทธิ์ **ถ้า** `expires_at > NOW()` | มี | มี |
| `deleted` | ไฟล์จริงถูกลบแล้ว (`deleted_reason` บอกสาเหตุ) | ไม่มี | **มี** + `deleted_at`, `deleted_reason`, `deleted_by` |

| `deleted_reason` | เมื่อ | `deleted_by` |
|---|---|---|
| `retention` | Scheduled job ลบเมื่อครบ 6 เดือน | `NULL` (= system) |
| `user` | ครู/Admin ลบรูปเองก่อนครบกำหนด (ใส่ผิด) | user id |

**กติกาการแสดงผล (กันช่องว่างระหว่าง "ครบกำหนด" กับ "job รัน"):**

```
viewable = (status = 'available') AND (expires_at > NOW())
```
- ถ้า `viewable = false` → UI แสดง placeholder ทันที ไม่ว่า job จะรันแล้วหรือยัง
- **ไม่สร้าง URL ของไฟล์** ให้ภาพที่ไม่ viewable (view render placeholder โดยตรง ไม่ใช่ `<img src="/media/...">` ที่จะ 404)
- ถ้ามีคนเรียก `/media/{public_id}` ของภาพที่ไม่ viewable → ตอบ placeholder SVG (`<img>`) หรือ `410 MEDIA_EXPIRED` (API) **โดยไม่พยายามเปิดไฟล์**
- ข้อความ placeholder: **"รูปภาพหมดอายุการจัดเก็บ"** (retention) / ไม่แสดงในรายการ (user delete)
- ไม่มี broken image ในทุกกรณี

## 3. Lifecycle Flow

```
Upload
  ↓  (ตรวจสิทธิ์ + ตรวจไฟล์ + ย่อ + ลบ EXIF)
Physical file → Private Storage   schools/{school_id}/{category}/{yyyy}/{mm}/{ulid}.jpg (+ _t.webp)
  ↓
INSERT media_files  status='available', uploaded_at=NOW(), expires_at = uploaded_at + 6 months
  ↓
ยังไม่ครบ 6 เดือน ── แสดงรูปตามสิทธิ์ (Policy → X-Accel-Redirect)
  ↓
ครบ 6 เดือน (expires_at <= NOW())
  ├─ UI: แสดง placeholder ทันที (กติกา viewable)
  └─ Scheduler (วันละครั้ง): media:cleanup-expired
        → ลบ physical file + thumbnail
        → UPDATE status='deleted', deleted_reason='retention', deleted_at=NOW()
        → Audit MEDIA_RETENTION_DELETE (executor = system)
        → media_files record + business record (ผลงาน/กิจกรรม/เมนู) ยังอยู่ครบ
  ↓
ผู้ใช้เปิดรายการย้อนหลัง → ผลงาน/กิจกรรมแสดงครบ (ชื่อ, หมวด, ความเห็นครู) + placeholder "รูปภาพหมดอายุการจัดเก็บ"
```

## 4. Automatic Cleanup Job — `php spark media:cleanup-expired`

### 4.1 การเรียกใช้
- Scheduler container (image เดียวกับ app) รัน cron **วันละครั้ง 03:00** (หลัง backup ภาพ 01:00 — ดู backup policy)
- ไม่ผูกกับ cron: command เป็น CLI ปกติ เรียกจาก Coolify Scheduled Task หรือ scheduler อื่นได้
- **Single-run lock**: `GET_LOCK('mykid_media_cleanup', 0)` ของ MySQL — ถ้ามีรอบอื่นทำงานอยู่ จะออกทันทีพร้อม log (กันรันซ้อนเมื่อมีหลาย container)
- ตัวเลือก: `--limit=N` (จำนวนสูงสุดต่อรอบ, ค่าเริ่มต้น 5,000), `--school=ID`, `--dry-run` (รายงานอย่างเดียว)

### 4.2 Algorithm (ต่อไฟล์ — แต่ละไฟล์เป็นงานอิสระ)

```
SELECT id, school_id, disk, storage_path, thumb_path
FROM media_files
WHERE status = 'available' AND expires_at <= NOW()
ORDER BY expires_at, id
LIMIT 500                       -- batch; วนจนครบ --limit หรือหมดรายการ
(index: ix_media_status_exp (status, expires_at))

for each row:
  1. ตรวจสถานะซ้ำ (row ยัง 'available'?)                 — ถ้าไม่ → ข้าม (มีคนทำไปแล้ว)
  2. storage.delete(thumb_path)   ไม่พบไฟล์ = ถือว่าสำเร็จ
  3. storage.delete(storage_path) ไม่พบไฟล์ = ถือว่าสำเร็จ
     └─ error อื่น (permission / IO / storage ไม่ตอบ) → บันทึกความล้มเหลว (§5) → ไปไฟล์ถัดไป
  4. UPDATE media_files
       SET status='deleted', deleted_reason='retention', deleted_at=NOW(), deleted_by=NULL,
           cleanup_last_error=NULL
     WHERE id=:id AND status='available'                   — conditional = ปลอดภัยต่อการรันซ้ำ/แข่งกัน
  5. INSERT audit_logs (action='MEDIA_RETENTION_DELETE', result='success', executor=system)
  6. ห้าม DELETE FROM ใด ๆ — media record และ business record ไม่ถูกแตะ
```

- **ลำดับ "ลบไฟล์ก่อน แล้วค่อย update DB"** → DB จะไม่บอกว่า "ลบแล้ว" ขณะที่ไฟล์ยังอยู่
- ทุกไฟล์อยู่ใน `try/catch` ของตัวเอง — ไฟล์หนึ่งล้มเหลว **ไม่หยุด** ไฟล์อื่น
- จบรอบ: audit สรุป `MEDIA_RETENTION_RUN` `{selected, deleted, missing_file, failed, bytes_freed, duration_ms}` + log `[MediaRetention][cleanupExpired] END`
- Exit code ≠ 0 เมื่อมี `failed > 0` → scheduler/monitor แจ้งเตือนได้

### 4.3 Idempotency
| สถานการณ์ | ผลเมื่อรันซ้ำ |
|---|---|
| รันซ้ำหลังรอบที่สำเร็จ | ไม่มีแถว `available` ที่หมดอายุ → ไม่ทำอะไร |
| รันซ้อนกันสองรอบ | รอบที่สองได้ lock ไม่ได้ → ออก; ถ้า lock ใช้ไม่ได้ conditional UPDATE ยังกันการนับซ้ำ |
| ไฟล์หายไปก่อนแล้ว (ลบมือ / restore ไม่ครบ) | `delete` คืน "not found" = สำเร็จ → update DB ตามปกติ (`metadata.file_missing=true`) |

## 5. Failure Handling

คอลัมน์ติดตามใน `media_files`: `cleanup_attempts`, `cleanup_last_attempt_at`, `cleanup_last_error` (error **code** เท่านั้น เช่น `PERMISSION_DENIED`, `STORAGE_UNAVAILABLE`, `DB_UPDATE_FAILED`)

| กรณี | การจัดการ | ผลลัพธ์ |
|---|---|---|
| **File ไม่พบ** | ถือว่าลบสำเร็จ (เป้าหมายคือไม่มีไฟล์) → update DB, audit `success` + `file_missing=true` | record = `deleted` |
| **Permission error** | ไม่ update status, `cleanup_attempts++`, `cleanup_last_error='PERMISSION_DENIED'`, audit `failed` | ลองใหม่รอบถัดไปอัตโนมัติ; UI ยังแสดง placeholder (viewable=false) |
| **Storage error / storage ไม่ตอบ** | เหมือนข้างบน (`STORAGE_UNAVAILABLE`); ถ้าล้มเหลวติดกัน 20 ไฟล์แรก → หยุดรอบ (circuit breaker) เพื่อไม่ทำงานเปล่า, exit ≠ 0 | ลองใหม่รอบถัดไป |
| **ลบไฟล์สำเร็จ แต่ DB update ล้มเหลว** | log error + audit `failed` (`DB_UPDATE_FAILED`) — record ยัง `available` | รอบถัดไปพบไฟล์ "ไม่มีแล้ว" → ถือว่าสำเร็จ → update DB (self-healing); ระหว่างนั้นไม่มีภาพรั่วเพราะ viewable=false |
| **DB update สำเร็จ แต่ไฟล์ลบไม่สำเร็จ** | **ป้องกันโดยลำดับขั้น** (update หลังลบสำเร็จเท่านั้น) — อาจเกิดได้จากการแทรกแซงภายนอก (เช่น restore backup ทับ) → `media:reconcile` (§6) หาไฟล์ที่ record เป็น `deleted` แต่ไฟล์ยังอยู่ แล้วลบ | ไม่มีไฟล์เกินกำหนดค้าง |
| **Job ทำงานซ้ำ / ซ้อน** | lock + conditional update (§4.3) | ไม่มีผลข้างเคียง |
| **ไฟล์จำนวนมาก** (เช่น ระบบหยุดหลายวัน) | batch 500, `--limit` ต่อรอบ, ORDER BY `expires_at` (เก่าสุดก่อน), ใช้ index; ถ้าเหลือ รอบถัดไปทำต่อ (หรือรัน manual ซ้ำได้ทันที) | ไม่มี long transaction, ไม่ล็อกตาราง |
| ล้มเหลวซ้ำ ≥ 3 รอบ | ขึ้นรายการ "ลบไม่สำเร็จ" ในหน้า Storage ของ Super Admin + แจ้งเตือน ops | ตรวจสอบรายการได้ (§7) |

## 6. Reconciliation — `php spark media:reconcile` (รายสัปดาห์)
- เดินไฟล์ใน `schools/*/…` เทียบกับ `media_files`:
  - ไฟล์ที่ record เป็น `deleted` หรือหมดอายุ → ลบ (กันไฟล์เกินกำหนดหลง เช่น หลัง restore)
  - ไฟล์ที่ไม่มี record (orphan จาก upload ที่ล้มกลางทาง) อายุ > 24 ชม. → ลบ
  - record `available` ที่ไฟล์หาย (ยังไม่หมดอายุ) → รายงาน (ไม่แก้ status อัตโนมัติ — ให้ ops ตรวจ)
- ไม่ลบ record ใด ๆ; audit `MEDIA_RECONCILE` พร้อมจำนวน

## 7. Visibility สำหรับผู้ดูแล (Admin Storage Management)
หน้า "จัดการพื้นที่จัดเก็บ" เปลี่ยนจาก "ปุ่มลบไฟล์" (Demo) เป็น **หน้าติดตาม**:
- พื้นที่ใช้ของโรงเรียน, จำนวนภาพ `available`, ภาพที่จะครบ 6 เดือนใน 30 วันข้างหน้า
- ผลการ cleanup ล่าสุด (วันที่, จำนวนที่ลบ, พื้นที่ที่คืน)
- รายการลบไม่สำเร็จ (`cleanup_last_error IS NOT NULL`) — Admin โรงเรียนเห็นของตน, Super Admin เห็นทุกโรงเรียน
- **ไม่มีปุ่มให้ Admin ต้องกดลบตาม retention** (ระบบทำเอง); การลบรูปรายรูป (`deleted_reason='user'`) ยังทำได้จากหน้าของ owner ตามสิทธิ์

## 8. Soft delete (ข้อมูลที่ผู้ใช้ลบเอง)
- ตาราง: schools, classrooms, students, users (ใช้ `status=disabled`), activities, portfolios, school_events, messages, notifications, cameras
- ไม่มี hard delete ผ่าน UI; **ไม่มี `ON DELETE CASCADE`** ในระบบ (การลบรูปไม่มีทางทำให้ business record หาย และกลับกัน)
- การลบข้อมูลจริงตามคำขอ PDPA เป็นกระบวนการ manual ของ Super Admin + audit (FUTURE: เครื่องมือ data subject request)
- Daily records แก้ไขได้ ไม่ลบ; Ledger/log (stars, pickup logs, audit) append-only
