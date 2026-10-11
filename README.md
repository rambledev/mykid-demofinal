# mykid-demofinal

Mykid — a school ↔ parent communication system for one school: ศูนย์พัฒนาเด็กเล็กบ้านโพนสูง (ศพด.บ้านโพนสูง).
Roles: **School Admin · Executive · Teacher · Parent** (no platform-level Super Admin — one school only).
It started as a copy of **Mykid Final Demo v1.0** and is now the real system — all demo data removed (ADR-020 – ADR-023 in
`docs/production/decision-log.md`).

| | |
|---|---|
| Stack | plain PHP 8.3+ (procedural, no framework, no Composer) + **MariaDB 11.4 LTS** via PDO |
| URLs | Clean URLs + router (`/teacher/attendance`, `/media/{token}`), ids only as secure tokens |
| Security | OWASP-style baseline — see [docs/security.md](docs/security.md) |
| Local URL | http://127.0.0.1:8081 |
| Copied from | `rambledev/mykid` · tag `final-demo-v1.0` → `b9f1099` (copied 2026-10-10, without git history) |

## Run locally

```bash
cd ~/Desktop/product/mykid-demofinal
cp .env.example .env            # first time only — fill in DB_PASS and APP_KEY (openssl rand -base64 48)
php database/install.php --school="ศูนย์พัฒนาเด็กเล็กบ้านโพนสูง" --short="ศพด.บ้านโพนสูง" --province="..." \
    --admin-name="ชื่อผู้ดูแล" --admin-phone=08xxxxxxxx --admin-pin=xxxxxx   # first time only
php -d upload_max_filesize=12M -d post_max_size=64M -d memory_limit=256M -S 127.0.0.1:8081 -t public public/index.php
```

- There is **no demo data**: install creates only the tables, the school and the first admin account; the admin enters
  classrooms, teachers, students and accounts. Until a school exists, every page shows "ระบบยังไม่พร้อมใช้งาน".
- `php database/install.php --reset --school=... ...` drops every table (all data is lost) and sets up again. Every
  argument is validated first — a wrong value stops before anything is dropped. An easy PIN (000000, 123456, …) needs
  `--allow-weak-pin`; change it after the first login (/admin/users → edit your own account).
- `php database/install.php --admin-phone=08xxxxxxxx --admin-pin=xxxxxx` adds an admin to the existing school, or resets
  the PIN of an existing admin (forgotten PIN) — nothing else is touched.
- Teacher and parent login accounts follow the admin's records ([app/lib/accounts.php](app/lib/accounts.php)): the
  teacher form has phone + PIN (account created / updated, removed with the teacher); the student form has the parent's
  phone + PIN (one phone = one parent account linked to every child with that phone; removed when no child is left).
  An empty PIN on edit keeps the current one.
- Every user can change their own PIN on the account page (current PIN + new PIN twice; easy PINs such as 000000 /
  123456 are refused; wrong current PINs count toward the login lock).
- `php database/import-school.php [file] [--confirm]` imports the school's real classrooms, teachers (+ login accounts,
  PIN from the file) and students from `database/data/school.json` (format: `school.example.json`). That file holds
  children's names, so it is gitignored / dockerignored (PDPA) and stays on the admin's machine. Dry run by default;
  re-running updates instead of duplicating. Parents get accounts when the admin enters their phone + PIN.
- `php database/check.php` tests the connection; missing tables / new columns are applied automatically (`schema.sql`).

### Every school day

The first request of a day opens an **empty daily sheet** per student (attendance not checked, nap pending, health and
meals not recorded) that the teachers fill in; a student added during the day gets one immediately. Nothing is
generated or copied automatically — schedules, menus and statuses are entered by the teachers.

### MariaDB on this Mac

MariaDB 11.4 LTS runs next to Homebrew MySQL (port 3306, other projects) without sharing anything:

| | |
|---|---|
| Formula | `mariadb@11.4` (keg-only: `$(brew --prefix mariadb@11.4)/bin/mariadb`) |
| Port / socket | `127.0.0.1:3307` · `/tmp/mariadb.sock` |
| Config / data | `/opt/homebrew/etc/mariadb/my.cnf` · `/opt/homebrew/var/mariadb` · log `/opt/homebrew/var/log/mariadb.err` |
| Service | `~/Library/LaunchAgents/local.mariadb-11.4.plist` (starts at login) — `launchctl unload/load -w <plist>` |
| Database / user | `mykid` / `mykid` (password in `.env`) · admin: `$(brew --prefix mariadb@11.4)/bin/mariadb --socket=/tmp/mariadb.sock` as your macOS user |

Do **not** use `brew services start mariadb@11.4` — the Homebrew service would point at MySQL's data directory.

## Chat

Parent ↔ the child's classroom teachers, one thread per student ([app/lib/chat.php](app/lib/chat.php)).
Chat pages poll `chat_poll` every 2.5 s (15 s in a background tab) for messages newer than the last one shown —
also while typing. Sending notifies the other side in the same write (one unread in-app notification per thread);
the thread on screen is marked read (`messages.read_at`) → unread badges on the "แชท" menu / thread list and
"✓ อ่านแล้ว" under the sender's last message. `/teacher/chat/{token}` and `/parent/chat/{token}` open one thread
(notification links; parents with several children pick the child).

## Date & time

Always Thai, in Asia/Bangkok time: Buddhist year and Thai month names ("วันเสาร์ที่ 10 ตุลาคม 2569", "10 ต.ค."),
24-hour clock with "น." ("14:30 น."). PHP: `thai_date()`, `thai_short_date()`, `thai_time()`, `thai_hm()` in
`app/lib/helpers.php`; JS: `thaiNow()` in `core.js` (independent of the device's time zone / calendar). The database
stores ISO values (`Y-m-d`, `Y-m-d H:i:s`) with the session time zone +07:00.

## Folder structure

```
public/                 ← web root: the ONLY folder served over HTTP
  index.php             front controller (every request that is not a static file)
  assets/css, js        base.css · core.css · style.css · core.js
app/
  bootstrap.php         loads config + lib (web and CLI)
  routes.php            route table (allow-list) — static routes + role sections
  config/               app.php (features, roles, navigation) · permissions.php · cctv-permissions.php
  lib/                  engine: db · store · security · id-token · router · auth · permissions ·
                        data-filter · components · layout · media · cctv · pickup · notifications · ...
  pages/                one file per screen: home, login, logout, api, media, healthz,
                        admin/ executive/ parent/ teacher/ common/
database/
  schema.sql            27 app tables + app_meta + login_attempts
  install.php           CLI: tables + school + first admin (--reset = start over)
  check.php             CLI: test the database connection
storage/uploads/        uploaded images (private — streamed by /media/{token} after a permission check)
docker/  Dockerfile     Apache image, DocumentRoot = public/
docs/                   security.md, production/ (architecture docs, decision log)
```

## How a request works

1. `public/index.php` → `app/bootstrap.php` → `mk_dispatch()` (`app/lib/router.php`).
2. The router matches `app/routes.php` (method + path). Unknown path → friendly 404, wrong method → 405.
3. Security headers are sent, the hardened session starts, `require_role()` checks the role section.
4. The page in `app/pages/` reads data through `store_rows()` / `scoped()` (permission scope) and renders HTML.
5. Writes go to `POST /api` (CSRF header + role + scope checks); ids arrive as tokens and are decoded there.

## URLs

| URL | Page |
|---|---|
| `/` | role selection |
| `/login`, `POST /logout` | sign in / out |
| `/{section}` and `/{section}/{page}` | role pages — section = `admin`, `executive`, `teacher`, `parent` |
| `/admin/users` | Admin / Executive accounts of the school (create, edit, delete — never your own role/account) |
| `/media/{token}` | an uploaded image (permission-checked) |
| `POST /api` | JSON actions (save, delete, set_status, pickup_*, ...) |
| `/healthz` | container health check (no database) |
| `/readyz` | database reachable? `ok` (200) / `unavailable` (503) |

Build links with `url('teacher/attendance')`, `rurl('attendance')` (current role section) and `asset_url('assets/...')`.
Ids that go to the browser always use `tok($table, $id)` / `public_row($table, $row)`; the API decodes with
`in_id()` / `untok()`.

## Database

- Schema: [database/schema.sql](database/schema.sql) — one table per app table (`foodMenus` → `food_menus`); column
  names are the app's row keys. `app_meta` holds `nextId`, `version`, `seedDate`, `schema`.
- Access only through [app/lib/store.php](app/lib/store.php): `store_rows()` reads one table per request;
  `store_mutate()` writes inside a transaction (global row lock) and saves only the changed rows. Pages never run SQL.
- Keys without a column are kept in the row's `extra` JSON column (logged in development).

## Deploy (Docker / Coolify)

- Application source: `rambledev/mykid-demofinal` (branch `main`) · Build Pack **Dockerfile** · port **80** ·
  health check `GET /healthz`.
- Persistent storage: a volume on `/var/www/html/storage` (uploaded images; the entrypoint makes it writable).
- Environment variables of the application:

```env
MYKID_ENV=production
DB_HOST=<MariaDB container name, from "MariaDB URL (internal)">
DB_PORT=3306
DB_NAME=default
DB_USER=mariadb
DB_PASS=<Normal User Password of the MariaDB resource>
APP_KEY=<openssl rand -base64 48 — never change after go-live>
```

- Test the connection: Coolify → Application → **Terminal** → `php database/check.php` (host, server version,
  tables, row counts — never the password), or open `https://<domain>/readyz` (`ok` = database reachable).
- The MariaDB resource stays private (no public port); the app reaches it over Coolify's internal network.
  Tables are created on the first connection; then run once in the application Terminal:
  `php database/install.php --school=... --admin-phone=... --admin-pin=...`. `TRUST_PROXY=1` (Dockerfile default) reads the client IP from
  Traefik's `X-Forwarded-For` for login throttling.
