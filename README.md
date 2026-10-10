# mykid-demofinal

Working copy of **Mykid Final Demo v1.0** — a separate project for further development.

| | |
|---|---|
| Copied from | `rambledev/mykid` · tag `final-demo-v1.0` → `b9f1099e976a5a54d24c815080531a7fef46e3ce` (copied 2026-10-10, without git history) |
| Original | `../mykid` — FROZEN reference, never edited from here |
| Stack | plain PHP 8.3+ (procedural, no framework, no Composer) + **MariaDB 11.4 LTS** via PDO — see ADR-020 in `docs/production/decision-log.md` |
| Local URL | http://127.0.0.1:8081 (original demo: http://127.0.0.1:8000) |

## Run locally

```bash
cd ~/Desktop/product/mykid-demofinal
cp .env.example .env            # first time only — fill in DB_PASS
php database/install.php        # first time only — creates the tables + seeds the demo data
php -d upload_max_filesize=12M -d post_max_size=64M -d memory_limit=256M -S 127.0.0.1:8081
```

`php database/install.php --reset` drops every table and seeds again. Tables are also created and seeded automatically on
the first request if the database is empty.

### MariaDB on this Mac

MariaDB 11.4 LTS runs next to Homebrew MySQL (port 3306, other projects) without sharing anything:

| | |
|---|---|
| Formula | `mariadb@11.4` (keg-only: `$(brew --prefix mariadb@11.4)/bin/mariadb`) |
| Port / socket | `127.0.0.1:3307` · `/tmp/mariadb.sock` |
| Config / data | `/opt/homebrew/etc/mariadb/my.cnf` · `/opt/homebrew/var/mariadb` · log `/opt/homebrew/var/log/mariadb.err` |
| Service | `~/Library/LaunchAgents/local.mariadb-11.4.plist` (starts at login) — stop/start with `launchctl unload/load -w <plist>` |
| Database / user | `mykid` / `mykid` (password in `.env`) · admin: `$(brew --prefix mariadb@11.4)/bin/mariadb --socket=/tmp/mariadb.sock` as your macOS user |

Do **not** use `brew services start mariadb@11.4` — the Homebrew service would point at MySQL's data directory.

## Database

- Schema: [database/schema.sql](database/schema.sql) — 27 tables, one per app table (`foodMenus` → `food_menus`); column
  names are the app's row keys. `app_meta` holds `nextId`, `version`, `seedDate`, `schema`.
- Access: only through [core/store.php](core/store.php) — `store_rows()` reads one table per request; `store_mutate()` writes
  inside a transaction (global row lock) and saves only the changed rows. Pages never run SQL themselves.
- Keys without a column are kept in the row's `extra` JSON column (logged in development) — add a column when you see one.
- Daily behaviour is unchanged: each new day rolls the demo data forward (`seed_roll_day`).
- Docker / Coolify: set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` (and add a MariaDB 11.4 service).

## Structure

Only Package A (Mykid Full) is kept, and it lives at the project root — there are no `pack-*` folders.

| Path | Contents |
|---|---|
| `index.php` | Role selection (entry page) |
| `login.php`, `logout.php`, `api.php`, `media.php` | Thin entry files → `core/pages/*` |
| `super-admin/`, `admin/`, `executive/`, `teacher/`, `parent/` | Role pages → `core/pages/<role>/*` |
| `includes/` | App config, permissions, mock data (`base-data.php` = former Package C source data) |
| `database/` | `schema.sql` + `install.php` (CLI) |
| `core/` | Shared engine (db, store, auth, layout, components, pages) |
| `assets/css/` | `base.css` (base design, former Package C) + `style.css` (Full tier accents) |
| `storage/` | Uploaded images (`uploads/`) — created on first use |

## Differences from the original

- Package A moved to the root; Package B / C removed. URLs are `/admin/…` instead of `/pack-a/admin/…`.
- Single school only: ศูนย์พัฒนาเด็กเล็กบ้านโพนสูง (ศพด.บ้านโพนสูง) — 3 rooms · 60 students · 5 teachers · 5 cameras.
  School 2 and the multi-school demo guide (`demo.php`) were removed.
- Session cookie name `MYKIDDFA` (original: `MYKIDA`) — both projects run on 127.0.0.1, where cookies are shared across
  ports, so the names must differ.
- Data in MariaDB instead of the JSON file `demo-state.json`.
- Uploaded images (`storage/uploads/`) are not copied — the seed recreates the demo images.

Demo data only (mock data of the Final Demo). No real student / teacher data, no production credentials.
