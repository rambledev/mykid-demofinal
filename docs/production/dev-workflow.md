# Development Workflow

> Production repository: `mykid-production` (ADR-014) · Final Demo repository: `mykid` (reference only)
> ไฟล์ที่อ้างถึงใน repo Production: `.github/workflows/ci.yml`, `docker-compose.yml`, `docker-compose.prod.yml`, `.env.example`, `README.md`

## 1. Repositories & Demo Isolation

| Repo | บทบาท | กติกา |
|---|---|---|
| `mykid` (Final Demo) | Business / UX reference, regression baseline, Customer Demo | **ห้ามแก้เพื่อสร้าง Production**; ห้าม refactor / ย้าย / ลบโค้ด Demo; deploy ของ Demo แยกจาก Production |
| `mykid-production` | ระบบจริง (CI4) | ทุกการเปลี่ยนแปลงผ่าน PR + CI; ไม่ copy โค้ด Demo มาใช้ (ยก UX / ข้อความ / test case เป็น reference เท่านั้น) |

## 2. Environments

| | Development | Staging | Production |
|---|---|---|---|
| ที่รัน | `docker compose` บนเครื่อง developer | Coolify (project แยก) | Coolify |
| `CI_ENVIRONMENT` | `development` | `production` | `production` |
| Config | `.env` (copy จาก `.env.example`) | Coolify Environment Variables | Coolify Environment Variables |
| Database | MySQL 8.4 container (`mysql-data` volume) | MySQL 8.4 ของตัวเอง | MySQL 8.4 LTS ของตัวเอง |
| ข้อมูล | ข้อมูลสมมติจาก seeder | ข้อมูลสมมติ / ข้อมูลที่ anonymize | ข้อมูลจริง |
| Secrets | ค่า dev ของตัวเอง | ชุดของ staging | ชุดของ production |

- **Secrets จริงห้าม commit Git** — `.env`, `.env.*` อยู่ใน `.gitignore` (ยกเว้น `.env.example` ที่ไม่มีค่า)
- Secrets ของ staging/production อยู่ใน Coolify Environment Variables เท่านั้น + สำรองใน password manager ขององค์กร (`encryption.key`, `AUTH_PEPPER` ห้ามหาย)
- ห้ามใช้ production database / storage / secrets ใน development หรือ staging
- MariaDB ที่สร้างใน Coolify ช่วงทดลอง = **EXPERIMENTAL / TEST ONLY** ไม่ใช้กับ staging/production
- การต่อ DB จากเครื่อง developer (DBeaver ฯลฯ) เป็นความสะดวกเท่านั้น — dev DB เปิดที่ `127.0.0.1:3307`; staging/production ไม่เปิด port DB สู่ภายนอก

## 3. Git Workflow

| เรื่อง | กติกา |
|---|---|
| Model | Trunk-based: `main` = พร้อม deploy เสมอ + feature branch อายุสั้น (≤ 3–5 วัน) |
| `main` | **Protected**: ห้าม push ตรง, ห้าม force-push, ห้ามลบ; merge ผ่าน PR เท่านั้น; ต้องผ่าน CI (`php`, `docker` jobs) |
| Branch naming | `feature/<scope>-<short>` · `fix/<short>` · `chore/<short>` · `docs/<short>` · `hotfix/<short>` (เช่น `feature/auth-pin-login`) |
| Pull Request | 1 PR = 1 เรื่อง; template ระบุ: เปลี่ยนอะไร, ทดสอบอย่างไร, migration (ถ้ามี) + rollback plan, screenshot (UI) |
| Code review | อย่างน้อย 1 approval; **2 approvals** สำหรับ auth / authorization / migration / storage-retention / security config |
| Merge | Squash merge (history ของ `main` อ่านง่าย 1 commit ต่อ PR) |
| Commit convention | Conventional Commits: `feat:`, `fix:`, `chore:`, `docs:`, `test:`, `refactor:`, `db:` (migration) — ภาษาอังกฤษ |
| Release | tag `vYYYY.MM.DD-N` บน `main` ก่อน deploy production → ใช้เป็นจุด rollback ของ application |

## 4. CI (`.github/workflows/ci.yml`)

ทุก PR และทุก push เข้า `main`:

| Step | ล้มเมื่อ |
|---|---|
| `composer validate --strict` | composer.json/lock ไม่ตรงกัน |
| `composer install` | dependency resolve ไม่ได้ |
| `composer lint` (`php -l`) | syntax error |
| `composer audit --no-dev` | มี advisory ด้าน security ใน dependency production |
| `php spark routes` | application boot ไม่ได้ |
| PHPUnit (PHP 8.3 + service MySQL 8.4) | test ล้ม |
| Smoke: `spark serve` + `GET /health/live` | health ไม่ตอบ `{"status":"ok"}` |
| Docker build `app` + `web`, `docker compose config` | Dockerfile/compose เสีย |

CI ใช้ credential ทิ้งได้ของ MySQL service เท่านั้น — ไม่มี secret จริง (FUTURE: PHPStan, code style เมื่อเริ่มโค้ด business)

## 5. Migration Workflow

### 5.1 Developer
1. สร้าง migration: `php spark make:migration <Name>` (1 migration = 1 เรื่อง, ชื่อบอกการเปลี่ยนแปลง)
2. ทดสอบ local: `php spark migrate` → ทดสอบ app → `php spark migrate:rollback` → `migrate` อีกครั้ง (พิสูจน์ว่า `down()` ใช้ได้ **ใน dev**)
3. เพิ่ม/ปรับ test ที่เกี่ยว (CI รัน migration กับ MySQL 8.4)
4. เปิด PR พร้อมหัวข้อ **"Rollback plan"** (ดู §7) + ประเภท: additive / data / destructive
5. Review (2 approvals) → merge
6. Deploy staging → ตรวจ → deploy production (§6)

### 5.2 กติกา
- ห้ามแก้ migration ที่ merge แล้ว — แก้ด้วย migration ใหม่เสมอ
- Migration ต้อง **backward-compatible** กับโค้ดเวอร์ชันก่อนหน้า (โค้ดเก่าต้องยังทำงานได้หลัง migrate) เพื่อให้ rollback application ได้
- **ห้าม destructive change (DROP/RENAME column/table, เปลี่ยนชนิดแบบเสียข้อมูล) ใน deploy เดียวกับการเปลี่ยนโค้ด** — ใช้ Expand → Contract
- FK ทุกตัว `ON UPDATE RESTRICT ON DELETE RESTRICT` (ADR-019); ไม่มี CASCADE
- Seeder ต้อง idempotent (รันซ้ำได้)
- DDL ที่ล็อกตารางนาน (ตารางใหญ่) → ทำนอกเวลาเรียน (หลัง 18:00) และระบุใน PR

### 5.3 Expand → Migrate → Contract

```
1. Expand      เพิ่มของใหม่แบบไม่ทำลาย (ADD COLUMN NULL / ADD TABLE / ADD INDEX)       deploy N
2. Deploy      โค้ดใหม่เขียนทั้งของเก่าและของใหม่ (dual-write) อ่านของเก่าเป็นหลัก        deploy N
3. Data migration  backfill ข้อมูลเก่า → ของใหม่ (command / migration แยก, ทำเป็น batch)     deploy N+1
4. Verify      ตรวจจำนวน/ความถูกต้อง, เปลี่ยนโค้ดให้อ่านของใหม่                           deploy N+1
5. Contract    ลบของเก่า (DROP) หลังจากมั่นใจ + มี backup ล่าสุด                           deploy N+2 (≥ 1 รอบ release ถัดไป)
```

ตัวอย่าง: เปลี่ยนชื่อคอลัมน์ = เพิ่มคอลัมน์ใหม่ → เขียนทั้งสอง → backfill → อ่านคอลัมน์ใหม่ → ลบคอลัมน์เก่าในรอบถัดไป

## 6. Deployment Procedure (Coolify)

### Staging (ทุก merge เข้า `main` หรือเมื่อสั่ง)
Coolify build จาก `main` → post-deploy `php spark migrate --all` → `/health/ready` → smoke test ตาม checklist ของ PR

### Production
1. **Backup**: pre-deploy DB snapshot (Coolify backup / `mysqldump`) — ต้องสำเร็จก่อนไปต่อ
2. **Deploy code**: Coolify deploy tag `vYYYY.MM.DD-N` (image `app` + `web`)
3. **Run migration**: Coolify post-deployment command บน container `app`: `php spark migrate --all` (migration เป็น backward-compatible → โค้ดเก่า/ใหม่ทำงานกับ schema ใหม่ได้ระหว่าง rollout)
4. **Health check**: `GET /health/ready` = 200 (Coolify healthcheck + ตรวจมือ)
5. **Verify**: smoke test สั้น (login แต่ละกลุ่ม, หน้าหลักต่อ role, feature ใน release) + ดู error log 15 นาที
6. **Announce / close**: บันทึก release note + ผล verify
- ช่วงเวลา: deploy ปกตินอกช่วงรับ-ส่ง (หลีกเลี่ยง 14:00–17:00); migration เสี่ยงทำหลัง 18:00
- ห้าม deploy production โดยไม่ผ่าน staging (ยกเว้น hotfix ด้านความปลอดภัย — ต้องมี 2 approvals)

## 7. Rollback Strategy

> **"git revert" อย่างเดียวไม่ใช่ rollback** — schema และไฟล์อาจเปลี่ยนไปแล้ว ต้องแยก 3 ชั้น

### A. Application rollback
- Coolify redeploy **tag ก่อนหน้า** (image เดิม) — ทำได้เสมอเพราะ migration ต้อง backward-compatible (§5.2)
- ใช้เมื่อ: bug ในโค้ด, health ล้ม, error rate สูงหลัง deploy
- schema ใหม่ยังอยู่ (additive) — ไม่ต้องย้อน DB

### B. Database rollback (ประเมินเป็นรายกรณี — กำหนดใน PR ล่วงหน้า)
| ประเภท migration | Rollback |
|---|---|
| Additive (เพิ่มตาราง/คอลัมน์/index) | ปกติ **ไม่ย้อน** — ปล่อยไว้ แล้ว rollback แค่ application; ถ้าจำเป็นจริงค่อยลบด้วย migration ใหม่ |
| Data migration (backfill/แปลงข้อมูล) | ต้องมี reverse script หรือคอลัมน์เดิมยังอยู่ (เพราะทำแบบ expand) — ระบุใน PR |
| Destructive (contract) | **ไม่มี automatic rollback** — recovery = restore จาก pre-deploy backup (ยอมรับข้อมูลหายตาม RPO) หรือ restore ตารางที่เกี่ยวลง DB ชั่วคราวแล้วคัดกลับ; จึงทำเฉพาะหลัง verify และมี backup ใหม่ |
- **ห้าม assume ว่า `migrate:rollback` / `down()` ใช้กับ production ได้เสมอ** — `down()` มีไว้พิสูจน์ใน dev; ใช้ใน production เฉพาะเมื่อ PR ระบุว่าปลอดภัย และหลัง backup
- ทุก DB rollback บันทึกใน incident note + audit

### C. Storage / file rollback
- Deploy ไม่แก้ไฟล์ใน storage (ไฟล์เขียนโดย upload และลบโดย retention job เท่านั้น)
- ถ้า bug ทำไฟล์เสีย/หาย → restore จาก backup ภาพ **แบบคัดกรอง** (เฉพาะ `media_files.status='available' AND expires_at > NOW()`) แล้วรัน `media:cleanup-expired` + `media:reconcile` (data-retention.md, backup-and-recovery.md §4.1)
- **ห้าม restore ไฟล์ที่ครบ 6 เดือนกลับเข้า Production**
- ถ้า retention job มี bug → ปิด scheduler entry ก่อน แล้วค่อย rollback application

## 8. Seed / Demo Data Strategy

| Seeder | Dev | Staging | Production |
|---|---|---|---|
| `RolePermissionSeeder` (roles, permissions, role_permissions) | ✅ | ✅ | ✅ (idempotent, version-controlled) |
| `DemoSchoolSeeder` (2 โรงเรียนสมมติ, ผู้ใช้ทดสอบ — ใช้ Final Demo เป็นแบบ) | ✅ | ✅ | ❌ **ห้าม** (seeder ตรวจ `ENVIRONMENT` แล้วปฏิเสธ) |
| Super Admin คนแรก | seeder | CLI | **CLI** `php spark auth:create-super-admin` (ถามรหัสแบบไม่แสดง, บังคับเปลี่ยนครั้งแรก, audit) |

- ไม่ import ข้อมูลหรือ PIN ของ Final Demo เข้า Production
- ข้อมูลเด็กจริงห้ามออกนอก Production (ไม่ copy ลง dev/staging)
- Initial Data Import (CSV) = PENDING P10

## 9. Checklist ต่อ Release
- [ ] CI เขียว, review ครบตามประเภท
- [ ] Migration: ประเภท + rollback plan ใน PR, ทดสอบ staging แล้ว
- [ ] Pre-deploy backup สำเร็จ
- [ ] Deploy tag → migrate → `/health/ready` 200 → smoke verify
- [ ] Release note + ผล verify
