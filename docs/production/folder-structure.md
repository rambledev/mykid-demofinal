# CodeIgniter 4 Folder Structure & Service Layer

> หลักเลือก folder: มีเหตุผลรองรับจริงเท่านั้น — ไม่สร้าง `Repositories/`, `Interfaces/` แยกทุก class, หรือ DDD bounded contexts เต็มรูปแบบ (ADR-010)

## 1. Repository Layout

```
mykid-production/                       ← repo ใหม่ (ADR-014, Locked) — **foundation สร้างแล้วใน Phase 0.5**; Demo อยู่ repo `mykid` เป็น reference
├── app/
│   ├── Config/
│   │   ├── Routes.php                  # web routes ต่อ role area + include Routes/api_v1.php
│   │   ├── Routes/api_v1.php           # /api/v1 group (filters: auth, csrf, throttle)
│   │   ├── Filters.php                 # alias: auth, perm, throttle, secureheaders, forcechange
│   │   ├── Mykid.php                   # ค่าธุรกิจ: retention 6 เดือน, ETA [5,10,15,20,30], upload limits, polling 4s
│   │   └── (CI4 configs: App, Database, Session, Security, Cookie, Logger, Email …)
│   ├── Controllers/
│   │   ├── BaseController.php
│   │   ├── Auth/                       # LoginController (เบอร์+PIN), StaffLoginController (account+password), LogoutController, CredentialController
│   │   ├── Web/
│   │   │   ├── SuperAdmin/             # Schools, SchoolAdmins, Compare, Cameras
│   │   │   ├── Admin/                  # Students, Teachers, Classrooms, Activities, Food, Calendar, Storage …
│   │   │   ├── Executive/              # Dashboard, Reports
│   │   │   ├── Teacher/                # Home, Attendance, Pickups, Portfolio, Stars, Chat …
│   │   │   └── Parent/                 # Home, Timeline, Pickups, Portfolio, Stars, Chat …
│   │   ├── Api/V1/                     # PickupController, MediaController, AttendanceController, ChatController …
│   │   ├── MediaController.php         # GET /media/{public_id} (X-Accel-Redirect)
│   │   ├── Health.php                  # /health/live, /health/ready  ✅ มีแล้ว (Phase 0.5)
│   │   └── Internal/MediaAuthController.php   # auth hook ของ Media Server
│   ├── Filters/
│   │   ├── AuthFilter.php              # login + โหลด AccessContext + school suspended
│   │   ├── PermissionFilter.php        # perm:<code>
│   │   ├── ForceCredentialChange.php   # user_credentials.must_change
│   │   ├── SecureHeadersFilter.php     # CSP, HSTS, …
│   │   ├── ApiThrottleFilter.php       # CI4 Throttler ต่อ user/IP
│   │   └── InternalOnlyFilter.php      # /internal/* (shared secret + network)
│   ├── Authorization/
│   │   ├── AccessContext.php           # value object ของ request ปัจจุบัน
│   │   ├── AccessContextFactory.php    # สร้างจาก user_roles / teacher_classrooms / parent_students
│   │   ├── ScopeResolver.php           # แปลง context → WHERE ต่อ scope level
│   │   └── Policies/                   # StudentPolicy, PickupPolicy, MediaPolicy, CameraPolicy, ChatPolicy …
│   ├── Services/                       # business logic (§3) — CI4 Config\Services ลงทะเบียน factory
│   ├── Models/                         # CI4 Model ต่อ table + scope helper forContext(), $allowedFields
│   ├── Entities/                       # CI4 Entity (casts, computed เช่น Media::isViewable())
│   ├── Enums/                          # PHP 8.3 backed enums: PickupStatus, AttendanceStatus, MediaStatus, RoleCode …
│   ├── Validation/                     # rule groups ต่อ form/endpoint + custom rules (thai_phone, pin_strength)
│   ├── Libraries/
│   │   ├── Storage/                    # StorageInterface, LocalStorage, (S3Storage FUTURE)
│   │   ├── Image/ImageProcessor.php    # resize, thumbnail, strip EXIF, re-encode
│   │   └── Cctv/StreamTokenSigner.php  # HMAC/JWT token
│   ├── Commands/                       # spark: media:cleanup-expired (daily, auto-delete files ≥ 6 เดือน), media:reconcile (weekly),
│   │                                   #        auth:prune-tokens, (cctv:sync-status FUTURE)
│   ├── Database/
│   │   ├── Migrations/                 # 1 migration ต่อกลุ่มตาราง, มี down()
│   │   └── Seeds/                      # RolePermissionSeeder (prod), DemoSchoolSeeder (dev/staging เท่านั้น)
│   ├── Language/
│   │   ├── th/                         # ข้อความผู้ใช้ทั้งหมด (default)
│   │   └── en/                         # FUTURE
│   ├── Views/
│   │   ├── layouts/                    # console.php (admin/exec/super), app.php (teacher/parent — bottom nav), auth.php
│   │   ├── components/                 # partials: avatar, status badge, media tile, sheet, empty state
│   │   └── {super_admin,admin,executive,teacher,parent}/   # หน้าต่อ role
│   └── Helpers/                        # view helpers: can(), thai_date(), media_url()
├── public/
│   ├── index.php
│   └── assets/                         # css/ (design tokens จาก Demo), js/ (ES modules: api.js, pickup.js, upload.js, cctv.js), img/
├── writable/                           # cache, logs, uploads temp (ไม่ใช่ที่เก็บภาพถาวร)
├── tests/
│   ├── unit/                           # Services, Policies, ScopeResolver, Enums
│   ├── feature/                        # HTTP: authorization matrix, pickup flow, upload security, retention
│   └── _support/                       # factories + fixture 2 โรงเรียน (เหมือน Demo)
├── docker/                             ✅ Phase 0.5
│   ├── nginx/default.conf              # FastCGI → app:9000, /_protected internal, security headers, client_max_body_size 64m
│   ├── php/zz-mykid.ini                # timezone Asia/Bangkok, upload limits, opcache (prod), session cookie flags
│   ├── php/zz-mykid-fpm.conf           # php-fpm pool
│   ├── php/zz-mykid-dev.ini            # dev only: opcache revalidate, display_errors
│   ├── mysql/my.cnf, mysql/init/       # dev MySQL 8.4: utf8mb4_0900_ai_ci, +07:00, strict, mykid_test schema
│   └── cron/mykid                      # scheduler entries (media phase — ยังไม่สร้าง)
├── Dockerfile                          # ✅ multi-stage: composer (no-dev) → target app (php 8.3-fpm + intl/mysqli/gd/opcache) / target web (nginx)
├── docker-compose.yml                  # ✅ dev: app, web (:8080), db (mysql:8.4 + volume mysql-data)
├── docker-compose.prod.yml             # ✅ Coolify: app, web (+ volume storage, writable); DB = Coolify MySQL 8.4 resource; scheduler เพิ่มใน media phase
├── .env.example                        # ✅ ชื่อตัวแปรทั้งหมด ไม่มีค่า secret
├── .github/workflows/ci.yml            # ✅ lint, composer validate/audit, boot (spark routes), PHPUnit + MySQL 8.4, smoke health, docker build
├── composer.json / composer.lock
├── phpunit.xml.dist
└── docs/                               # คัด docs/production จาก repo Demo มาเป็นจุดเริ่ม
```

### เหตุผลของ folder ที่ไม่ใช่ค่ามาตรฐาน CI4
| Folder | เหตุผล |
|---|---|
| `Authorization/` | รวม AccessContext + Policies ไว้ที่เดียว — ทำให้ "ไม่มี if role กระจาย" เป็นจริงและ review ง่าย |
| `Services/` | business logic ที่ข้ามหลาย Model / มี transaction / state machine |
| `Enums/` | สถานะธุรกิจเป็น type ที่ตรวจได้ (แทน string ลอย ๆ ใน Demo) |
| `Libraries/Storage` | ขอบเขตกับ infrastructure (เปลี่ยน Local → S3 ได้) |
| **ไม่มี** `Repositories/` | CI4 Model คือ repository อยู่แล้ว (query builder + entity) — เพิ่มชั้นอีกชั้นไม่ได้ประโยชน์ที่ขนาดนี้ |

## 2. Layer Rules

| Layer | ทำ | ห้ามทำ |
|---|---|---|
| Controller | อ่าน request → validation rules → เรียก Service 1 method → render view / JSON | query DB ตรง, ตัดสินสิทธิ์รายชิ้น, business rule |
| Service | ตรวจ Policy, business rule, transaction (`$db->transStart()`), เรียกหลาย Model, audit, notification | รู้จัก `$this->request`/HTML |
| Policy | คืน bool จาก (AccessContext, entity) | query DB, side effect |
| Model | query + `forContext()` scope + entity mapping | business rule, เรียก Service |
| View | แสดงผล + `esc()` + `can()` ซ่อนปุ่ม | logic ธุรกิจ, query |

CRUD ธรรมดาที่ไม่มีกติกาข้ามตาราง (เช่น ปฏิทินโรงเรียน) **อนุญาต** ให้ Controller → Policy → Model ได้โดยไม่ต้องสร้าง Service — แต่ต้องผ่าน Policy และ `forContext()` เสมอ

## 3. Service Layer (เฉพาะที่จำเป็นจริง)

| Service | ความรับผิดชอบ | ทำไมต้องเป็น Service |
|---|---|---|
| `AuthService` | login 2 แบบ (เบอร์+PIN / account+password), Argon2id+pepper, lockout, session rotation, remember-me, reset/change credential | security-critical, หลายตาราง + audit |
| `AccessContextFactory` (ใน Authorization) | สร้าง/refresh context ตาม `access_version` | ใช้ทุก request |
| `RoleAssignmentService` | มอบ/ถอด role, ผูกครู-ห้อง, bump `access_version` | กติกาข้ามตาราง (role ต้องอยู่ school เดียวกับห้อง) |
| `StudentService` | สร้าง/แก้/ย้ายห้อง, ผูก/ยกเลิกผู้ปกครอง, sync chat participants | ผลกระทบหลายตาราง |
| `DailyCareService` | attendance (ตั้ง `checked_in_at` อัตโนมัติ), sleep, health, intake — upsert ต่อวัน | กติการายวัน + audit |
| `ActivityService` | ตารางกิจกรรม + แนบภาพ | ผูก MediaService |
| `FoodMenuService` | เมนู + items + ภาพ | หลายตาราง |
| `MediaService` | upload pipeline, owner binding, view resolution (placeholder), ลบรายรูป, stats | security + storage |
| `MediaRetentionService` | ใช้โดย `media:cleanup-expired` / `media:reconcile`: เลือก batch, ลบไฟล์, conditional update, failure tracking, audit (actor=system) | idempotent job + failure handling แยกจาก flow ผู้ใช้ |
| `PortfolioService` | ผลงาน + ภาพ (validate ไฟล์ก่อนบันทึกผลงาน — บทเรียนจาก Demo) | transaction กับไฟล์ |
| `StarService` | award, reversal, ยอดรวม | ledger rules |
| `PickupService` | state machine, unique active, log, notification, audit | กติกาหลักของ feature รับ-ส่ง |
| `ChatService` | ส่งข้อความ, participants, unread | sync กับ parent/teacher links |
| `NotificationService` | สร้างประกาศ + fan-out ผู้รับตาม target, mark read | fan-out logic |
| `CameraService` | metadata, permissions, ออก stream token, ตรวจ auth hook | security |
| `ReportService` | aggregate dashboard/report ต่อ scope | query ซับซ้อน แยกจาก controller |
| `AuditService` | เขียน audit log (whitelist metadata) | ใช้ร่วมทุก service |

ไม่มี: `SchoolService`/`ClassroomService`/`CalendarService` แยก (CRUD ธรรมดา) — เพิ่มเมื่อมีกติกาจริง

## 4. ลำดับการเรียก (ตัวอย่างรับ-ส่ง)

```
Api\V1\PickupController::transition($id)
   └─ validate {to}
   └─ PickupService::transition($ctx, $id, PickupStatus::from($to))
        ├─ PickupRequestModel::forContext($ctx)->find($id)          → null ⇒ NotFound (404)
        ├─ PickupPolicy::transition($ctx, $pickup)                   → false ⇒ NotFound
        ├─ PickupStatus::assertNext($pickup->status, $to)           → invalid ⇒ Conflict (409)
        ├─ DB transaction
        │    ├─ UPDATE pickup_requests … WHERE id=? AND status=?    → 0 rows ⇒ Conflict
        │    ├─ INSERT pickup_status_logs
        │    ├─ NotificationService::toParents($pickup, …)
        │    └─ AuditService::log('UPDATE_PICKUP' / 'COMPLETE_PICKUP', …)
        └─ return PickupView (DTO)
```

## 5. Production Dependency Manifest

กติกา: ติดตั้งเมื่อ phase ที่ใช้งานจริงเริ่ม — **ห้ามติดตั้งล่วงหน้าเพื่อ "เตรียมอนาคต"**

### PHP extensions (ใน Docker image)
| Extension | สถานะ | ใช้ทำอะไร |
|---|---|---|
| `intl`, `mbstring`, `json` | NOW ✅ | CodeIgniter 4 บังคับ (Time, Language, validation) |
| `mysqli` | NOW ✅ | MySQL 8.4 driver |
| `opcache` | NOW ✅ | performance |
| `gd` (jpeg, png, webp, freetype) | NOW ✅ (ติดตั้งใน image แล้ว ใช้งานจริงใน media phase) | ย่อรูป ≤1600px, thumbnail, ลบ EXIF ด้วยการ re-encode — เลือก **GD** แทน Imagick (เบากว่า, attack surface เล็กกว่า, พอสำหรับ JPEG/PNG/WebP) |
| `sodium` | built-in | random tokens / ไม่ต้องติดตั้งเพิ่ม |
| Argon2id (`PASSWORD_ARGON2ID`) | NOW ✅ | ตรวจตอน `docker build` แล้ว |

### Composer — require
| Package | สถานะ | เหตุผล |
|---|---|---|
| `php ^8.3` (+ `config.platform.php = 8.3.0`) | NOW ✅ | lock dependency ให้ตรง PHP production |
| `codeigniter4/framework ^4.7` (lock 4.7.4) | NOW ✅ | framework (ADR-001) |
| ULID: `symfony/uid` | PLANNED (media phase) | `media_files.public_id` (ADR-015) — ไม่ติดตั้งจนกว่าจะสร้างตาราง media |
| Auth library | **ไม่มี** | ADR-017 — Custom AuthService ใช้ของ CI4 + PHP core |
| Image library (Intervention ฯลฯ) | **ไม่มี** | ใช้ GD ผ่าน `ImageProcessor` ของเราเอง |
| `aws/aws-sdk-php` หรือ Flysystem S3 | FUTURE | เฉพาะเมื่อย้ายไป Object Storage (ADR-003) |
| Redis client / queue | FUTURE | ADR-011 |

### Composer — require-dev
| Package | สถานะ | เหตุผล |
|---|---|---|
| `phpunit/phpunit ^10.5` | NOW ✅ | unit / feature tests |
| `fakerphp/faker` | NOW ✅ (มากับ appstarter) | factories สำหรับ test/seed ข้อมูลสมมติ |
| `mikey179/vfsstream` | NOW ✅ (มากับ appstarter) | test ระบบไฟล์ (StorageInterface) |
| Static analysis (PHPStan) / code style (php-cs-fixer) | PLANNED (Phase 1) | เพิ่มเมื่อเริ่มมีโค้ด business — ตัดสินพร้อม CI รอบถัดไป |

### Frontend
| Tool | สถานะ |
|---|---|
| Vanilla JS (ES modules) + CSS | NOW — ไม่มี framework, ไม่มี build step (ADR-007) |
| npm | ไม่ใช้ใน foundation (เพิ่มได้เฉพาะ tooling เช่น linter ถ้าจำเป็น) |
