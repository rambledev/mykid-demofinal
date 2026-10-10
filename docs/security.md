# Mykid — Security baseline

This is the standard every change must keep (OWASP ASVS level 1 style). The code lives mainly in
`app/lib/security.php`, `app/lib/id-token.php`, `app/lib/router.php`, `app/lib/auth.php`, and `app/lib/permissions.php`.

## 1. Attack surface

- **Only `public/` is served.** Apache's `DocumentRoot` is `public/`, with `FallbackResource /index.php`. Locally, `php -S … -t public public/index.php` does the same.
  - `app/`, `database/`, `storage/`, `.env`, and `docs/` cannot be downloaded.
  - Apache also refuses hidden files and every `.php` file except `index.php`.
- **Route allow-list.** Only the paths in `app/routes.php` exist.
  - Each route declares its HTTP methods.
  - Role sections run `require_role()`.
  - Anything else returns a friendly 404 or 405.
- **Private uploads.** Uploads live in `storage/uploads/`. The only way to reach one is `/media/{token}`, which checks the viewer's scope. "Missing" and "not yours" give the same 404.

## 2. Authentication

- **PIN storage.** The 6-digit PIN is stored with `password_hash()`: Argon2id, or bcrypt when Argon2id is unavailable.
  - Seeded PINs are hashed before they reach the database.
  - Legacy or old-algorithm hashes are upgraded on the next login.
  - A PIN is never rendered back to the browser. Leaving the PIN empty on edit keeps the current one.
- **Throttling.** Failures are counted in the `login_attempts` table over a 15-minute window.
  - 5 failures for one phone lock that phone.
  - 20 failures from one IP lock that IP.
  - A successful login clears the phone's failures.
- **Session.** Sessions run in strict mode, and the cookie is `HttpOnly` and `SameSite=Lax`. It is `Secure` on HTTPS, including behind a proxy that sends `X-Forwarded-Proto`.
  - The session ID is regenerated on login and every 15 minutes.
  - Sessions expire after 2 h idle or 12 h in total.
- **Logout.** Logout requires `POST` with a CSRF token. A plain `GET /logout` never logs anyone out.

## 3. Authorization

- **Role.** `app/config/permissions.php` maps each role to the tables it can read and write.
- **Scope.** `row_in_scope()` limits every row by school → classroom → student, for both reads (`scoped()`) and writes (`apply_write_scope()`).
- **Server-side scope.** Scope fields are always filled or checked on the server, never taken on trust from the request.
- **Query strings.** `?school_id=` and similar parameters in a URL are ignored and logged (`guard_scope_query`).

## 4. Secure ID tokens

- **No raw IDs.** Database IDs never reach the browser: not in URLs, data attributes, select options, or API JSON.
- **Token format.** `tok($table, $id)` encrypts one AES-128 block made of `[4-byte table tag | 8-byte id | 4 zero bytes]`. The key is derived with HKDF from `APP_KEY`.
  - Tokens are deterministic and bound to their table.
  - Tampering, or using a token for the wrong table, fails the tag and zero checks.
- **API decoding.** The API decodes tokens with `in_id()` / `validate_fields()` and rejects raw numbers. After decoding, the permission checks still run as usual.

## 5. Requests and output

- **CSRF.** Each session has a 256-bit random token, compared with `hash_equals`.
  - It is required on `POST /login`, `POST /logout`, and every API call (`X-CSRF-Token` header).
- **SQL.** All queries are prepared statements through PDO, with emulated prepares off and `sql_mode` STRICT.
- **Output.** Output is escaped with `e()` (`htmlspecialchars`, `ENT_QUOTES`). JSON embedded in HTML uses `JSON_HEX_TAG` / `json_attr()`.
- **Uploads.**
  - Only JPEG, PNG, and WebP are accepted, checked by real content and not the file extension.
  - Images are capped at 10 MB and 36 MP.
  - Every image is re-encoded with GD and stored under a random filename.
- **CCTV secrets.** The RTSP URL is write-only, must not contain credentials, and is never echoed back.

## 6. Headers

These are sent on every app response:
- `Content-Security-Policy`: `default-src 'self'`, with inline `<script>` allowed only by a per-request nonce. hls.js may load from jsDelivr (pinned version). Also `object-src 'none'`, `frame-ancestors 'none'`, `form-action 'self'`, and `base-uri 'self'`.
- `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy`, `Cross-Origin-Opener-Policy: same-origin`, and `Cache-Control: no-store`
- `Strict-Transport-Security` on HTTPS

## 7. Errors and logging

- **What users see.** Users only see calm Thai messages, for example "ระบบกำลังดำเนินการ กรุณาลองใหม่อีกครั้ง". Technical details go to the server log.
- **Error handling.** `display_errors` is off for web requests. The front controller catches every uncaught error and shows a friendly 500 page.
- **Development logging.** `MYKID_ENV=development` turns on the `[Mykid][Scope]` developer logs and the browser console groups. Production keeps them off.

## 8. Secrets

- **Where secrets live.** `DB_*` and `APP_KEY` live only in environment variables: Coolify in production, `.env` locally.
  - `.env` is git-ignored and docker-ignored.
- **Rotating `APP_KEY`.** Rotating the key invalidates every ID token in bookmarked or open pages, which then simply reload. Stored data is not affected.

## Known follow-ups

- Admin roles still sign in with a PIN. ADR-016 plans account + password for admin roles.
- The schema does not yet have foreign keys. See ADR-020.
- There is no audit log table yet.
