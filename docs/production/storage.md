# Storage Architecture

> **Physical image files are automatically deleted after 6 months. Database records are retained.** (LOCKED — ADR-006)

> Retention: [data-retention.md](data-retention.md) · Upload security: [security-model.md §8](security-model.md#8-file-upload-security-ต่อยอดกติกา-demo) · ตาราง `media_files`: [database.md §12.1](database.md#121-media_files)

## 1. แนวคิด

```
MediaService (กติกาธุรกิจ: สิทธิ์, retention, owner, audit)
      │ ใช้
      ▼
StorageInterface  ◄── เลือก driver จาก config `STORAGE_DISK`
   ├── LocalStorage        NOW   : volume /var/lib/mykid/storage (private)
   └── S3Storage           FUTURE: S3-compatible bucket (private) + presigned URL
```

Business code **ไม่รู้** ว่าไฟล์อยู่ที่ไหน — รู้แค่ `disk` + `storage_path` ใน `media_files`

### StorageInterface (สัญญา — ไม่ใช่โค้ดจริง)

| Method | หน้าที่ |
|---|---|
| `put(string $path, resource $stream, array $meta): void` | เขียนไฟล์ (atomic: เขียน temp แล้ว rename) |
| `exists(string $path): bool` | |
| `size(string $path): int` | |
| `delete(string $path): DeleteResult` | ลบไฟล์จริง — คืน `deleted` / `not_found` (ถือว่าสำเร็จ) / `failed(code)`; ใช้โดย retention job และการลบรายรูปเท่านั้น |
| `readStream(string $path): resource` | ใช้ตอน backup/ย้าย disk |
| `deliver(string $path, DeliveryOptions $o): ResponseInterface` | Local: ตอบ `X-Accel-Redirect`; S3: redirect 302 ไป presigned URL อายุสั้น |
| `usage(string $prefix): int` | ขนาดรวมต่อโรงเรียน (หน้า Storage) — คำนวณจาก DB เป็นหลัก ใช้ตัวนี้ตรวจสอบเท่านั้น |

## 2. File Naming & Directory Structure

```
/var/lib/mykid/storage/                         ← STORAGE_LOCAL_ROOT (volume, นอก webroot)
└── schools/
    └── {school_id}/
        └── {category}/                         ← activity | food | portfolio | student_avatar
            └── {yyyy}/{mm}/
                ├── {ulid}.jpg                  ← ภาพหลัก (≤1600px, EXIF ถูกลบ)
                └── {ulid}_t.webp               ← thumbnail 400px
```

- `{ulid}` = `media_files.public_id` → ไม่ซ้ำ, เรียงตามเวลา, เดาไม่ได้, ไม่มีชื่อเด็ก/ชื่อไฟล์เดิม
- แยกตามโรงเรียนก่อน → สำรอง/ย้าย/ลบ/คำนวณพื้นที่ต่อโรงเรียนได้ง่าย (และตรงกับ prefix ของ object storage อนาคต)
- แยกปี/เดือน → backup แบบ incremental และตรวจ reconcile ทำง่าย, directory ไม่ใหญ่เกิน (การเลือกไฟล์ที่จะลบใช้ `expires_at` ใน DB ไม่ใช่ชื่อโฟลเดอร์)
- `storage_path` ใน DB เป็น relative เสมอ (`schools/1/portfolio/2026/10/01J….jpg`) → เปลี่ยน root/driver ได้

## 3. Access Control (Private by default)

| ประเภทไฟล์ | Public? | วิธีส่ง |
|---|---|---|
| ภาพนักเรียน (portfolio, avatar) | **ไม่** | `/media/{public_id}` → policy → `X-Accel-Redirect` |
| ภาพกิจกรรม / อาหาร | **ไม่** (มีเด็กอยู่ในภาพ) | เหมือนข้างบน |
| Thumbnail | **ไม่** | `/media/{public_id}/thumb` |
| CSS / JS / font / icon ของระบบ | ใช่ | nginx static `/assets` + cache ยาว + fingerprint |
| Placeholder "รูปภาพหมดอายุการจัดเก็บ" | ใช่ (ไม่มีข้อมูลเด็ก) | `/assets/img/media-expired.svg` — view render โดยตรงเมื่อภาพไม่ viewable |

### Flow การส่งไฟล์ (Local)

```
GET /media/01J9…  (cookie session)
  → AuthFilter → MediaController::show()
  → MediaService::resolveForView(ctx, publicId)
       - โหลด media_files + owner, MediaPolicy::view() ไม่ผ่าน → 404
       - ไม่ viewable (status='deleted' หรือ expires_at <= NOW()) → placeholder SVG (img) หรือ 410 (API)
         **โดยไม่เปิด/stat ไฟล์** (ไฟล์อาจถูกลบไปแล้ว)
  → response header: X-Accel-Redirect: /_protected/schools/1/portfolio/2026/10/01J9….jpg
                      Content-Type: image/jpeg, Cache-Control: private, max-age=300
  → nginx: location /_protected/ { internal; alias /var/lib/mykid/storage/; }
```

ข้อดี: PHP ตัดสินสิทธิ์อย่างเดียว ไม่ต้องอ่านไฟล์เอง (ประหยัด memory/worker), URL ใช้ซ้ำนอก session ไม่ได้

### Object Storage (FUTURE)
- Bucket **private** (ไม่มี public-read), ไม่มี listing
- `deliver()` = ตรวจสิทธิ์แล้ว redirect 302 → presigned GET URL อายุ **5 นาที**, `response-cache-control=private`
- ข้อจำกัด: URL ที่ถูก copy ไปใช้ได้จนหมดอายุ (≤ 5 นาที) — ยอมรับได้; ถ้าไม่ยอมรับ ให้ proxy ผ่าน PHP/nginx แทน
- Server-side encryption (SSE) เปิดไว้; lifecycle rule ของ bucket ใช้เป็นตาข่ายสำรองเท่านั้น (DB เป็นตัวตัดสิน retention)
- ย้ายข้อมูล: command `media:migrate-disk local s3 --school=1` อ่าน `readStream` → `put` → update `disk` ทีละไฟล์ + checksum

## 4. Image Pipeline (synchronous ใน MVP)

1. รับไฟล์ (ผ่าน security checks ใน security-model §8)
2. decode → ย่อด้าน​ยาวสุด 1600px → encode JPEG q82 / WebP (PNG ที่ไม่มี alpha แปลงเป็น JPEG)
3. thumbnail 400px WebP
4. `put()` ทั้งสองไฟล์ → insert `media_files` (status `available`, `expires_at = uploaded_at + 6 months`) + join table
   (ถ้า insert DB ล้มเหลว → ลบไฟล์ที่เพิ่งเขียนทันที; ที่หลุดรอดจะถูก `media:reconcile` เก็บกวาด)
5. ใช้เวลาโดยประมาณ < 1 วินาที/ภาพบน VM 2 vCPU — ถ้า p95 ของการอัปโหลด 6 ภาพเกิน 3 วินาที → FUTURE: queue

## 5. Automatic Expiration & Cleanup
- ทุกภาพ: `expires_at = uploaded_at + 6 months` (ไม่มี NULL)
- **ภาพที่เปิดดูได้** = `status='available' AND expires_at > NOW()` — เลยกำหนดแล้วแสดง placeholder ทันที แม้ job ยังไม่รัน
- **Scheduled job `php spark media:cleanup-expired` วันละครั้ง (03:00)** ลบไฟล์จริง + thumbnail ของภาพที่ครบกำหนด
  → `status='deleted'`, `deleted_reason='retention'`, `deleted_at` → audit `MEDIA_RETENTION_DELETE` (executor = system)
- Job เป็น **idempotent** (ไฟล์ไม่พบ = สำเร็จ, conditional update, lock กันรันซ้อน) และ **ล้มเหลวทีละไฟล์** โดยไม่หยุดไฟล์อื่น
- ไม่มีการลบ record ของภาพหรือ business record; ไม่มี Admin ต้องกดลบตาม retention
- `media:reconcile` รายสัปดาห์เก็บกวาดไฟล์ที่ไม่ควรอยู่ (orphan, ไฟล์ที่ถูก restore กลับมาหลังครบกำหนด)
- รายละเอียด algorithm + failure handling: [data-retention.md §4–6](data-retention.md#4-automatic-cleanup-job--php-spark-mediacleanup-expired)

## 6. Backup (คนละ Policy กับ Retention)
- **Production Storage** = ที่ผู้ใช้เห็นภาพ → ภาพอยู่ไม่เกิน 6 เดือน (กฎด้านบน)
- **Backup** = สำเนาเพื่อกู้ภัย ไม่ใช่ที่ให้ผู้ใช้ดู → มี retention ของตัวเอง **30 วัน** (snapshot ภาพ)
  ⇒ ไฟล์ที่ถูกลบตาม retention อาจยังอยู่ใน backup ได้ไม่เกิน 30 วันหลังถูกลบ จากนั้นหายไปตาม rotation
- การ restore ต้อง **ไม่** นำไฟล์ที่ครบกำหนดกลับมาให้เห็น: restore เฉพาะไฟล์ที่ record เป็น `available` และ `expires_at > NOW()`
  + รัน `media:cleanup-expired` และ `media:reconcile` ทันทีหลัง restore (ภาพไม่ viewable อยู่แล้วตามกติกา)
- รายละเอียด: [backup-and-recovery.md](backup-and-recovery.md)

## 7. Capacity Estimate (Package A: 2 โรงเรียน)

| รายการ | สมมติ | ต่อเดือน |
|---|---|---|
| ภาพกิจกรรม | 6 ห้อง × 22 วัน × 6 ภาพ × ~300 KB | ~240 MB |
| ภาพอาหาร | 6 ห้อง × 22 วัน × 2 ภาพ × 300 KB | ~80 MB |
| Portfolio | 120 เด็ก × 4 ชิ้น × 2 ภาพ × 300 KB | ~290 MB |
| Thumbnail | ~10% ของข้างบน | ~60 MB |
| **รวม** | | **~0.7 GB/เดือน → ประมาณ 5 GB เป็น Initial Capacity Estimate** (ไม่ใช่ Hard Limit) |

- ตัวเลขนี้คือ **ค่าประมาณเริ่มต้น ไม่ใช่ขีดจำกัด** — ขึ้นกับจำนวนรูปที่ครูถ่ายจริง และเปลี่ยนเมื่อเพิ่มโรงเรียน
- เพราะไฟล์ถูกลบอัตโนมัติที่ 6 เดือน ปริมาณจะเข้าสู่ภาวะคงตัวประมาณ 6 เท่าของปริมาณรายเดือน
- เสนอ volume เริ่มต้น 50 GB

### Storage Monitoring / Alert (Requirement — สร้างจริงใน Phase ที่เกี่ยวข้อง)
| ระดับ | เงื่อนไข (ใช้ disk ของ storage volume) | การตอบสนอง |
|---|---|---|
| Warning | ≥ 70% | แจ้ง ops; ตรวจว่า retention job ทำงานปกติ |
| High | ≥ 80% | แจ้ง ops + Super Admin; วางแผนขยาย volume / ย้าย Object Storage |
| Critical | ≥ 90% | แจ้งด่วน; ขยาย volume ทันที (upload จะเริ่มล้มเมื่อเต็ม) |
| Job health | cleanup ไม่สำเร็จ (exit ≠ 0) หรือไม่ได้รัน > 26 ชม. | แจ้ง ops |
| Growth | ปริมาณรายเดือนเกินค่าประมาณ 2 เท่า | ทบทวน capacity estimate |

ที่มาข้อมูล: disk usage ของ volume (Coolify/host monitor) + ผลรอบล่าสุดของ job (`MEDIA_RETENTION_RUN` audit) + สถิติจาก `media_files`

## 8. NOW vs FUTURE

| NOW | FUTURE |
|---|---|
| `LocalStorage` + `X-Accel-Redirect` | `S3Storage` + presigned URL (เมื่อมีหลาย app node หรือ disk ไม่พอ) |
| ย่อภาพ synchronous | Queue worker สำหรับย่อภาพ/สร้าง variant |
| Thumbnail 1 ขนาด | `srcset` หลายขนาด / AVIF |
| ไม่มี CDN | CDN แบบ signed cookie/URL (ต้องรักษา private) |
