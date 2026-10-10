# API Architecture (Contract level — ยังไม่ implement)

## 1. Web Routes vs API Routes

| | Web routes | API routes |
|---|---|---|
| Prefix | `/` (เช่น `/login`, `/dashboard`, `/teacher/pickups`) | `/api/v1/...` |
| ตอบ | HTML (server-rendered) + redirect | JSON เท่านั้น |
| ผู้ใช้ | Browser (ทุก role) | JS ของหน้าเว็บเอง (fetch) — FUTURE: mobile app |
| Auth | Session cookie | Session cookie + `X-CSRF-TOKEN` (NOW); Bearer token (FUTURE mobile) |
| Error | หน้า error ภาษาไทย / flash message | JSON error format (§4) |
| ไฟล์ Routes | `app/Config/Routes.php` → group ตาม role area | `app/Config/Routes/api_v1.php` (include จาก Routes.php) |

หลักการ: **หน้าเว็บ render ด้วย server** — API มีไว้สำหรับ interaction ที่ต้องไม่ reload ทั้งหน้า
(polling รับ-ส่ง, เปลี่ยนสถานะ, อัปโหลดภาพ, แชท, badge แจ้งเตือน, stream token) ไม่ทำ API ครบทุก CRUD ถ้าหน้าเว็บใช้ form ปกติได้

### Web routes (ตัวอย่างโครง)
```
GET  /login                  POST /login            (เบอร์มือถือ + PIN — Parent/Teacher)
GET  /staff/login            POST /staff/login      (Account + Password — Super Admin/School Admin/Executive)
POST /logout
GET  /                       → redirect ไปหน้าหลักของ active role
GET  /super-admin/...        (filter: auth, perm:platform.*)
GET  /admin/...              GET /admin/storage     (ติดตามพื้นที่/ผล cleanup — ไม่มีปุ่มลบตาม retention)
GET  /executive/...
GET  /teacher/...            GET /teacher/pickups   GET /teacher/attendance
GET  /parent/...             GET /parent/pickups    GET /parent/portfolio
GET  /media/{public_id}      GET /media/{public_id}/thumb
GET  /health/live            GET /health/ready
```

## 2. API v1 Resources (เฉพาะที่จำเป็นใน MVP)

| Method | Path | Permission | หน้าที่ |
|---|---|---|---|
| GET | `/api/v1/me` | auth | ข้อมูลผู้ใช้ + active role + เมนู |
| POST | `/api/v1/me/role` | auth | สลับ role (ถ้ามีหลาย role) |
| GET | `/api/v1/pickups?date=YYYY-MM-DD` | `pickup.view` | **get pickup status** — ภายใน scope (ครู: ห้องตน, ผู้ปกครอง: ลูก) รองรับ `ETag`/`If-None-Match` → 304 |
| POST | `/api/v1/pickups` | `pickup.request` | **create pickup request** `{student_id, eta_minutes}` → 201 |
| POST | `/api/v1/pickups/{id}/transitions` | `pickup.transition` | **update pickup status** `{to: preparing\|waiting\|completed}` → 200 / 409 |
| GET | `/api/v1/pickups/{id}/history` | `pickup.view` | status log |
| PUT | `/api/v1/attendance/{student_id}/{date}` | `attendance.write` | upsert สถานะเช็คชื่อ (แตะปุ่มเดียว) |
| PUT | `/api/v1/food-intakes/{student_id}/{date}/{meal}` | `food.write` | ระดับการกิน (ถ้าอยู่ใน scope) |
| POST | `/api/v1/media` (multipart) | `<owner>.write` | อัปโหลดภาพ `{owner_type, owner_id, images[]}` |
| DELETE | `/api/v1/media/{public_id}` | `<owner>.write` | ลบภาพ (status `deleted`) |
| POST | `/api/v1/stars` | `star.award` | ให้ดาว `{student_id, points, reason_code, reason_text}` |
| POST | `/api/v1/stars/{id}/reversal` | `star.reverse` | กลับรายการ |
| GET | `/api/v1/conversations/{id}/messages?before_id=` | `chat.view` | keyset pagination |
| POST | `/api/v1/conversations/{id}/messages` | `chat.send` | ส่งข้อความ |
| GET | `/api/v1/notifications?unread=1` | `notification.view` | รายการ + จำนวนยังไม่อ่าน |
| POST | `/api/v1/notifications/{id}/read` | `notification.view` | อ่านแล้ว |
| POST | `/api/v1/cameras/{id}/stream-sessions` | `camera.view` | ออก stream token |
| POST | `/api/v1/classrooms/{id}/status` | `classroom.status` | สถานะห้องเรียน (ถ้าอยู่ใน scope) |
| POST | `/internal/media-auth` | shared secret + internal network | auth hook ของ Media Server (ไม่ใช่ public API) |

> การสร้าง/แก้ข้อมูลหลัก (นักเรียน, ห้อง, ครู, ปฏิทิน, เมนู) ใช้ **web form** (POST → redirect) ใน MVP

## 3. หลักการ (ทุก endpoint)

| เรื่อง | กติกา |
|---|---|
| Authentication | `AuthFilter` บน group `/api/v1` — ไม่มี session → 401 |
| CSRF | ทุก method ที่ไม่ใช่ GET ต้องมี `X-CSRF-TOKEN` (session-cookie auth) |
| Authorization | `PermissionFilter` (perm code) + Policy ใน Service — นอก scope → **404** (ไม่เปิดเผยว่ามีอยู่) |
| Validation | CI4 Validation rules ใน `app/Validation/*Rules` + การตรวจทางธุรกิจใน Service; field ที่ไม่รู้จักถูกเพิกเฉย (ไม่ mass-assign) |
| Idempotency | สร้างรับ-ส่งซ้ำ = 409 จาก unique constraint; PUT ของ daily records เป็น idempotent โดยธรรมชาติ |
| Pagination | keyset (`before_id`/`after_id`) สำหรับ chat/notification, `page`+`per_page` (≤ 50) สำหรับรายการทั่วไป |
| Time | ISO 8601 พร้อม offset `+07:00` |
| Localization | `message` ภาษาไทยตาม `users.locale` (Language files) |
| Versioning | path `/api/v1`; breaking change → `/api/v2`, v1 คงไว้อย่างน้อย 6 เดือนหลังประกาศ |
| Caching | GET ที่ poll ได้ใช้ `ETag` (hash ของ id+status/updated_at) → 304 ไม่มี body |
| Rate limit | Throttler ต่อ user/IP (ดู security-model §4); เกิน → 429 + `Retry-After` |
| Logging | request_id ทุก response (`X-Request-Id`), audit สำหรับ action ที่กำหนด |

## 4. Response Format

**Success**
```json
{
  "data": { "id": 123, "status": "preparing", "student": { "id": 5, "nickname": "น้องต้น" } },
  "meta": { "request_id": "01J9ZK…" }
}
```

**Collection**
```json
{
  "data": [ { … }, { … } ],
  "meta": { "request_id": "01J9ZK…", "next_cursor": "8812", "etag": "\"a1b2c3\"" }
}
```

**Error**
```json
{
  "error": {
    "code": "PICKUP_INVALID_TRANSITION",
    "message": "สถานะมีการเปลี่ยนแปลงแล้ว กรุณาลองใหม่อีกครั้ง",
    "fields": { "eta_minutes": "กรุณาเลือกเวลาที่จะถึงโรงเรียน" }
  },
  "meta": { "request_id": "01J9ZK…" }
}
```
- `message` เป็นภาษาที่ผู้ใช้เข้าใจ ไม่มีคำว่า null/500/SQL/stack trace
- `code` เป็นค่าคงที่สำหรับโปรแกรม (UPPER_SNAKE) — JS ใช้ตัดสินพฤติกรรม ไม่ parse ข้อความ

## 5. HTTP Status

| Status | ใช้เมื่อ |
|---|---|
| 200 | สำเร็จ (อ่าน/อัปเดต) |
| 201 | สร้างสำเร็จ (pickup, star, media) |
| 204 | สำเร็จไม่มี body (mark read) |
| 304 | ETag ตรง (polling ไม่มีอะไรเปลี่ยน) |
| 400 | request ผิดรูปแบบ (JSON เสีย) |
| 401 | ไม่ได้ login / session หมดอายุ |
| 403 | login แล้วแต่ไม่มี **permission** สำหรับ action นี้ / CSRF ไม่ผ่าน |
| 404 | ไม่พบ หรือ **อยู่นอก scope** (กัน enumeration) |
| 409 | ขัดกับสถานะปัจจุบัน (transition ข้ามขั้น, แจ้งรับซ้ำ) |
| 410 | ภาพครบกำหนดเก็บ/ถูกลบไฟล์แล้ว (`MEDIA_EXPIRED`) — record ยังอยู่ |
| 413 | ไฟล์ใหญ่เกิน |
| 415 | ชนิดไฟล์ไม่รองรับ |
| 422 | validation ไม่ผ่าน (มี `fields`) |
| 429 | เกิน rate limit |
| 500 / 503 | ระบบขัดข้อง (ข้อความกลาง ๆ + request_id) |

## 6. Pickup API Contract (รายละเอียด)

```
GET /api/v1/pickups?date=2026-10-06
  If-None-Match: "9f2c…"
→ 304   (ไม่มีการเปลี่ยนแปลง)
→ 200   { data: [ { id, student:{id,nickname,classroom,avatar_url}, status, eta_minutes,
                    requested_at, eta_at, preparing_at, waiting_at, completed_at,
                    requested_by_name, completed_by_name } ],
          meta: { etag, server_time } }

POST /api/v1/pickups            { "student_id": 5, "eta_minutes": 10 }
→ 201   { data: { id, status: "coming", eta_at } }
→ 404   นักเรียนไม่อยู่ใน scope ของผู้ปกครอง
→ 409   PICKUP_ALREADY_ACTIVE
→ 422   eta_minutes ∉ {5,10,15,20,30}

POST /api/v1/pickups/123/transitions   { "to": "preparing" }
→ 200   { data: { id, status: "preparing", preparing_at, preparing_by_name } }
→ 404   นอกห้องของครู
→ 409   PICKUP_INVALID_TRANSITION (สถานะปัจจุบันไม่ใช่ขั้นก่อนหน้า)
```

State machine (ใน `PickupService`): `coming → preparing → waiting → completed` เท่านั้น (ไม่มีข้าม/ย้อน)

## 7. FUTURE
- Token auth (Bearer, hashed personal access token / OAuth) สำหรับ mobile app
- OpenAPI spec (`docs/production/openapi.yaml`) generate/maintain คู่กับ endpoint จริงใน Phase 1
- Webhook/Push (LINE OA) แทน polling บางส่วน
