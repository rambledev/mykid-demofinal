-- Mykid — MariaDB schema (11.4 LTS, utf8mb4).
--
-- One table per app table of core/schema.php (camelCase app name → snake_case table name,
-- see mk_db_table() in core/db.php). Column names are the app's row keys, unchanged.
--
-- Conventions
--   id        assigned by the app from app_meta.nextId (global sequence), not AUTO_INCREMENT
--   DATE      Y-m-d,  DATETIME  Y-m-d H:i:s
--   *_hm      clock times are VARCHAR(5) "HH:MM" ('' = not recorded yet)
--   JSON      lists / objects (MariaDB stores JSON as LONGTEXT + json_valid check)
--   extra     JSON bag for row keys that have no column yet (core/store.php) — keeps writes lossless
--
-- Applied automatically on first request when app_meta is missing (core/db.php), or by hand:
--   php database/install.php

CREATE TABLE IF NOT EXISTS app_meta (
    k VARCHAR(32)  NOT NULL PRIMARY KEY,
    v VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO app_meta (k, v) VALUES ('lock', '1');

-- ---------------------------------------------------------------- Organization

CREATE TABLE IF NOT EXISTS schools (
    id        INT UNSIGNED NOT NULL PRIMARY KEY,
    name      VARCHAR(120) NULL DEFAULT '',
    shortName VARCHAR(60)  NULL DEFAULT '',
    emoji     VARCHAR(16)  NULL DEFAULT '',
    color     VARCHAR(16)  NULL DEFAULT '',
    province  VARCHAR(60)  NULL DEFAULT '',
    status    VARCHAR(16)  NULL DEFAULT 'active',
    extra     JSON NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS classrooms (
    id        INT UNSIGNED NOT NULL PRIMARY KEY,
    school_id INT UNSIGNED NOT NULL,
    name      VARCHAR(60)  NULL DEFAULT '',
    emoji     VARCHAR(16)  NULL DEFAULT '',
    color     VARCHAR(16)  NULL DEFAULT '',
    capacity  SMALLINT UNSIGNED NULL,
    extra     JSON NULL,
    KEY idx_school (school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    id        INT UNSIGNED NOT NULL PRIMARY KEY,
    school_id INT UNSIGNED NOT NULL,
    phone     VARCHAR(20)  NULL DEFAULT '',
    address   VARCHAR(255) NULL DEFAULT '',
    openTime  VARCHAR(5)   NULL DEFAULT '',
    closeTime VARCHAR(5)   NULL DEFAULT '',
    motto     VARCHAR(255) NULL DEFAULT '',
    extra     JSON NULL,
    KEY idx_school (school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- People

CREATE TABLE IF NOT EXISTS users (
    id           INT UNSIGNED NOT NULL PRIMARY KEY,
    name         VARCHAR(120) NULL DEFAULT '',
    phone        VARCHAR(20)  NOT NULL,
    pin          VARCHAR(255) NULL DEFAULT '',
    role         VARCHAR(20)  NOT NULL,
    school_id    INT UNSIGNED NULL,
    classroom_id INT UNSIGNED NULL,
    student_id   INT UNSIGNED NULL,
    teacher_id   INT UNSIGNED NULL,
    relation     VARCHAR(20)  NULL,
    children     JSON NULL,
    extra        JSON NULL,
    KEY idx_phone (phone),
    KEY idx_school (school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teachers (
    id           INT UNSIGNED NOT NULL PRIMARY KEY,
    school_id    INT UNSIGNED NOT NULL,
    classroom_id INT UNSIGNED NULL,
    nickname     VARCHAR(60)  NULL DEFAULT '',
    fullName     VARCHAR(120) NULL DEFAULT '',
    position     VARCHAR(60)  NULL DEFAULT '',
    emoji        VARCHAR(16)  NULL DEFAULT '',
    phone        VARCHAR(20)  NULL,
    extra        JSON NULL,
    KEY idx_school_room (school_id, classroom_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS students (
    id             INT UNSIGNED NOT NULL PRIMARY KEY,
    school_id      INT UNSIGNED NOT NULL,
    classroom_id   INT UNSIGNED NULL,
    code           VARCHAR(20)  NULL DEFAULT '',
    name           VARCHAR(120) NULL DEFAULT '',
    nickname       VARCHAR(60)  NULL DEFAULT '',
    gender         VARCHAR(4)   NULL DEFAULT '',
    classroom      VARCHAR(60)  NULL DEFAULT '',
    parentName     VARCHAR(120) NULL DEFAULT '',
    parentRelation VARCHAR(20)  NULL DEFAULT '',
    avatar         JSON NULL,
    extra          JSON NULL,
    KEY idx_school_room (school_id, classroom_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS parent_students (
    id         INT UNSIGNED NOT NULL PRIMARY KEY,
    school_id  INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    relation   VARCHAR(20)  NULL DEFAULT '',
    extra      JSON NULL,
    KEY idx_user (user_id),
    KEY idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Classroom day

CREATE TABLE IF NOT EXISTS activities (
    id           INT UNSIGNED NOT NULL PRIMARY KEY,
    school_id    INT UNSIGNED NOT NULL,
    classroom_id INT UNSIGNED NULL,
    date         DATE NULL,
    time         VARCHAR(5)   NULL DEFAULT '',
    icon         VARCHAR(16)  NULL DEFAULT '',
    title        VARCHAR(120) NULL DEFAULT '',
    detail       VARCHAR(255) NULL DEFAULT '',
    extra        JSON NULL,
    KEY idx_room_date (classroom_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS food_menus (
    id           INT UNSIGNED NOT NULL PRIMARY KEY,
    school_id    INT UNSIGNED NOT NULL,
    classroom_id INT UNSIGNED NULL,
    date         DATE NULL,
    breakfast    JSON NULL,
    lunch        JSON NULL,
    snack        JSON NULL,
    milk         JSON NULL,
    fruit        JSON NULL,
    extra        JSON NULL,
    KEY idx_room_date (classroom_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Classroom status history (newest first in the app: read ORDER BY id DESC).
CREATE TABLE IF NOT EXISTS statuses (
    id           INT UNSIGNED NOT NULL PRIMARY KEY,
    school_id    INT UNSIGNED NOT NULL,
    classroom_id INT UNSIGNED NULL,
    code         VARCHAR(32)  NULL DEFAULT '',
    note         VARCHAR(120) NULL DEFAULT '',
    at           DATETIME NULL,
    `by`         INT UNSIGNED NULL,
    extra        JSON NULL,
    KEY idx_room (classroom_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS photos (
    id           INT UNSIGNED NOT NULL PRIMARY KEY,
    school_id    INT UNSIGNED NOT NULL,
    classroom_id INT UNSIGNED NULL,
    date         DATE NULL,
    caption      VARCHAR(120) NULL DEFAULT '',
    activity     VARCHAR(120) NULL DEFAULT '',
    emoji        VARCHAR(16)  NULL DEFAULT '',
    art          TINYINT UNSIGNED NULL,
    extra        JSON NULL,
    KEY idx_room_date (classroom_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calendar_events (
    id        INT UNSIGNED NOT NULL PRIMARY KEY,
    school_id INT UNSIGNED NOT NULL,
    date      DATE NULL,
    time      VARCHAR(5)   NULL DEFAULT '',
    emoji     VARCHAR(16)  NULL DEFAULT '',
    title     VARCHAR(120) NULL DEFAULT '',
    dress     VARCHAR(120) NULL DEFAULT '',
    bring     VARCHAR(160) NULL DEFAULT '',
    dismiss   VARCHAR(5)   NULL DEFAULT '',
    extra     JSON NULL,
    KEY idx_school_date (school_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id           INT UNSIGNED NOT NULL PRIMARY KEY,
    school_id    INT UNSIGNED NOT NULL,
    classroom_id INT UNSIGNED NULL,
    student_id   INT UNSIGNED NULL,
    type         VARCHAR(32)  NULL DEFAULT '',
    title        VARCHAR(160) NULL DEFAULT '',
    body         TEXT NULL,
    at           DATETIME NULL,
    extra        JSON NULL,
    KEY idx_school (school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Student records

CREATE TABLE IF NOT EXISTS attendance (
    id           INT UNSIGNED NOT NULL PRIMARY KEY,
    school_id    INT UNSIGNED NOT NULL,
    classroom_id INT UNSIGNED NULL,
    student_id   INT UNSIGNED NOT NULL,
    date         DATE NULL,
    status       VARCHAR(16)  NULL DEFAULT '',
    checkIn      VARCHAR(5)   NULL DEFAULT '',
    note         VARCHAR(120) NULL DEFAULT '',
    extra        JSON NULL,
    KEY idx_student_date (student_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sleep_records (
    id           INT UNSIGNED NOT NULL PRIMARY KEY,
    school_id    INT UNSIGNED NOT NULL,
    classroom_id INT UNSIGNED NULL,
    student_id   INT UNSIGNED NOT NULL,
    date         DATE NULL,
    start        VARCHAR(5)   NULL DEFAULT '',
    end          VARCHAR(5)   NULL DEFAULT '',
    quality      VARCHAR(16)  NULL DEFAULT '',
    note         VARCHAR(255) NULL DEFAULT '',
    extra        JSON NULL,
    KEY idx_student_date (student_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS health_records (
    id           INT UNSIGNED NOT NULL PRIMARY KEY,
    school_id    INT UNSIGNED NOT NULL,
    classroom_id INT UNSIGNED NULL,
    student_id   INT UNSIGNED NOT NULL,
    date         DATE NULL,
    temperature  DOUBLE NULL,
    `condition`  VARCHAR(16)  NULL DEFAULT '',
    symptoms     VARCHAR(120) NULL DEFAULT '',
    note         VARCHAR(255) NULL DEFAULT '',
    extra        JSON NULL,
    KEY idx_student_date (student_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS food_intake (
    id           INT UNSIGNED NOT NULL PRIMARY KEY,
    school_id    INT UNSIGNED NOT NULL,
    classroom_id INT UNSIGNED NULL,
    student_id   INT UNSIGNED NOT NULL,
    date         DATE NULL,
    levels       JSON NULL,
    extra        JSON NULL,
    KEY idx_student_date (student_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_statuses (
    id           INT UNSIGNED NOT NULL PRIMARY KEY,
    school_id    INT UNSIGNED NOT NULL,
    classroom_id INT UNSIGNED NULL,
    student_id   INT UNSIGNED NOT NULL,
    date         DATE NULL,
    status       VARCHAR(100) NULL DEFAULT '',
    note         TEXT NULL,
    at           DATETIME NULL,
    `by`         VARCHAR(120) NULL,
    by_user      INT UNSIGNED NULL,
    extra        JSON NULL,
    KEY idx_student_date (student_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stars (
    id           INT UNSIGNED NOT NULL PRIMARY KEY,
    school_id    INT UNSIGNED NOT NULL,
    classroom_id INT UNSIGNED NULL,
    student_id   INT UNSIGNED NOT NULL,
    date         DATE NULL,
    points       SMALLINT NOT NULL DEFAULT 0,
    reason       VARCHAR(120) NULL DEFAULT '',
    `by`         INT UNSIGNED NULL,
    extra        JSON NULL,
    KEY idx_student_date (student_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS development (
    id           INT UNSIGNED NOT NULL PRIMARY KEY,
    school_id    INT UNSIGNED NOT NULL,
    classroom_id INT UNSIGNED NULL,
    student_id   INT UNSIGNED NOT NULL,
    updated      DATE NULL,
    language     TINYINT UNSIGNED NULL,
    math         TINYINT UNSIGNED NULL,
    social       TINYINT UNSIGNED NULL,
    motor        TINYINT UNSIGNED NULL,
    creative     TINYINT UNSIGNED NULL,
    note         TEXT NULL,
    extra        JSON NULL,
    KEY idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portfolio (
    id           INT UNSIGNED NOT NULL PRIMARY KEY,
    school_id    INT UNSIGNED NOT NULL,
    classroom_id INT UNSIGNED NULL,
    student_id   INT UNSIGNED NOT NULL,
    date         DATE NULL,
    title        VARCHAR(120) NULL DEFAULT '',
    category     VARCHAR(32)  NULL DEFAULT '',
    art          TINYINT UNSIGNED NULL,
    comment      TEXT NULL,
    extra        JSON NULL,
    KEY idx_student_date (student_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
    id           INT UNSIGNED NOT NULL PRIMARY KEY,
    school_id    INT UNSIGNED NOT NULL,
    classroom_id INT UNSIGNED NULL,
    student_id   INT UNSIGNED NOT NULL,
    `from`       VARCHAR(16)  NULL DEFAULT '',
    sender       VARCHAR(120) NULL DEFAULT '',
    text         TEXT NULL,
    at           DATETIME NULL,
    extra        JSON NULL,
    KEY idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- รับ-ส่ง (core/pickup.php)
CREATE TABLE IF NOT EXISTS pickups (
    id                INT UNSIGNED NOT NULL PRIMARY KEY,
    school_id         INT UNSIGNED NOT NULL,
    classroom_id      INT UNSIGNED NULL,
    student_id        INT UNSIGNED NOT NULL,
    date              DATE NULL,
    status            VARCHAR(32)  NULL DEFAULT '',
    parent_id         INT UNSIGNED NULL,
    parent_name       VARCHAR(120) NULL DEFAULT '',
    requested_at      DATETIME NULL,
    preparing_at      DATETIME NULL,
    ready_at          DATETIME NULL,
    completed_at      DATETIME NULL,
    completed_by      INT UNSIGNED NULL,
    completed_by_name VARCHAR(120) NULL,
    extra             JSON NULL,
    KEY idx_student_date (student_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Notifications (per user)

CREATE TABLE IF NOT EXISTS user_notifications (
    id             INT UNSIGNED NOT NULL PRIMARY KEY,
    school_id      INT UNSIGNED NULL,
    user_id        INT UNSIGNED NOT NULL,
    type           VARCHAR(32)  NULL DEFAULT '',
    title          VARCHAR(160) NULL DEFAULT '',
    message        TEXT NULL,
    reference_type VARCHAR(32)  NULL,
    reference_id   INT UNSIGNED NULL,
    created_at     DATETIME NULL,
    read_at        DATETIME NULL,
    extra          JSON NULL,
    KEY idx_user (user_id, read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- CCTV

CREATE TABLE IF NOT EXISTS cameras (
    id           INT UNSIGNED NOT NULL PRIMARY KEY,
    school_id    INT UNSIGNED NOT NULL,
    classroom_id INT UNSIGNED NULL,
    code         VARCHAR(16)  NULL DEFAULT '',
    name         VARCHAR(120) NULL DEFAULT '',
    location     VARCHAR(120) NULL DEFAULT '',
    description  VARCHAR(255) NULL DEFAULT '',
    scene        VARCHAR(32)  NULL DEFAULT '',
    vendor       VARCHAR(60)  NULL DEFAULT '',
    camera_model VARCHAR(60)  NULL DEFAULT '',
    stream_type  VARCHAR(16)  NULL DEFAULT 'mock',
    stream_url   VARCHAR(500) NULL DEFAULT '',
    rtsp_url     VARCHAR(500) NULL DEFAULT '',
    status       VARCHAR(16)  NULL DEFAULT 'online',
    is_active    TINYINT(1)   NOT NULL DEFAULT 1,
    extra        JSON NULL,
    KEY idx_school (school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS camera_permissions (
    id           INT UNSIGNED NOT NULL PRIMARY KEY,
    school_id    INT UNSIGNED NOT NULL,
    camera_id    INT UNSIGNED NOT NULL,
    role         VARCHAR(20)  NOT NULL,
    classroom_id INT UNSIGNED NULL,
    can_view     TINYINT(1)   NOT NULL DEFAULT 0,
    extra        JSON NULL,
    KEY idx_camera (camera_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Media (files live in storage/uploads)

CREATE TABLE IF NOT EXISTS media_files (
    id           INT UNSIGNED NOT NULL PRIMARY KEY,
    school_id    INT UNSIGNED NOT NULL,
    classroom_id INT UNSIGNED NULL,
    student_id   INT UNSIGNED NULL,
    owner_type   VARCHAR(16)  NOT NULL,
    owner_id     INT UNSIGNED NOT NULL,
    meal         VARCHAR(16)  NULL,
    file_path    VARCHAR(255) NULL DEFAULT '',
    file_size    INT UNSIGNED NOT NULL DEFAULT 0,
    mime_type    VARCHAR(32)  NULL DEFAULT '',
    width        INT UNSIGNED NULL,
    height       INT UNSIGNED NULL,
    uploaded_at  DATETIME NULL,
    expires_at   DATETIME NULL,
    deleted_at   DATETIME NULL,
    image_status VARCHAR(16)  NULL DEFAULT '',
    uploaded_by  INT UNSIGNED NULL,
    extra        JSON NULL,
    KEY idx_owner (owner_type, owner_id),
    KEY idx_school (school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Migrations (idempotent, run when this file changes)

-- ธนาคารความดีขับขี่ปลอดภัย: stars for parents too (recipient = 'parent' + parent_id).
ALTER TABLE stars ADD COLUMN IF NOT EXISTS recipient VARCHAR(10) NOT NULL DEFAULT 'student' AFTER student_id;
ALTER TABLE stars ADD COLUMN IF NOT EXISTS parent_id INT UNSIGNED NULL AFTER recipient;
ALTER TABLE stars ADD INDEX IF NOT EXISTS idx_recipient_date (recipient, date);

-- Parent login account phone kept on the student record (app/lib/accounts.php).
ALTER TABLE students ADD COLUMN IF NOT EXISTS parentPhone VARCHAR(20) NULL AFTER parentRelation;

-- Chat: when the OTHER side read the message (NULL = unread) → unread badges + "อ่านแล้ว".
ALTER TABLE messages ADD COLUMN IF NOT EXISTS read_at DATETIME NULL AFTER at;
ALTER TABLE messages ADD INDEX IF NOT EXISTS idx_student_read (student_id, read_at);

-- ---------------------------------------------------------------- Security

-- Login throttling (app/lib/security.php): failed attempts per phone / IP inside a 15-minute window.
CREATE TABLE IF NOT EXISTS login_attempts (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    phone        VARCHAR(20)  NOT NULL,
    ip           VARCHAR(45)  NOT NULL,
    success      TINYINT(1)   NOT NULL DEFAULT 0,
    attempted_at DATETIME     NOT NULL,
    KEY idx_phone_time (phone, attempted_at),
    KEY idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
