# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A plain PHP + MySQL website + CMS for a Dutch Scouting group (Scouting Voerendaal). No framework, no
build step, no package manager — just `.php` files served directly by Apache, designed to run on
ordinary shared hosting. All copy, docs, comments, and UI strings are in Dutch.

## Deployment target: old-school shared hosting

Production runs on a traditional shared hosting plan (PHP + MySQL via a control panel like
DirectAdmin/cPanel/Plesk, files uploaded over FTP/SFTP — see `INSTALL.md`). Docker is **only** for local
development (see below) and is never deployed. This constrains every implementation choice:

- No shell access, no Composer/npm, no build step, no queue/cron workers, no Redis/object cache — assume
  none of that is available. Anything the app needs must work as plain PHP files plus MySQL, executed
  per-request under standard Apache + `mod_php`/PHP-FPM.
- No control over the PHP version beyond what the host offers (assume PHP 7.4+ per `INSTALL.md`; the
  Docker image uses 8.2 but don't rely on syntax/features newer than what shared hosts commonly run).
- No CLI on the server — there's no `php artisan`-style tooling. Schema/data changes ship as plain PHP +
  SQL that runs per-request instead: a fresh install gets the full schema from `sql/install.sql` (via the
  `install.php` wizard); an existing install gets incrementally upgraded by numbered migration files in
  `sql/migrations/`, run automatically by **Beheerpaneel → Updates** (see `includes/updater.php`). **Any
  schema change needs both**: update `sql/install.sql` (so fresh installs get it) *and* add a new
  `sql/migrations/000X_*.sql`/`.php` file (so existing installs get upgraded) — the two must produce the
  same end state.
- Behavior differences between hosts must be handled defensively (e.g. don't assume specific PHP
  extensions beyond `pdo_mysql`, don't assume `.htaccess`/`mod_rewrite` semantics beyond what's already
  used, don't assume writable paths outside `assets/uploads/`).
- Deployment is "upload the files" — there's no atomic release/rollback, so avoid changes that require a
  specific multi-file update order to avoid breaking a half-deployed site.

## Running locally

There is no local PHP toolchain expected (no composer.json/package.json). Docker is a dev-only
convenience that approximates the shared-hosting environment (PHP + Apache + MySQL) — it is not how the
site is deployed:

```bash
docker compose up -d --build      # build + start web (PHP 8.2 + Apache), MySQL 8, phpMyAdmin
docker compose down               # stop, keep DB volume
docker compose down -v            # stop and wipe DB volume (full reset)
```

- Website: http://localhost:8080/
- Admin panel: http://localhost:8080/admin/
- First run: visit http://localhost:8080/install.php to create the first admin account (blocks itself once an `admin_users` row exists)
- phpMyAdmin: http://localhost:8081/ (server `db`, user/pass `root`/`root`)

The project directory is bind-mounted into the container, so PHP/CSS/JS edits are reflected immediately —
no rebuild needed unless `Dockerfile` itself changes. `sql/install.sql` is auto-imported only the first
time the `db_data` volume is created.

There are no automated tests, linters, or formatters configured in this repo.

## Configuration

Configuration is split across two files so that an automated update (see **Updates** below) never
clobbers site-specific secrets:
- `config.php` — tracked in git, safe to overwrite on every update. Only defines fallback constants
  (environment variables for Docker, or placeholders for shared hosting) using `if (!defined(...))`
  guards, and requires `config.local.php` first if it exists.
- `config.local.php` — **not** in git, never touched by an update, created by the site owner from
  `config.local.php.example`. Holds the real DB credentials, `BACKUP_CRON_KEY`, and (optionally)
  `GITHUB_REPO` for a fork. Docker doesn't need this file — it gets its DB credentials from
  `docker-compose.yml` environment variables instead.
- Sites installed before this split existed (real credentials hardcoded directly in `config.php`) get
  auto-migrated the first time an update runs: `ensure_config_local_migrated()` in `includes/updater.php`
  writes those already-active values into a fresh `config.local.php` *before* `config.php` gets
  overwritten.

`APP_DEBUG` (env `APP_DEBUG=1`) toggles PHP error display — on in Docker, off by default in production.
`GITHUB_REPO` (`owner/repo`) is the repository **Beheerpaneel → Updates** checks/downloads releases from.
`BACKUP_DIR` (optional override in `config.local.php`) is where back-ups go — see **Back-ups**.

## Architecture

**Two public front controllers plus the admin panel, no router/framework:**
- `index.php` — the public one-page site. Pulls all content from the DB via helper functions and renders
  a single long HTML page (hero → programma → lidworden → verhuur → contact).
- `pagina.php` — standalone informational pages (e.g. member info) that don't belong on the one-pager,
  looked up by `?slug=` against the `pages` table (see **Data model**). Public URLs are `/<slug>` via a
  `mod_rewrite` rule in the root `.htaccess` (old `pagina.php?slug=` links get a 301); build links with
  `page_url()`, and slugs matching a real top-level directory are refused (`PAGE_RESERVED_SLUGS`). Both public front controllers
  share `<head>`/header-nav/footer chrome via `includes/site_layout_top.php` /
  `includes/site_layout_bottom.php` (the public-page analog of the admin's `layout_top.php`
  /`layout_bottom.php`); `index.php` sets `$isHome = true` before including the top layout so nav anchors
  render as same-page `#fragment` links instead of `index.php#fragment`, and so the homepage-only JSON-LD
  schema block renders. Active pages flagged `in_menu` in the admin are appended to the header nav
  automatically by the shared top layout.
- `admin/*.php` — one PHP file per admin screen (`speltakken.php` list + `speltak_form.php` create/edit,
  same pattern for `info_cards`/`info_card_form`, `documents`, `accounts`, `settings`, `uploads`,
  `backups`, `updates`, `controle`, `paginas`/`pagina_form`). Every admin page requires `admin/includes/auth.php` and
  calls `require_login()` first.

**Shared includes** (`includes/`, loaded via `require_once`, not web-accessible — blocked by `.htaccess`):
- `includes/db.php` — `db(): PDO` returns a lazily-created, memoized PDO singleton (prepared statements,
  exceptions on error, no emulated prepares).
- `includes/functions.php` — everything else: the `e()` HTML-escape helper, the settings key/value store
  (`get_setting()`/`set_setting()`, request-scoped statically cached), speltakken/documents/info_cards
  data access, `handle_upload()` for safe file uploads, CSRF helpers, and flash-message helpers.
  `admin/includes/auth.php` additionally requires this file. Also requires `includes/version.php`
  (defines `APP_VERSION`, bumped per release).
- `includes/backup.php` / `includes/updater.php` — back-up creation and the update mechanism (see
  **Back-ups** and **Updates** below).

**Data model** (`sql/install.sql`, MySQL/MariaDB, InnoDB/utf8mb4):
- `settings` — a single flat key/value table driving nearly every string on the public site (hero text,
  contact info, verhuur terms, footer, social links, feature cards, etc). This is the CMS's core: adding
  a new piece of editable copy means adding a new settings key, not a new table/column.
- `speltakken` — scouting sections/groups (name, color, age range, schedule, leader, optional external
  JSON "opkomsten" feed URL (Scoutdash), active flag, sort order). Public homepage renders one block per
  active speltak; if `feed_url` is set the block is populated client-side via AJAX against our own
  `api/opkomsten.php` (see below), otherwise it falls back to the static `toelichting` HTML field.
- `documents` — uploaded PDFs (registration forms etc.), referenced by filename in `UPLOAD_DIR`.
- `info_cards` — the "Doe mee!" cards (title/text/image/optional linked document), grouped by `sectie`,
  manually orderable.
- `pages` — standalone informational pages rendered by `pagina.php` (title, unique `slug`, `inhoud`
  HTML, optional meta description, `in_menu`/`actief` flags, sort order). `inhoud` is authored via the
  same CKEditor as other rich-text fields, extended with image upload and YouTube/media embedding (see
  **Rich-text editing** below).
- `admin_users` — CMS login accounts (`password_hash`), created via `install.php` (self-disables after
  first admin exists) or `admin/accounts.php`.
- `schema_migrations` — bookkeeping for the update mechanism: one row per applied filename from
  `sql/migrations/`, so each migration runs exactly once (see **Updates** below).

**Admin CRUD pattern** — every entity list/form pair follows the same shape: GET loads existing row (or
defaults for "new"), POST checks `csrf_verify()`, validates/normalizes `$_POST`, writes via a prepared
statement, sets a flash message with `flash_set()`, then redirects (POST/redirect/GET). Forms render
inline in the same file below the logic, wrapped by `admin/includes/layout_top.php` /`layout_bottom.php`.

**Content trust model**: most `settings`/`info_cards`/`speltakken` text fields are stored and echoed as
raw HTML (commented inline as `/* HTML toegestaan */`) since they're only editable by trusted logged-in
admins — this is intentional, not an oversight. Only user-facing dynamic values (URLs, filenames, ids)
go through `e()`.

**File uploads** (`handle_upload()` in `includes/functions.php`): extension allowlist per call site, file
renamed to a random hex name (prevents overwrite/collision and hides original filename), stored under
`assets/uploads/` (`UPLOAD_DIR`/`UPLOAD_URL` from `config.php`). That directory is configured to never
execute scripts, so an uploaded file can't become a code-execution vector even if the extension check is
somehow bypassed.

**Rich-text editing** (`assets/js/admin.js`, CKEditor 5 **super-build** + Dutch translation loaded from
CDN in `admin/includes/layout_bottom.php`, exposed as `CKEDITOR.ClassicEditor`): every
`<textarea class="rich-text">` in the admin panel becomes a CKEditor instance with every open-source
feature enabled (fonts/colours, alignment, highlight, tables with properties, image resize/styles/captions,
code blocks, to-do lists, find & replace, source editing, HTML embed, word count, …). The super-build also
contains premium plugins that error without a licence — they must stay in `EDITOR_REMOVE_PLUGINS`.
General HTML Support allows all markup except `<script>` and `on*` attributes. Output that relies on CSS
classes (CKEditor's `image-style-*`, `marker-*`, `figure.table`, `todo-list`, and our own `cms-*` styles
from `style.definitions`) is styled globally in `assets/css/style.css`; the `cms-*` styles are mirrored as
`.ck-content` rules in `assets/css/admin.css` — keep those three places in sync when adding a style. Pasted/uploaded images go through a custom `FileRepository` upload adapter
(`CkeditorUploadAdapter`) that posts to `admin/upload_image.php` (session-auth + CSRF header check, then
`handle_upload()` with the same image-extension allowlist as elsewhere) and gets back an **absolute**
URL built from `site_url` — a relative path would resolve differently when previewed live under `/admin/`
versus when the same stored HTML is later rendered from the project root (`index.php`/`pagina.php`), so
the adapter always returns a fully-qualified URL to avoid that mismatch. `mediaEmbed.previewsInData` is
enabled so pasting a YouTube URL bakes the actual responsive iframe markup into the saved HTML, instead of
a semantic placeholder that would only render correctly inside CKEditor itself.

**Security surface**: `includes/`, `admin/includes/`, `sql/`, `tools/`, `backups/` and `docker/` are
blocked from direct HTTP access via per-directory `.htaccess`. Admin passwords must satisfy
`password_policy_error()` in `includes/functions.php` (12+ chars, 2+ digits, 2+ non-alphanumeric) — use
it wherever a password is set.

**`.htaccess` rules** — they must protect on as many shared hosts as possible *and never cause a 500*:
- Directory denies use the canonical dual block (`<IfModule mod_authz_core.c> Require all denied` +
  `<IfModule !mod_authz_core.c> Order/Deny`), so they work on Apache 2.2, 2.4 with or without
  `mod_access_compat`, and LiteSpeed. Never use bare `Order/Deny` (500 on 2.4 without access_compat).
- Every module-dependent directive (`Header`, `Rewrite*`, `RedirectMatch`, `RemoveHandler`, `php_flag`,
  `DirectoryIndex`) sits inside `<IfModule>` — `php_flag` outside `<IfModule mod_php*.c>` 500s under
  PHP-FPM. Assumed minimum `AllowOverride`: `AuthConfig FileInfo Indexes Limit Options` (restricted
  `Options=` list is enough); don't add directives that need more (e.g. `Options -ExecCGI` was dropped).
- A **new sensitive directory** needs both its own `.htaccess` with the canonical block *and* an entry in
  the `^(includes|admin/includes|sql|backups|tools|docker)` rewrite rule in the root `.htaccess` (the
  rewrite rule is the only protection on OpenLiteSpeed). New dev/config file types go in the root
  `FilesMatch`, and in the nginx snippet in `INSTALL.md` (**Hostingvereisten en nginx**) — keep the two
  in sync.
- `assets/uploads/.htaccess` is an **allowlist** (only `^[^.]+\.(jpe?g|png|gif|webp|pdf)$` is served),
  plus PHP engine off / handler removal / sandbox CSP as further layers. Adding an upload type means
  extending that allowlist as well as the `handle_upload()` call site.
- Security headers (`nosniff`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, conditional
  short HSTS) come from the root `.htaccess`; the **CSP comes from PHP** (see below) — don't add a second
  CSP in `.htaccess`, two policies combine restrictively.

**Admin sessions**: always start sessions with `start_secure_session()` (`includes/functions.php`), never
a bare `session_start()` — it sets the `sv_session` cookie name, HttpOnly, SameSite=Lax, Secure on HTTPS
(incl. `X-Forwarded-Proto`), strict mode and `gc_maxlifetime`. Loading `admin/includes/auth.php` runs
`admin_session_check_expired()`: 2 h idle (`ADMIN_SESSION_IDLE_TIMEOUT`), 12 h absolute
(`ADMIN_SESSION_MAX_LIFETIME`), and a password fingerprint compared against the DB, so changing/resetting
a password logs out that account's other sessions. Anything that logs a user in or changes their
password must go through `admin_session_login($user)`, or that session is treated as expired. Logout is
POST + CSRF only (`admin/logout.php`); any new logout link must be a form. Dotfiles in `assets/uploads/`
are never listed, deletable, or accepted as upload names (protects its `.htaccess`).

**Admin login geoblocking** (`includes/geoip.php`): `admin/login.php` refuses (403, no form, POST not
processed) unless `admin_login_allowed_from_ip()` passes — i.e. the IP is in a European country
(`GEO_EUROPE_COUNTRIES`, optionally narrowed by `ADMIN_LOGIN_COUNTRIES` in `config.local.php`). No external
API: lookups binary-search (`fseek`, no full load) the bundled fixed-record files
`includes/geo/europe-ipv4.bin`/`europe-ipv6.bin`, generated from the five RIRs' public delegated stats by
the dev-only CLI script `tools/build_geo_europe.php` — **re-run it before each release** to keep the
ranges current (`docker run --rm -v "$PWD":/app -w /app php:8.2-cli php tools/build_geo_europe.php`).
Deliberately fails open for private/reserved IPs (local Docker) and when the data files are missing
(half-uploaded update), so the owner can't lock themselves out; `ADMIN_GEO_BLOCK=false` in
`config.local.php` is the escape hatch for admins abroad. CSRF tokens (`csrf_field()`/`csrf_verify()`) guard all admin POST forms.
`install.php` must be deleted after first use (it self-disables once an admin exists, but stays running
until removed).

**Content-Security-Policy**: public pages send a CSP from `includes/site_layout_top.php`, the admin panel
from `admin/includes/auth.php` (as a PHP `header()`, so it doesn't depend on `mod_headers`). Both allow
scripts only from our own origin plus a fixed CDN (Google Tag Manager / CKEditor) — **no inline `<script>`
blocks and no inline event handlers** (`onclick=`, `onsubmit=`, …); they would silently stop working. Put
JS in `assets/js/` instead: admin confirmation prompts use `<form data-confirm="...">` (handled in
`assets/js/admin.js`), and Google Analytics is initialised from `assets/js/gtag.js`. Inline `style`
attributes are allowed (CKEditor output relies on them). Adding a new external script/font/API host means
extending the relevant CSP directive.

**Public JSON feed integration**: the browser never calls Scoutdash directly. `assets/js/main.js` makes a
single AJAX call to our own `api/opkomsten.php` and distributes the per-speltak results into each
`.prog-list[data-feed-slug]` block. That endpoint (`includes/scoutdash.php`) fetches every active
speltak's `feed_url` server-side in one request cycle and caches the combined result in
`includes/cache/opkomsten.json` for 10 minutes (`OPKOMSTEN_CACHE_TTL`), using `flock()` so concurrent
visitors don't all trigger a refresh at once. If a speltak's Scoutdash feed fails to load, the last
known-good items for that speltak are served from cache (marked `ok:false`) instead of breaking that
block — this also shields the site from Scoutdash downtime. Only when there's no cached data at all does
the block show the Dutch fallback error message. Unlike CMS content, feed content is **untrusted** (anyone
with edit rights in Scoutdash writes it), and `main.js` inserts each item's `omschrijving` as HTML — so it
is passed through `sanitize_feed_html()` (`includes/scoutdash.php`) before it's cached: a DOM-based cleaner
that keeps regular tags/attributes including `style`, but drops `<script>`/`<svg>`/`<object>`/etc.,
`on*` handlers, `srcdoc`, and `javascript:`/`data:` URLs, and re-serialises everything fully escaped.
Bump `OPKOMSTEN_CACHE_VERSION` when changing the sanitizer, so existing caches get re-cleaned.
`scoutdash_http_get()` fetches **https only**, on every redirect hop too (max `SCOUTDASH_MAX_REDIRECTS`),
always verifies TLS, and caps responses at `SCOUTDASH_MAX_BYTES`; without curl it follows redirects
manually because PHP's http wrapper allows https→http. `url_has_allowed_scheme()` (same file) validates
URLs in `admin/speltak_form.php` (feed: empty or https; afmeldlink: empty or http/https) — reuse it for any
admin URL field that's fetched server-side or rendered as a public link. There's deliberately no
private-IP/DNS-rebinding check (not robustly doable without curl); https + certificate validation is the
mitigation.

**Back-ups** (`includes/backup.php`, `admin/backups.php`, `cron/backup_cron.php`): a back-up is a single
zip `backup-<datetime>-<8 hex>.zip` (random suffix so names can't be guessed) containing a pure-PHP
database dump (`database.sql`, built via `SHOW CREATE TABLE`/`SELECT *` — no `mysqldump` CLI, per the
no-shell-access constraint above) plus the entire project directory except `BACKUP_EXCLUDES` in
`includes/backup.php` (currently `backups`, `.git`, `.claude`, OS junk files) and any known back-up
directory. Back-ups contain `config.local.php` and password hashes, so they're stored **outside the web
root** by default: `BACKUP_DIR` = `<parent of project>/<project folder name>-backups` (named after the
project folder so two sites under one hosting account don't share — and purge — each other's back-ups).
`backup_dir_status()`/`backup_dir()` resolve the directory once per request; if `BACKUP_DIR` can't be
created/written (permissions, `open_basedir`) they fall back to the in-project `backups/` (protected by
`.htaccess`, auto-written/upgraded as `BACKUP_HTACCESS`) and the admin page shows a warning. Listing,
download, delete and purge go through `backup_known_dirs()` (active dir + configured `BACKUP_DIR` +
legacy `backups/`), and downloads/deletes must resolve paths via `find_backup_path()`. The zip is built
from `backup_project_root()` — never from `dirname(BACKUP_DIR)`. Triggered manually from **Beheerpaneel →
Back-ups**, or weekly via a hosting-panel cronjob hitting `cron/backup_cron.php` (URL + `BACKUP_CRON_KEY`
from `config.php`, or direct PHP-CLI if the host offers that in cron). Every run also deletes backups
older than `BACKUP_RETENTION_MONTHS` (12). Restore is not implemented yet (deliberately — see the file
for how to manually restore in the meantime).

**When adding a new feature that introduces persistent state**, check whether it's already covered by the
back-up: new tables are automatic (the dump iterates all tables), and new files/directories under the
project root are automatic too *unless* you add them to `BACKUP_EXCLUDES`. The one case that needs
explicit handling is data stored **outside this project directory** (e.g. a call to an external storage
service, or a path outside `UPLOAD_DIR`) — that must be added to `includes/backup.php` explicitly or it
will silently be missing from every back-up.

**Updates** (`includes/updater.php`, `admin/updates.php`): checks GitHub Releases for `GITHUB_REPO`
(cached in `includes/cache/update_check.json` for `UPDATE_CHECK_CACHE_TTL`, same `flock()` pattern as the
opkomsten cache), compares the tag against `APP_VERSION` (`includes/version.php`), and renders the
release body through a small escape-first markdown-lite renderer (`render_release_notes()` — everything
is HTML-escaped before any tags we generate ourselves are added back in, so an external release body can
never inject raw HTML/JS). Applying an update (`perform_full_update()`) always:
1. Auto-migrates a pre-split `config.php` into `config.local.php` if needed (see **Configuration**).
2. Runs `run_backup()` first — a failed backup aborts the whole update, nothing gets touched.
3. Downloads the release's GitHub zipball and extracts it to `.update-tmp/` under the active back-up
   directory (`update_tmp_dir()`; normally outside the web root, otherwise `.htaccess`-protected —
   reused rather than adding another writable/protected directory). All updater HTTP is https-only with
   certificate checks and max 5 redirects (`updater_curl_options()`, or `updater_stream_get()` without
   curl).
4. Copies every file over the project **except** paths listed in `UPDATE_PRESERVE`
   (`config.local.php`, `assets/uploads/`, `backups/`, `includes/cache/`, `install.php`, `.git`,
   `.claude`) — extend this list the same way as `BACKUP_EXCLUDES` whenever a feature adds a new
   site-specific/local-only path that must survive an update.
5. Runs any not-yet-applied files from `sql/migrations/` in filename order (`run_pending_migrations()`),
   tracked in the `schema_migrations` table — `.sql` files for schema changes, `.php` files for other
   migrations (e.g. moving/renaming uploaded files, backfilling data). Stops at the first failure so later
   migrations don't build on a half-applied one; the pre-update backup is the recovery path.

**Releasing**: don't bump `APP_VERSION` by hand — push a tag `vX.Y.Z` and `.github/workflows/release.yml`
commits the matching `APP_VERSION` on top of the tagged commit, moves the tag to that commit (the
release zipball must contain the right version), fast-forwards `main` if possible, and creates the GitHub
release with generated notes if it doesn't exist yet.

There's also a "run migrations only" action on the same admin page, for hosts where automatic
download/overwrite isn't possible (no outgoing HTTPS, or file permissions) — the site owner uploads the
new release via FTP/SFTP themselves (preserving the same `UPDATE_PRESERVE` paths by hand) and then only
needs the migrations applied.

`install.php` is itself a small wizard now: test DB connectivity (with instructions to fill in
`config.local.php` if it fails) → one-click "install schema" button that runs `sql/install.sql` via PDO
and marks all migrations that already exist at that point as applied (`mark_all_migrations_applied()`,
since a fresh install already has the up-to-date schema) → the existing first-admin-account form.

**Systeemcontrole** (`includes/checks.php`, `admin/controle.php`): a read-only health page. Server-side
checks (`run_system_checks()`: PHP version/extensions/limits, DB tables + pending migrations, config
placeholders, `install.php`, HTTPS, `site_url`, geoblock data, back-up dir/age/cron heuristic, update
status from cache, write permissions, `.htaccess` presence, opkomsten cache) run on every load; outgoing
connections (GitHub, each Scoutdash feed — `checks_external()`) only on a POST button. Web-server behaviour
(rewrites, blocked directories/files, security headers) is tested **from the admin's browser** via
`<tr data-probe-url data-probe-expect>` rows handled in `assets/js/admin.js` — not by PHP loopback requests,
which are unreliable on shared hosts. When adding a new sensitive file/directory or a new hard requirement,
add it to `CHECK_BLOCKED_FILES`/`CHECK_HTACCESS_FILES` or a check function there.
