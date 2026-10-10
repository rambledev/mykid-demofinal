# Database ERD

> รายละเอียดคอลัมน์: [database.md](database.md) · สัญลักษณ์ Mermaid: `||--o{` = หนึ่งต่อศูนย์หรือหลาย, `|o--o{` = ศูนย์หรือหนึ่งต่อหลาย
> ใน diagram แสดงเฉพาะคีย์และคอลัมน์ที่เกี่ยวกับความสัมพันธ์ เพื่อให้อ่านง่าย

## 1. Overview (ทุกโดเมน)

```mermaid
erDiagram
    SCHOOLS ||--o{ CLASSROOMS : has
    SCHOOLS ||--o{ STUDENTS : enrolls
    CLASSROOMS ||--o{ STUDENTS : "current room"
    SCHOOLS ||--o{ USER_ROLES : "scope of"
    USERS ||--o{ USER_ROLES : "assigned"
    ROLES ||--o{ USER_ROLES : ""
    ROLES ||--o{ ROLE_PERMISSIONS : grants
    PERMISSIONS ||--o{ ROLE_PERMISSIONS : ""
    USERS ||--o{ TEACHER_CLASSROOMS : teaches
    CLASSROOMS ||--o{ TEACHER_CLASSROOMS : ""
    USERS ||--o{ PARENT_STUDENTS : "parent of"
    STUDENTS ||--o{ PARENT_STUDENTS : ""
    CLASSROOMS ||--o{ ACTIVITIES : schedules
    CLASSROOMS ||--o{ FOOD_MENUS : serves
    STUDENTS ||--o{ ATTENDANCE : ""
    STUDENTS ||--o{ SLEEP_RECORDS : ""
    STUDENTS ||--o{ HEALTH_RECORDS : ""
    STUDENTS ||--o{ PORTFOLIOS : ""
    STUDENTS ||--o{ STAR_TRANSACTIONS : ""
    STUDENTS ||--o{ PICKUP_REQUESTS : ""
    STUDENTS ||--o| CONVERSATIONS : "chat thread"
    SCHOOLS ||--o{ SCHOOL_EVENTS : ""
    SCHOOLS ||--o{ CAMERAS : ""
    SCHOOLS ||--o{ MEDIA_FILES : "owns files"
    SCHOOLS |o--o{ NOTIFICATIONS : ""
    USERS ||--o{ NOTIFICATION_RECIPIENTS : receives
    USERS |o--o{ AUDIT_LOGS : "actor (no FK)"
```

## 2. Identity & Access (RBAC + Scope)

```mermaid
erDiagram
    USERS {
        bigint id PK
        varchar display_name
        varchar email UK "nullable"
        enum status
        int access_version
    }
    USER_CREDENTIALS {
        bigint id PK
        bigint user_id FK "UK with type"
        enum type "mobile_pin|account_password"
        varchar identifier "UK with type: mobile no. or username"
        varchar secret_hash "Argon2id + pepper"
        datetime locked_until
    }
    ROLES {
        smallint id PK
        varchar code UK
        enum scope_level "platform|school|classroom|student"
        enum auth_type "mobile_pin|account_password"
    }
    PERMISSIONS {
        smallint id PK
        varchar code UK "module.action"
    }
    ROLE_PERMISSIONS {
        smallint role_id PK, FK
        smallint permission_id PK, FK
    }
    USER_ROLES {
        bigint id PK
        bigint user_id FK
        smallint role_id FK
        bigint school_id FK "NULL only for platform role"
        datetime revoked_at
    }
    TEACHER_CLASSROOMS {
        bigint id PK
        bigint user_id FK
        bigint school_id FK
        bigint classroom_id FK "composite (classroom_id, school_id)"
        date ended_at
    }
    PARENT_STUDENTS {
        bigint id PK
        bigint user_id FK
        bigint school_id FK
        bigint student_id FK "composite (student_id, school_id)"
        enum relationship
        tinyint can_pickup
        datetime ended_at
    }
    AUTH_REMEMBER_TOKENS {
        bigint id PK
        bigint user_id FK
        char selector UK
    }
    USERS ||--|{ USER_CREDENTIALS : "login methods (max 1 per type)"
    USERS ||--o{ USER_ROLES : ""
    ROLES ||--o{ USER_ROLES : ""
    SCHOOLS |o--o{ USER_ROLES : ""
    ROLES ||--o{ ROLE_PERMISSIONS : ""
    PERMISSIONS ||--o{ ROLE_PERMISSIONS : ""
    USERS ||--o{ TEACHER_CLASSROOMS : ""
    CLASSROOMS ||--o{ TEACHER_CLASSROOMS : ""
    USERS ||--o{ PARENT_STUDENTS : ""
    STUDENTS ||--o{ PARENT_STUDENTS : ""
    USERS ||--o{ AUTH_REMEMBER_TOKENS : ""
    USER_CREDENTIALS ||--o{ AUTH_REMEMBER_TOKENS : "mobile_pin only"
    ROLES ||--o{ CAMERA_PERMISSIONS : ""
```

## 3. Organization & Students

```mermaid
erDiagram
    SCHOOLS {
        bigint id PK
        varchar code UK
        varchar name
        enum status
    }
    CLASSROOMS {
        bigint id PK
        bigint school_id FK
        smallint academic_year
        varchar name
    }
    STUDENTS {
        bigint id PK
        bigint school_id FK
        bigint classroom_id FK "composite (classroom_id, school_id)"
        varchar student_code "UK with school_id"
        bigint avatar_media_id FK "nullable"
        enum status
    }
    SCHOOLS ||--o{ CLASSROOMS : ""
    SCHOOLS ||--o{ STUDENTS : ""
    CLASSROOMS ||--o{ STUDENTS : ""
    STUDENTS |o--o| MEDIA_FILES : "avatar"
```

## 4. Daily Operations, Portfolio, Stars

```mermaid
erDiagram
    ACTIVITIES {
        bigint id PK
        bigint school_id FK
        bigint classroom_id FK
        date activity_date
    }
    ACTIVITY_IMAGES {
        bigint activity_id PK, FK
        bigint media_file_id PK, FK "UK"
    }
    FOOD_MENUS {
        bigint id PK
        bigint classroom_id FK "UK with menu_date"
        date menu_date
    }
    FOOD_MENU_ITEMS {
        bigint id PK
        bigint food_menu_id FK
        enum meal_type
    }
    FOOD_IMAGES {
        bigint food_menu_id PK, FK
        bigint media_file_id PK, FK "UK"
    }
    ATTENDANCE {
        bigint id PK
        bigint student_id FK "UK with attendance_date"
        bigint classroom_id FK "snapshot"
        enum status
    }
    SLEEP_RECORDS {
        bigint id PK
        bigint student_id FK "UK with sleep_date"
    }
    HEALTH_RECORDS {
        bigint id PK
        bigint student_id FK
        date check_date
    }
    PORTFOLIOS {
        bigint id PK
        bigint student_id FK
        enum category
    }
    PORTFOLIO_IMAGES {
        bigint portfolio_id PK, FK
        bigint media_file_id PK, FK "UK"
    }
    STAR_TRANSACTIONS {
        bigint id PK
        bigint student_id FK
        smallint points
        bigint awarded_by FK
        bigint reversal_of_id FK "UK, self"
    }
    CLASSROOMS ||--o{ ACTIVITIES : ""
    ACTIVITIES ||--o{ ACTIVITY_IMAGES : ""
    MEDIA_FILES ||--o| ACTIVITY_IMAGES : ""
    CLASSROOMS ||--o{ FOOD_MENUS : ""
    FOOD_MENUS ||--o{ FOOD_MENU_ITEMS : ""
    FOOD_MENUS ||--o{ FOOD_IMAGES : ""
    MEDIA_FILES ||--o| FOOD_IMAGES : ""
    STUDENTS ||--o{ ATTENDANCE : ""
    STUDENTS ||--o{ SLEEP_RECORDS : ""
    STUDENTS ||--o{ HEALTH_RECORDS : ""
    STUDENTS ||--o{ PORTFOLIOS : ""
    PORTFOLIOS ||--o{ PORTFOLIO_IMAGES : ""
    MEDIA_FILES ||--o| PORTFOLIO_IMAGES : ""
    STUDENTS ||--o{ STAR_TRANSACTIONS : ""
    STAR_TRANSACTIONS |o--o| STAR_TRANSACTIONS : "reversal of"
    USERS ||--o{ STAR_TRANSACTIONS : "awarded_by"
```

> ตาราง PENDING BUSINESS CONFIRMATION (`food_intakes`, `classroom_status_logs`, `development_assessments`) ผูกกับ `STUDENTS`/`CLASSROOMS`
> แบบเดียวกับ daily records — ไม่แสดงใน diagram เพื่อลดความซับซ้อน

## 5. Media (กลาง) + Owner

```mermaid
erDiagram
    MEDIA_FILES {
        bigint id PK
        char public_id UK "ULID"
        bigint school_id FK
        bigint classroom_id FK "nullable"
        bigint student_id FK "nullable"
        enum category "activity|food|portfolio|student_avatar"
        varchar storage_path
        datetime expires_at "uploaded_at + 6 months, NOT NULL"
        enum status "available|deleted"
        enum deleted_reason "retention|user"
        datetime deleted_at
        bigint uploaded_by FK
    }
    SCHOOLS ||--o{ MEDIA_FILES : ""
    STUDENTS |o--o{ MEDIA_FILES : "student images"
    USERS ||--o{ MEDIA_FILES : "uploaded_by"
    MEDIA_FILES ||--o| ACTIVITY_IMAGES : "category=activity"
    MEDIA_FILES ||--o| FOOD_IMAGES : "category=food"
    MEDIA_FILES ||--o| PORTFOLIO_IMAGES : "category=portfolio"
```

Retention: ไฟล์จริงถูกลบอัตโนมัติเมื่อครบ 6 เดือน → `status='deleted'`; **record ใน `MEDIA_FILES` และ join table และ owner คงอยู่ทั้งหมด** (FK เป็น RESTRICT ไม่มี CASCADE)

กติกา: ภาพ 1 ไฟล์มี owner ได้ **หนึ่งเดียว** (`media_file_id` UNIQUE ใน join table) และ `category` ต้องตรงกับ join table
(ตรวจใน `MediaService` + test)

## 6. Pickup / รับ-ส่ง

```mermaid
erDiagram
    PICKUP_REQUESTS {
        bigint id PK
        bigint school_id FK
        bigint classroom_id FK
        bigint student_id FK "UK with pickup_date + active_flag"
        date pickup_date
        bigint requested_by FK "parent user"
        tinyint eta_minutes "5|10|15|20|30"
        enum status "coming|preparing|waiting|completed"
        bigint preparing_by FK
        bigint waiting_by FK
        bigint completed_by FK
    }
    PICKUP_STATUS_LOGS {
        bigint id PK
        bigint pickup_request_id FK
        enum from_status
        enum to_status
        bigint changed_by FK
        datetime changed_at
    }
    STUDENTS ||--o{ PICKUP_REQUESTS : ""
    USERS ||--o{ PICKUP_REQUESTS : "requested_by"
    PICKUP_REQUESTS ||--|{ PICKUP_STATUS_LOGS : "history (>=1)"
    USERS ||--o{ PICKUP_STATUS_LOGS : "changed_by"
```

## 7. Chat & Notification

```mermaid
erDiagram
    CONVERSATIONS {
        bigint id PK
        bigint school_id FK
        enum type "student_thread"
        bigint student_id FK "UK with type"
    }
    CONVERSATION_PARTICIPANTS {
        bigint conversation_id PK, FK
        bigint user_id PK, FK
        bigint last_read_message_id
    }
    MESSAGES {
        bigint id PK
        bigint conversation_id FK
        bigint sender_user_id FK
    }
    NOTIFICATIONS {
        bigint id PK
        bigint school_id FK "nullable"
        enum type
        enum target_type
        bigint target_id
    }
    NOTIFICATION_RECIPIENTS {
        bigint notification_id PK, FK
        bigint user_id PK, FK
        datetime read_at
    }
    STUDENTS ||--o| CONVERSATIONS : ""
    CONVERSATIONS ||--o{ CONVERSATION_PARTICIPANTS : ""
    USERS ||--o{ CONVERSATION_PARTICIPANTS : ""
    CONVERSATIONS ||--o{ MESSAGES : ""
    USERS ||--o{ MESSAGES : "sender"
    NOTIFICATIONS ||--o{ NOTIFICATION_RECIPIENTS : ""
    USERS ||--o{ NOTIFICATION_RECIPIENTS : ""
```

## 8. CCTV, Calendar, Audit

```mermaid
erDiagram
    CAMERAS {
        bigint id PK
        bigint school_id FK
        bigint classroom_id FK "nullable = common area"
        varchar code "UK with school_id"
        varchar stream_path UK "media-server path, no RTSP"
    }
    CAMERA_PERMISSIONS {
        bigint id PK
        bigint camera_id FK
        smallint role_id FK
        bigint classroom_id FK "nullable"
        tinyint can_view
    }
    SCHOOL_EVENTS {
        bigint id PK
        bigint school_id FK
        bigint classroom_id FK "nullable = whole school"
        date event_date
    }
    AUDIT_LOGS {
        bigint id PK
        bigint user_id "no FK"
        bigint school_id "no FK"
        varchar action
        varchar entity_type
        bigint entity_id
    }
    SCHOOLS ||--o{ CAMERAS : ""
    CLASSROOMS |o--o{ CAMERAS : ""
    CAMERAS ||--o{ CAMERA_PERMISSIONS : ""
    ROLES ||--o{ CAMERA_PERMISSIONS : ""
    SCHOOLS ||--o{ SCHOOL_EVENTS : ""
    CLASSROOMS |o--o{ SCHOOL_EVENTS : ""
```

## 9. Relationship Summary

| From | To | Cardinality | Enforced by |
|---|---|---|---|
| School | Classroom | 1 : N | FK `classrooms.school_id` |
| School | Student | 1 : N | FK + composite FK ผ่าน classroom (เด็กอยู่โรงเรียนเดียวกับห้อง) |
| Classroom | Student | 1 : N (ห้องปัจจุบัน) | composite FK `(classroom_id, school_id)` |
| Student | Parent (User) | N : M | `parent_students` (+ relationship, can_pickup, ended_at) |
| Teacher (User) | Classroom | N : M | `teacher_classrooms` (+ ended_at) |
| School | Teacher/Admin/Executive/Parent (User) | N : M ผ่าน role | `user_roles.school_id` |
| User | Credential | 1 : 1..2 (สูงสุด 1 ต่อชนิด) | `user_credentials` UNIQUE `(user_id, type)` และ `(type, identifier)` |
| User | Role → Permission | N : M → N : M | `user_roles`, `role_permissions`; role ใช้ได้เฉพาะเมื่อ login ด้วย credential ตาม `roles.auth_type` |
| Student | Portfolio / Stars / Attendance / Sleep / Health / Pickup | 1 : N | composite FK `(student_id, school_id)` |
| Portfolio / Activity / Food menu | Media file | 1 : N (ภาพ 1 ไฟล์มี owner เดียว) | join table + `media_file_id` UNIQUE |
| Pickup request | Status log | 1 : N (≥1) | FK + เขียนใน transaction เดียว |
| Student | Conversation | 1 : 0..1 (`student_thread`) | UNIQUE `(type, student_id)` |
| Conversation | Message / Participant | 1 : N | FK |
| Notification | User | N : M | `notification_recipients` |
| School | Camera | 1 : N | FK; Camera → Classroom 0..1 |
| Camera | Role | N : M (+ห้อง) | `camera_permissions` |
| Audit log | User / School | N : 0..1 | **ไม่มี FK** (log ต้องอยู่ได้แม้ข้อมูลอ้างอิงเปลี่ยน) |

## 10. Consistency Check (ตรวจแล้ว)

| ความเสี่ยงที่ตรวจ | ผล |
|---|---|
| เด็กอยู่ห้องของอีกโรงเรียน | ป้องกันโดย composite FK `students(classroom_id, school_id)` |
| Daily record ชี้เด็กโรงเรียนอื่น | composite FK `(student_id, school_id)` ทุกตาราง |
| Teacher ผูกห้องโรงเรียนที่ตัวเองไม่มี role | DB ป้องกัน school ของห้อง; การมี role TEACHER ใน school นั้นตรวจใน `RoleAssignmentService` (ข้ามตารางเกินกว่าที่ FK ทำได้ — ไม่ใช้ trigger เพื่อความเรียบง่าย) |
| Parent ผูกเด็กโดยไม่มี role PARENT | ตรวจใน `StudentService::linkParent` + test |
| ภาพ 1 ไฟล์ถูกใช้หลาย owner | UNIQUE `media_file_id` ในแต่ละ join table; ข้าม join table ตรวจด้วย `category` |
| รับ-ส่งซ้อนกันของเด็กคนเดียว | UNIQUE `(student_id, pickup_date, active_flag)` |
| สถานะรับ-ส่งข้ามขั้น / แข่งกันกด | `UPDATE … WHERE status = :from` + state machine ใน `PickupService` |
| ดาวถูกแก้ย้อนหลัง | ledger ไม่มี UPDATE/DELETE, แก้ด้วย reversal (`reversal_of_id` UNIQUE = กลับรายการได้ครั้งเดียว) |
| ลบ user แล้วประวัติหาย | ไม่ลบ user จริง (`status=disabled`), FK `RESTRICT`, audit ไม่มี FK |
| วงจร FK (`students.avatar_media_id` ↔ `media_files.student_id`) | ทั้งคู่ nullable → insert เด็กก่อน, ภาพทีหลัง, แล้ว update avatar — ไม่ติด |
| ลบ owner แล้วภาพกำพร้า | owner ใช้ soft delete; ภาพยังผูกอยู่และไฟล์ถูกลบอัตโนมัติตาม retention ปกติ |
| ลบไฟล์ภาพแล้ว business record หาย | เป็นไปไม่ได้: retention เปลี่ยนแค่ `status` (ไม่ DELETE แถว) และทุก FK เป็น `RESTRICT` ไม่มี `CASCADE` |
