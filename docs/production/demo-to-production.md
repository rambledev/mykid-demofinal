# Demo → Production Mapping

> **Final Demo เป็น Business / UX Reference — ไม่ใช่ Code Base สำหรับ Production**
> Production สร้างใหม่ด้วย CodeIgniter 4 + MySQL 8.4 LTS ตามเอกสารชุดนี้ ห้ามนำโค้ด Demo (procedural PHP, JSON store, mock data, PIN แบบ plain) มา wrap หรือแก้ต่อ
> Demo ไม่ถูกแก้ไขและคงอยู่ใน repo `mykid` เพื่อใช้เทียบ flow / หน้าจอ / ข้อความ, regression และ Customer Demo — Production อยู่ใน repo `mykid-production` (ADR-014)

## 1. สิ่งที่เปลี่ยนในทุก Feature (Demo Architecture → Production Architecture)

| เรื่อง | Final Demo | Production |
|---|---|---|
| ข้อมูล | JSON file (`storage/demo-state.json`) + `flock`, seed/roll day อัตโนมัติ | MySQL 8.4 relational, migrations, FK, unique, index |
| สิทธิ์ | permission matrix เป็น PHP array + `row_in_scope()` | RBAC ใน DB + AccessContext + Policy + `Model::forContext()` + composite FK |
| Login | เบอร์ + PIN 123456 (plain) ทุก role, หน้าเลือก role แบบ demo | Teacher/Parent: เบอร์ + PIN; กลุ่ม Admin: Account + Password; `user_credentials` แยกตามชนิด (ADR-018), Argon2id + pepper, lockout, session rotation, Custom AuthService (ADR-017) |
| หน้าแรก | Role selection 5 การ์ด (เข้าบัญชี demo อัตโนมัติ) | หน้า login จริง — **ไม่มี** การเข้าบัญชีอัตโนมัติ |
| Real-time | `[data-live]` re-fetch ทั้งหน้า + poll version | endpoint เฉพาะ + ETag/304 (pickup, badge) |
| Logging | `mk_log` | CI4 Logger (JSON stdout) + audit_logs + request_id |

## 2. Feature Mapping

| Demo Feature | Production Domain | Production Tables | Production Service | Notes / Differences |
|---|---|---|---|---|
| **School** (2 ศพด., หน้า Super Admin: รายโรงเรียน, เปรียบเทียบ) | Organization | `schools` | — (CRUD + Policy), `ReportService` (เปรียบเทียบ) | เพิ่ม `code`, `status=suspended`, soft delete; ชื่อจริง: ศูนย์พัฒนาเด็กเล็กบ้านโพนสูง 1 / 2 |
| **Classroom** | Organization | `classrooms`, `teacher_classrooms` | — (CRUD + Policy), `RoleAssignmentService` (มอบหมายครู) | `academic_year` = PENDING P4; composite key `(id, school_id)` กันข้ามโรงเรียน |
| **Student** | Students | `students`, `parent_students` | `StudentService` | Demo ผูกผู้ปกครอง 1:1 ผ่าน `users.student_id` → Production N:M (`parent_students` + relationship, `can_pickup`); `student_code` unique ต่อโรงเรียน; รูปโปรไฟล์ = media (P7) |
| **Teacher** | Identity / Access | `users`, `user_roles` (TEACHER), `teacher_classrooms` | `RoleAssignmentService`, `AuthService` | Demo มีตาราง `teachers` แยกจาก `users` → Production รวมเป็น user + role + assignment (มีประวัติ `ended_at`) |
| **Parent** | Identity / Access | `users`, `user_roles` (PARENT), `parent_students` | `StudentService::linkParent`, `AuthService` | ผู้ปกครองมีลูกหลายคน/เด็กมีผู้ปกครองหลายคนได้; การ์ดต่อเด็ก (รับ-ส่ง) ทำงานจริงตามข้อมูล |
| **Super Admin / School Admin / Executive** | Identity / Access | `users`, `user_roles`, `roles`, `role_permissions` | `RoleAssignmentService` | login ด้วย Account + Password (ADR-016); Executive read-only ด้วย permission ไม่ใช่ if-role |
| **Daily Activity** (ตารางกิจกรรมห้อง) | Daily Operations | `activities` | `ActivityService` | Demo clone กิจกรรมทุกวันอัตโนมัติ → Production ครู/Admin สร้างจริง (FUTURE: template ตารางประจำ) |
| **Activity Images** | Media | `media_files` (category `activity`), `activity_images` | `MediaService`, `ActivityService` | join table มี FK จริง (Demo: `owner_type/owner_id` ใน JSON); ไฟล์ลบอัตโนมัติที่ 6 เดือน |
| **Food Menu** (5 มื้อ) | Daily Operations | `food_menus`, `food_menu_items` | `FoodMenuService` | รายการอาหารเป็นแถว (Demo เก็บ array ใน JSON) |
| **Food Images** | Media | `media_files` (category `food`), `food_images` | `MediaService`, `FoodMenuService` | ระบุมื้อได้ (`meal_type`) |
| Food intake (กินได้ดี/บางส่วน/…) | Daily Operations | `food_intakes` | `DailyCareService` | **PENDING P3** |
| Attendance (เช็คชื่อ) | Daily Operations | `attendance` | `DailyCareService` | Production ตั้ง `checked_in_at` อัตโนมัติเมื่อเปลี่ยนเป็นมาเรียน/สาย (Demo ไม่ตั้ง) |
| Sleep / Health | Daily Operations | `sleep_records`, `health_records` | `DailyCareService` | Health วัดได้หลายครั้ง/วัน; รายละเอียด = PENDING P3 |
| Classroom Status (สถานะห้อง) | Daily Operations | `classroom_status_logs` | `DailyCareService` หรือ CRUD | **PENDING P1** |
| Development (พัฒนาการ) | Students | `development_assessments` | — | **PENDING P2** |
| **Student Portfolio** | Portfolio | `portfolios`, `portfolio_images`, `media_files` (category `portfolio`) | `PortfolioService`, `MediaService` | ตรวจไฟล์ก่อนบันทึกผลงาน (บทเรียนจาก Demo); ผลงานอยู่ถาวร ภาพลบอัตโนมัติที่ 6 เดือน |
| **Stars** (ดาวสะสม + เหตุผล) | Stars | `star_transactions` | `StarService` | Production เป็น **ledger** แก้ด้วย reversal (Demo แก้/ลบ row ได้) |
| Calendar | Calendar | `school_events` | — (CRUD + Policy) | ระบุห้องเป้าหมายได้ |
| Chat | Chat | `conversations`, `conversation_participants`, `messages` | `ChatService` | 1 ห้องต่อเด็ก (เหมือน Demo) + unread ต่อผู้ใช้ |
| **Notifications** (ประกาศ/แจ้งเตือน) | Notification | `notifications`, `notification_recipients` | `NotificationService` | Demo: ประกาศ targeted อ่านจาก scope → Production fan-out ต่อผู้รับ + `read_at`; pickup แจ้งผู้ปกครองรายคน; ช่องทางภายนอก = PENDING P11 |
| **CCTV** (Tapo C200C, 10 กล้อง, mock/HLS/WebRTC) | CCTV | `cameras`, `camera_permissions` | `CameraService` | Demo เก็บ `rtsp_url` (write-only) ใน JSON → Production **ไม่เก็บ RTSP ในแอปเลย** (อยู่ Media Server), `stream_path` แทน; token อายุ 5 นาที (เหมือน Demo); ตำแหน่ง Media Server = PENDING P6 |
| **Pickup / รับ-ส่ง** | Pickup | `pickup_requests`, `pickup_status_logs` | `PickupService` | สถานะ/ETA/ข้อความเหมือน Demo (coming → preparing → waiting → completed, ETA 5/10/15/20/30); Production เพิ่ม **status log ทุกขั้น + ผู้ทำ**, unique active ด้วย DB constraint, atomic transition (409); ยกเลิก/แจ้งซ้ำ/ผู้มีสิทธิ์รับ = PENDING P8 |
| Timeline (ผู้ปกครอง) | Reports | (อ่านจากหลายตาราง) | `ReportService` | ไม่มีตารางของตัวเอง |
| Dashboard / Reports / Compare | Reports | aggregate จาก daily records | `ReportService` | ใช้ index `(school_id, date, status)` |
| **Image Retention** (6 เดือน, placeholder, Admin กดล้างไฟล์) | Media / Retention | `media_files` (`status`, `expires_at`, `deleted_*`, `cleanup_*`), `audit_logs` | `MediaRetentionService` (`media:cleanup-expired`, `media:reconcile`) | **ต่างจาก Demo**: Production **ลบไฟล์จริงอัตโนมัติ** ด้วย scheduled job (Demo ให้ Admin กดเอง); record ภาพ + business record คงอยู่; สถานะเหลือ `available`/`deleted`; job idempotent + failure tracking + audit (ADR-006) |
| Admin Storage Management | Media | `media_files` | `MediaService::stats` | Demo: ปุ่มล้างไฟล์หมดอายุ → Production: หน้าติดตามพื้นที่, ภาพใกล้ครบกำหนด, ผล job, รายการลบไม่สำเร็จ |
| Demo guide / Package B / Package C / หน้าเลือก Package | — | — | — | **ไม่นำมา** (เครื่องมือขาย/ทดสอบ Demo เท่านั้น) |

## 3. ข้อความและ UX ที่ยกมาใช้ได้ตรง ๆ
- ข้อความสถานะรับ-ส่ง: "แจ้งครูแล้ว คุณจะถึงโรงเรียนภายใน XX นาที", "ครูกำลังพานักเรียนไปจุดรับ", "ครูพานักเรียนมาถึงจุดรับกลับบ้านแล้ว กรุณามารับนักเรียนได้เลย", "ส่งมอบนักเรียนเรียบร้อย"
- เมนูชื่อ "รับ-ส่ง" แสดงเฉพาะ Teacher / Parent
- Placeholder ภาพ: "รูปภาพหมดอายุการจัดเก็บ"
- ธีมสีต่อ role, layout console (Admin/Executive/Super Admin) vs app + bottom nav (Teacher/Parent), mobile-first
- ข้อความ error แบบสุภาพ ("กรุณาลองใหม่อีกครั้ง", "ไม่พบข้อมูล")
- ทั้งหมดย้ายเข้า `app/Language/th/` (ไม่ hard-code ใน view)

## 4. Test Knowledge ที่ควรนำมาเป็น Acceptance Criteria
ชุดทดสอบของ Demo (permission 49 + A/B 108 + CCTV 60 + 2 โรงเรียน 13 + media 37 + รับ-ส่ง 47 + flow ใน browser) ใช้เป็นรายการ **กรณีที่ต้องทดสอบ** ใน Production
(ไม่ใช่โค้ดทดสอบ) เช่น ครูห้องอื่น → ปฏิเสธ, ผู้ปกครองเด็กอื่น → ปฏิเสธ, ข้ามขั้นสถานะ → ปฏิเสธ, upload `.php` ปลอม → ปฏิเสธ
