# Security Model (Production-grade)

> Authorization รายละเอียด: [authorization.md](authorization.md) · Upload/ไฟล์: [storage.md](storage.md) · CCTV: [cctv-architecture.md](cctv-architecture.md)
> ข้อมูลในระบบเป็น **ข้อมูลเด็ก** (ภาพ, สุขภาพ, ตำแหน่งห้อง, เวลารับ-ส่ง) — ถือเป็นข้อมูลอ่อนไหวตาม PDPA ทุกการออกแบบเลือกทาง "ปิดไว้ก่อน"

## 1. Threat Model สรุป

| ภัย | ตัวอย่างใน Mykid | มาตรการหลัก |
|---|---|---|
| เห็นข้อมูลเด็กคนอื่น (IDOR) | เปลี่ยน id ใน URL ดูผลงาน/รูปเด็กอื่น | Scope ทุก query + Policy + 404 (§4) |
| ข้ามโรงเรียน | Admin โรงเรียน A ดูข้อมูล B | `school_id` จาก AccessContext + composite FK |
| เดา PIN | PIN 6 หลัก = 1,000,000 แบบ | throttle + lockout + pepper (§2) |
| ขโมย session | Wi-Fi สาธารณะ | HTTPS only, HSTS, Secure/HttpOnly cookie, rotation |
| อัปโหลดไฟล์อันตราย | `.php` ปลอมเป็น `.jpg` | re-encode ภาพ, เก็บนอก webroot, ไม่ execute (§8) |
| เห็น stream กล้อง | ขอ RTSP/URL กล้องตรง | RTSP ไม่ออกนอก Media Server, token อายุสั้น |
| คนในทำผิด | ครูแก้ดาว/ลบรูปย้อนหลัง | ledger, soft delete, audit log |
| ข้อมูลรั่วจาก backup/log | log มี PIN/เนื้อหาแชท | ห้าม log ข้อมูลอ่อนไหว, backup เข้ารหัส |

## 2. Authentication

> **LOCKED (ADR-016)** — Parent / Teacher: **Mobile Number + PIN 6 หลัก** · Super Admin / School Admin / Executive: **Account (username) + Password**
> Security ทั้งสองแบบ: Argon2id + Pepper, Login attempt limit, Account lockout, Session rotation, CSRF, Security headers
> Multi-role (ADR-018): credential แยกตามชนิด login (`user_credentials`) — role กลุ่ม Admin ใช้ได้เฉพาะ session ที่เข้าด้วย Account + Password
> Implementation (ADR-017): **Custom `AuthService`** บน CI4 Session + Throttler + `password_hash` (ไม่ใช้ CodeIgniter Shield)

| เรื่อง | NOW (MVP) | หมายเหตุ / FUTURE |
|---|---|---|
| Identifier | `user_credentials(type, identifier)`: `mobile_pin` → **เบอร์มือถือ** (normalized) · `account_password` → **username** | Email ใช้ติดต่อ ไม่ใช่ identifier |
| Credential | Parent/Teacher: **PIN 6 หลัก** · Super Admin / School Admin / Executive: **Password ≥ 10 ตัว** (ห้ามรหัสที่พบบ่อย/ตรงกับ username) | FUTURE: 2FA (TOTP) สำหรับกลุ่ม Admin |
| Hashing | **Argon2id + Pepper** ทั้ง PIN และ Password: `password_hash(hash_hmac('sha256', $secret, AUTH_PEPPER), PASSWORD_ARGON2ID)` + `pepper_version` (Docker image ตรวจว่ามี Argon2id ตอน build แล้ว) | pepper อยู่ใน env → DB หลุดอย่างเดียวยัง brute-force PIN offline ไม่ได้; `password_needs_rehash` ตอน login |
| Login | 2 ฟอร์มแยก: `POST /login` (เบอร์มือถือ + PIN — หน้าแรก) และ `POST /staff/login` (Account + Password — ลิงก์ "สำหรับผู้ดูแลระบบ"); ทั้งคู่มี CSRF; ข้อความผิดพลาดกลาง ๆ "ข้อมูลเข้าสู่ระบบไม่ถูกต้อง" (ไม่บอกว่าบัญชีมีอยู่) | ฟอร์มกำหนดชนิด credential ที่ค้นหา; identifier ของอีกชนิด = login ไม่สำเร็จแบบเดียวกัน |
| Failed login / Lock | ต่อ credential: 5 ครั้งผิดติดกัน → `user_credentials.locked_until = +15 นาที` (เพิ่มเป็น 1 ชม. ถ้าซ้ำ) + audit `LOGIN_FAILED`/`ACCOUNT_LOCKED` | Admin ปลดล็อกได้; ล็อก PIN ไม่ล็อก password ของคนเดียวกัน |
| Rate limit | CI4 Throttler: 10 req/นาที/IP ต่อฟอร์ม login, 5 req/นาที/identifier (เบอร์หรือ account) | กันทั้งเดาหลายบัญชีและบัญชีเดียว |
| Session | CI4 Session `DatabaseHandler` (Phase 1; foundation ใช้ FileHandler ชั่วคราว), `regenerate(true)` ทันทีหลัง login และเมื่อสลับ role; session เก็บ `credential_type` ที่ใช้ login → AccessContext โหลดเฉพาะ role ที่ `roles.auth_type` ตรงกัน | session fixation, privilege mixing |
| Session timeout | Admin/Executive/Super Admin: idle 30 นาที, absolute 8 ชม. · Teacher/Parent: idle 7 วัน (มือถือส่วนตัว) | ปรับได้ใน config |
| Remember me | Teacher/Parent เท่านั้น: selector + validator (hash) ใน `auth_remember_tokens`, อายุ 30 วัน, rotate ทุกครั้งที่ใช้, ยกเลิกทั้งหมดเมื่อเปลี่ยน PIN/ถูก disable | |
| Logout | ทำลาย session + ลบ remember token ของ device นั้น + audit `LOGOUT` | "ออกจากทุกอุปกรณ์" = ลบ token ทั้งหมด |
| Forgot PIN/Password | **NOW (ไม่มีค่าใช้จ่ายภายนอก)**: School Admin reset PIN ของ Teacher/Parent, Super Admin reset รหัสของ School Admin/Executive (Super Admin ด้วยกันหรือ CLI ฉุกเฉิน) → ค่าชั่วคราวแสดงครั้งเดียว + `user_credentials.must_change=1` | OTP ผ่าน SMS/LINE ผูกกับ **PENDING P11** (External Notification) |
| Change PIN | ต้องใส่ PIN เดิม, ห้าม PIN ง่าย (`000000`, `123456`, เลขเรียง, วันเกิด) | |
| Account status | `users.status=disabled` หรือ `schools.status=suspended` → login ไม่ได้ + session เดิมถูกตัดใน request ถัดไป | |
| Demo accounts | **ไม่มี** ใน Production (PIN 123456 เป็นของ Demo เท่านั้น) | |

## 3. Authorization
ดู [authorization.md](authorization.md) — สรุป: `AuthFilter → PermissionFilter → Service → Policy → Model::forContext()`
ไม่มี `if role === 'teacher'` กระจายในโค้ด

## 4. OWASP Controls

| หัวข้อ | มาตรการใน CI4 |
|---|---|
| **IDOR** | โหลดทุก entity ผ่าน `forContext()`; ไม่เจอ = 404; Policy ต่อ action; test matrix ข้ามโรงเรียน/ห้อง/เด็ก |
| **School / Classroom isolation / Student ownership** | AccessContext จาก DB, composite FK, ห้ามรับ `school_id` จาก input |
| **CSRF** | CI4 CSRF filter global (`csrfProtection='session'`, `regenerate=false` เพื่อใช้กับ AJAX ได้, ยกเว้น `health/*` ที่ไม่มี session — ตั้งค่าแล้วใน foundation), API ที่ใช้ session ต้องส่ง `X-CSRF-TOKEN`; SameSite=Lax เป็นชั้นที่สอง |
| **XSS** | View ใช้ `esc()` ทุกจุด (ค่า default ของ template helper), ไม่ใช้ `innerHTML` กับข้อมูลผู้ใช้ใน JS (ใช้ `textContent`), CSP ห้าม inline script (§6) |
| **SQL Injection** | Query Builder / prepared statements เท่านั้น; ห้าม string concat SQL; ชื่อคอลัมน์ sort มาจาก whitelist |
| **Mass assignment** | `$allowedFields` ทุก Model; Service map input → field ทีละตัว; ฟิลด์ scope/actor/status ตั้งโดย server |
| **Sensitive data exposure** | API คืนเฉพาะฟิลด์ที่ต้องใช้ (DTO/transformer), ไม่คืน hash/phone ของคนอื่น, ไม่มี debug ใน production, birth_date/health เห็นเฉพาะ role ที่จำเป็น |
| **Path traversal** | ไม่ใช้ชื่อไฟล์จากผู้ใช้ (ใช้ ULID), StorageInterface normalize + ปฏิเสธ `..`, ส่งไฟล์ผ่าน `X-Accel-Redirect` จาก path ใน DB เท่านั้น |
| **Rate limiting** | Throttler: login (ข้างบน), API ทั่วไป 120/นาที/user, upload 30 ครั้ง/ชม./user, pickup polling ≥ 3 วินาที/ครั้ง, stream token 30/นาที/user |
| **CORS** | ไม่เปิด (same-origin เท่านั้น); FUTURE mobile app แบบ native ไม่ต้องใช้ CORS; ถ้ามี web client โดเมนอื่นค่อย whitelist เฉพาะ origin |
| **Error handling** | Production: หน้า error ภาษาไทยสุภาพ ("ระบบกำลังดำเนินการ กรุณาลองใหม่อีกครั้ง"), JSON error format มาตรฐาน, ไม่มี stack trace/SQL ใน response; รายละเอียดอยู่ใน log พร้อม request_id |
| **Dependency** | `composer audit` ใน CI, Dependabot บน GitHub, pin major version |

## 5. Session & Cookie

| Setting | ค่า |
|---|---|
| Cookie | `Secure`, `HttpOnly`, `SameSite=Lax`, path `/`, ไม่มี domain กว้าง (host-only) |
| Session ID | 128-bit+ random (CI4 default), regenerate ตอน login/role switch/เปลี่ยน PIN |
| Storage | `ci_sessions` (MySQL) — ไม่เก็บข้อมูลอ่อนไหวใน session นอกจาก user id + AccessContext snapshot |
| Fingerprint | ผูก session กับ user-agent family (ไม่ผูก IP เพราะมือถือเปลี่ยน IP บ่อย) |

## 6. Security Headers (ตั้งที่ nginx + CI4 `SecureHeaders` filter)

| Header | ค่า |
|---|---|
| Strict-Transport-Security | `max-age=31536000; includeSubDomains` |
| Content-Security-Policy | `default-src 'self'; img-src 'self' data: blob:; media-src 'self' blob: https://<media-domain>; connect-src 'self' https://<media-domain>; script-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; frame-ancestors 'none'; base-uri 'self'; form-action 'self'` |
| X-Content-Type-Options | `nosniff` |
| Referrer-Policy | `strict-origin-when-cross-origin` |
| Permissions-Policy | `geolocation=(), camera=(), microphone=(), payment=()` (ระบบไม่ใช้ตำแหน่ง — ตรงกับกติการับ-ส่ง) |
| X-Frame-Options | `DENY` (สำรองของ frame-ancestors) |
| Cache-Control (ข้อมูลส่วนตัว/ภาพ) | `private, no-store` สำหรับ HTML ที่ login แล้ว; ภาพ `private, max-age=300` |

## 7. HTTPS
- TLS ที่ Coolify proxy (Let's Encrypt), redirect HTTP → HTTPS, `app.forceGlobalSecureRequests = true`
- `TRUSTED_PROXIES` ตั้งเฉพาะ proxy ของ Coolify เพื่อให้ IP ใน log/throttle ถูกต้อง
- Media Server ต้องเป็น HTTPS เช่นกัน (browser ไม่ยอม mixed content)

## 8. File Upload Security (ต่อยอดกติกา Demo)

| ขั้น | ตรวจ |
|---|---|
| 1. Authorization | ต้องมีสิทธิ์เขียน owner (กิจกรรม/เมนู/ผลงาน) ใน scope ก่อนรับไฟล์ |
| 2. จำนวน/ขนาด | ≤ 6 ไฟล์/ครั้ง, ≤ 10 MB/ไฟล์ (PHP `upload_max_filesize`, nginx `client_max_body_size` 64m) |
| 3. ชนิด | นามสกุล whitelist `jpg/jpeg/png/webp` **และ** MIME จาก `finfo` **และ** `getimagesize()` อ่านได้; ปฏิเสธ SVG/GIF/HEIC (HEIC: Open — iPhone แปลงฝั่ง client) |
| 4. ขนาดพิกเซล | ≤ 36 MP (กัน decompression bomb) |
| 5. Re-encode | GD/Imagick ย่อ ≤ 1600px + สร้าง thumbnail 400px + **ลบ EXIF ทั้งหมด** (รวม GPS ในรูป) |
| 6. ตั้งชื่อ | `{ulid}.{ext}` — ไม่มีชื่อเดิม/ชื่อเด็กใน path |
| 7. ที่เก็บ | นอก webroot (`/var/lib/mykid/storage`), nginx ไม่ execute PHP ในนั้น, ส่งผ่าน internal location เท่านั้น |
| 8. บันทึก | `media_files` + join table + audit `UPLOAD_MEDIA` ใน transaction; ถ้า DB fail → ลบไฟล์ที่เพิ่งเขียน |
| 9. Client | ย่อภาพด้วย canvas ก่อนส่ง (ลด data มือถือ) — **ไม่ใช่** ด่านความปลอดภัย |

## 9. Secrets Management
- ค่าจริงอยู่ใน Coolify Environment Variables (encrypted at rest) เท่านั้น; repo มี `env.example` ที่เป็นค่าว่าง
- `.env`, `writable/`, `storage/` อยู่ใน `.gitignore` + `.dockerignore`
- แยก secret ต่อ environment (dev/staging/prod ไม่ใช้ key ร่วมกัน)
- Rotation: `CCTV_STREAM_TOKEN_SECRET` และ DB password หมุนได้โดยไม่ต้อง deploy code; `encryption.key` และ `AUTH_PEPPER` **ห้ามหาย** (สำรองไว้ใน password manager ขององค์กร — ถ้า pepper หาย PIN ทุกคนต้อง reset)
- RTSP username/password อยู่ที่ Media Server เท่านั้น (ไม่เคยอยู่ใน app DB/frontend)
- DB user แยก: `mykid_migrator` (DDL ตอน deploy) / `mykid_app` (DML, ไม่มี DROP/ALTER, audit_logs INSERT/SELECT เท่านั้น)

## 10. Data Protection (PDPA-oriented)
- Data minimization: ไม่เก็บเลขบัตรประชาชน, ไม่เก็บตำแหน่ง GPS (รับ-ส่งใช้ ETA เท่านั้น), ลบ EXIF จากภาพ
- ภาพเด็ก: private เสมอ, หมดอายุ 6 เดือน, backup ของภาพมีอายุจำกัด (ดู [backup-and-recovery.md](backup-and-recovery.md))
- Consent การถ่ายภาพ/CCTV: กระบวนการของโรงเรียน (นอกระบบ) — ระบบรองรับการปิดสิทธิ์ดูกล้องของผู้ปกครองต่อกล้อง
- Hosting / ที่ตั้งข้อมูล: **PENDING P9** — ข้อเสนอคือในไทย/ภูมิภาคใกล้

## 11. Logging Rules
- Format `[ModuleName][FunctionName] START/END/ERROR` + context (ตามมาตรฐานทีม) แต่ **mask**: PIN, password, token, remember selector, เนื้อหาข้อความแชท, ข้อมูลสุขภาพ
- API request/response log ระดับ debug เฉพาะ development; production log เฉพาะ metadata (method, route, status, latency, user_id, request_id)

## 12. Health Check
- `/health/live` → `200 {"status":"ok"}` ไม่แตะ DB (Docker HEALTHCHECK)
- `/health/ready` → ตรวจ DB `SELECT 1` + storage writable → `200`/`503` พร้อม `{"status":"fail"}` (ไม่มีรายละเอียด error, version, path)
- ไม่ต้อง login แต่ throttle และไม่มีข้อมูลอ่อนไหว

## 13. Audit Logging

ตาราง `audit_logs` (ดู [database.md §12.2](database.md#122-audit_logs)) — append-only, DB user ของแอปไม่มีสิทธิ์ UPDATE/DELETE; `actor_type` = `user` หรือ `system` (job/command)

| Action | เมื่อ | metadata (whitelist) |
|---|---|---|
| `LOGIN`, `LOGIN_FAILED`, `ACCOUNT_LOCKED`, `LOGOUT`, `ROLE_SWITCH` | auth | `reason` (wrong_credential/locked/disabled) |
| `RESET_CREDENTIAL`, `CHANGE_CREDENTIAL` | PIN/password | — (ไม่มีค่า PIN) |
| `CREATE_USER`, `UPDATE_USER`, `DISABLE_USER`, `ASSIGN_ROLE`, `REVOKE_ROLE` | user admin | `role`, `school_id` |
| `CREATE_STUDENT`, `UPDATE_STUDENT`, `LINK_PARENT`, `UNLINK_PARENT`, `MOVE_CLASSROOM` | students | `fields` (ชื่อฟิลด์ที่เปลี่ยน ไม่ใช่ค่า) |
| `UPLOAD_MEDIA`, `DELETE_MEDIA` (ผู้ใช้ลบรายรูป) | media | `count`, `bytes`, `category` |
| `MEDIA_RETENTION_DELETE` (ต่อไฟล์, `actor_type=system`) | scheduled retention job | entity = media id, `school_id`, `deleted_at`, `result` (success/failed), `error_code` เมื่อล้มเหลว, `file_missing` — **ไม่มี path ไฟล์/ชื่อเด็ก** |
| `MEDIA_RETENTION_RUN` (ต่อรอบ, `actor_type=system`) | scheduled retention job | `selected`, `deleted`, `missing_file`, `failed`, `bytes_freed`, `duration_ms` |
| `MEDIA_RECONCILE`, `MEDIA_RESTORE` | ops commands | จำนวนไฟล์ |
| `AWARD_STAR`, `REVERSE_STAR` | stars | `points` |
| `CREATE_PICKUP`, `UPDATE_PICKUP`, `COMPLETE_PICKUP` | รับ-ส่ง | `from`, `to`, `eta_minutes` |
| `UPDATE_ATTENDANCE`, `UPDATE_HEALTH` | daily records | `status` (ไม่ใส่อาการ/อุณหภูมิ) |
| `CREATE_CAMERA`, `UPDATE_CAMERA`, `VIEW_CAMERA` (ออก token) | CCTV | `camera_code` |
| `PERMISSION_DENIED` | 403/404 จาก policy | `route`, `entity_type` |
| `PUBLISH_NOTIFICATION` | ประกาศ | `target_type`, `recipient_count` |

ห้ามเก็บ: PIN/password/token, เนื้อหาแชท, ค่าผลสุขภาพ, ไฟล์ภาพ, เบอร์โทรของบุคคลอื่นใน metadata

Retention ของ audit: เก็บตามระบบ (ไม่ลบอัตโนมัติ) — ปริมาณประมาณ < 50k แถว/เดือนที่ 2 โรงเรียน;
FUTURE: partition รายปี / archive เมื่อเกิน 10M แถว
