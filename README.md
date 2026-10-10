# mykid-demofinal

Mykid — a school ↔ parent communication system for one school: ศูนย์พัฒนาเด็กเล็กบ้านโพนสูง (ศพด.บ้านโพนสูง).
It started as a copy of **Mykid Final Demo v1.0** and is being developed further as the real system (ADR-020, ADR-021 in
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
php database/install.php        # first time only — creates the tables + seeds the demo data
php -d upload_max_filesize=12M -d post_max_size=64M -d memory_limit=256M -S 127.0.0.1:8081 -t public public/index.php
```

- `php database/install.php --reset` drops every table and seeds again.
- Missing tables are also created automatically on the first request (`database/schema.sql` is idempotent).

### DEMO_MODE (`.env`)

| | `DEMO_MODE=1` (testing) | `DEMO_MODE=0` (real use) |
|---|---|---|
| Home page role cards | one tap logs into that role's demo account (through the real login: CSRF, hashed PIN, throttling) | open the login page for that role |
| Login page | list of test accounts (PIN `123456`) | phone + PIN only |
| New day | random but realistic records for every child + sample pickups | an **empty daily sheet** per child (attendance not checked, nap pending, …) that teachers fill in |
| "รีเซ็ตข้อมูล Demo" | available (account page) | hidden and blocked in the API |

Activity schedules and food menus are copied from the latest day as a template in both modes.

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
                        admin/ executive/ parent/ super-admin/ teacher/ common/
database/
  schema.sql            27 app tables + app_meta + login_attempts
  install.php           CLI: create tables / --reset
  seeds/                demo source data (base-data, mock-data, cctv-data)
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
| `/{section}` and `/{section}/{page}` | role pages — section = `super-admin`, `admin`, `executive`, `teacher`, `parent` |
| `/super-admin/school/{token}`, `/super-admin/cctv/{token}` | one school (token = school id token) |
| `/media/{token}` | an uploaded image (permission-checked) |
| `POST /api` | JSON actions (save, delete, set_status, pickup_*, ...) |
| `/healthz` | container health check |

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

Set env vars `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `APP_KEY` (32+ random characters) and keep
`DEMO_MODE=0`; add a MariaDB 11.4 service and a volume on `/var/www/html/storage`. Health check: `GET /healthz`.
