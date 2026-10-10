# Mykid Production — Master Blueprint

> สถานะ: **Phase 0.5 — Foundation Closure (Revision 0.3)** · Production foundation: repo `mykid-production` (ADR-014)
> ขอบเขต: Package A (Multi-school) ที่ลูกค้าเลือกแล้ว
> Stack: **PHP 8.3+ (ไม่ใช้ framework) · MariaDB 11.4 LTS** · Docker · Coolify — ตาม [ADR-020](decision-log.md) (2026-10-10)
>
> ⚠️ ADR-020 แทนที่ ADR-001 / ADR-002 / ADR-014: ส่วนที่อ้างถึง CodeIgniter 4, MySQL 8.4 หรือ repo `mykid-production` ในเอกสารชุดนี้เป็นแบบเดิม — ใช้หลักการด้าน business / security ได้ แต่รายละเอียดเทคนิคต้องปรับตาม ADR-020

เอกสารชุดนี้คือแบบพิมพ์เขียวสำหรับสร้าง Mykid Production **ใหม่ทั้งหมด** ด้วย CodeIgniter 4
Final Demo (procedural PHP + JSON store ใน repo นี้) ใช้เป็น **Business / UX Reference เท่านั้น** —
ห้ามนำโค้ด Demo มา wrap หรือแก้ต่อเป็น Production

---

## 1. ลำดับการอ่าน

| # | เอกสาร | ตอบคำถาม |
|---|---|---|
| 1 | [architecture.md](architecture.md) | ภาพรวมระบบ, ส่วนประกอบ, Demo vs Production, Environment, Observability, MVP vs Future |
| 2 | [database.md](database.md) | ตารางทั้งหมด, คอลัมน์, ชนิดข้อมูล, FK, Unique, Index, Soft delete |
| 3 | [erd.md](erd.md) | ERD (Mermaid) + ความสัมพันธ์ + การตรวจความสอดคล้อง |
| 4 | [authorization.md](authorization.md) | RBAC, Scope (School → Classroom → Student), Policy, Filter, IDOR |
| 5 | [security-model.md](security-model.md) | Authentication, Session, OWASP, Upload, Headers, Secrets, Audit |
| 6 | [storage.md](storage.md) | StorageInterface, Local → Object Storage, การส่งไฟล์แบบมีสิทธิ์ |
| 7 | [data-retention.md](data-retention.md) | ลบไฟล์ภาพอัตโนมัติที่ 6 เดือน, DB ไม่ลบ, cleanup job (idempotent), failure handling, placeholder |
| 8 | [cctv-architecture.md](cctv-architecture.md) | Tapo C200C → RTSP → Media Server → HLS/WebRTC |
| 9 | [api-architecture.md](api-architecture.md) | Web routes vs `/api/v1`, Response/Error format, Status, Rate limit |
| 10 | [folder-structure.md](folder-structure.md) | โครงสร้าง CI4, Service layer, Docker |
| 11 | [performance.md](performance.md) | Bottleneck, Index, Polling, Thumbnail, MVP vs Future |
| 12 | [backup-and-recovery.md](backup-and-recovery.md) | Backup DB/ภาพ, RPO/RTO, Restore drill |
| 13 | [decision-log.md](decision-log.md) | ADR ทุกเรื่องที่มีทางเลือก + Locked / Pending decisions |
| 14 | [demo-to-production.md](demo-to-production.md) | Map feature จาก Final Demo → Domain / Tables / Service / ความต่าง |
| 15 | [dev-workflow.md](dev-workflow.md) | Git / PR / CI, migration (expand → contract), deploy บน Coolify, rollback (app / DB / storage), seed |
| 16 | [production-start-plan.md](production-start-plan.md) | ลำดับ Phase 0.5 → 8, scope / dependencies / exit criteria / สิ่งที่ห้ามทำ, ความสัมพันธ์กับ Final Demo v1.0 ([../final-demo-v1.0.md](../final-demo-v1.0.md)) |

---

## 2. หลักการออกแบบ (Design Principles)

1. **Server-side authorization ทุก request** — ทุกข้อมูลผ่าน `User → Role → School → Classroom → Student`
   ไม่เชื่อ `school_id` / `student_id` จาก URL, hidden input หรือ JSON body
2. **School isolation ถึงระดับฐานข้อมูล** — ทุกตารางธุรกิจมี `school_id` และ composite FK
   ป้องกันข้อมูลข้ามโรงเรียนแม้โค้ดผิดพลาด
3. **Physical image files are automatically deleted after 6 months. Database records are retained.**
   — ระบบลบไฟล์ภาพจริงอัตโนมัติด้วย scheduled job เมื่อครบ 6 เดือน; record ของภาพและ business record ไม่ถูกลบอัตโนมัติ
4. **History ต้องมีจริง** — รับ-ส่งมี status log, ดาวเป็น ledger, ทุกการกระทำสำคัญมี audit log
5. **Private by default** — ไฟล์ภาพนักเรียนไม่มี public URL ส่งผ่าน authorization layer เสมอ
6. **CCTV แยกจาก PHP** — PHP ถือ metadata + สิทธิ์ + token อายุสั้น, Media Server ถือ stream และ credential
7. **ไม่ Over-engineer** — ทุกการตัดสินใจแบ่ง `NOW` / `FUTURE`; ไม่มี Redis, Queue, WebSocket, SPA ในระยะแรก
8. **Thai first, i18n ready** — ข้อความทั้งหมดผ่าน CI4 Language files (`th` เริ่มต้น, `en` อนาคต)

---

## 3. Scope Package A (Production)

| # | Module | หมายเหตุ |
|---|---|---|
| 1–6 | Multi-school, Super Admin, School Admin, Executive, Teacher, Parent | RBAC + scope |
| 7–8 | Classroom, Student | + parent_students, teacher_classrooms |
| 9 | Daily Activity | + activity images |
| 10 | Food Menu | + food images (รายละเอียดการกิน = **PENDING P3**) |
| 11–13 | Attendance, Sleep, Health | daily records ต่อเด็ก |
| 14–15 | Activity Images, Student Portfolio | media + retention |
| 16 | Stars / Star History | ledger ไม่แก้ย้อนหลัง |
| 17 | School Calendar | school_events |
| 18 | Chat | conversation ต่อเด็ก (ผู้ปกครอง ↔ ครู) |
| 19 | Notification | fan-out ต่อผู้ใช้ + read state |
| 20 | รับ-ส่งนักเรียน | coming → preparing → waiting → completed + history |
| 21 | CCTV | 2 โรงเรียน × 5 กล้อง = 10 (DB รองรับมากกว่า) |
| 22–23 | Image Retention 6 เดือน, Admin Storage Management | ระบบลบไฟล์อัตโนมัติ / DB คงอยู่; หน้า Storage = ติดตามพื้นที่ + ผล cleanup |
| 24 | Dashboard / Reports | aggregate จาก daily records |

---

## 4. Demo → Production: สิ่งที่ "นำมา" และ "ไม่นำมา"

| นำมาจาก Demo (Business / UX) | ไม่นำมา (Demo Architecture) |
|---|---|
| Flow ของทุก role, หน้าจอ, ข้อความภาษาไทย, สีตาม role | JSON file store (`demo-state.json`) + `flock` |
| ระดับ scope: platform / school / classroom / student | permission matrix ในไฟล์ PHP array |
| กติการับ-ส่ง 4 สถานะ, ETA 5/10/15/20/30 | การ seed ข้อมูลรายวันอัตโนมัติ (`seed_roll_day`) |
| กติกาภาพ: ≤10MB, ≤6 ไฟล์/ครั้ง, ย่อ ≤1600px, หมดอายุ 6 เดือน, placeholder | การหา row ด้วย loop ทั้งตาราง; ปุ่ม Admin cleanup (Production ลบอัตโนมัติแทน) |
| CCTV: RTSP ห้ามถึง browser, token อายุสั้น, สิทธิ์ตาม role/ห้อง | PIN แบบ plain text ใน mock data |
| ดาวพร้อมเหตุผล, portfolio หมวดหมู่, ปฏิทินพร้อมชุด/ของที่ต้องเตรียม | `mk_render()`, `[data-live]` re-fetch ทั้งหน้า (ปรับเป็น endpoint เฉพาะ) |

---

## 5. Decisions (สรุป — รายละเอียดใน [decision-log.md](decision-log.md))

### 5.1 LOCKED (ยืนยันแล้ว)
| เรื่อง | Decision |
|---|---|
| Stack | **PHP 8.3+ ไม่ใช้ framework · MariaDB 11.4 LTS** · Docker + Coolify — พัฒนาต่อใน `mykid-demofinal` (ADR-020) |
| Frontend | Server-rendered HTML + Vanilla JS (ไม่ใช้ SPA) |
| Timezone | Asia/Bangkok |
| Authorization | RBAC + School Isolation 2 ชั้น (app + DB composite FK) |
| **Authentication** | Parent/Teacher = **เบอร์มือถือ + PIN 6 หลัก**; Super Admin/School Admin/Executive = **Account + Password**; Argon2id + Pepper, login attempt limit, lockout, session rotation, CSRF, security headers (ADR-016) · credential แยกตามชนิด login สำหรับผู้ใช้หลาย role (ADR-018) · Custom AuthService ไม่ใช้ Shield (ADR-017) |
| Database conventions | ทุก FK `ON UPDATE RESTRICT ON DELETE RESTRICT`, ไม่มี CASCADE (ADR-019); BIGINT id + ULID เฉพาะไฟล์ภาพ (ADR-015) |
| Repository | Production = repo ใหม่ `mykid-production`; Final Demo = reference / regression / customer demo เท่านั้น ห้ามแก้เพื่อสร้าง Production (ADR-014) |
| Storage | Private storage, `X-Accel-Redirect`, Local ก่อน → S3/Object Storage อนาคต |
| **Image Retention** | **Physical image files are automatically deleted after 6 months. Database records are retained.** — scheduled job idempotent + audit; backup retention เป็นคนละ policy (ADR-006) |
| CCTV | แยก Media Server, token อายุสั้น, RTSP ไม่ถึง browser |
| Real-time | Polling สำหรับ MVP; Redis/WebSocket = Future |
| อื่น ๆ | Audit log, Backup/Restore, PDPA considerations |

### 5.2 PENDING BUSINESS CONFIRMATION (ห้ามเดาแทนลูกค้า — Architecture รองรับได้ทั้งสองทาง)
| # | เรื่อง | ผลต่อ Architecture |
|---|---|---|
| P1 | Classroom Status (สถานะห้องเรียน) | ตาราง `classroom_status_logs` เพิ่ม/ตัดได้อิสระ |
| P2 | Development (พัฒนาการ) | ตาราง `development_assessments` เพิ่ม/ตัดได้อิสระ |
| P3 | Health / Eating details | `health_records` อยู่ใน scope; รายละเอียดคอลัมน์ + `food_intakes` รอยืนยัน |
| P4 | Academic Year / Promotion | `classrooms.academic_year` nullable + snapshot `classroom_id` ใน record |
| P5 | Multi-role user — เหลือเฉพาะ UX สลับ Teacher ↔ Parent | schema ปิดแล้ว (ADR-018) |
| P6 | Media Server Location | Edge ที่โรงเรียน vs Cloud + VPN — ไม่กระทบ app |
| P7 | Student Profile Image Retention | ค่าเริ่มต้นอยู่ใต้กฎ 6 เดือน; ข้อยกเว้นเปลี่ยนแค่การคำนวณ `expires_at` ของ category นั้น |
| P8 | Pickup: ยกเลิก / แจ้งซ้ำ / ผู้มีสิทธิ์รับ | state machine เพิ่ม `cancelled` ได้; `parent_students.can_pickup` มีแล้ว |
| P9 | Hosting / Data Location | ไม่กระทบ design |
| P10 | Initial Data Import | ถ้าใช่ → เพิ่ม import command/หน้า Admin |
| P11 | External Notification (LINE OA / Web Push / SMS) | ต่อจาก `notification_recipients`; OTP ลืม PIN ผูกกับข้อนี้ |

### 5.3 PENDING TECHNICAL (ตัดสินได้ในทีม ก่อน/ระหว่าง Phase 0)
| # | เรื่อง | ข้อเสนอ |
|---|---|---|
| T2 | Teacher เห็นประวัติเด็กก่อนย้ายเข้าห้องหรือไม่ (ผูกกับ P4) | เห็น |

---

## 6. Final Architecture Review (ตรวจแล้ว)

| ข้อ | สถานะ | อ้างอิง |
|---|---|---|
| Multi-school ถูกต้อง | ✅ `school_id` ทุกตาราง + composite FK + AccessContext | database §2, authorization §2 |
| RBAC ถูกต้อง | ✅ roles/permissions/role_permissions + scope_level, ไม่มี role check กระจาย | authorization §3–4 |
| Parent เห็นเฉพาะลูกตัวเอง | ✅ `parent_students` (active) → `ctx.studentIds` | authorization §5 |
| Teacher เห็นเฉพาะห้องตัวเอง | ✅ `teacher_classrooms` (active) → `ctx.classroomIds` | authorization §5 |
| School isolation | ✅ app layer + DB constraint (defense in depth) | database §2 |
| Pickup มี History | ✅ `pickup_status_logs` append-only + `*_at/*_by` + audit | database §10 |
| Media retention ถูกต้อง | ✅ ไฟล์จริงลบอัตโนมัติที่ 6 เดือน (scheduled, idempotent), record ไม่ลบ, placeholder, backup policy แยก | data-retention, backup-and-recovery |
| CCTV แยก Media layer | ✅ Media Server แยก, token 5 นาที, RTSP ไม่อยู่ใน DB/frontend | cctv-architecture |
| Secrets ไม่อยู่ใน Frontend | ✅ env ใน Coolify, `.env` ไม่ commit, RTSP อยู่ Media Server | security-model §9 |
| Upload security ครบ | ✅ whitelist + finfo + re-encode + strip EXIF + นอก webroot + ULID | security-model §8 |
| Audit log | ✅ append-only, action list, ห้ามข้อมูลอ่อนไหว | security-model §13 |
| Backup | ✅ RPO DB 1 ชม. / ภาพ 24 ชม., RTO 4 ชม., drill รายไตรมาส | backup-and-recovery |
| Docker / Coolify | ✅ nginx + php-fpm + mysql + scheduler, health live/ready | architecture §5 |
| CI4 Structure | ✅ Controllers/Filters/Authorization/Services/Models/Entities/Enums | folder-structure |
| Database Index | ✅ ทุก index ผูกกับ query จริง | database §14 |
| API Architecture | ✅ web vs `/api/v1`, format, status, ETag, versioning | api-architecture |
| Future Scalability | ✅ ทางเปลี่ยนชัด (S3, SSE/WS, Queue, Redis, replica) พร้อม trigger | architecture §8, performance |
| ไม่มี Over-engineering | ✅ ไม่มี Redis/Queue/WebSocket/SPA/Repository layer ใน MVP | decision-log ADR-004/007/010/011 |
