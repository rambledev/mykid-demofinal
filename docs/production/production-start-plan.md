# Production Start Plan

> Production repository: `mykid-production` (ADR-014) — **แยกจาก Final Demo โดยสมบูรณ์**
> Final Demo v1.0 (`docs/final-demo-v1.0.md`) = **Reference Implementation** ของ Business / UX: ใช้ดู flow, ข้อความ, หน้าจอ, กติกาสิทธิ์ และใช้ test cases เป็น acceptance criteria — **ไม่ copy โค้ด, ไม่ rewrite Demo เป็น CI4, ไม่ใส่ Composer / CI4 / Production Docker ลงใน Demo**
> Stack (Locked): PHP 8.3+ · CodeIgniter 4 · MySQL 8.4 LTS · Nginx + PHP-FPM · Docker / Compose · Coolify · GitHub · Asia/Bangkok

```
Browser → Coolify proxy (TLS) → Nginx → PHP-FPM → CodeIgniter 4 → MySQL 8.4
Private media:  PHP authorization → Storage → X-Accel-Redirect
CCTV:           Tapo → RTSP → Media Server → HLS/WebRTC → Browser   (Media Server แยกจาก PHP)
```

## หลักการที่ใช้กับทุก Phase
- MySQL จริงตั้งแต่ Phase 1 — **ไม่มี mock arrays เป็นฐานข้อมูลหลัก**; สร้างตาราง **ตาม domain/phase** (ไม่สร้าง ~40 ตารางรวดเดียว)
- Server-side authorization เสมอ: ไม่เชื่อ `student_id` / `school_id` / `classroom_id` / role จาก frontend (RBAC + school isolation + IDOR protection)
- ข้อมูลที่มีประวัติธุรกิจ → soft delete / inactive; ไม่ hard delete
- ทุก phase จบด้วย: migration ผ่าน CI (MySQL 8.4), test ผ่าน, review ตาม `dev-workflow.md`, deploy staging, ไม่มี regression ของ phase ก่อน
- **ไม่ Over-engineer** ใน MVP: ไม่มี Redis, Queue, WebSocket, S3, CDN, Read replica, Microservices (Future เมื่อ metric ชี้ว่าจำเป็น)

---

## Phase 0.5 — Foundation Closure  *(สถานะ: ทำแล้วบางส่วน — ปิดเงื่อนไขที่เหลือ)*
**Scope**
- ✅ ทำแล้ว: CI4 4.7.4 skeleton, `composer.json/lock` (PHP 8.3 platform), Dockerfile (`app` php-fpm + `web` nginx), `docker-compose.yml` (app/web/MySQL 8.4), `docker-compose.prod.yml`, `/health/live` + `/health/ready`, `.env.example`, CI workflow, PHPUnit 9 tests, `dev-workflow.md`, ADR-014/017/018/019
- ⏳ ที่เหลือ:
  1. **รัน Docker build + `docker compose up` หรือ CI จริงอย่างน้อย 1 ครั้ง** (เครื่อง dev ดิสก์เต็ม — เคลียร์พื้นที่ หรือสร้าง GitHub repo `mykid-production` แล้ว push เพื่อให้ CI รัน)
  2. Commit แรกของ `mykid-production` + ย้าย `docs/production/` ไปไว้ใน repo Production (แหล่งเดียว) + ตั้ง branch protection บน `main`
  3. **Revise เอกสาร Production ให้ตรงกับ Final Demo v1.0** (requirement ล่าสุด):
     - รับ-ส่ง: เปลี่ยนจาก ETA (`coming/waiting`, `eta_minutes`) เป็น `pending → preparing → ready_for_pickup → completed` ไม่มีเวลา — ไฟล์ที่ต้องแก้: `README.md`, `architecture.md` §4.2, `api-architecture.md` §2/§6, `database.md` §10, `erd.md` §6, `demo-to-production.md`
     - Multiple children: `parent_students` ใช้กับ **ทุกหน้า** ของผู้ปกครอง (+ child switcher)
     - In-app notification: ยืนยันชื่อตาราง/คอลัมน์ (`notifications` + `notification_recipients` ↔ Demo `userNotifications`) และรายการ event
     - **เพิ่มการออกแบบใหม่**: Central Admin, **Timeline + Scheduler** (template → daily activity → activity record → media), Food Schedule → Daily Food Record → media, Student Work naming (`student_works` vs `portfolios`)
**Dependencies**: ไม่มี (เริ่มได้ทันที) · ต้องการ: พื้นที่ดิสก์สำหรับ Docker หรือสิทธิ์สร้าง GitHub repo
**Exit Criteria**: Docker image build + `/health/ready` 200 กับ MySQL 8.4 · CI เขียวบน GitHub · docs revise ครบและไม่มี conflict กับ Final Demo v1.0 · repo Production มี commit แรก + branch protection
**ห้ามทำ**: business table/migration, auth feature, UI feature, deploy production
**ความสัมพันธ์กับ Final Demo**: ใช้ Demo v1.0 เป็นแหล่งยืนยัน requirement ในการ revise เอกสาร

## Phase 1 — Database Foundation + Seed
**Scope**
- Migration กลุ่มแรก (เฉพาะที่ Phase 2–3 ต้องใช้): `users`, `user_credentials`, `roles`, `permissions`, `role_permissions`, `user_roles`, `auth_remember_tokens`, `ci_sessions`, `audit_logs`, `schools`, `classrooms`, `students`, `teacher_classrooms`, `parent_students`
- กติกา: FK `ON UPDATE RESTRICT ON DELETE RESTRICT` (ADR-019), composite FK `(x_id, school_id)`, `utf8mb4_0900_ai_ci`, timezone `+07:00`, soft delete ตาม database.md
- Seeder: `RolePermissionSeeder` (ทุก env), `DemoSchoolSeeder` (dev/staging เท่านั้น — 2 โรงเรียนแบบ Final Demo), CLI `auth:create-super-admin`
- Model scope helper `forContext()` + test ข้ามโรงเรียน/ห้อง
**Dependencies**: Phase 0.5 · ตัดสิน **P4 Academic Year / Promotion** และ **T2** ก่อนสร้าง `classrooms` / `students`
**Exit Criteria**: `php spark migrate` / rollback ใน dev ผ่าน · CI รัน migration บน MySQL 8.4 · composite FK ปฏิเสธข้อมูลข้ามโรงเรียน (test) · seed idempotent · ไม่มี seed demo ใน production
**ห้ามทำ**: ตาราง daily / media / pickup / notification / CCTV (ไว้ phase ของมัน), UI, auth flow
**ความสัมพันธ์กับ Final Demo**: ข้อมูล seed สมมติใช้รูปแบบ 2 โรงเรียน/6 ห้อง/บัญชีทดสอบจาก Demo (ไม่ import PIN/ข้อมูล Demo)

## Phase 2 — Authentication + Central Admin
**Scope**
- Custom `AuthService` (ADR-017): Teacher/Parent = **Mobile + PIN 6 หลัก**, Admin กลุ่ม = **Account + Password**; Argon2id + pepper, login attempt limit, lockout ต่อ credential, session rotation, remember-me (PIN), reset โดย Admin, `must_change`
- CSRF (session), security headers, rate limiting, audit `LOGIN*`
- AccessContext + RBAC + Policy base, layouts (console / app) ตาม UX Demo
- **Central Admin** (Super Admin): จัดการโรงเรียน, ผู้ดูแลโรงเรียน (Account + Password), ดูข้อมูลพื้นฐาน — inactive แทนการลบ
**Dependencies**: Phase 1
**Exit Criteria**: security test ครบ (lockout, throttle, session regenerate, remember-me rotation, role ↔ credential gating ADR-018) · PIN/password ไม่มี plain text ใดใน DB/log · matrix test 5 roles × in/out of scope
**ห้ามทำ**: OTP / LINE login / 2FA (Future), business features ของครู/ผู้ปกครอง
**ความสัมพันธ์กับ Final Demo**: ข้อความ/หน้าจอ login และ layout ต่อ role ยึด Demo; **ไม่มี** หน้าเลือกบทบาทอัตโนมัติ (เป็นของ Demo เท่านั้น)

## Phase 3 — School / Classroom / Student
**Scope**: School Admin จัดการห้อง, ครู (มอบหมาย `teacher_classrooms`), นักเรียน, ผูกผู้ปกครอง (`parent_students` many-to-many, `can_pickup`), ย้ายห้อง, inactive/graduated — audit ทุกการเปลี่ยน; (ถ้าตัดสิน P10) import CSV
**Dependencies**: Phase 2 · P4 ตัดสินแล้ว
**Exit Criteria**: Teacher เห็นเฉพาะห้องตน, Admin เฉพาะโรงเรียนตน (test) · ประวัติไม่หายเมื่อย้ายห้อง/ยกเลิกความสัมพันธ์ · ไม่มี hard delete
**ห้ามทำ**: daily records, timeline, media
**ความสัมพันธ์กับ Final Demo**: หน้าจัดการนักเรียน/ครู/ห้องของ Admin ใน Demo เป็น UX reference

## Phase 4 — Timeline + Scheduler
**Scope**
- Admin กำหนด **Timeline template**: school, classroom, activity, schedule (วันในสัปดาห์), start time, end time, status rules
- **Scheduler ทุก 1 นาที** (`php spark timeline:tick` ผ่าน cron ใน scheduler container + `GET_LOCK`) สร้าง daily activity ของวัน และเปลี่ยนสถานะอัตโนมัติ `scheduled → active → completed` ตามเวลา Asia/Bangkok
- **Idempotent** (conditional `UPDATE … WHERE status = :from`, unique `(template_id, date)`), ไม่ประมวลผลซ้ำ, audit (`actor_type = system`)
- Teacher **manual completion** ตาม business rule (บันทึก actor = teacher)
**Dependencies**: Phase 3 · ต้องออกแบบตารางใน Phase 0.5 (timeline templates / daily activities)
**Exit Criteria**: test เวลาขอบ (00:00, ข้ามวัน, รันซ้ำ, scheduler หยุดแล้วกลับมา) · ไม่มีสถานะถอยหลัง · audit ครบ · health ของ scheduler ตรวจได้
**ห้ามทำ**: ผูกรูปกับ timeline template (รูปผูกกับ activity record รายวันเท่านั้น), Queue/Redis
**ความสัมพันธ์กับ Final Demo**: Demo ใช้ตารางกิจกรรมรายวัน + สถานะห้องเรียนแบบครูกดเอง — Production เพิ่ม scheduler อัตโนมัติ (ความต่างที่ตั้งใจ)

## Phase 5 — Teacher Daily Operations
**Scope**: หน้าหลักครู, เช็คชื่อ (`checked_in_at` อัตโนมัติ), การนอน, สุขภาพ, อาหาร (Food Schedule → Daily Food Record), activity record รายวัน, สถานะรายบุคคล (ครูพิมพ์เอง + หมายเหตุ), สถานะห้อง (ถ้า P1 อนุมัติ), ดาว (ledger) — mobile-first
**Dependencies**: Phase 4 · ตัดสิน P1/P2/P3 ก่อนสร้างตารางที่เกี่ยวข้อง
**Exit Criteria**: ครูทำงานได้เฉพาะห้องตน · บันทึกรายวัน upsert ไม่ซ้ำ · audit · UI ผ่าน 320/375 px
**ห้ามทำ**: media upload (Phase 7), notification (Phase 8)
**ความสัมพันธ์กับ Final Demo**: หน้าครู (หน้าหลัก, เช็คชื่อ, อาหาร, การนอน, สุขภาพ, สะสมดาว, สถานะรายบุคคล) = UX reference

## Phase 6 — Parent + Multiple Children
**Scope**: หน้าผู้ปกครองทั้งหมด (หน้าหลัก, ไทม์ไลน์, อาหาร, การนอน, สุขภาพ, ดาว ฯลฯ) อ่านผ่าน `parent_students` — **ลูกหลายคนในทุกหน้า** (child switcher), ไม่เคยเห็นเด็กอื่น
**Dependencies**: Phase 5
**Exit Criteria**: test ผู้ปกครองลูก 2 คน (คนละห้อง) ทุกหน้า · เด็กที่ไม่ได้ผูก → 404 · switcher เก็บใน session ที่ตรวจกับ relation ทุก request
**ห้ามทำ**: เชื่อ child id จาก URL โดยไม่ตรวจ relation
**ความสัมพันธ์กับ Final Demo**: แก้ข้อจำกัด Demo (#1 — หลายลูกเฉพาะรับ-ส่ง/ผลงาน) ให้ครบทุกหน้า

## Phase 7 — Student Work + Media
**Scope**
- Student → Student Work → Media Files (หลายรูป, preview, แก้ไข, soft delete)
- Activity: Timeline → Daily Activity → Activity Record → Media · Food: Food Schedule → Daily Food Record → Media
- `StorageInterface` + LocalStorage, private + `X-Accel-Redirect`, upload security (whitelist, finfo, re-encode, **EXIF removal**, ≤10MB, ≤6 ไฟล์), thumbnail
- **Retention**: `media:cleanup-expired` ลบไฟล์อัตโนมัติที่ 6 เดือน (idempotent + audit), DB record คงอยู่, placeholder; `media:reconcile`; disk alert 70/80/90%
**Dependencies**: Phase 4–6
**Exit Criteria**: test สิทธิ์ (ครูห้องตน, ผู้ปกครองลูกตน รวมลูกคนที่ 2), path traversal, ไฟล์ปลอม, retention job (รันซ้ำ, ไฟล์หาย, storage error)
**ห้ามทำ**: S3 / CDN (Future), ผูกรูปกับ template
**ความสัมพันธ์กับ Final Demo**: เมนู "ผลงานนักเรียน" / "ผลงานของฉัน" + placeholder "รูปภาพหมดเวลาเก็บไฟล์" = reference; Demo ใช้ปุ่ม Admin ล้างไฟล์ → Production อัตโนมัติ (ADR-006)

## Phase 8 — Pickup + Notification
**Scope**
- รับ-ส่ง: Parent **"กำลังไปรับลูก"** (ไม่มีเวลา) → Teacher **"เตรียมกลับบ้าน"** → **"ถึงจุดรับส่งแล้ว"** → **"ส่งมอบนักเรียนแล้ว"**; ต่อเด็ก 1 request/วัน; ห้ามข้ามสถานะ; **transaction + atomic transition** (`UPDATE … WHERE status = :from`); `pickup_status_logs`
- In-app notification (MVP ไม่มี push): `NotificationService` กลาง — create, list, unread count, mark read, mark all; badge สีแดง; polling (ETag) — สร้างทุก state transition สำคัญใน transaction เดียวกัน
- ส่วนอธิบายสถานะ (🔵🟡🟢✅) บนหน้ารับ-ส่ง
**Dependencies**: Phase 6 (parent_students ครบ)
**Exit Criteria**: acceptance cases จาก Final Demo (48 + 27 flow) แปลงเป็น test ของ Production ผ่านครบ · user isolation ของแจ้งเตือน · race test (กดพร้อมกัน)
**ห้ามทำ**: Firebase / APNs / Web Push / WebSocket / Redis / external provider
**ความสัมพันธ์กับ Final Demo**: flow, ข้อความ และ UX ยึด Demo v1.0 ตรงตัว

## หลัง Phase 8
Stars (ถ้ายังไม่ครบใน Phase 5), CCTV (Media Server — ตัดสิน P6 ก่อน), Reports / Dashboard, Calendar, Chat, Central Admin ขยาย — จัดลำดับตามความต้องการลูกค้า

## Pending Decisions ที่ผูกกับ Phase
| Decision | ต้องตัดสินก่อน |
|---|---|
| P4 Academic Year / Promotion, T2 | Phase 1 (classrooms/students) |
| P1 Classroom Status, P2 Development, P3 Health/Eating details | Phase 5 |
| P8 Pickup cancellation / authorized person / แจ้งซ้ำ | Phase 8 |
| P6 Media Server location | ก่อน CCTV |
| P9 Hosting / data location | ก่อน staging/go-live |
| P10 Initial data import | Phase 3 |
| P11 External notification (LINE/Web Push) | หลัง MVP |
