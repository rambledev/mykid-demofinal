# Architecture Decision Log

รูปแบบ: Context → Decision → Alternatives → Consequences · สถานะ: `Locked` (ยืนยันโดยเจ้าของโครงการ), `Accepted` (ตาม stack/ข้อกำหนดที่ล็อก), `Proposed` (ข้อเสนอเชิงเทคนิค รอทีมตรวจ)

> Revision 0.4 (2026-10-10): **ADR-020** — พัฒนาต่อจากโปรเจกต์นี้ (`mykid-demofinal`) ด้วย PHP ธรรมดา + MariaDB → ADR-001, ADR-002, ADR-014 เป็น `Superseded`
> Revision 0.3 (2026-10-07, Phase 0.5): ADR-008/009/010/012/013/015 → Accepted/Locked, ADR-014 Locked (repo ใหม่), ADR-017 Accepted (Custom AuthService), ADR-018 (multi-role credential), ADR-019 (FK RESTRICT)
> Revision 0.2 (2026-10-07): ล็อก Image Retention แบบลบไฟล์อัตโนมัติ (ADR-006), ล็อก Authentication (ADR-016), ระบุ MySQL 8.4 LTS เป็น Production DB, แยก Pending Business / Technical

---

### ADR-001 — CodeIgniter 4 เป็น framework ของ Production · `Superseded by ADR-020`
- **Context**: ทีมคุ้น PHP, Demo เป็น procedural PHP, ต้องการ framework เบา deploy ง่ายบน Docker/Coolify
- **Decision**: CodeIgniter 4 (latest 4.x) + PHP 8.3 สร้าง repo ใหม่ ไม่ wrap โค้ด Demo
- **Alternatives**: Laravel (ecosystem ใหญ่ แต่หนักกว่าและ convention มาก), ต่อยอด Demo (ไม่มี DB/test/security layer — ไม่เหมาะ production)
- **Consequences**: ต้องสร้าง authorization layer เอง (CI4 ไม่มี policy ในตัว) — ออกแบบไว้ใน [authorization.md](authorization.md)

### ADR-002 — MySQL 8.4 LTS เป็นฐานข้อมูล default · `Superseded by ADR-020`
- **Context**: Stack ให้เลือก MySQL 8.x หรือ MariaDB
- **Decision**: **MySQL 8.4 LTS**
- **เหตุผล**: CHECK constraint บังคับจริง, generated column (ใช้ทำ unique ที่มี NULL เช่น `active_flag`), JSON (audit metadata), window functions, รองรับยาว (LTS), Coolify มี one-click MySQL + backup, CI4 driver `MySQLi` รองรับเต็ม
- **Alternatives**: MariaDB 11 — เข้ากันได้เกือบทั้งหมด แต่ JSON/generated column มีรายละเอียดต่างกัน และทีมต้องทดสอบสองแบบ; PostgreSQL — ดีมากแต่อยู่นอก stack ที่ล็อก
- **Consequences**: ใช้ `utf8mb4_0900_ai_ci`; dev/staging/prod ใช้ MySQL 8.4 เหมือนกันทั้งหมด
- **หมายเหตุ**: MariaDB ที่สร้างไว้ใน Coolify ช่วงทดลอง **ไม่ถือเป็น Production Database** (ไม่มีการเปลี่ยนแปลง Coolify ในรอบ Architecture นี้)

### ADR-003 — Local Storage ในระยะแรก ผ่าน StorageInterface · `Accepted`
- **Context**: ภาพประมาณ 0.7 GB/เดือน → ประมาณ 5 GB เป็น **Initial Capacity Estimate (ไม่ใช่ Hard Limit)** เพราะไฟล์ถูกลบอัตโนมัติที่ 6 เดือน; มี app node เดียว
- **Decision**: `LocalStorage` บน Docker volume นอก webroot + `X-Accel-Redirect`; โค้ดธุรกิจเรียกผ่าน `StorageInterface`
- **Alternatives**: S3-compatible ตั้งแต่แรก (เพิ่มค่าใช้จ่าย/ความซับซ้อน, presigned URL leak window)
- **Consequences**: backup volume เอง; disk alert 70/80/90%; ย้ายไป S3 ได้ด้วย `media:migrate-disk` เมื่อมีหลาย node หรือ disk ใกล้เต็ม

### ADR-004 — รับ-ส่งใช้ Polling · `Accepted`
- **Context**: ต้องเห็นสถานะใหม่ใน 3–5 วินาที, ผู้ใช้พร้อมกัน < 100 หน้าในช่วงพีค
- **Decision**: Polling 4 วินาที ผ่าน endpoint เฉพาะ + `ETag`/304, หยุดเมื่อแท็บซ่อน (พิสูจน์แล้วใน Demo: อัปเดตภายใน ~3 วินาที)
- **Alternatives**: WebSocket (ต้องมี process/infra เพิ่ม + sticky/pubsub), SSE (ถือ connection ค้างกับ php-fpm worker — ไม่เหมาะกับ FPM)
- **Consequences**: ~18 req/s ช่วงพีค (เบา); ทางเปลี่ยนเมื่อเกิน ~500 หน้าเปิดพร้อมกัน → SSE ผ่าน service แยก หรือ WebSocket

### ADR-005 — CCTV แยก Media Server · `Accepted`
- **Context**: Tapo C200C ส่ง RTSP (browser เล่นไม่ได้), credential กล้องต้องไม่รั่ว, วิดีโอกิน bandwidth/CPU
- **Decision**: Media Server แยก (MediaMTX หรือเทียบเท่า) แปลง RTSP → HLS/WebRTC; CI4 ถือ metadata + สิทธิ์ + token 5 นาที; RTSP/credential อยู่ที่ Media Server เท่านั้น
- **Alternatives**: PHP proxy วิดีโอ (กิน worker/bandwidth, ไม่ scale), cloud ของ Tapo (ไม่มี API ฝังเว็บแบบควบคุมสิทธิ์ได้)
- **Consequences**: ตำแหน่ง Media Server (edge vs cloud+VPN) = PENDING P6

### ADR-006 — Image Retention Policy · `Locked`
- **Context**: Requirement ที่ลูกค้ายืนยัน — รูปภาพทุกประเภท (Portfolio, Activity, Food Menu และ user/school-generated media อื่น) เก็บไฟล์จริงไม่เกิน 6 เดือน; ประวัติทางธุรกิจต้องอยู่ครบ
- **Decision**:
  - เก็บ Physical Image สูงสุด 6 เดือน (`expires_at = uploaded_at + 6 months`, ทุก category, ไม่มี NULL)
  - **ลบ Physical File อัตโนมัติ** เมื่อครบ 6 เดือน ผ่าน Scheduled Job `php spark media:cleanup-expired` (วันละครั้ง)
  - **Database Record ของภาพไม่ถูกลบ** (`status: available → deleted`, `deleted_reason='retention'`, `deleted_at`)
  - **Business Record ไม่ถูกลบ** (ผลงาน/กิจกรรม/เมนูอยู่ครบ, แสดง placeholder "รูปภาพหมดอายุการจัดเก็บ"); ไม่มี `ON DELETE CASCADE`
  - Cleanup ต้อง **Idempotent** (ไฟล์ไม่พบ = สำเร็จ, conditional update, lock กันรันซ้อน) และล้มเหลว **ทีละไฟล์** โดยไม่หยุดไฟล์อื่น พร้อมตรวจสอบรายการที่ไม่สำเร็จได้
  - **Audit Log** บันทึกทุกการลบ (`MEDIA_RETENTION_DELETE`, actor = system) + สรุปต่อรอบ
  - **Backup Retention เป็นคนละ Policy** กับ User-facing Retention (snapshot ภาพ 30 วัน; restore ห้ามนำไฟล์ครบกำหนดกลับเข้า Production)
  - UI แสดงภาพเฉพาะ `status='available' AND expires_at > NOW()` — ไม่มี broken image, ไม่เปิด URL ของไฟล์ที่ลบแล้ว
- **เหตุผล**: ลด Storage Cost; รักษา Database History; สอดคล้อง Requirement ของลูกค้า; ลดความเสี่ยง (PDPA) จากการเก็บภาพเด็กเกินความจำเป็น
- **Alternatives ที่ไม่เลือก**: Admin กดลบเอง (แบบ Demo — ไฟล์ค้างเกินกำหนดถ้าไม่มีคนกด), ลบทั้งไฟล์และ record (เสียประวัติ), เก็บไฟล์ไม่มีกำหนด (ขัด requirement)
- **Consequences**: ต้องมี monitoring ของ job (exit code, รายการล้มเหลว) และ `media:reconcile` รายสัปดาห์; หน้า Admin Storage เป็นหน้าติดตาม ไม่ใช่หน้าลบ — รายละเอียดใน [data-retention.md](data-retention.md)

### ADR-007 — ไม่ใช้ SPA · `Accepted`
- **Context**: Demo ที่ลูกค้ายืนยันเป็น server-rendered + JS เล็กน้อย; ทีม PHP; SEO ไม่สำคัญแต่ความเรียบง่ายสำคัญ
- **Decision**: CI4 Views (server-rendered) + Vanilla JS modules สำหรับส่วน interactive
- **Alternatives**: React/Vue SPA (build pipeline, state ซ้ำซ้อน, ต้องทำ API ทุกอย่าง, authorization ต้องทำซ้ำฝั่ง client)
- **Consequences**: API ทำเฉพาะที่จำเป็น; หน้าใหม่ใช้ pattern เดียวกัน; FUTURE mobile app ใช้ `/api/v1` ได้

### ADR-008 — Media: ตารางกลาง + join table ต่อชนิด owner · `Locked`
- **Context**: ต้องรองรับ portfolio/activity/food และชนิดใหม่ในอนาคต พร้อม referential integrity
- **Decision**: `media_files` (metadata + retention + scope) + `activity_images`/`food_images`/`portfolio_images` (FK จริงทั้งสองข้าง, `media_file_id` UNIQUE)
- **Alternatives**: polymorphic `owner_type/owner_id` (ไม่มี FK, ภาพกำพร้า/ผูกผิดชนิดได้), ตารางภาพแยกทั้งก้อนต่อชนิด (retention/สถิติ/หน้า Storage ต้อง UNION หลายตาราง)
- **Consequences**: ชนิดใหม่ = 1 enum value + 1 join table (migration เล็ก)

### ADR-009 — RBAC จาก DB + Scope จาก assignment tables · `Locked`
- **Context**: 5 role, ต้องรองรับ role ใหม่, ห้าม hard-code ใน controller
- **Decision**: `roles(scope_level)` + `permissions` + `role_permissions` (seeded) ตัดสิน "action"; `user_roles(school_id)`, `teacher_classrooms`, `parent_students` ตัดสิน "ข้อมูลชิ้นไหน"; Policy class ต่อ entity
- **Alternatives**: role check ใน controller (กระจาย, ผิดง่าย — ปัญหาที่ Demo ต้องคุมด้วย test จำนวนมาก), CodeIgniter Shield groups (global ไม่ผูกโรงเรียน)
- **Consequences**: AccessContext ต้อง rebuild เมื่อ assignment เปลี่ยน (`access_version`)

### ADR-010 — ไม่มี Repository layer แยก · `Accepted`
- **Decision**: ใช้ CI4 Model เป็น repository (+ `forContext()` scope) และ Service เฉพาะที่มีกติกาข้ามตาราง
- **เหตุผล**: ลด boilerplate; ขนาดระบบไม่ต้องการ abstraction เพิ่ม
- **Consequences**: unit test ของ Service ใช้ test DB (CI4 `DatabaseTestTrait`) แทน mock repository

### ADR-011 — ไม่มี Redis / Queue ใน MVP · `Accepted`
- **Decision**: session ใน MySQL, upload ย่อภาพ synchronous, cron สำหรับงานกลางคืน
- **Trigger ที่จะเพิ่ม**: upload p95 > 3 วินาที, dashboard p95 > 300 ms หลังทำ index แล้ว, หลาย app container
- **Consequences**: infra เหลือ 3–4 container, ดูแลง่าย

### ADR-012 — เก็บเวลาเป็น Asia/Bangkok · `Locked`
- **Context**: ผู้ใช้ทั้งหมดอยู่ไทย (UTC+7 ไม่มี DST); business date (วันเช็คชื่อ, วันรับ-ส่ง) ต้องเป็นวันไทย
- **Decision**: `DATETIME` เวลาไทย, connection `time_zone='+07:00'`, PHP `date.timezone=Asia/Bangkok`; วันธุรกิจเป็น `DATE`
- **Alternatives**: UTC ทั้งหมด (มาตรฐานสากล แต่ต้องแปลงทุก query ตามวัน — เสี่ยง bug ช่วง 00:00–07:00)
- **Consequences**: ถ้าขยายต่างประเทศต้อง migrate เป็น UTC + timezone ต่อโรงเรียน (ยอมรับได้ ณ scope นี้)

### ADR-013 — Notification แบบ fan-out on write · `Locked`
- **Decision**: สร้าง `notification_recipients` ต่อผู้รับตอน publish
- **เหตุผล**: unread count เป็น index lookup, ส่งเฉพาะคน (รับ-ส่ง) ง่าย, ปริมาณต่ำ (หลักร้อยแถว/ประกาศ)
- **Alternatives**: fan-out on read (คำนวณ target ตอนอ่าน — query ซับซ้อน, read state ต้องมีตารางแยกอยู่ดี)
- **Consequences**: ประกาศทั้งโรงเรียนใหญ่ ๆ ใน FUTURE ควร insert แบบ batch/queue

### ADR-014 — Production เป็น repository ใหม่ แยกจาก Final Demo · `Superseded by ADR-020`
- **Context**: Final Demo (procedural PHP, JSON store, mock data, PIN plain) ผ่านการยืนยันจากลูกค้าแล้ว แต่สถาปัตยกรรมไม่เหมาะกับ production
- **Decision**:
  - **Final Demo** (repo `mykid`): PHP procedural/demo — ใช้เป็น Business/UX reference, regression baseline และ Customer Demo เท่านั้น; **ห้ามแก้เพื่อสร้าง Production**, ห้าม refactor/ย้าย/ลบโค้ด Demo
  - **Production** (repo ใหม่ `mykid-production`, local: `~/Desktop/product/mykid-production`, remote GitHub สร้างเมื่อได้รับอนุญาต): CodeIgniter 4 · PHP 8.3+ · MySQL 8.4 LTS · Nginx + PHP-FPM · Docker · Coolify · GitHub
  - `docs/production/` อยู่ใน repo Demo ชั่วคราว → ย้ายไป repo Production ใน commit แรก (แหล่งเดียว ไม่ duplicate)
- **Alternatives**: refactor Demo ให้เป็น production (ความเสี่ยงโค้ด Demo หลุดเข้า production, history ปนกัน), monorepo (ปน Demo กับ Production deploy)
- **Consequences**: Demo deploy (Apache image) และ Production deploy (nginx + php-fpm) แยกกันใน Coolify; Demo ยังเปิดให้ลูกค้าดูได้เหมือนเดิม

### ADR-015 — Internal BIGINT id + ULID เฉพาะไฟล์ภาพ · `Locked`
- **Decision**: ใช้ `id` ตัวเลขทั่วระบบ (ป้องกัน IDOR ด้วย authorization) และ `public_id` ULID เฉพาะ `media_files` (URL ถูกฝังใน `<img>`/อาจถูกแชร์ และเป็นชื่อไฟล์)
- **Alternatives**: UUID ทุกตาราง (index ใหญ่ขึ้น, debug ยาก — ไม่ได้แทน authorization อยู่ดี)

### ADR-016 — Authentication แยกตามกลุ่ม Role · `Locked`
- **Decision**:
  - Parent / Teacher: **เบอร์มือถือ + PIN 6 หลัก** (UX ที่ยืนยันจาก Demo) + remember-me บนมือถือ
  - Super Admin / School Admin / Executive: **Account (username) + Password** (≥ 10 ตัว)
  - ทั้งหมด: Argon2id + Pepper, Login attempt limit (throttle ต่อ IP + ต่อ identifier), Account lockout, Session rotation, CSRF, Security headers
- **Consequences**: identifier อยู่ใน `user_credentials` (ADR-018); หน้า login 2 ฟอร์ม (`/login`, `/staff/login`); FUTURE 2FA สำหรับกลุ่ม Admin

### ADR-017 — Custom AuthService (ไม่ใช้ CodeIgniter Shield) · `Accepted` (ปิด T1)

| เกณฑ์ | CodeIgniter Shield | **Custom AuthService (เลือก)** |
|---|---|---|
| Mobile + PIN | ต้องเขียน authenticator เอง (identity หลักคือ email/username) | ออกแบบตรงกับ `user_credentials.type = mobile_pin` |
| Username + Password | รองรับ | รองรับ (`account_password`) |
| Multi-role + school scope | groups/permissions เป็น global ไม่ผูกโรงเรียน → ต้องปิดทิ้งแล้วใช้ RBAC ของเราอยู่ดี | ใช้ RBAC/scope ของเรา (ADR-009) ตรง ๆ |
| ตาราง | สร้างชุดตารางของตัวเอง (`users`, `auth_identities`, `auth_groups_users`, `auth_permissions_users`, `auth_logins`, `auth_remember_tokens`, …) ซ้อนกับ schema ของเรา | ใช้ schema ใน database.md เท่านั้น |
| Pepper + pepper_version | ไม่มีในตัว (ต้องแทรก hasher) | ออกแบบไว้แล้ว |
| Session / lockout / CSRF | session + throttle มีให้; lockout ต่อบัญชีต้องเพิ่ม; CSRF เป็นของ CI4 อยู่แล้ว | CI4 Session (DatabaseHandler) + Throttler + lockout ต่อ credential + CSRF ของ CI4 |
| Complexity / Maintainability | ต้องเข้าใจ + override library ภายนอกหลายจุด, อัปเกรดอาจชน customization | โค้ดเล็ก (login, lockout, remember-me, reset) ทั้งหมดอยู่ในทีม แต่ **ต้องมี security test ครบ** |

- **Decision**: `App\Services\AuthService` บาง ๆ บน CI4 Session + CI4 Throttler + `password_hash/verify` (Argon2id) + `hash_hmac` pepper — **ไม่เพิ่ม Composer dependency สำหรับ auth**
- **Consequences**: Phase ที่สร้าง auth ต้องมี test: lockout, throttle, session regenerate, remember-me rotation, reset/must_change, role ↔ credential gating; review โดยคนที่ 2 บังคับ

### ADR-018 — Multi-role user: credential แยกตามชนิด login · `Locked` (ปิด M2)
- **Problem**: คนเดียวอาจเป็น Teacher + School Admin (หรือ Parent + Teacher) แต่ ADR-016 กำหนดวิธี login ตามกลุ่ม role
- **ตัวเลือกที่พิจารณา**:
  - A. หนึ่ง user มี credential แบบเดียว → Teacher + School Admin เลือกได้แค่ PIN หรือ password → ขัด ADR-016 ข้อใดข้อหนึ่ง
  - **B. credential แยกตาม login type** (เลือก) → 1 user มี `mobile_pin` และ/หรือ `account_password`
  - C. แยก account ต่อกลุ่ม role → ข้อมูลบุคคลซ้ำ, audit ตามตัวคนยาก, เบอร์โทรเดียวกันใช้ซ้ำไม่ได้
  - D. role เป็นตัวกำหนดวิธี login (ไม่มีโครงสร้างรองรับ) → คนที่มีสองกลุ่ม role ก็ยังต้องมีสอง credential = กลายเป็น B
- **Decision**: **B + กติกาจาก D**: ตาราง `user_credentials` (สูงสุด 1 ต่อชนิดต่อ user); `roles.auth_type` บอกว่า role ใช้ credential ชนิดใด; session ที่ login ด้วย PIN โหลดเฉพาะ role TEACHER/PARENT, session ที่ login ด้วย password โหลดเฉพาะ role กลุ่ม Admin
- **เหตุผล**: คง ADR-016 ครบ, สิทธิ์ระดับผู้ดูแลต้องผ่าน password เสมอ (PIN 6 หลักไม่เคยเปิดสิทธิ์ Admin), คนเดียว = user เดียว (audit/แชท/แจ้งเตือนตามตัวคน), lockout แยกต่อ credential
- **Consequences**: `users` ไม่มีคอลัมน์ credential; RoleAssignmentService ต้องสร้าง credential ที่ตรงกับ `roles.auth_type` ก่อนมอบ role; UX การสลับระหว่าง Teacher กับ Parent (ใน session PIN เดียวกัน) = **P5 ยัง Pending เฉพาะเรื่อง UX** (ไม่กระทบ schema)

### ADR-019 — FK Policy: ON UPDATE RESTRICT ON DELETE RESTRICT · `Locked` (ปิด HIGH-1)
- **Context**: MySQL 8.4 ไม่อนุญาต `CASCADE`/`SET NULL` บน FK ที่คอลัมน์เป็นต้นทางของ STORED generated column (`user_roles.scope_key`, `camera_permissions.scope_key`); id ในระบบไม่ควรเปลี่ยนอยู่แล้ว; business record ห้ามหายจากการลบอื่น
- **Decision**: ทุก FK = `ON UPDATE RESTRICT ON DELETE RESTRICT`; ไม่มี CASCADE ในระบบ; generated columns เป็น STORED
- **Consequences**: การ "ลบ" ทั้งหมดเป็น soft delete / status; การลบจริงตาม PDPA เป็นกระบวนการ manual ที่ลบลูกก่อนแม่อย่างตั้งใจ

### ADR-020 — PHP ธรรมดา + MariaDB, พัฒนาต่อจาก `mykid-demofinal` · `Locked` (2026-10-10)
- **Context**: เจ้าของโครงการต้องการพัฒนาโปรเจกต์ `mykid-demofinal` (สำเนา Final Demo v1.0, เหลือเฉพาะ Package A และโรงเรียนเดียว ศพด.บ้านโพนสูง) ต่อโดยตรง ด้วย PHP ธรรมดาและฐานข้อมูล MariaDB
- **Decision**:
  - ภาษา/โครงสร้าง: **PHP 8.3+ procedural ไม่ใช้ framework** (ไม่มี Composer) — คงโครง `core/` + หน้าบาง ๆ ต่อ role เดิม
  - ฐานข้อมูล: **MariaDB 11.4 LTS** ผ่าน PDO (`pdo_mysql`), utf8mb4, `sql_mode` STRICT, time zone +07:00
  - ขั้นที่ 1 (เสร็จแล้ว): ย้าย JSON store → MariaDB **ตามโครงข้อมูลเดิม** — 1 ตาราง/ชนิดข้อมูล (27 ตาราง, `database/schema.sql`), `core/store.php` คง API เดิม (`store_rows`, `store_mutate`, ...) จึงไม่ต้องแก้หน้าเว็บ
  - Local dev: MariaDB บนเครื่อง (Homebrew `mariadb@11.4`, port 3307) · Deploy: Docker (Apache image) + MariaDB service ใน Coolify
- **Alternatives**: CodeIgniter 4 + MySQL 8.4 ใน repo ใหม่ (ADR-001/002/014 — ต้องเขียนใหม่ทั้งหมด), ออกแบบ schema ตาม [database.md](database.md) ทันที (งานใหญ่ ต้องเขียนหน้าเว็บและระบบสิทธิ์ใหม่)
- **Consequences**:
  - เอกสารอื่นใน `docs/production/` ที่อ้างถึง CodeIgniter 4 / MySQL 8.4 / repo `mykid-production` เป็นแบบเดิมก่อน ADR-020 — ใช้เป็นแนวทางด้าน business/security ได้ แต่ต้องปรับรายละเอียดเทคนิคเมื่อทำแต่ละเรื่อง
  - ขั้นต่อไป (ยังไม่ทำ): FK + composite FK ตาม [database.md](database.md), PIN/password แบบ hash (ADR-016), เลิก seed/roll-day อัตโนมัติ, เขียนข้อมูลด้วย SQL ตรงต่อแถวแทน diff ทั้งชุด

## Locked Decisions (สรุป)
**PHP 8.3+ (ไม่ใช้ framework) · MariaDB 11.4 LTS ตาม ADR-020** · Docker + Coolify · Server-rendered HTML + Vanilla JS · Asia/Bangkok ·
RBAC · School Isolation 2 ชั้น · Private Storage + X-Accel-Redirect · Local Storage ก่อน / S3 อนาคต · CCTV แยก Media Server ·
Polling (MVP) / Redis-WebSocket (Future) · Audit Log · Backup/Restore · PDPA considerations ·
**Authentication ตาม ADR-016 + ADR-017 + ADR-018** · **Image Retention ตาม ADR-006** · **FK RESTRICT ตาม ADR-019** · **พัฒนาต่อใน `mykid-demofinal` ตาม ADR-020**

## Pending Business Confirmation (ห้ามเดาแทนลูกค้า)

| # | เรื่อง | ทางเลือก | ข้อเสนอ (ถ้าลูกค้าไม่มีความเห็น) | Architecture รองรับอย่างไร |
|---|---|---|---|---|
| P1 | Classroom Status | เอา / ไม่เอา | ยืนยันกับลูกค้า (มีใน Demo) | `classroom_status_logs` แยกอิสระ |
| P2 | Development (พัฒนาการ) | เอา / ไม่เอา | ยืนยันกับลูกค้า (มีใน Demo) | `development_assessments` แยกอิสระ |
| P3 | Health / Eating details | ระดับรายละเอียดสุขภาพ, บันทึกการกินต่อมื้อ | ตาม Demo | คอลัมน์ nullable + `food_intakes` แยกอิสระ |
| P4 | Academic Year / Promotion | ไม่เก็บ / `academic_year` / enrollment เต็ม | `academic_year` + snapshot | nullable column; FUTURE `student_enrollments` |
| P5 | Multi-role user — **เหลือเฉพาะ UX** การสลับ Teacher ↔ Parent ใน session PIN (schema ปิดแล้วด้วย ADR-018) | role switcher / แยกหน้าจอ | role switcher | ไม่กระทบ schema |
| P6 | Media Server Location | Edge ที่โรงเรียน / Cloud + VPN | Edge | แยกจาก app อยู่แล้ว |
| P7 | Student Profile Image Retention | อยู่ใต้กฎ 6 เดือน / ยกเว้น | ใต้กฎ 6 เดือนจนกว่าจะยืนยัน | เปลี่ยนเฉพาะ `expires_at` ของ category |
| P8 | Pickup: ยกเลิก / แจ้งซ้ำ / ผู้มีสิทธิ์รับ | ตาม Demo / เพิ่ม | ตาม Demo ใน MVP | state `cancelled` เพิ่มได้, `can_pickup` มีแล้ว |
| P9 | Hosting / Data Location | ในไทย / ต่างประเทศ | ในไทยหรือภูมิภาคใกล้ | ไม่กระทบ design |
| P10 | Initial Data Import | กรอกมือ / CSV | CSV โดย Admin | เพิ่ม command/หน้า import |
| P11 | External Notification | ไม่มี / LINE OA / Web Push / SMS | ไม่มีใน MVP | ต่อจาก `notification_recipients`; OTP ลืม PIN ผูกข้อนี้ |

## Pending Technical (ทีมตัดสินได้)

| # | เรื่อง | ข้อเสนอ |
|---|---|---|
| T2 | Teacher เห็นประวัติเด็กก่อนย้ายห้อง (ผูก P4) | เห็น |
