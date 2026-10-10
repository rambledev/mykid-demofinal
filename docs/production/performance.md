# Performance

> ขนาดงานปัจจุบัน (Package A): 2 โรงเรียน · 6 ห้อง · ~120 นักเรียน · ~10 ครู · ~150–200 ผู้ปกครอง · 10 กล้อง
> เป้าหมาย MVP: หน้าเว็บ p95 < 500 ms, API polling p95 < 100 ms บน VM 2 vCPU / 4 GB

## 1. Bottleneck Analysis

| จุด | ทำไมอาจช้า | MVP Production (NOW) | Future Scale |
|---|---|---|---|
| **Student / Parent Dashboard** | รวมข้อมูลหลายตาราง (เช็คชื่อ, นอน, สุขภาพ, อาหาร, กิจกรรม, ดาว) ของวันนี้ | query แยกตามตารางด้วย UNIQUE `(student_id, date)` = index lookup ละ 1 แถว (~6–8 query เล็ก) | cache ต่อเด็กต่อวัน (Redis) เมื่อ p95 > 300 ms |
| **Admin / Executive Dashboard** | aggregate ทั้งโรงเรียน | `GROUP BY status` บน index `(school_id, date, status)` (covering); ≤ 120 แถว/วัน | summary table รายวัน (`daily_school_stats`) เมื่อหลายสิบโรงเรียน |
| **Activity Timeline (ผู้ปกครอง)** | merge กิจกรรม + สถานะห้อง + record ของเด็ก | ประกอบใน `ReportService` จาก query ที่มี index; จำกัดเฉพาะวันที่เลือก | — |
| **Images** | ไฟล์ใหญ่บนมือถือ, PHP ส่งไฟล์เอง | ย่อเหลือ ≤1600px + thumbnail 400px, `loading="lazy"`, nginx ส่งไฟล์ (`X-Accel-Redirect`), `Cache-Control: private, max-age=300` | Object Storage + CDN แบบ signed, `srcset`/AVIF |
| **Portfolio / ประวัติ** | สะสมเรื่อย ๆ | pagination 20 ชิ้น/หน้า, thumbnail ในรายการ | — |
| **Notifications** | badge ทุกหน้า | `COUNT` บน `(user_id, read_at)` — ไม่ scan; render badge ตอนโหลดหน้า + poll พร้อม pickup | SSE/Push |
| **Chat** | ประวัติยาว | keyset pagination 30 ข้อความ, `messages(conversation_id, id)` | WebSocket |
| **Pickup polling** | ทุก 4 วินาทีต่อหน้าเปิด | ดู §2 | SSE |
| **Upload** | ย่อภาพใน request | ≤6 ภาพ/ครั้ง, client resize ก่อนส่ง, server resize ด้วย GD | Queue worker |
| **CCTV** | วิดีโอใช้ bandwidth สูง | **ไม่ผ่าน PHP** — Media Server แยก, on-demand pull, SD เป็นค่าเริ่มต้นสำหรับผู้ปกครอง | หลาย media node |
| **Reports ย้อนหลัง** | ช่วงวันที่กว้าง | จำกัดช่วง ≤ 1 ปี, index date, export CSV แบบ streaming | read replica |

## 2. Pickup Polling — ประมาณภาระ

- ช่วงพีค 14:30–16:30: สมมติหน้า "รับ-ส่ง" เปิดพร้อมกัน ~10 ครู + ~60 ผู้ปกครอง = 70 หน้า
- 70 / 4 วินาที ≈ **18 req/s** — แต่ละ request:
  1. session lookup (PK) 2. query `pickup_requests` ด้วย index `(classroom_id, pickup_date, status)` หรือ `(student_id, pickup_date)` (≤ 20 แถว)
  3. ถ้า `ETag` ตรง → **304 ไม่มี body**
- ประมาณ < 5 ms DB time ต่อ request → สบายบน VM เดียว
- การป้องกัน: poll หยุดเมื่อแท็บถูกซ่อน (Page Visibility API — ทำแล้วใน Demo), ช่วงนอกเวลารับ-ส่ง (ก่อน 14:00) ลดเป็น 30 วินาที (config), throttle ≥ 3 วินาที/user
- จุดเปลี่ยนไป SSE/WebSocket: concurrent > ~500 หน้า หรือ DB CPU > 50% จาก polling

## 3. Database
- Index ตาม [database.md §14](database.md#14-index-strategy-สรุปเหตุผลตาม-query-จริง) — ทุก query บนหน้าหลักต้องเป็น index range/lookup (ตรวจ `EXPLAIN` ใน code review)
- ไม่มี N+1: list ของเด็กโหลด avatar/สถานะวันนี้ด้วย `WHERE student_id IN (...)` ครั้งเดียว
- `innodb_buffer_pool_size` ≈ 50–60% RAM ของ container DB (ข้อมูลทั้งระบบ < 1 GB ในปีแรก → อยู่ใน memory ทั้งหมด)
- Slow query log เปิด (`long_query_time = 0.5`) ใน staging/production

## 4. PHP / Web
- OPcache เปิด (`validate_timestamps=0` ใน production), preload ไม่จำเป็น
- php-fpm `pm = dynamic`, `pm.max_children` ตาม RAM (~20 สำหรับ 2 GB ให้ app)
- Static assets: fingerprint (`app.css?v=hash`), `Cache-Control: public, max-age=31536000, immutable`, gzip/brotli ที่ nginx
- ไม่มี frontend build/bundle ขนาดใหญ่ — JS แยก module ต่อ feature, โหลดเฉพาะหน้าที่ใช้

## 5. MVP vs Future (สรุป)

| NOW | FUTURE (ทำเมื่อ metric ชี้ว่าจำเป็น) |
|---|---|
| Index ถูกต้อง + pagination + lazy loading + thumbnail | Redis cache (dashboard, session) |
| Polling 4 วินาที + ETag/304 | SSE หรือ WebSocket |
| Upload synchronous | Queue (DB-backed ก่อน, Redis ภายหลัง) |
| 1 app container + 1 DB | หลาย app container (session ใน DB รองรับแล้ว) + read replica |
| Local storage | Object storage + CDN (signed) |

**ห้ามเพิ่ม Redis / Queue / WebSocket ใน MVP** จนกว่าจะมีตัวเลขจาก production monitoring ยืนยัน (ADR-004, ADR-011)
