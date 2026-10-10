# Database Design (Production)

> Engine: **MySQL 8.4 LTS**, InnoDB, `utf8mb4` / `utf8mb4_0900_ai_ci`
> ERD: [erd.md](erd.md) · Index rationale: §12 · Retention: [data-retention.md](data-retention.md)

## 1. Conventions

| เรื่อง | กติกา |
|---|---|
| ชื่อ | ตาราง `snake_case` พหูพจน์, คอลัมน์ `snake_case`, FK = `<entity>_id` |
| Primary key | `id BIGINT UNSIGNED AUTO_INCREMENT` (lookup เล็กใช้ `SMALLINT UNSIGNED`) |
| Public id | เฉพาะ entity ที่ต้องอยู่ใน URL ภายนอกและไม่ควรเดาได้: `media_files.public_id CHAR(26)` (ULID) — ที่เหลือใช้ `id` + authorization (ADR-015) |
| เวลา | `DATETIME` ตามเวลา `Asia/Bangkok` (connection `time_zone='+07:00'`), วันทางธุรกิจใช้ `DATE` (ADR-012) |
| Timestamps | `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`, `updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP` |
| Soft delete | `deleted_at DATETIME NULL` + `deleted_by` เฉพาะตารางที่ผู้ใช้ "ลบ" ได้ (§11); daily records ใช้แก้ไข ไม่ลบ; ledger/log เป็น append-only |
| Enum | ใช้ `ENUM` สำหรับค่าที่เป็นกติกาธุรกิจคงที่ (status) — เปลี่ยนผ่าน migration; รายการที่โรงเรียนอาจปรับเองใช้ `VARCHAR` code + config |
| School isolation | ทุกตารางธุรกิจมี `school_id NOT NULL` + **composite FK** `(x_id, school_id)` → parent `(id, school_id)` เพื่อให้ DB ปฏิเสธการผูกข้ามโรงเรียน (§2) |
| Actor | คอลัมน์ `*_by` = `users.id` (FK, `ON DELETE RESTRICT`; ผู้ใช้ไม่ถูกลบจริง ใช้ `status=disabled`) |
| FK actions | **ทุก FK = `ON UPDATE RESTRICT ON DELETE RESTRICT`** (ADR-019) — ไม่มี `CASCADE` / `SET NULL` ในระบบ: id ไม่ถูกเปลี่ยน, record ไม่ถูก hard delete ใน flow ปกติ, การลบไฟล์ภาพไม่มีทางลาม business record; และ MySQL ไม่อนุญาต CASCADE บน FK ที่เป็นคอลัมน์ต้นทางของ STORED generated column (เช่น `scope_key`) |
| Generated columns | `scope_key` / `active_flag` เป็น `STORED` ทุกตัว (ใช้ใน UNIQUE); คอลัมน์ต้นทางที่มี FK ต้องใช้ `RESTRICT` ตามแถวบน |
| Transactions | InnoDB `REPEATABLE READ` (ค่า default ของ MySQL 8.4); เปิด transaction **ใน Service เท่านั้น** (ไม่เปิดใน Controller/Model); 1 business action = 1 transaction สั้น ๆ; เปลี่ยนสถานะแบบ conditional `UPDATE … WHERE status = :from`; deadlock (1213) / lock wait (1205) → retry สูงสุด 3 ครั้งเฉพาะ action ที่ idempotent; งานที่แตะไฟล์ → เขียนไฟล์ก่อน commit DB และลบไฟล์ที่เขียนเมื่อ rollback (upload) / ลบไฟล์ก่อน update DB (retention) |
| Naming (DB) | database: `mykid` (ทุก environment แยก server กัน), test: `mykid_test`; DB users: `mykid_app` (DML) / `mykid_migrator` (DDL) |
| ไม่มี JSON storage | ข้อมูลธุรกิจเป็นคอลัมน์จริงทั้งหมด; `JSON` ใช้เฉพาะ `audit_logs.metadata` (whitelisted keys) |

## 2. School Isolation ที่ระดับฐานข้อมูล

```sql
-- parent tables expose (id, school_id) as a candidate key
ALTER TABLE classrooms ADD UNIQUE KEY uq_classrooms_id_school (id, school_id);
ALTER TABLE students   ADD UNIQUE KEY uq_students_id_school   (id, school_id);

-- child tables reference BOTH columns
ALTER TABLE students ADD CONSTRAINT fk_students_classroom
  FOREIGN KEY (classroom_id, school_id) REFERENCES classrooms (id, school_id)
  ON UPDATE RESTRICT ON DELETE RESTRICT;
ALTER TABLE attendance ADD CONSTRAINT fk_attendance_student
  FOREIGN KEY (student_id, school_id) REFERENCES students (id, school_id)
  ON UPDATE RESTRICT ON DELETE RESTRICT;
-- nullable composite FKs (e.g. cameras.classroom_id) follow MATCH SIMPLE: not checked while a column is NULL (intended)
```

ผล: แม้ bug ใน service จะส่ง `school_id` ผิด DB จะไม่ยอมให้ record ของโรงเรียน A ชี้ไปที่เด็กของโรงเรียน B
(defense in depth — ด่านหลักยังเป็น Authorization layer)

---

## 3. Identity & Access

### 3.1 `users` (บุคคล — 1 คน 1 แถว)
| Column | Type | Null | Default | หมายเหตุ |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK AI | N | | |
| display_name | VARCHAR(100) | N | | เช่น "ครูมะลิ", "คุณแม่ของน้องต้น" |
| phone | VARCHAR(15) | Y | NULL | เบอร์ติดต่อ (normalized) — **ไม่ใช่ที่ตัดสิน login** (login identifier อยู่ใน `user_credentials`) |
| email | VARCHAR(191) | Y | NULL | **UNIQUE** (NULL ซ้ำได้) — ติดต่อ/แจ้งเตือน |
| status | ENUM('active','disabled') | N | 'active' | ปิดบัญชีแทนการลบ (ปิดทุก credential) |
| last_login_at | DATETIME | Y | NULL | |
| access_version | INT UNSIGNED | N | 1 | เพิ่มเมื่อ role/ห้อง/ความสัมพันธ์ผู้ปกครองเปลี่ยน → session rebuild AccessContext |
| locale | VARCHAR(5) | N | 'th' | i18n |
| created_at / updated_at / deleted_at | DATETIME | | | |

Index: `uq_users_email (email)`, `ix_users_status (status)`, `ix_users_phone (phone)`

### 3.1a `user_credentials` (วิธีเข้าสู่ระบบ — 1 user มีได้สูงสุด 1 ต่อชนิด) · ADR-016 / ADR-018
| Column | Type | Null | Default | หมายเหตุ |
|---|---|---|---|---|
| id | BIGINT UNSIGNED PK AI | N | | |
| user_id | BIGINT UNSIGNED FK→users | N | | |
| type | ENUM('mobile_pin','account_password') | N | | `mobile_pin` = Teacher/Parent, `account_password` = Super Admin/School Admin/Executive |
| identifier | VARCHAR(64) | N | | `mobile_pin`: เบอร์มือถือ normalized `0XXXXXXXXX`; `account_password`: username (a–z, 0–9, `.` `_` `-`) |
| secret_hash | VARCHAR(255) | N | | `password_hash(hash_hmac('sha256', secret, AUTH_PEPPER), PASSWORD_ARGON2ID)` — ไม่เคยเก็บ plain |
| pepper_version | TINYINT UNSIGNED | N | 1 | รองรับการหมุน pepper ในอนาคตโดยไม่ต้อง reset ทุกคนพร้อมกัน |
| must_change | TINYINT(1) | N | 0 | หลัง Admin reset |
| failed_count | SMALLINT UNSIGNED | N | 0 | reset เมื่อ login สำเร็จ |
| locked_until | DATETIME | Y | NULL | account lockout **ต่อ credential** |
| changed_at | DATETIME | Y | NULL | ยกเลิก remember-me ของ credential นี้เมื่อเปลี่ยน |
| last_used_at | DATETIME | Y | NULL | |
| disabled_at | DATETIME | Y | NULL | ปิด credential เดียวโดยไม่ปิด user |
| created_at / updated_at | DATETIME | | | |

Unique: `uq_cred_identifier (type, identifier)` (เบอร์/username ซ้ำไม่ได้ในชนิดเดียวกัน), `uq_cred_user_type (user_id, type)`
CHECK: `type <> 'mobile_pin' OR identifier REGEXP '^0[0-9]{9}$'`

**กติกา role ↔ credential (บังคับใน `AuthService` + `AccessContextFactory`)**
- login ด้วย `mobile_pin` → session ได้เฉพาะ role กลุ่ม **TEACHER / PARENT** ของ user นั้น
- login ด้วย `account_password` → session ได้เฉพาะ role กลุ่ม **SUPER_ADMIN / SCHOOL_ADMIN / EXECUTIVE**
- user ที่มี role ทั้งสองกลุ่ม (เช่น Teacher + School Admin) มี 2 credential; สิทธิ์ Admin ต้องเข้าด้วย password เสมอ
- การมอบ role กลุ่ม Admin ให้ user ที่ยังไม่มี `account_password` → ต้องสร้าง credential ก่อน (RoleAssignmentService)
- role ใหม่ในอนาคตต้องระบุว่าใช้ credential ชนิดใด (คอลัมน์ `roles.auth_type`)

### 3.2 `roles`
| Column | Type | หมายเหตุ |
|---|---|---|
| id | SMALLINT UNSIGNED PK | |
| code | VARCHAR(32) UNIQUE | `SUPER_ADMIN`, `SCHOOL_ADMIN`, `EXECUTIVE`, `TEACHER`, `PARENT` |
| scope_level | ENUM('platform','school','classroom','student') | กำหนดว่า assignment ต้องผูกกับอะไร และ scope resolver ใช้กติกาไหน |
| auth_type | ENUM('mobile_pin','account_password') | credential ที่ต้องใช้เพื่อใช้ role นี้ (TEACHER/PARENT = `mobile_pin`, อื่น ๆ = `account_password`) |
| name_th / name_en | VARCHAR(60) | |
| is_system | TINYINT(1) | role ระบบแก้ code/scope ไม่ได้ |
| created_at / updated_at | | |

### 3.3 `permissions`
| Column | Type | หมายเหตุ |
|---|---|---|
| id | SMALLINT UNSIGNED PK | |
| code | VARCHAR(64) UNIQUE | `<module>.<action>` เช่น `student.view`, `pickup.transition`, `media.stats` |
| module | VARCHAR(32) | จัดกลุ่มในหน้าจัดการสิทธิ์ |
| description_th | VARCHAR(150) | |

### 3.4 `role_permissions`
`role_id` FK, `permission_id` FK — **PK (role_id, permission_id)**; ข้อมูลตั้งต้นมาจาก seeder ที่ version-controlled
(ดู matrix ใน [authorization.md](authorization.md#3-permission-matrix-ค่าตั้งต้น))

### 3.5 `user_roles` (แทน `user_schools`)
| Column | Type | Null | หมายเหตุ |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | N | |
| user_id | BIGINT UNSIGNED FK→users | N | |
| role_id | SMALLINT UNSIGNED FK→roles | N | |
| school_id | BIGINT UNSIGNED FK→schools | Y | **NULL เฉพาะ role scope `platform`** (CHECK ในระดับ service + trigger-free rule: validated by `RoleAssignmentService`) |
| granted_by | BIGINT UNSIGNED FK→users | Y | |
| revoked_at | DATETIME | Y | เก็บประวัติแทนการลบ |
| scope_key | BIGINT UNSIGNED **GENERATED** `COALESCE(school_id,0)` STORED | N | ใช้ทำ unique ที่มี NULL |
| created_at / updated_at | | | |

Unique: `uq_user_roles (user_id, role_id, scope_key)` · Index: `ix_user_roles_school_role (school_id, role_id)`

> เหตุผลที่ไม่มี `user_schools` แยก: ความสัมพันธ์ user↔school มีความหมายก็ต่อเมื่อมี role (เป็น admin ของ
> โรงเรียนไหน) จึงรวมไว้ใน assignment เดียว ลดข้อมูลซ้ำซ้อนและความขัดกัน

### 3.6 `teacher_classrooms` (แทน `user_classrooms`)
| Column | Type | หมายเหตุ |
|---|---|---|
| id | BIGINT UNSIGNED PK | |
| user_id | FK→users | ต้องมี `user_roles` = TEACHER ใน `school_id` เดียวกัน (ตรวจใน service) |
| school_id | FK→schools | |
| classroom_id | composite FK `(classroom_id, school_id)`→classrooms | |
| is_homeroom | TINYINT(1) DEFAULT 1 | ครูประจำชั้น / ครูผู้ช่วย |
| assigned_at | DATE | |
| ended_at | DATE NULL | ประวัติการสอน (ไม่ลบ) |
| active_flag | TINYINT GENERATED `IF(ended_at IS NULL,1,NULL)` STORED | |

Unique: `uq_teacher_classroom_active (user_id, classroom_id, active_flag)` · Index: `ix_tc_classroom (classroom_id, active_flag)`

### 3.7 `parent_students`
| Column | Type | หมายเหตุ |
|---|---|---|
| id | BIGINT UNSIGNED PK | |
| user_id | FK→users | ต้องมี role PARENT ใน school เดียวกับเด็ก |
| school_id | FK→schools | |
| student_id | composite FK `(student_id, school_id)`→students | |
| relationship | ENUM('mother','father','grandparent','guardian','other') | |
| is_primary | TINYINT(1) DEFAULT 0 | ผู้ติดต่อหลัก |
| can_pickup | TINYINT(1) DEFAULT 1 | ใช้กับ รับ-ส่ง (แจ้งมารับได้ไหม) |
| ended_at | DATETIME NULL | ยกเลิกความสัมพันธ์โดยไม่ลบ |
| active_flag | TINYINT GENERATED `IF(ended_at IS NULL,1,NULL)` STORED | |
| created_at / updated_at | | |

Unique: `uq_parent_student_active (user_id, student_id, active_flag)` · Index: `ix_ps_student (student_id, active_flag)`
→ รองรับ "ผู้ปกครอง 1 คนมีลูกหลายคน" และ "เด็ก 1 คนมีผู้ปกครองหลายคน" (Demo ทำได้แค่ 1:1)

### 3.8 `auth_remember_tokens`
`id`, `user_id` FK, `user_credential_id` FK→user_credentials (**เฉพาะ `mobile_pin`**), `selector CHAR(24) UNIQUE`, `validator_hash CHAR(64)` (SHA-256), `expires_at`, `last_used_at`,
`created_at` · Index `ix_remember_user (user_id)`, `ix_remember_cred (user_credential_id)`
เหตุผลที่ต้องมี: ครู/ผู้ปกครองใช้บนมือถือทุกวัน — ต้อง "จำการเข้าสู่ระบบ" แบบปลอดภัย (rotate ทุกครั้งที่ใช้, ยกเลิกทั้งหมดเมื่อเปลี่ยน PIN)

### 3.9 `ci_sessions` (CodeIgniter DatabaseHandler)
`id VARCHAR(128) PK`, `ip_address VARCHAR(45)`, `timestamp TIMESTAMP`, `data BLOB` · Index `ix_sessions_ts (timestamp)`
เหตุผล: session อยู่รอดเมื่อ container restart/redeploy และรองรับหลาย app container ในอนาคตโดยไม่ต้องใช้ Redis

---

## 4. Organization

### 4.1 `schools`
| Column | Type | หมายเหตุ |
|---|---|---|
| id | BIGINT UNSIGNED PK | |
| code | VARCHAR(20) UNIQUE | รหัสภายใน เช่น `PONSUNG1` |
| name | VARCHAR(150) | ศูนย์พัฒนาเด็กเล็กบ้านโพนสูง 1 |
| short_name | VARCHAR(60) | ศพด.บ้านโพนสูง 1 |
| province | VARCHAR(60) | |
| address | VARCHAR(255) NULL | |
| phone | VARCHAR(20) NULL | |
| open_time / close_time | TIME NULL | ใช้ตรวจ ETA/รายงาน |
| motto | VARCHAR(150) NULL | |
| status | ENUM('active','trial','suspended') DEFAULT 'active' | suspended = login ไม่ได้ทั้งโรงเรียน |
| created_at / updated_at / deleted_at | | |

### 4.2 `classrooms`
| Column | Type | หมายเหตุ |
|---|---|---|
| id | BIGINT UNSIGNED PK | |
| school_id | FK→schools | |
| name | VARCHAR(60) | อนุบาล 1 |
| academic_year | SMALLINT UNSIGNED NULL | พ.ศ. เช่น 2569 — **PENDING P4** (Academic Year / Promotion); nullable เพื่อให้สร้างได้ทั้งสองแนวทาง |
| capacity | TINYINT UNSIGNED NULL | |
| color_code / emoji | VARCHAR(10) / VARCHAR(16) | UX จาก Demo |
| status | ENUM('active','archived') DEFAULT 'active' | ปิดห้องหลังจบปีการศึกษา |
| created_at / updated_at / deleted_at | | |

Unique: `uq_classrooms_id_school (id, school_id)`, `uq_classrooms_name (school_id, academic_year, name)`
Index: `ix_classrooms_school_status (school_id, status)`

---

## 5. Students

### 5.1 `students`
| Column | Type | หมายเหตุ |
|---|---|---|
| id | BIGINT UNSIGNED PK | |
| school_id | FK→schools | |
| classroom_id | composite FK `(classroom_id, school_id)`→classrooms | ห้องปัจจุบัน |
| student_code | VARCHAR(20) | **UNIQUE (school_id, student_code)** |
| first_name / last_name | VARCHAR(80) | |
| nickname | VARCHAR(40) | น้องต้น |
| gender | ENUM('m','f','x') NULL | |
| birth_date | DATE NULL | ข้อมูลส่วนบุคคล — แสดงเฉพาะ admin/ครูประจำชั้น |
| avatar_media_id | BIGINT UNSIGNED NULL FK→media_files | รูปโปรไฟล์ — อยู่ใต้ retention 6 เดือนตามกฎหลัก; ข้อยกเว้น = **PENDING P7** |
| status | ENUM('active','graduated','transferred','inactive') DEFAULT 'active' | |
| enrolled_at / left_at | DATE NULL | |
| created_at / updated_at / deleted_at | | |

Unique: `uq_students_id_school (id, school_id)`, `uq_students_code (school_id, student_code)`
Index: `ix_students_classroom (classroom_id, status)`, `ix_students_school (school_id, status)`

> ประวัติการย้ายห้อง: daily records ทุกตารางเก็บ `classroom_id` ณ วันที่บันทึก (snapshot) จึงทำรายงานย้อนหลังได้
> โดยไม่ต้องมีตาราง enrollment แยกใน MVP (FUTURE: `student_enrollments` ถ้าต้องการประวัติปีการศึกษาแบบเต็ม)

---

## 6. Daily Operations

ทุกตาราง daily record มีคอลัมน์ scope: `school_id`, `classroom_id` (snapshot), `student_id` + composite FK
และ `recorded_by`, `created_at`, `updated_at`, `updated_by` — **ไม่มี delete** (แก้ไขได้ + audit)

### 6.1 `activities`
| Column | Type | หมายเหตุ |
|---|---|---|
| id | BIGINT UNSIGNED PK | |
| school_id, classroom_id | composite FK | |
| activity_date | DATE | |
| start_time | TIME | |
| title | VARCHAR(100) | |
| detail | VARCHAR(500) NULL | |
| icon_code | VARCHAR(20) NULL | ไอคอนจาก catalog |
| created_by / updated_by | FK→users | |
| created_at / updated_at / deleted_at / deleted_by | | ลบได้ (กิจกรรมใส่ผิด) — soft delete |

Index: `ix_activities_room_date (classroom_id, activity_date, start_time)`, `ix_activities_school_date (school_id, activity_date)`

### 6.2 `activity_images`
`activity_id` FK→activities, `media_file_id` FK→media_files **UNIQUE**, `sort_order TINYINT`, `created_at`
PK `(activity_id, media_file_id)` — ดูเหตุผลการออกแบบ media ใน §10

### 6.3 `food_menus`
`id`, `school_id`, `classroom_id` (composite FK), `menu_date DATE`, `note VARCHAR(255) NULL`, `created_by`, `updated_by`, timestamps
Unique: `uq_food_menu (classroom_id, menu_date)` · Index: `ix_food_school_date (school_id, menu_date)`

### 6.4 `food_menu_items` *(เพิ่มจากรายการขั้นต่ำ)*
`id`, `food_menu_id` FK, `meal_type ENUM('breakfast','lunch','snack','milk','fruit')`, `name VARCHAR(100)`, `sort_order TINYINT`
Index: `ix_food_items (food_menu_id, meal_type, sort_order)`
เหตุผล: 1 มื้อมีหลายรายการ (ข้าวมันไก่ + ซุปฟักทอง) — ถ้าไม่แยกจะต้องเก็บ JSON/ข้อความคั่น ซึ่งค้นหา/รายงานไม่ได้

### 6.5 `food_images`
`food_menu_id` FK, `media_file_id` FK **UNIQUE**, `meal_type` ENUM NULL, `sort_order`, `created_at` — PK `(food_menu_id, media_file_id)`

### 6.6 `food_intakes` *(PENDING P3 — Eating details)*
`id`, scope cols, `intake_date DATE`, `meal_type ENUM(...)`, `level ENUM('good','some','little','none')`, `recorded_by`, timestamps
Unique: `uq_intake (student_id, intake_date, meal_type)` · Index: `ix_intake_room_date (classroom_id, intake_date)`

### 6.7 `attendance`
| Column | Type | หมายเหตุ |
|---|---|---|
| id | BIGINT UNSIGNED PK | |
| school_id, classroom_id, student_id | composite FKs | |
| attendance_date | DATE | |
| status | ENUM('present','late','leave','absent') | |
| checked_in_at | TIME NULL | ตั้งอัตโนมัติเมื่อเปลี่ยนเป็น present/late ครั้งแรก (แก้จุดอ่อนของ Demo) |
| leave_type | ENUM('sick','personal') NULL | |
| note | VARCHAR(150) NULL | |
| recorded_by / updated_by | FK→users | |
| created_at / updated_at | | |

Unique: `uq_attendance (student_id, attendance_date)`
Index: `ix_att_room_date (classroom_id, attendance_date, status)`, `ix_att_school_date (school_id, attendance_date, status)`

### 6.8 `sleep_records`
scope cols, `sleep_date DATE`, `started_at TIME NULL`, `ended_at TIME NULL`, `quality ENUM('good','restless','none')`, `note VARCHAR(150)`, `recorded_by`, timestamps
CHECK `(ended_at IS NULL OR started_at IS NULL OR ended_at > started_at)` · Unique `uq_sleep (student_id, sleep_date)` · Index `ix_sleep_room_date (classroom_id, sleep_date)`

### 6.9 `health_records`
scope cols, `check_date DATE`, `checked_at TIME`, `temperature DECIMAL(3,1) NULL` CHECK `BETWEEN 34.0 AND 42.0`,
`condition ENUM('normal','watch','sick')`, `symptoms VARCHAR(150) NULL`, `note VARCHAR(150) NULL`, `recorded_by`, timestamps
(Health อยู่ใน scope หลัก; ระดับรายละเอียด เช่น อุณหภูมิ/อาการ = **PENDING P3** — คอลัมน์ที่ไม่ใช้ปล่อย NULL ได้)
Index: `ix_health_student_date (student_id, check_date)`, `ix_health_room_date (classroom_id, check_date, condition)`
(อนุญาตหลายครั้งต่อวัน — วัดไข้ซ้ำได้; หน้าจอแสดงล่าสุด)

### 6.10 `classroom_status_logs` *(PENDING P1 — Classroom Status)*
`id`, `school_id`, `classroom_id`, `status_code VARCHAR(20)` (line_up/study/eat/sleep/…), `note VARCHAR(60)`, `changed_by`, `changed_at DATETIME`
Index: `ix_room_status (classroom_id, changed_at)` — append-only (ผู้ปกครองเห็นสถานะล่าสุด + timeline)

### 6.11 `development_assessments` *(PENDING P2 — Development)*
scope cols, `assessed_on DATE`, `language/math/social/motor/creative TINYINT UNSIGNED` CHECK 0–100, `note VARCHAR(255)`, `assessed_by`, timestamps
Index: `ix_dev_student (student_id, assessed_on)`

---

## 7. Portfolio

### 7.1 `portfolios`
| Column | Type | หมายเหตุ |
|---|---|---|
| id | BIGINT UNSIGNED PK | |
| school_id, classroom_id, student_id | composite FKs | |
| title | VARCHAR(100) | |
| category | ENUM('art','language','math','science','music','other') | |
| description | VARCHAR(500) NULL | ความเห็นครู |
| work_date | DATE | |
| created_by / updated_by | FK→users | |
| created_at / updated_at / deleted_at / deleted_by | | soft delete |

Index: `ix_portfolio_student (student_id, work_date)`, `ix_portfolio_room (classroom_id, work_date)`

### 7.2 `portfolio_images`
`portfolio_id` FK, `media_file_id` FK **UNIQUE**, `sort_order`, `created_at` — PK `(portfolio_id, media_file_id)`

---

## 8. Stars (Ledger)

### 8.1 `star_transactions`
| Column | Type | หมายเหตุ |
|---|---|---|
| id | BIGINT UNSIGNED PK | |
| school_id, classroom_id, student_id | composite FKs | |
| points | SMALLINT | CHECK `points <> 0 AND points BETWEEN -10 AND 10` |
| reason_code | VARCHAR(32) NULL | จาก catalog (ตั้งใจทำกิจกรรม = 5 …) |
| reason_text | VARCHAR(100) | ข้อความที่ผู้ปกครองเห็น |
| awarded_by | FK→users | |
| awarded_at | DATETIME | |
| reversal_of_id | BIGINT UNSIGNED NULL FK→star_transactions **UNIQUE** | การแก้ไข = รายการกลับรายการ (ห้าม UPDATE/DELETE) |
| created_at | DATETIME | ไม่มี updated_at — immutable |

Index: `ix_stars_student (student_id, awarded_at)`, `ix_stars_school (school_id, awarded_at)`
ยอดรวม = `SUM(points)` ต่อ student (เร็วพอด้วย index; FUTURE: `student_star_balances` เป็น cache)

---

## 9. Calendar, Chat, Notification

### 9.1 `school_events`
`id`, `school_id`, `classroom_id NULL` (composite FK เมื่อไม่ NULL; NULL = ทั้งโรงเรียน), `event_date DATE`, `start_time TIME NULL`,
`end_time TIME NULL`, `title VARCHAR(100)`, `icon_code VARCHAR(16)`, `dress_code VARCHAR(100) NULL`, `bring_items VARCHAR(150) NULL`,
`dismiss_time TIME NULL`, `description VARCHAR(500) NULL`, `created_by`, timestamps, `deleted_at`
Index: `ix_events_school_date (school_id, event_date)`

### 9.2 `conversations`
`id`, `school_id`, `type ENUM('student_thread')` (FUTURE: 'direct','group'), `student_id NULL` (composite FK),
`last_message_at DATETIME NULL`, `created_at`
Unique: `uq_conv_student (type, student_id)` · Index: `ix_conv_school_last (school_id, last_message_at)`
> 1 ห้องแชทต่อเด็ก (ผู้ปกครองทุกคนของเด็ก ↔ ครูประจำห้อง) ตาม UX Demo

### 9.3 `conversation_participants`
`conversation_id` FK, `user_id` FK, `participant_role ENUM('parent','teacher','admin')`, `joined_at`, `left_at NULL`,
`last_read_message_id BIGINT UNSIGNED NULL` — PK `(conversation_id, user_id)` · Index `ix_cp_user (user_id, left_at)`
> participants ถูก sync จาก `parent_students` / `teacher_classrooms` โดย `ChatService`; การเข้าถึงยังตรวจ scope ซ้ำทุกครั้ง

### 9.4 `messages`
`id`, `conversation_id` FK, `sender_user_id` FK, `body VARCHAR(1000)`, `created_at`, `edited_at NULL`, `deleted_at NULL`
Index: `ix_messages_conv (conversation_id, id)` (keyset pagination)

### 9.5 `notifications`
| Column | Type | หมายเหตุ |
|---|---|---|
| id | BIGINT UNSIGNED PK | |
| school_id | FK NULL | NULL = ประกาศระดับแพลตฟอร์ม |
| type | ENUM('announcement','event','teacher','alert','pickup','system') | |
| title | VARCHAR(120) | |
| body | VARCHAR(500) NULL | |
| target_type | ENUM('school','classroom','student','user') | ใช้แสดง "ส่งถึง" |
| target_id | BIGINT UNSIGNED NULL | |
| related_type / related_id | VARCHAR(40) / BIGINT NULL | เช่น pickup_request 123 (ลิงก์ไปหน้าที่เกี่ยวข้อง) |
| created_by | FK→users NULL | NULL = ระบบ |
| created_at / deleted_at | | |

Index: `ix_notif_school (school_id, created_at)`

### 9.6 `notification_recipients` (= `notification_reads` ที่ขยายความ)
`notification_id` FK, `user_id` FK, `created_at`, `read_at DATETIME NULL` — PK `(notification_id, user_id)`
Index: `ix_nr_user_unread (user_id, read_at, notification_id)`
> **Fan-out on write**: ตอนสร้างประกาศ ระบบแตกเป็นแถวต่อผู้รับ (ระดับ 2 โรงเรียน ≈ ไม่กี่ร้อยแถว/ประกาศ)
> ทำให้นับ "ยังไม่อ่าน" ด้วย index เดียว และส่งแจ้งเตือนเฉพาะคน (เช่น รับ-ส่ง) ได้ตรงตัว (ADR-013)

---

## 10. Pickup / รับ-ส่ง

### 10.1 `pickup_requests`
| Column | Type | Null | หมายเหตุ |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | N | |
| school_id, classroom_id, student_id | composite FKs | N | classroom = snapshot ตอนแจ้ง |
| pickup_date | DATE | N | วันตามเวลาไทย |
| requested_by | FK→users | N | ผู้ปกครองที่แจ้ง (ต้องมี `parent_students.can_pickup=1`) |
| eta_minutes | TINYINT UNSIGNED | N | CHECK `IN (5,10,15,20,30)` |
| requested_at | DATETIME | N | |
| eta_at | DATETIME | N | requested_at + eta |
| status | ENUM('coming','preparing','waiting','completed') | N | สถานะปัจจุบัน |
| preparing_at / preparing_by | DATETIME / FK→users | Y | denormalized จาก log เพื่อแสดงผลเร็ว |
| waiting_at / waiting_by | DATETIME / FK→users | Y | |
| completed_at / completed_by | DATETIME / FK→users | Y | ผู้ส่งมอบ |
| active_flag | TINYINT GENERATED `IF(status <> 'completed', 1, NULL)` STORED | Y | |
| created_at / updated_at | | | |

- Unique: `uq_pickup_active (student_id, pickup_date, active_flag)` → **1 คำขอที่ยังไม่จบต่อเด็กต่อวัน** (กันกดซ้ำ/แข่งกัน)
- CHECK สอดคล้องสถานะ: `status='completed' → completed_at IS NOT NULL` ฯลฯ
- Index: `ix_pickup_room_day (classroom_id, pickup_date, status)` (หน้าครู + polling),
  `ix_pickup_school_day (school_id, pickup_date, status)` (admin/report), `ix_pickup_student_day (student_id, pickup_date)` (หน้าผู้ปกครอง)
- การเปลี่ยนสถานะ: `UPDATE … SET status=:to … WHERE id=:id AND status=:from` → affected rows = 0 ⇒ 409 (optimistic, ไม่มี race)

### 10.2 `pickup_status_logs` (History — append-only)
| Column | Type | หมายเหตุ |
|---|---|---|
| id | BIGINT UNSIGNED PK | |
| pickup_request_id | FK→pickup_requests | |
| school_id | FK | ใช้ query/partition ตามโรงเรียน |
| from_status | ENUM(...) NULL | NULL = สร้างคำขอ |
| to_status | ENUM('coming','preparing','waiting','completed') | |
| changed_by | FK→users | |
| changed_at | DATETIME(3) | |
| source | ENUM('web','api','system') | |
| note | VARCHAR(150) NULL | |

Index: `ix_pickup_log_req (pickup_request_id, changed_at)`
> ทุก transition เขียน `pickup_requests` + `pickup_status_logs` + `notification_recipients` + `audit_logs` ใน **transaction เดียว**

---

## 11. CCTV

### 11.1 `cameras`
| Column | Type | หมายเหตุ |
|---|---|---|
| id | BIGINT UNSIGNED PK | |
| school_id | FK | |
| classroom_id | composite FK NULL | NULL = พื้นที่ส่วนกลาง (สนามเด็กเล่น/ประตู) |
| code | VARCHAR(20) | **UNIQUE (school_id, code)** เช่น CAM-01 |
| name / location | VARCHAR(80) / VARCHAR(120) | |
| vendor / model | VARCHAR(40) | TP-Link / Tapo C200C |
| stream_path | VARCHAR(100) **UNIQUE** | ชื่อ path บน Media Server เช่น `s1-cam01` — **ไม่ใช่ RTSP URL** |
| protocols | SET('hls','webrtc') DEFAULT 'hls,webrtc' | |
| status | ENUM('online','offline','maintenance') | อัปเดตจาก health ของ Media Server (FUTURE: อัตโนมัติ) |
| is_active | TINYINT(1) DEFAULT 1 | |
| last_status_at | DATETIME NULL | |
| created_by / timestamps / deleted_at | | |

> **RTSP URL + username/password ไม่อยู่ในฐานข้อมูลนี้** — อยู่ใน config/secret ของ Media Server ที่ map `stream_path → rtsp://…` (ADR-005)

### 11.2 `camera_permissions`
`id`, `camera_id` FK, `role_id` FK→roles, `classroom_id NULL` (จำกัดเฉพาะห้อง), `can_view TINYINT(1)`, `created_by`, timestamps
Unique: `uq_cam_perm (camera_id, role_id, scope_key)` (scope_key = COALESCE(classroom_id,0))
Index: `ix_cam_perm_role (role_id, camera_id)`
กติกา (เหมือน Demo): Admin/Executive เห็นทุกกล้องในโรงเรียน; Teacher เห็นกล้องห้องตัวเอง + กล้องส่วนกลางที่อนุญาต;
Parent เห็นกล้องห้องลูก (เมื่ออนุญาต) + ส่วนกลางที่อนุญาต; Super Admin เห็นทั้งหมด

---

## 12. Media & Audit

### 12.1 `media_files`
| Column | Type | Null | หมายเหตุ |
|---|---|---|---|
| id | BIGINT UNSIGNED PK | N | |
| public_id | CHAR(26) UNIQUE | N | ULID — ใช้ใน URL `/media/{public_id}` และชื่อไฟล์ |
| school_id | FK | N | scope + โฟลเดอร์ |
| classroom_id | composite FK NULL | Y | snapshot (activity/food/portfolio) |
| student_id | composite FK NULL | Y | มีค่าเมื่อเป็นภาพของเด็กรายคน (portfolio/avatar) |
| category | ENUM('activity','food','portfolio','student_avatar') | N | ชนิด owner — ต้องตรงกับ join table |
| disk | VARCHAR(20) DEFAULT 'local' | N | ชื่อ storage driver |
| storage_path | VARCHAR(255) | N | relative path ภายใน disk |
| thumb_path | VARCHAR(255) | Y | thumbnail 400px (WebP) |
| mime_type | VARCHAR(40) | N | หลังตรวจด้วย finfo + re-encode |
| file_size | INT UNSIGNED | N | bytes หลัง optimise |
| width / height | SMALLINT UNSIGNED | N | |
| checksum_sha256 | CHAR(64) | N | ตรวจ backup/restore, กันไฟล์ซ้ำ |
| uploaded_by | FK→users | N | |
| uploaded_at | DATETIME | N | |
| expires_at | DATETIME | **N** | `uploaded_at + 6 months` — **ทุก category** (LOCKED, ADR-006); ไม่มีค่า NULL |
| status | ENUM('available','deleted') | N | `available` = ไฟล์จริงยังอยู่; `deleted` = ไฟล์จริงถูกลบแล้ว **record คงอยู่** (ดู [data-retention.md §2](data-retention.md#2-image-status-model)) |
| deleted_reason | ENUM('retention','user') | Y | `retention` = scheduled job ลบเมื่อครบ 6 เดือน; `user` = ผู้ใช้ลบก่อนกำหนด |
| deleted_at | DATETIME | Y | เวลาที่ไฟล์จริงถูกลบ |
| deleted_by | FK→users | Y | `NULL` เมื่อ `deleted_reason='retention'` (executor = system) |
| cleanup_attempts | TINYINT UNSIGNED DEFAULT 0 | N | จำนวนครั้งที่ job พยายามลบแล้วล้มเหลว |
| cleanup_last_attempt_at | DATETIME | Y | |
| cleanup_last_error | VARCHAR(40) | Y | error **code** เท่านั้น (`PERMISSION_DENIED`, `STORAGE_UNAVAILABLE`, `DB_UPDATE_FAILED`) — ไม่มี path/secret |
| created_at / updated_at | | | |

CHECK: `(status='available' AND deleted_at IS NULL) OR (status='deleted' AND deleted_at IS NOT NULL AND deleted_reason IS NOT NULL)`
Index: `ix_media_status_exp (status, expires_at)` (**scheduled cleanup job** — หา `available` ที่ครบกำหนดโดยไม่ scan),
`ix_media_school_status_exp (school_id, status, expires_at)` (หน้า Storage ต่อโรงเรียน: ใกล้หมดอายุ / ค้าง),
`ix_media_cleanup_error (cleanup_last_error)` (รายการลบไม่สำเร็จ),
`ix_media_school_uploaded (school_id, uploaded_at)` (สถิติรายเดือน), `ix_media_student (student_id, uploaded_at)`

> **ภาพที่เปิดดูได้** = `status='available' AND expires_at > NOW()` — record ของภาพและ business record ไม่ถูกลบอัตโนมัติไม่ว่ากรณีใด

#### ทำไมไม่ใช้ polymorphic `owner_type/owner_id` ล้วน
| แบบ | ข้อดี | ข้อเสีย |
|---|---|---|
| Polymorphic (`owner_type`, `owner_id`) | ตารางเดียว เพิ่มชนิดใหม่ง่าย | **ไม่มี FK** → owner ถูกลบแล้วภาพกำพร้า, ผูกผิดชนิดได้, scope ตรวจยาก |
| **Join table ต่อชนิด + `media_files` กลาง (เลือก)** | FK จริงทั้งสองฝั่ง, `media_file_id UNIQUE` บังคับ 1 ภาพ 1 owner, query ตาม owner เร็ว, retention/สถิติทำที่ตารางเดียว | ต้องเพิ่ม join table + ค่า enum เมื่อมีชนิดใหม่ (migration เล็ก) |

ชนิดใหม่ในอนาคต (เช่น ภาพแนบแชท) = เพิ่ม `category` + ตาราง `message_attachments(message_id, media_file_id)` — ไม่กระทบของเดิม
(ADR-008)

### 12.2 `audit_logs`
| Column | Type | หมายเหตุ |
|---|---|---|
| id | BIGINT UNSIGNED PK | |
| occurred_at | DATETIME(3) | |
| request_id | CHAR(26) | ตามรอยกับ application log |
| actor_type | ENUM('user','system') | `system` = scheduled job / command (เช่น media retention cleanup) |
| user_id | BIGINT UNSIGNED NULL | **ไม่มี FK** — log ต้องอยู่ได้อิสระ; NULL เมื่อ `actor_type='system'` |
| acting_role | VARCHAR(32) NULL | role ที่ใช้ตอนทำ |
| school_id | BIGINT UNSIGNED NULL | |
| action | VARCHAR(40) | `LOGIN`, `LOGIN_FAILED`, `CREATE_STUDENT`, `UPLOAD_MEDIA`, `MEDIA_RETENTION_DELETE`, `COMPLETE_PICKUP`, … |
| entity_type / entity_id | VARCHAR(40) / BIGINT NULL | |
| result | ENUM('success','denied','failed') | |
| ip_address | VARBINARY(16) | `INET6_ATON` |
| user_agent | VARCHAR(255) | ตัดความยาว |
| metadata | JSON NULL | key ที่อนุญาตเท่านั้น เช่น `{"from":"coming","to":"preparing"}` ห้าม PIN/token/เนื้อหาแชท |

Index: `ix_audit_school_time (school_id, occurred_at)`, `ix_audit_user_time (user_id, occurred_at)`,
`ix_audit_entity (entity_type, entity_id)`, `ix_audit_action_time (action, occurred_at)`
DB user `app` มีสิทธิ์ `INSERT, SELECT` บนตารางนี้เท่านั้น (ไม่มี UPDATE/DELETE)

---

## 13. สรุปรายการตาราง (41)

| กลุ่ม | ตาราง |
|---|---|
| Identity / Access (10) | users, user_credentials, roles, permissions, role_permissions, user_roles, teacher_classrooms, parent_students, auth_remember_tokens, ci_sessions |
| Organization (2) | schools, classrooms |
| Students (1) | students |
| Daily operations (11) | activities, activity_images, food_menus, food_menu_items, food_images, attendance, sleep_records, health_records, *food_intakes*, *classroom_status_logs*, *development_assessments* |
| Portfolio (2) | portfolios, portfolio_images |
| Stars (1) | star_transactions |
| Calendar (1) | school_events |
| Chat (3) | conversations, conversation_participants, messages |
| Notification (2) | notifications, notification_recipients |
| Pickup (2) | pickup_requests, pickup_status_logs |
| CCTV (2) | cameras, camera_permissions |
| Media (1) | media_files |
| Audit (1) | audit_logs |
| CI framework (1) | `migrations` (สร้างโดย CI4) |

*ตัวเอียง* = **PENDING BUSINESS CONFIRMATION** (พบใน Demo แต่ไม่อยู่ใน Scope list) — ถ้าไม่เอา ตัดออกได้โดยไม่กระทบตารางอื่น

### เทียบกับรายการขั้นต่ำที่โจทย์ให้
| โจทย์ | Production | เหตุผล |
|---|---|---|
| user_schools | รวมใน `user_roles.school_id` | ความสัมพันธ์มีความหมายผ่าน role เท่านั้น |
| user_classrooms + teacher_classrooms | `teacher_classrooms` ตารางเดียว | มีแค่ครูที่ผูกห้อง |
| notification_reads | `notification_recipients.read_at` | fan-out on write — แถวผู้รับ = สถานะการอ่าน |
| star_transactions | ใช้ชื่อเดิม (ledger) | |
| (เพิ่ม) food_menu_items | ไม่ใช้ JSON เก็บรายการอาหาร | |
| (เพิ่ม) auth_remember_tokens, ci_sessions | ความปลอดภัย session บนมือถือ | |

## 14. Index Strategy (สรุปเหตุผลตาม query จริง)

| Query ที่เกิดบ่อย | Index | เหตุผล |
|---|---|---|
| Login (เบอร์+PIN หรือ username+password) | `user_credentials(type, identifier)` UNIQUE | lookup ตรงตามชนิดฟอร์ม + กันซ้ำ |
| สร้าง AccessContext ทุก request | `user_roles(user_id, role_id, scope_key)`, `teacher_classrooms(user_id, …)`, `parent_students(user_id, …)` | ทำครั้งเดียวต่อ request แล้ว cache ใน session |
| หน้าห้องเรียนของครู (เด็กในห้อง) | `students(classroom_id, status)` | |
| Admin นับเด็กทั้งโรงเรียน | `students(school_id, status)` | |
| เช็คชื่อวันนี้ทั้งห้อง | `attendance(classroom_id, attendance_date, status)` | range เดียว + นับสถานะจาก index |
| Dashboard โรงเรียนวันนี้ | `attendance(school_id, attendance_date, status)` | aggregate จาก index (covering) |
| ป้องกันบันทึกซ้ำรายวัน | UNIQUE `(student_id, date)` ของ attendance/sleep, `(student_id,date,meal)` ของ intake | integrity + lookup "วันนี้ของเด็กคนนี้" |
| ตารางกิจกรรมห้องตามวัน | `activities(classroom_id, activity_date, start_time)` | เรียงตามเวลาโดยไม่ sort เพิ่ม |
| เมนูอาหารโรงเรียนตามวัน | `food_menus(school_id, menu_date)` + UNIQUE `(classroom_id, menu_date)` | |
| Polling รับ-ส่งของครู | `pickup_requests(classroom_id, pickup_date, status)` | query ทุก 4 วินาที — ต้องเป็น index range เล็ก |
| รับ-ส่งทั้งโรงเรียน (admin/report) | `pickup_requests(school_id, pickup_date, status)` | |
| หน้าผู้ปกครอง | `pickup_requests(student_id, pickup_date)` | |
| ประวัติสถานะ | `pickup_status_logs(pickup_request_id, changed_at)` | |
| Badge ยังไม่อ่าน | `notification_recipients(user_id, read_at, notification_id)` | `COUNT(*) WHERE user_id=? AND read_at IS NULL` จาก index |
| ห้องแชทของ user | `conversation_participants(user_id, left_at)` + `messages(conversation_id, id)` | keyset pagination |
| Portfolio ของเด็ก | `portfolios(student_id, work_date)` | |
| ยอดดาว + ประวัติ | `star_transactions(student_id, awarded_at)` | SUM + list จาก index เดียว |
| Scheduled cleanup (ทั้งระบบ) | `media_files(status, expires_at)` | `WHERE status='available' AND expires_at <= NOW() ORDER BY expires_at` เป็น index range |
| หน้า Storage ต่อโรงเรียน | `media_files(school_id, status, expires_at)` | ภาพใกล้ครบกำหนด / สถิติต่อโรงเรียน |
| รายการลบไม่สำเร็จ | `media_files(cleanup_last_error)` | หน้า Storage / monitoring |
| สถิติพื้นที่รายเดือน | `media_files(school_id, uploaded_at)` | |
| ภาพของ owner | PK ของ join table (`activity_id, media_file_id`) | |
| Audit ตามโรงเรียน/คน/entity | `audit_logs(school_id, occurred_at)`, `(user_id, occurred_at)`, `(entity_type, entity_id)` | |
| ปฏิทิน | `school_events(school_id, event_date)` | |
| กล้องของโรงเรียน | `cameras(school_id, code)` UNIQUE | |

กติกา: ทุก index ต้องมี query จริงรองรับ — ตรวจด้วย `EXPLAIN` ใน Phase ที่สร้าง feature; ไม่สร้าง index "เผื่อ"
