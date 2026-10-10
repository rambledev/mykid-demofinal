# Production Architecture

> อ้างอิง: [README.md](README.md) · ADR ใน [decision-log.md](decision-log.md)

## 1. ภาพรวม (Overview)

Mykid Production เป็น **server-rendered web application** (CodeIgniter 4) ที่ใช้งานผ่าน browser บนมือถือ
(ครู / ผู้ปกครอง) และ desktop (Admin / ผู้บริหาร / Super Admin) โดยแยก **CCTV streaming** ออกไปเป็น
Media Server อีกระบบหนึ่ง

```
                        ┌────────────────────────── Coolify host (Production) ─────────────────────────┐
 Browser (มือถือ/PC)     │                                                                                │
  HTML + CSS + JS  ──HTTPS──► Coolify Proxy (TLS) ──► nginx ──FastCGI──► php-fpm (CodeIgniter 4)          │
   │  ▲                  │                          │  ▲  X-Accel-Redirect     │                           │
   │  │ HLS/WebRTC       │                          │  └──── /storage volume ◄─┤ (private images)          │
   │  │ + short token    │                          │                          ├──► MySQL 8.4 (volume)     │
   │  │                  │                          │                          └──► scheduled commands     │
   │  │                  └───────────────────────────────────────────────────────────────────────────────┘
   │  │                                   ▲ token validation (HTTP auth hook / signed token)
   │  └──────────── Media Server (MediaMTX หรือเทียบเท่า) ◄── RTSP (LAN/VPN) ── Tapo C200C × 5 / โรงเรียน
   │
   └── ไม่มี RTSP / credential ใด ๆ ถึง browser
```

### ส่วนประกอบ

| Component | หน้าที่ | NOW / FUTURE |
|---|---|---|
| **nginx** | TLS อยู่ที่ Coolify proxy; nginx เสิร์ฟ `/public` assets, ส่ง PHP ไป FPM, ส่งไฟล์ภาพ private ด้วย `X-Accel-Redirect` (internal location) | NOW |
| **php-fpm + CodeIgniter 4** | Web UI (server-rendered), JSON API `/api/v1`, Authentication, Authorization, Business logic, Upload pipeline | NOW |
| **MySQL 8.4 LTS** | ข้อมูลธุรกิจทั้งหมด + session (`ci_sessions`) — MariaDB ที่สร้างใน Coolify ช่วงทดลอง **ไม่ใช่** Production DB | NOW |
| **Storage volume** | ไฟล์ภาพ (private) ผ่าน `StorageInterface` → `LocalStorage` | NOW |
| **Scheduler** | `php spark` commands ผ่าน cron ใน container: **`media:cleanup-expired` วันละครั้ง (ลบไฟล์ภาพที่ครบ 6 เดือนอัตโนมัติ)**, `media:reconcile` รายสัปดาห์, `auth:prune-tokens` | NOW |
| **Media Server** | รับ RTSP จากกล้อง แปลงเป็น HLS/WebRTC, ตรวจ token | NOW (deploy แยก stack) |
| Object Storage (S3-compatible) | แทน LocalStorage เมื่อไฟล์โตเกิน disk | FUTURE |
| Redis / Queue / WebSocket / CDN | เมื่อมีโรงเรียนมากขึ้นจน polling/การย่อภาพเป็นคอขวด | FUTURE |

## 2. Technology Stack (Locked)

| Layer | เลือก | เหตุผลสั้น |
|---|---|---|
| Language | PHP 8.3+ | ตรงกับทีม, typed properties, enums, readonly |
| Framework | CodeIgniter 4 (latest 4.x) | เบา, เรียนรู้เร็ว, มี Filters / Validation / Migrations / Spark ครบ (ADR-001) |
| Database | **MySQL 8.4 LTS** (default) | CHECK constraint, generated column, window function, JSON, LTS ยาว, CI4 รองรับเต็ม (ADR-002) |
| Web server | Nginx + PHP-FPM | `X-Accel-Redirect` สำหรับไฟล์ private, ประสิทธิภาพดีกว่า Apache prefork |
| Frontend | Server-rendered HTML + CSS + Vanilla JS (ES modules) | ต่อยอด UI Demo ได้ตรง, ไม่ต้อง build step (ADR-007) |
| UI kit | CSS เดิมจาก Demo (pastel, mobile-first) ปรับเป็น design tokens | ไม่เพิ่ม framework ใหญ่; Charts ใช้ HTML/CSS แบบ Demo |
| Deploy | Docker + Docker Compose + Coolify | Coolify จัดการ TLS, env, deploy จาก GitHub, backup DB |
| VCS | Git + GitHub | branch protection บน `main`, PR review |
| Timezone | `Asia/Bangkok` (app + DB session `+07:00`) | ADR-012 |
| Language | Thai (`th`) default, English (`en`) future | CI4 `app/Language/{th,en}` |

## 3. Logical Architecture (Layers)

```
HTTP Request
   │
   ▼
Filters (global → route)     SecureHeaders · CSRF · Session · Auth · ForcePasswordChange · Permission · Throttle
   │
   ▼
Controller (Web / Api\V1)    อ่าน input, เรียก Service, เลือก View หรือ JSON — ไม่มี business logic
   │
   ▼
Service (Domain)             กติกาธุรกิจ, transaction, state machine, audit, notification
   │      └── Policy         "user คนนี้ทำ action นี้กับ entity นี้ได้ไหม" (ใช้ AccessContext)
   ▼
Model (CI4 Model = Repository)  query + scope helpers (forContext) + Entity mapping
   │
   ▼
MySQL / StorageInterface / MediaServer client
```

- **AccessContext** ถูกสร้างครั้งเดียวต่อ request จาก session (user, active role, school ids,
  classroom ids, student ids) — ทุก layer ใช้ตัวเดียวกัน (ดู [authorization.md](authorization.md))
- **ไม่มี Repository layer แยก** — CI4 Model ทำหน้าที่นั้นแล้ว (ADR-010)

## 4. Request Flows สำคัญ

### 4.1 ผู้ปกครองเปิดรูปผลงานลูก
1. `GET /media/{public_id}` → AuthFilter
2. `MediaService::authorizeView()` → โหลด `media_files` + owner → `MediaPolicy::view(ctx, media)`
   (parent: `student_id ∈ ctx.studentIds`) — ไม่ผ่าน = **404**
3. ไม่ viewable (`status='deleted'` หรือ `expires_at <= NOW()`) → placeholder "รูปภาพหมดอายุการจัดเก็บ" (HTTP 410 สำหรับ API, SVG สำหรับ `<img>`) **โดยไม่เปิดไฟล์**
   — ปกติ view จะ render placeholder ตั้งแต่ต้นโดยไม่สร้าง URL ของภาพที่หมดอายุ
4. ผ่าน → ตอบ header `X-Accel-Redirect: /_protected/{storage_path}` + `Cache-Control: private` → nginx ส่งไฟล์

### 4.2 รับ-ส่ง
1. Parent `POST /api/v1/pickups` `{student_id, eta_minutes}` → `PickupService::request()`
   ตรวจ `PickupPolicy::create` + ETA ∈ {5,10,15,20,30} + unique active request (DB constraint)
2. Teacher หน้า "รับ-ส่งนักเรียน" poll `GET /api/v1/pickups?date=today` ทุก 4 วินาที (ETag → 304)
3. Teacher `POST /api/v1/pickups/{id}/transitions` `{to: "preparing"}` → `UPDATE … WHERE status = 'coming'`
   (atomic) + insert `pickup_status_logs` + notification ถึงผู้ปกครอง ใน transaction เดียว

### 4.3 Image Retention (อัตโนมัติ)
**Physical image files are automatically deleted after 6 months. Database records are retained.**
Scheduler → `php spark media:cleanup-expired` (วันละครั้ง) → ลบไฟล์จริง + thumbnail ของภาพที่ `expires_at <= NOW()`
→ `media_files.status='deleted'` (`deleted_reason='retention'`) → audit `MEDIA_RETENTION_DELETE` (actor = system)
— record ภาพและ business record คงอยู่; job idempotent และล้มเหลวทีละไฟล์ ([data-retention.md](data-retention.md))

### 4.4 CCTV
1. `POST /api/v1/cameras/{id}/stream-sessions` → `CameraPolicy::view` → ออก token อายุ 5 นาที
   (ผูก user + camera stream path)
2. Browser เล่น `https://media.<domain>/<stream_path>/index.m3u8?token=…` หรือ WHEP
3. Media Server ตรวจ token กับ CI4 (`/internal/media-auth`, shared secret) หรือ verify signature เอง

## 5. Deployment Topology (Docker Compose / Coolify)

| Service | Image | Volume | Network |
|---|---|---|---|
| `web` | `nginx:1.27-alpine` + config ของ repo | code (ro), `storage` (ro, internal location) | public (ผ่าน Coolify proxy) |
| `app` | image ของเรา `php:8.3-fpm` + ext: `intl`, `mysqli`, `gd` (jpeg/png/webp), `exif`, `zip`(ถ้าต้องใช้) | `storage` (rw), `writable` (rw) | internal |
| `db` | `mysql:8.4` (หรือ Coolify-managed MySQL) | `mysql-data` | internal เท่านั้น |
| `scheduler` | image เดียวกับ `app` รัน `crond` → `php spark` | `storage` (rw) | internal |
| `media` (stack แยก) | MediaMTX (หรือเทียบเท่า) | config + secrets | public (stream) + LAN/VPN ถึงกล้อง |

- `web` กับ `app` ใช้ code จาก image เดียวกัน (multi-stage build) — ไม่ mount source ใน production
- ไฟล์จริงอยู่ใน repo `mykid-production` (Phase 0.5): `Dockerfile` (targets `app`, `web`), `docker-compose.yml` (dev: app/web/db MySQL 8.4), `docker-compose.prod.yml` (Coolify), `docker/` configs — ขั้นตอน deploy/rollback อยู่ใน [dev-workflow.md](dev-workflow.md)
- Health check: `GET /health/live` (ไม่แตะ DB) สำหรับ Docker, `GET /health/ready` (DB + storage writable) สำหรับ Coolify
- `NIXPACKS` ไม่ใช้ — Production ใช้ Dockerfile/Compose ของ repo โดยตรง

## 6. Environments

| | Development | Staging | Production |
|---|---|---|---|
| ที่รัน | Docker Compose บนเครื่อง dev | Coolify (project แยก) | Coolify |
| `CI_ENVIRONMENT` | `development` | `production` (ให้พฤติกรรมเหมือนจริง) | `production` |
| Database | MySQL container ของเครื่อง + seed ข้อมูลสมมติ | DB แยก + ข้อมูลสมมติ/ข้อมูลที่ anonymize แล้ว | DB จริง |
| Storage | volume local | volume แยก | volume production |
| Media Server | mock stream / กล้องทดสอบ | กล้องทดสอบ 1 ตัว | กล้องจริง 10 ตัว |
| Mail | Mailpit (ดักเมล) | Mailpit/SMTP sandbox | SMTP จริง |
| Debug toolbar | เปิด | ปิด | ปิด |

กติกา: **ห้ามใช้ Production DB / storage / secret ใน Development หรือ Staging**,
ห้าม copy ข้อมูลเด็กจริงลง Development, `.env` ไม่อยู่ใน Git (มีแค่ `env.example`)

### Secrets / Config ที่ต้องมี (ค่าจริงอยู่ใน Coolify env เท่านั้น)

| กลุ่ม | ตัวแปร |
|---|---|
| App | `CI_ENVIRONMENT`, `app.baseURL`, `app.forceGlobalSecureRequests=true`, `TRUSTED_PROXIES` |
| Encryption | `encryption.key` (CI4 key, ใช้กับข้อมูลที่ต้องเข้ารหัส), `AUTH_PEPPER` (HMAC pepper สำหรับ PIN และ password) |
| Database | `database.default.hostname/database/username/password/port`, user แยก `migrator` (DDL) กับ `app` (DML) |
| Session | `session.driver=DatabaseHandler`, `session.cookieName`, `session.expiration`, `cookie.secure=true`, `cookie.samesite=Lax` |
| Mail | `email.SMTPHost/User/Pass/Port/Crypto`, `email.fromEmail` |
| Storage | `STORAGE_DISK=local`, `STORAGE_LOCAL_ROOT=/var/lib/mykid/storage`; FUTURE: `S3_ENDPOINT/BUCKET/KEY/SECRET/REGION` |
| Monitoring | ระดับแจ้งเตือน disk ของ storage volume: 70% / 80% / 90% (ดู [storage.md §7](storage.md)) |
| Media / Retention | `MEDIA_RETENTION_MONTHS=6` (**ค่าคงที่ตาม requirement — ห้ามตั้งเกิน 6**), `MEDIA_MAX_UPLOAD_MB=10` |
| CCTV | `CCTV_MEDIA_PUBLIC_URL`, `CCTV_STREAM_TOKEN_SECRET`, `CCTV_AUTH_HOOK_SECRET` (RTSP credential อยู่ที่ Media Server เท่านั้น) |
| Backup | credential ของปลายทาง backup (ตั้งใน Coolify backup / restic env) |
| Observability (FUTURE) | `SENTRY_DSN` หรือเทียบเท่า |

## 7. Observability

| ประเภท | ที่เก็บ | เนื้อหา | ห้าม |
|---|---|---|---|
| Application log | stdout (JSON lines) → Coolify logs | `[Module][Function] START/END`, request_id, user_id, latency | PIN, password, token, เนื้อหาแชท |
| Error log | stdout + `writable/logs` (rotate 14 วัน) | exception + stack trace (ภายในเท่านั้น) | แสดง stack trace ให้ user |
| Security log | channel `security` (stdout) + `audit_logs` (result=denied/failed) | login fail, lockout, 403/404 จาก scope, upload reject, CSRF fail | ข้อมูลส่วนตัวเกินจำเป็น |
| Audit log | ตาราง `audit_logs` | การกระทำทางธุรกิจ (ดู [security-model.md §Audit](security-model.md#13-audit-logging)) | ค่าก่อน/หลังที่เป็นข้อมูลอ่อนไหว |
| Health check | `/health/live`, `/health/ready` | `{"status":"ok"}` / `{"status":"fail","checks":{"db":"fail"}}` | version, path, host, error message |
| Metrics (FUTURE) | Prometheus/Uptime monitor | latency, disk usage, pickup poll rate | — |

- ทุก request มี `X-Request-Id` (ULID) ส่งกลับใน header และใส่ใน log/audit เพื่อตามรอย
- Browser: `console.group('[DEV] API Response')` เฉพาะเมื่อ `CI_ENVIRONMENT=development` (ตามมาตรฐานทีม)
- แจ้งเตือน (NOW แบบง่าย): uptime monitor ภายนอกเช็ก `/health/ready` + Coolify disk alert; FUTURE: Sentry

## 8. MVP (NOW) vs FUTURE — สรุปทั้งระบบ

| เรื่อง | MUST HAVE NOW | FUTURE (มีเงื่อนไขเมื่อไหร่) |
|---|---|---|
| Database | MySQL 8.4 เครื่องเดียว + backup | Read replica เมื่อ report หนัก |
| Storage | Local volume + StorageInterface + **ลบไฟล์ภาพอัตโนมัติที่ 6 เดือน** + disk alert 70/80/90% | S3-compatible + presigned URL เมื่อ disk ใกล้เต็มแม้มี retention หรือ > 1 node |
| Real-time | Polling 4 วินาที + ETag | SSE / WebSocket เมื่อ concurrent > ~500 หน้าเปิดพร้อมกัน |
| Background jobs | ย่อภาพแบบ synchronous ตอนอัปโหลด + cron | Queue (DB-backed หรือ Redis) เมื่อ upload p95 > 3 วินาที |
| Cache | ไม่มี (index ดีพอ) + HTTP cache สำหรับ static | Redis cache dashboard เมื่อ query > 200ms |
| Notification | ในระบบ (inbox) | LINE OA / Web Push / SMS |
| Auth | Teacher/Parent: เบอร์มือถือ + PIN 6 หลัก; กลุ่ม Admin: Account + Password; Argon2id + pepper, lockout, session rotation, remember-me (Teacher/Parent) | OTP, LINE Login, 2FA สำหรับกลุ่ม Admin |
| CCTV | Media Server 1 ตัว (หรือ 1 ต่อโรงเรียน) | Recording/playback, AI, scale media nodes |
| Scaling | 1 VM, 1 app container | หลาย app container (session ใน DB รองรับแล้ว) + Object Storage |
| Mobile app | Responsive web | Native app ใช้ `/api/v1` + token auth |
