# Mykid — Final Demo v1.0 (Baseline / Reference Implementation)

> **สถานะ: FROZEN** — Final Demo นี้คือ *Reference Implementation* ของ Business / UX สำหรับ Production
> ไม่ใช่ Production codebase · ห้าม refactor / rewrite / เปลี่ยน architecture / เปลี่ยน UI-Flow ที่ยืนยันแล้ว
> สิ่งที่ควรปรับแต่ไม่ใช่ bug หรือ requirement ที่ยืนยัน → **DO NOT CHANGE** → บันทึกใน §14 (Future Improvement)

## 1. Final Demo Version
| รายการ | ค่า |
|---|---|
| Version | **Final Demo v1.0** |
| Git tag (เป้าหมาย) | `final-demo-v1.0` — **ยังไม่สร้าง** (ดู §16 Git Freeze Status) |
| Repository | `rambledev/mykid` (branch `main`) |
| Commit ล่าสุดบน `main` | `90c6203` + งานที่ยังไม่ commit 4 ชุด (§16) |
| Stack | PHP 8.x procedural + HTML/CSS/Vanilla JS, ข้อมูล mock ใน JSON file store (`pack-*/storage/demo-state.json`, schema **6**) |
| Deploy (Demo) | Dockerfile PHP 8.3 + Apache (Coolify) |

## 2. Freeze Date
**2026-10-07** (Asia/Bangkok)

## 3. Scope
- **Package A (ลูกค้าเลือก — Reference หลัก)**: multi-school, 5 roles, ฟีเจอร์ครบ (`/pack-a/`)
- **Package B / C**: คงไว้เพื่อ regression เท่านั้น (`/pack-b/`, `/pack-c/`) — ไม่ใช่ scope Production
- หน้าแรก (`/`): **Role Selection** 5 บทบาท → เข้าบัญชี demo ของ Package A อัตโนมัติ (เฉพาะ Demo)

## 4. Roles
| Role | เห็นข้อมูล | Layout |
|---|---|---|
| Super Admin | ทุกโรงเรียน | console (desktop) |
| School Admin | โรงเรียนตัวเอง | console |
| Executive | โรงเรียนตัวเอง (อ่านอย่างเดียว) | console |
| Teacher | ห้องที่รับผิดชอบ | app (mobile, bottom nav) |
| Parent | ลูกของตัวเอง (`parentStudents`) | app (mobile, bottom nav) |

## 5. Schools
| id | ชื่อ | ห้อง | นักเรียน | กล้อง |
|---|---|---|---|---|
| 1 | ศูนย์พัฒนาเด็กเล็กบ้านโพนสูง 1 (ศพด.บ้านโพนสูง 1) | 3 | 60 | 5 |
| 2 | ศูนย์พัฒนาเด็กเล็กบ้านโพนสูง 2 (ศพด.บ้านโพนสูง 2) | 3 | 60 | 5 |

School isolation: ทุกข้อมูลกรองด้วย scope จาก session (school → classroom → student) ไม่เชื่อ id จาก URL

## 6. Main Features (Package A)
| กลุ่ม | ฟีเจอร์ |
|---|---|
| Daily | สถานะห้องเรียน, กิจกรรมประจำวัน + รูป, เมนูอาหาร 5 มื้อ + รูปรายมื้อ (ถ่ายภาพ/เลือกรูป), การรับประทานอาหารรายคน, เช็คชื่อ, การนอน, สุขภาพ, **สถานะรายบุคคล** (ครูพิมพ์เอง + หมายเหตุ, ผู้ปกครองเห็น) |
| Student | **ผลงานนักเรียน / ผลงานของฉัน**, ดาวสะสม ("สะสมดาว" อยู่ใน bottom nav ครู), พัฒนาการ, โปรไฟล์ |
| Communication | แชทผู้ปกครอง↔ครู, ประกาศโรงเรียน, ปฏิทิน, **แจ้งเตือนในแอป + badge สีแดง** |
| รับ-ส่ง | Flow ใหม่ไม่ระบุเวลา (§7) รองรับลูกหลายคน |
| CCTV | 10 กล้อง (mock / HLS / WebRTC abstraction), สิทธิ์ตาม role + ห้อง, token อายุสั้น, RTSP ไม่ถึง browser |
| Admin | จัดการนักเรียน/ครู/ห้อง/กิจกรรม/อาหาร/ปฏิทิน/ดาว/กล้อง, หน้า "จัดการพื้นที่จัดเก็บ" |
| Super Admin | ภาพรวม 2 โรงเรียน, ดูรายโรงเรียน, เปรียบเทียบ, จัดการผู้ดูแลโรงเรียน, CCTV Management |
| Media | Upload ปลอดภัย (whitelist + finfo + getimagesize + ≤10MB + ≤6 ไฟล์ + ≤36MP), ย่อ ≤1600px, ชื่อไฟล์สุ่ม, ส่งผ่าน `media.php?id` ตรวจสิทธิ์ทุกครั้ง |

## 7. Pickup Flow (รับ-ส่ง)
- ผู้ปกครอง **ไม่ระบุเวลา/ETA** — กดปุ่มเดียว **"กำลังไปรับลูก"** แยกต่อเด็กแต่ละคน (ไม่รวม request)

```
pending ──(ครู: เตรียมกลับบ้าน)──► preparing ──(ครู: ถึงจุดรับส่งแล้ว)──► ready_for_pickup ──(ครู: ส่งมอบนักเรียนแล้ว)──► completed
```

| Status | แสดง | ความหมาย |
|---|---|---|
| `pending` | 🔵 กำลังไปรับลูก | ผู้ปกครองแจ้งว่ากำลังเดินทางมารับ |
| `preparing` | 🟡 เตรียมกลับบ้าน | ครูกำลังเตรียมนักเรียนและพาไปยังจุดรับ-ส่ง |
| `ready_for_pickup` | 🟢 ถึงจุดรับส่งแล้ว | นักเรียนถึงจุดรับ-ส่งแล้ว ผู้ปกครองสามารถมารับได้ |
| `completed` | ✅ ส่งมอบนักเรียนแล้ว | ครูยืนยันการส่งมอบนักเรียนแล้ว |

- ข้ามสถานะไม่ได้ (422) · กดซ้ำวันเดียวกันไม่ได้ (422) · ผู้ปกครองเปลี่ยนสถานะไม่ได้ (403)
- ครูจัดการได้เฉพาะเด็กในห้องตัวเอง · ผู้ปกครองสร้าง request ได้เฉพาะลูกที่ผูกใน `parentStudents`
- ทั้งสองหน้ามีส่วน **"สถานะการรับนักเรียน"** อธิบายแต่ละสถานะ · หน้ารับ-ส่ง polling ทุก 4 วินาที
- Implementation อ้างอิง: `core/pickup.php`, `core/pages/{teacher,parent}/pickup.php`

## 8. Notification Flow (In-App Only)
- **มี**: Notification Center (เมนู "แจ้งเตือน"), unread count, **badge สีแดง** (bottom nav / เมนู / กระดิ่งบน header), ● ยังไม่อ่าน / ○ อ่านแล้ว, เปิดแล้วเป็น read + ไปหน้าที่อ้างอิง (`reference_type/reference_id`), "อ่านทั้งหมด"
- **ไม่มี**: Firebase, APNs, Web Push, push token, WebSocket, Redis, external provider
- Service กลาง: `core/notifications.php` (`notify_users` / `notification_create`, `notifications_for`, `notifications_unread_count`, `notification_mark_read`, `notification_mark_all_read`)
- Model `userNotifications`: id, school_id, user_id, type, title, message, reference_type, reference_id, created_at, read_at
- Badge อัปเดตจาก polling (`version` ทุก 8 วินาที, `pickup_status` ทุก 4 วินาที) และจากทุก response ของ API
- Event ที่สร้างแจ้งเตือน: ผู้ปกครองกด "กำลังไปรับลูก" → ครูประจำห้องของเด็ก · ครูเปลี่ยนสถานะ → ผู้ปกครองทุกคนของเด็ก
- ผู้ใช้เห็น/อ่านได้เฉพาะของตัวเอง (ของคนอื่น → 403)
- Role ที่มีเมนูแจ้งเตือน: Teacher, Parent

## 9. Student Work Flow
**Teacher — เมนู "ผลงานนักเรียน"**: เลือกห้อง → เลือกนักเรียนรายบุคคล → เพิ่มผลงาน (ชื่อ, หมวด, วันที่, รายละเอียด, upload หลายรูป + preview) → แก้ไข → ลบ
**Parent — เมนู "ผลงานของฉัน"**: เลือกลูก (แท็บ เมื่อมีหลายคน) → ดูผลงานรายบุคคล หลายรูปต่อผลงาน → แตะรูปเพื่อดูเต็มจอ

- Data: ใช้ `portfolio` (1 แถว = 1 ผลงานของเด็ก 1 คน, `student_id`) + `mediaFiles` (owner `portfolio`, หลายรูปต่อผลงาน)
- Authorization: Teacher เห็นเฉพาะ student ใน classroom ของตน · Parent เห็นเฉพาะ student ที่ผูกผ่าน `parentStudents` (รวมรูปของลูกคนที่ 2) · รูปของเด็กอื่น → 404
- Empty state เมื่อเด็กยังไม่มีผลงาน · เมนูเดิม "ผลงาน & ภาพ" เปลี่ยนชื่อเป็น "ภาพกิจกรรม" (ไม่ได้ลบ)

## 10. Multiple Children Flow
- Relationship `parentStudents` (Many-to-Many): parent 1 คนมีลูกหลายคน, เด็ก 1 คนมีผู้ปกครองหลายคน
- ตัวอย่าง: **คุณแม่ของน้องน้ำใสและน้องไผ่** (0898765440) — ลูก 2 คน **คนละห้อง** (อนุบาล 1 / อนุบาล 2)
- รองรับหลายลูกใน: **รับ-ส่ง** (การ์ดแยกต่อเด็ก, request แยก, แจ้งครูของแต่ละห้อง) และ **ผลงานของฉัน** (แท็บต่อลูก)
- หน้าอื่นแสดงลูกคนแรก (Known limitation §14)

## 11. Image Retention Behavior (Demo)
| รายการ | Final Demo v1.0 |
|---|---|
| รูปที่อยู่ใต้กฎ | Activity, Food, Student Work / Portfolio |
| อายุไฟล์ | 6 เดือน (`expires_at = uploaded_at + 6 months`) |
| เมื่อหมดอายุ | แสดง placeholder **"รูปภาพหมดเวลาเก็บไฟล์"** ทันที (ไม่มี broken image) |
| การลบไฟล์จริง | **Admin กดปุ่มล้างไฟล์ที่หน้า "จัดการพื้นที่จัดเก็บ"** → ลบ physical file + `image_status=deleted`, `deleted_at` |
| Database record | **ไม่ลบ** ทั้ง media record และ business/history record |

> ต่างจาก Production: Production ลบไฟล์จริง **อัตโนมัติ** ด้วย scheduled job (ADR-006) — ดู §15

## 12. Demo Accounts (Package A · PIN `123456` ทุกบัญชี)
| Role | ศพด.บ้านโพนสูง 1 | ศพด.บ้านโพนสูง 2 |
|---|---|---|
| Super Admin | 0900000001 ผู้ดูแลระบบ Mykid (ทุกโรงเรียน) | — |
| School Admin | 0900000002 คุณแอดมินโพนสูง 1 | 0920000002 คุณแอดมินโพนสูง 2 |
| Executive | 0900000003 ผู้อำนวยการโพนสูง 1 | 0920000003 ผู้อำนวยการโพนสูง 2 |
| Teacher | 0812345678 ครูมะลิ (อนุบาล 1) · 0812345679 ครูใบเตย (อนุบาล 2) · 0812345680 ครูน้ำ (อนุบาล 3) | 0920000011 ครูแพรว · 0920000012 ครูปิยะ · 0920000013 ครูนภา |
| Parent | 0898765432 คุณแม่ของน้องต้น · 0898765433–37 | 0920000021 คุณแม่ของน้องกาย · 0920000022–24 |
| Parent (ลูก 2 คน) | **0898765440 คุณแม่ของน้องน้ำใสและน้องไผ่** | — |

Demo data ตั้งต้น: รับ-ส่งครบ 4 สถานะทุกห้อง (น้องน้ำใส 🟢, น้องไผ่ยังไม่มี request), แจ้งเตือนทั้ง read/unread ทุกบัญชีครู/ผู้ปกครอง, ผลงานหลายรูป, เด็กทุกคนที่ 10 ไม่มีผลงาน (empty state)

## 13. Test Results (2026-10-07 — ตรวจซ้ำก่อน Freeze, state ใหม่)
| ชุด | ผล |
|---|---|
| Final Demo (รับ-ส่ง, ลูกหลายคน, แจ้งเตือน, ผลงาน, สิทธิ์) | **48/48** |
| Mobile flow (2 จอพร้อมกัน, badge, gallery, upload, 320/360 px) | **27/27** |
| Package C | **49/49** |
| Package A/B | **108/108** |
| CCTV | **60/60** |
| 2 Schools | **13/13** |
| Media + Cleanup | **37/37** |
| Food photos | **21/21** |
| Individual Status | **17/17** |
| Role Selection | **29/29** |
| Smoke | **95 pages / no errors** |
| Crawl | **220 links / no broken links** |
| PHP lint ทุกไฟล์ / JS syntax | ผ่าน |

ไม่มีชุดทดสอบใดไม่ผ่าน (test scripts อยู่นอก repo — scratchpad ของ session พัฒนา)

## 14. Known Limitations (DO NOT CHANGE in Demo — Future Enhancement)
| # | ข้อจำกัด | สถานะ |
|---|---|---|
| 1 | Multiple Children รองรับเฉพาะ **รับ-ส่ง** และ **ผลงานของฉัน**; หน้าอื่น (หน้าหลัก, อาหาร, การนอน, สุขภาพ, ดาว, พัฒนาการ, แชท, ไทม์ไลน์) แสดง **ลูกคนแรก** | Future Enhancement |
| 2 | การลบไฟล์ภาพหมดอายุเป็นปุ่มของ Admin (Production = อัตโนมัติ) | ตั้งใจ — Demo |
| 3 | ข้อมูลเป็น JSON file + mock data; schema เปลี่ยน → reseed; วันใหม่ → roll forward | ตั้งใจ — Demo |
| 4 | PIN เก็บแบบ plain ใน mock data, หน้าแรกเข้าบัญชีอัตโนมัติ | ตั้งใจ — Demo เท่านั้น |
| 5 | เมนูแจ้งเตือนมีเฉพาะ Teacher / Parent | Future Enhancement |
| 6 | รับ-ส่ง: ไม่มียกเลิก / รายชื่อผู้มีสิทธิ์รับ / แจ้งซ้ำหลัง completed | Pending business |
| 7 | ลบผลงานใน Demo เป็น hard delete ของแถว `portfolio` (Production = soft delete) | ตั้งใจ — Demo |
| 8 | หน้า "ภาพกิจกรรม" เดิมยังมีแท็บผลงานซ้อนกับเมนูผลงานใหม่ | Future Improvement |
| 9 | สถานะรายบุคคลแสดงเฉพาะวันนี้ ไม่มีปุ่มล้าง (บันทึก "หายดีแล้ว" แทน) | Future Improvement |
| 10 | CCTV เป็น mock stream (ยังไม่ต่อ Media Server จริง) | Production phase |
| 11 | Polling 8 วินาที / 4 วินาที (ไม่มี real-time push) | ตั้งใจ — MVP |

## 15. Production Migration Notes
Final Demo = Business / UX reference — Production สร้างใหม่ใน repo `mykid-production` (ADR-014) ตาม `docs/production/`

| Demo | Production |
|---|---|
| JSON store + mock arrays | MySQL 8.4 LTS จริงตั้งแต่ต้น สร้างตาราง **ตาม phase** |
| `parentStudents` | `parent_students` (many-to-many, `ended_at`, `can_pickup`) — ใช้กับ **ทุกหน้า** ของผู้ปกครอง |
| `userNotifications` + `core/notifications.php` | `notifications` + `notification_recipients` (read_at) + `NotificationService` |
| `pickups` (pending → preparing → ready_for_pickup → completed) | `pickup_requests` + `pickup_status_logs` (history) + atomic transition ใน transaction — **สถานะเดียวกับ Demo, ไม่มี ETA** |
| `portfolio` + `mediaFiles` (owner portfolio) | `student_works` / `portfolios` + `portfolio_images` + `media_files` |
| Activity / food images ผูกกับ activity / food menu ของวัน | Timeline → Daily Activity → Activity Record → Media; Food Schedule → Daily Food Record → Media (**ไม่ผูกกับ template**) |
| Admin ล้างไฟล์หมดอายุเอง | Scheduled job ลบไฟล์อัตโนมัติ (idempotent + audit), DB record คงอยู่ |
| PIN plain, login อัตโนมัติ | Mobile + PIN (ครู/ผู้ปกครอง), Account + Password (Admin), Argon2id + pepper, lockout |
| `row_in_scope()` + mock permissions | RBAC + AccessContext + Policy + composite FK |
| Test scripts (curl / Playwright) | ใช้เป็น **acceptance cases** ของ Production (ไม่ใช่โค้ด) |

แผน phase: [docs/production/production-start-plan.md](production/production-start-plan.md)

## 16. Git Freeze Status
- ณ วันที่ Freeze: **มี uncommitted changes ที่ต้องตรวจสอบก่อน Freeze** (37 รายการ: รูปอาหาร, เมนูสะสมดาว, สถานะรายบุคคล, Student Work / Pickup ใหม่ / Notification / ลูกหลายคน, `docs/production/`, และเอกสารนี้)
- จึง **ยังไม่สร้าง tag `final-demo-v1.0`** — tag ต้องชี้ commit ที่รวมโค้ดข้างต้นทั้งหมด
- ขั้นตอนเมื่อได้รับอนุญาต: ตรวจ diff → commit (เช่น `Final Demo v1.0: student work, pickup v2, in-app notifications, multiple children, food photos, individual status, stars menu`) → `git tag -a final-demo-v1.0 -m "Mykid Final Demo v1.0 (frozen 2026-10-07)"` → push branch + tag (ไม่ force)
