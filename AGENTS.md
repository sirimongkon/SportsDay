# AGENTS.md — เว็บรายงานผลกีฬาสี

Vanilla PHP + SQLite, no composer/npm/build. Tailwind via CDN, QR lib vendored at `asset/qrcode.min.js` (qrcodejs 1.0.0, `new QRCode(el,{text,width,height})` — NOT the `qrcode` npm package; `build/qrcode.min.js` does not exist there, don't "fix" the src back to CDN).

## Run
- Dev server (local PHP has pdo_sqlite DLL but NOT enabled — flags are required):
  `php -d extension=pdo_sqlite -d extension=sqlite3 -S 127.0.0.1:8000 -t .`
- Or press F5 in VSCode (`.vscode/` runs the same command + opens Chrome).
- Default admin: `admin / Admin1234`. DB auto-creates at `data/app.sqlite` on first hit.

## NEVER delete `data/app.sqlite`
It holds live user data (past mistake wiped it). For tests: back up, test, then clean up test rows **via API** (`match_del`, `mgr_del`) — never `Remove-Item` the DB.

## Shell quirks (Windows PowerShell 5.1)
- No `&&` — chain with `; if ($?) { ... }`. No `grep` — use the Grep tool.
- `php -r "..."` breaks on quotes — write temp scripts to `C:\Users\Acer\AppData\Local\Temp\opencode\` and run the file instead.
- Each bash call is a fresh shell: `Invoke-WebRequest -SessionVariable` does NOT persist across calls — do login + authed requests in ONE call.
- PHP 8.2+: unparenthesized nested ternary (`a ? b : c ? d : e`) is fatal — use explicit `if` in templates.

## Architecture (all logic server-rendered, one JSON endpoint)
- `lib/db.php` — PDO singleton, `migrate()` (idempotent ALTERs, runs every request — add new columns/tables here), `init_db()` seed (fresh DB only), `standings()` (sorted pts → wins → diff; rank sports map 🥇🥈🥉 into w/d/l), `tlogo()` (logo file or color dot fallback), auth helpers.
- `api.php` — every action via `act=` POST **only** (never GET). QR-token (`score.php?t=`) writes bypass login but NEVER bypass `locked`. `finish` locks immediately; only `unlock` (admin) reopens.
- Shared helpers in `lib/db.php`: `try_login()` (5 fails → 5-min lock, use in ALL login forms), `j()` (JS-safe json for `onclick="fn(...)"` — never `h()` there), `base_url()` (sanitized host for QR links).
- `index.php` — public scoreboard. Entry/QR buttons render ONLY for `$me` with scope (admin: all, manager: own `sport_id`); managers are force-filtered to their sport.
- `score.php` — two entries: QR token (no login) or manager/admin login + `?match=` picker. Three form types: `points` (+/- steppers), `sets` (dynamic rows), `rank` (🥇🥈🥉 dropdowns, dupes rejected in API).
- `admin.php` — admin-only CRUD + QR print modal. Edit forms reuse the add form (hidden id + `*_set` action).
- Sport types: `points` | `sets` (best_of) | `rank`. Changing a sport's type or deleting it is BLOCKED while matches reference it.
- `uploads/` must be writable; logo upload enforces getimagesize + jpg/png/gif/webp + 2MB (`uploads/.htaccess` blocks script execution).

## Conventions
- Thai UI (Prompt font). Team display: `short` name + full name in `title`.
- Keep diffs minimal (Ponytail plugin active). Verify with `php -l` + a live-server smoke test per changed page; `index.php` auto-reloads every 60s.
- Deploy = copy files, `chmod 775 data uploads` (see `README_DEPLOY.txt`).
