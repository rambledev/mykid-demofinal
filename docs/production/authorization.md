# Authorization Model (RBAC + Scope)

> ตาราง: [database.md §3](database.md#3-identity--access) · Security ภาพรวม: [security-model.md](security-model.md)

## 1. สองคำถามที่ต้องตอบทุกครั้ง

| คำถาม | ใครตอบ | ตัวอย่าง |
|---|---|---|
| **ทำ action นี้ได้ไหม** (Permission) | RBAC: `user_roles → roles → role_permissions → permissions` | Teacher มี `pickup.transition` |
| **ทำกับ "ข้อมูลชิ้นนี้" ได้ไหม** (Scope) | Scope Resolver + Policy จาก assignment tables | pickup นี้เป็นของเด็กในห้องที่ครูคนนี้สอนอยู่ไหม |

ต้องผ่าน **ทั้งสองข้อ** — permission อย่างเดียวไม่พอ (นี่คือที่มาของ IDOR)

## 2. Scope Levels

| Role | `scope_level` | เห็นข้อมูล | ที่มาของ scope (จาก DB เท่านั้น) |
|---|---|---|---|
| SUPER_ADMIN | platform | ทุกโรงเรียน | `user_roles.school_id IS NULL` |
| SCHOOL_ADMIN | school | โรงเรียนตัวเอง (อ่าน/เขียนตาม permission) | `user_roles.school_id` |
| EXECUTIVE | school | โรงเรียนตัวเอง **read-only** | `user_roles.school_id` + ไม่มี permission เขียน |
| TEACHER | classroom | ห้องที่ได้รับมอบหมาย (ยังไม่ `ended_at`) ในโรงเรียนตัวเอง | `teacher_classrooms` |
| PARENT | student | เฉพาะเด็กที่ตนเป็นผู้ปกครอง (ยังไม่ `ended_at`) | `parent_students` |

Role ใหม่ในอนาคต (เช่น ครูพี่เลี้ยง, เจ้าหน้าที่การเงิน) = เพิ่มแถวใน `roles` (เลือก `scope_level` ที่มีอยู่) +
`role_permissions` — **ไม่ต้องแก้ controller** ถ้า scope level ใหม่จริง ๆ จึงเพิ่ม resolver หนึ่งตัว

### AccessContext (สร้างครั้งเดียวต่อ request)

```text
AccessContext {
  userId:        int
  roleCode:      'TEACHER'            // active role ของ session (กรณีมีหลาย role → role switcher)
  scopeLevel:    'classroom'
  schoolIds:     [1]                  // platform = ALL (flag)
  classroomIds:  [1]                  // teacher only
  studentIds:    []                   // parent only
  permissions:   {'pickup.view', 'pickup.transition', ...}
}
```

- สร้างจาก DB ตอน login แล้วเก็บ snapshot ใน session พร้อม `context_version`
- โหลดเฉพาะ role ที่ `roles.auth_type` ตรงกับ credential ที่ใช้ login (ADR-018): session จาก PIN → TEACHER/PARENT เท่านั้น, session จาก password → SUPER_ADMIN/SCHOOL_ADMIN/EXECUTIVE เท่านั้น
- เมื่อ Admin เปลี่ยน assignment/role → เพิ่ม `users.access_version` → request ถัดไป rebuild context
  (ไม่มี "สิทธิ์ค้าง" นานกว่า 1 request)
- ไม่มีค่าใดใน AccessContext มาจาก URL / form / header

## 3. Permission Matrix (ค่าตั้งต้น)

`R` = read, `W` = create/update, `—` = ไม่มี (permission code จริงอยู่ในวงเล็บ)

| Module (permission prefix) | SUPER_ADMIN | SCHOOL_ADMIN | EXECUTIVE | TEACHER | PARENT |
|---|---|---|---|---|---|
| Schools (`school.*`) | R W | R (ของตน) W settings | R | R (ของตน) | R (ของตน) |
| Users / role assignment (`user.*`) | R W (admin/exec ทุกโรงเรียน) | R W (teacher/parent ในโรงเรียน) | — | — | — |
| Classrooms (`classroom.*`) | R | R W | R | R (ห้องตน) | R (ห้องลูก) |
| Students (`student.*`) | R | R W | R | R (ห้องตน) | R (ลูก) |
| Parent links (`student.link_parent`) | — | W | — | — | — |
| Activities + images (`activity.*`) | R | R W | R | R W | R |
| Food menu + images (`food.*`) | R | R W | R | R W | R |
| Attendance / Sleep / Health (`attendance.*`, `sleep.*`, `health.*`) | R | R W | R | R W | R (ลูก) |
| Portfolio (`portfolio.*`) | R | R W | R | R W | R (ลูก) |
| Stars (`star.award`, `star.reverse`, `star.view`) | R | R W | R | R W | R (ลูก) |
| Calendar (`event.*`) | R | R W | R | R | R |
| Chat (`chat.view`, `chat.send`) | — | R (กำกับดูแล) | — | R W (ห้องตน) | R W (ลูก) |
| Notifications (`notification.publish`, `notification.view`) | W (แพลตฟอร์ม) | R W | R | R W (ห้องตน) | R (ของตน) |
| Pickup (`pickup.request`, `pickup.transition`, `pickup.view`) | R | R | R | R + transition | R + request |
| CCTV (`camera.view`, `camera.manage`) | R W | R W (โรงเรียนตน) | R | R (ตาม camera_permissions) | R (ตาม camera_permissions) |
| Media storage (`media.stats` — พื้นที่, ภาพใกล้ครบกำหนด, ผล/รายการลบไม่สำเร็จของ retention job) | R (ทุกโรงเรียน) | R (โรงเรียนตน) | R (โรงเรียนตน) | — | — |
| Reports / Dashboard (`report.view`) | R (ข้ามโรงเรียน) | R | R | R (ห้องตน) | — |
| Audit (`audit.view`) | R | R (โรงเรียนตน) | — | — | — |

- Navigation แสดงเมนูจาก permission (เช่น เมนู "รับ-ส่ง" แสดงเมื่อมี `pickup.request` หรือ `pickup.transition` → Teacher/Parent เท่านั้น ตาม Demo)
- Matrix อยู่ใน seeder (`RolePermissionSeeder`) ที่ review ผ่าน PR — UI แก้ permission ของ role ระบบ: FUTURE

## 4. ตำแหน่งของ Authorization ใน CodeIgniter 4

```
Route ──► AuthFilter ──► PermissionFilter('pickup.transition') ──► Controller ──► Service ──► Policy ──► Model->forContext()
          (มี session?)   (มี permission code?)                                    (ชิ้นนี้ได้ไหม)  (query ถูกจำกัด scope)
```

| Layer | ไฟล์ (เสนอ) | หน้าที่ | ห้าม |
|---|---|---|---|
| **AuthFilter** | `app/Filters/AuthFilter.php` | ต้อง login, โหลด AccessContext, ตรวจ school `suspended` | — |
| **PermissionFilter** | `app/Filters/PermissionFilter.php` (`perm:<code>` ใน Routes) | ตัด request ที่ role ไม่มี permission ตั้งแต่ต้น (หยาบ) | ตัดสิน scope |
| **Policy** | `app/Authorization/Policies/*Policy.php` | ตัดสิน "ชิ้นนี้" เช่น `PickupPolicy::transition(ctx, $pickup)` | query DB เอง (รับ entity ที่โหลดแล้ว) |
| **ScopeResolver / Model scope** | `app/Authorization/ScopeResolver.php` + `Model::forContext($ctx)` | ใส่ `WHERE school_id IN … AND classroom_id IN …` ใน **ทุก list query** | ข้าม scope ด้วย raw query |
| **Service** | `app/Services/*Service.php` | เรียก policy ก่อนทำ business action ทุกครั้ง | เชื่อ id จาก request โดยไม่โหลด+ตรวจ |
| **View** | helper `can('pickup.transition')` | ซ่อนปุ่ม/เมนู (UX เท่านั้น) | ใช้แทนการตรวจฝั่ง server |

### กติกาเขียนโค้ด (บังคับด้วย code review + test)
1. ห้ามเขียน `if ($user['role'] === 'teacher')` ใน controller/view — ใช้ `$ctx->can('…')` หรือ Policy
2. ทุกการโหลดด้วย id: `$model->forContext($ctx)->find($id)` → ไม่เจอ = **404** (ไม่บอกว่ามีอยู่จริง)
3. ทุก list: ต้องผ่าน `forContext()` — มี test ที่ fail ถ้า Model ใด query ตารางที่มี `school_id` โดยไม่ผ่าน scope
4. ค่า scope ตอนเขียน (`school_id`, `classroom_id`) มาจาก **entity ที่โหลดแล้ว** (เช่น เด็ก) ไม่ใช่จาก request
5. Mass assignment: Model ทุกตัวกำหนด `$allowedFields` ไม่รวม `school_id`, `*_by`, `status` ที่มี state machine

## 5. Scope Rules ต่อ Entity

| Entity | Super Admin | School Admin / Executive | Teacher | Parent |
|---|---|---|---|---|
| School | ทั้งหมด | `id ∈ schoolIds` | โรงเรียนของห้องตน | โรงเรียนของลูก |
| Classroom | ทั้งหมด | `school_id ∈ schoolIds` | `id ∈ classroomIds` | ห้องปัจจุบันของลูก |
| Student | ทั้งหมด | `school_id ∈ schoolIds` | `classroom_id ∈ classroomIds` (ห้อง **ปัจจุบัน**) | `id ∈ studentIds` |
| Student-level record (attendance, sleep, health, portfolio, stars, pickup) | ทั้งหมด | school | เด็กที่อยู่ในห้องตนตอนนี้ (record ตามตัวเด็ก) | `student_id ∈ studentIds` |
| Classroom-level record (activity, food menu, status) | ทั้งหมด | school | `classroom_id ∈ classroomIds` | `classroom_id` = ห้องของลูก ณ วันที่ของ record |
| School-level (events, notifications) | ทั้งหมด | school | school + target ห้องตน | school + target ห้อง/ลูก/ตัวเอง |
| Media | ตาม owner | ตาม owner | ตาม owner | ตาม owner (portfolio = ลูก, activity/food = ห้องลูกในวันนั้น) |
| Camera | ทั้งหมด | school | `camera_permissions` (role + ห้อง) + `is_active` | `camera_permissions` + ห้องลูก + `is_active` |
| Conversation | — (FUTURE: ตรวจสอบ) | school (read) | เด็กในห้องตน | ลูก |
| Audit log | ทั้งหมด | school | — | — |

> Pending (ผูกกับ P4 Academic Year / Promotion): Teacher ควรเห็นประวัติของเด็กก่อนย้ายมาห้องตนหรือไม่ (ค่าเริ่มต้นที่เสนอ: **เห็น** เพราะเป็นข้อมูลการดูแลต่อเนื่อง)

## 6. Policy ตัวอย่าง (pseudocode — ไม่ใช่โค้ดจริง)

```text
PickupPolicy
  request(ctx, student):     ctx.can('pickup.request') AND student.id ∈ ctx.studentIds
                             AND parentLink(ctx.userId, student.id).can_pickup
  view(ctx, pickup):         StudentScope.contains(ctx, pickup.student_id)
  transition(ctx, pickup):   ctx.can('pickup.transition') AND pickup.classroom_id ∈ ctx.classroomIds
                             AND pickup.school_id ∈ ctx.schoolIds

MediaPolicy
  view(ctx, media):          ownerPolicy(media.category).view(ctx, owner(media))
  upload(ctx, ownerEntity):  ownerPolicy.update(ctx, ownerEntity) AND ctx.can('<module>.write')
  stats(ctx, school):        ctx.can('media.stats') AND school.id ∈ ctx.schoolIds
  (retention cleanup ไม่ใช่การกระทำของผู้ใช้ — เป็น scheduled job ที่รันแบบ system ไม่ผ่าน Policy ของผู้ใช้)
```

## 7. IDOR Prevention Checklist

| ตัวอย่าง request | การตรวจ |
|---|---|
| `GET /students/123` | โหลดด้วย `StudentModel::forContext($ctx)->find(123)` → ไม่อยู่ใน scope = 404 |
| `POST /api/v1/pickups {student_id: 5}` (ผู้ปกครองเด็กคนอื่น) | `PickupPolicy::request` → 404 (มี permission แต่เด็กอยู่นอก scope), audit `result=denied` |
| `POST …/transitions` ของห้องอื่น | Policy → 404 |
| `GET /media/{public_id}` | public_id เดาไม่ได้ **และ** ตรวจ owner policy ทุกครั้ง |
| แก้ `school_id` ใน form | ไม่อยู่ใน `$allowedFields`; service ใช้ school ของ entity |
| เปลี่ยน `?classroom_id=` ใน URL ของครู | filter param ถูก intersect กับ `ctx.classroomIds` เสมอ |
| ผู้ใช้ถูกถอดจากห้องระหว่าง session | context_version เปลี่ยน → rebuild ใน request ถัดไป |

## 8. Testing Authorization (บังคับใน Phase ที่สร้าง feature)

- **Matrix test**: ทุก endpoint × ทุก role × (ใน scope / นอก scope / โรงเรียนอื่น) → expected 200/403/404
  (ต่อยอดแนวคิดจาก test ของ Demo: perm 49, perm-ab 108, cctv 60, pickup 47 ข้อ)
- **Model scope test**: ทุก Model ที่มี `school_id` ต้องมี `forContext()` และ test ข้ามโรงเรียน
- **Regression**: Parent เห็นเฉพาะลูก, Teacher เห็นเฉพาะห้อง, Executive เขียนไม่ได้, โรงเรียน A ไม่เห็น B
