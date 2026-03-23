# Technical PRD — Easy Lead Capture

## Overview

A lightweight, embeddable lead capture system distributed as a Composer package. Developers install it, define a config array, and get a beautiful lead form, SQLite storage, email notifications, optional reCAPTCHA v3 protection, and a basic admin panel — all with zero framework dependencies on the host project.

---

## Architecture

### Framework Decision: Slim 4

**Use Slim 4 micro-framework.** Rationale:

- The app needs routing (form page, form submission endpoint, admin panel, CSV export, JS embed script) — hand-rolling a router adds complexity for no gain.
- Slim 4 is ~75KB, adds negligible overhead, and provides PSR-7/PSR-15 compliance.
- Middleware support is useful for admin auth and CAPTCHA validation.
- Widely adopted — developers consuming this package will recognize the patterns.

### Package Structure

```
iserter/easy-lead-capture/
├── composer.json
├── src/
│   ├── App.php                    # Main entry point — boots Slim, registers routes
│   ├── Config/
│   │   └── ConfigValidator.php    # Validates & normalizes the user-provided config array
│   ├── Controllers/
│   │   ├── FormController.php     # GET  /form        — renders the lead form
│   │   ├── SubmitController.php   # POST /submit      — handles form submission
│   │   ├── EmbedController.php    # GET  /embed.js    — serves the JS embed loader
│   │   └── AdminController.php   # GET  /admin, GET /admin/export
│   ├── Database/
│   │   ├── Database.php           # SQLite connection manager (WAL mode)
│   │   └── Migrations.php         # Auto-creates tables on first run
│   ├── Mail/
│   │   ├── Mailer.php             # Sends lead notification emails
│   │   └── MailerFactory.php      # Builds mailer from config (smtp/sendmail/ses)
│   ├── Captcha/
│   │   └── RecaptchaVerifier.php  # Validates reCAPTCHA v3 tokens server-side
│   ├── Support/
│   │   ├── DeferredTaskRunner.php # Runs callbacks after response is sent to client
│   │   └── ApiPinger.php          # POSTs lead data to external API endpoint
│   ├── Middleware/
│   │   ├── CaptchaMiddleware.php  # Applies to POST /submit when captcha enabled
│   │   └── AdminAuthMiddleware.php# Session-based password auth for /admin routes
│   └── Views/
│       ├── form.php               # Lead form template (Tailwind, inline)
│       ├── success.php            # Post-submission CTA with social links
│       ├── admin/
│       │   ├── login.php
│       │   └── dashboard.php      # Leads table + CSV export button
│       └── layouts/
│           └── base.php           # Minimal HTML shell
├── assets/
│   ├── embed.js                   # Client-side embed loader (source, <2KB)
│   ├── styles.css                 # Pre-compiled Tailwind CSS (~15KB)
│   └── input.css                  # Tailwind directives (source, not shipped)
├── tailwind.config.js             # Scans src/Views/**/*.php for used classes
├── package.json                   # Dev-only: Tailwind CLI build script
├── public/
│   ├── .htaccess                  # Sample Apache rewrite rules
│   └── index.php                  # Sample entry point
└── tests/
```

### Key Dependencies (Composer)

| Package | Purpose |
|---|---|
| `slim/slim` ^4.0 | Micro-framework (routing, middleware) |
| `slim/psr7` | PSR-7 implementation |
| `symfony/mailer` | Email (SMTP, Sendmail, SES transports) |
| `google/recaptcha` ^1.3 | reCAPTCHA v3 server-side verification |

No Twig/Blade — plain PHP templates to minimize dependencies. Tailwind CSS is pre-compiled at development time using Tailwind CLI — only the classes used in the view templates are included, producing a ~15KB CSS file shipped with the package. Consumers never need Node.js.

---

## Embedding Strategy

### Primary: iframe (recommended)

```html
<iframe src="https://yoursite.com/lead-capture/form"
        style="border:none; width:100%; height:500px;"
        loading="lazy">
</iframe>
```

**Why iframe is primary:**
- reCAPTCHA v3 works cleanly — the captcha script loads inside the iframe's origin, no cross-origin issues.
- CSS isolation — the form's Tailwind styles can't conflict with the host page.
- Simplest integration for the developer.

The form auto-resizes height via `postMessage` to the parent window (handled by embed.js if used).

### Secondary: JavaScript Loader

```html
<script src="https://yoursite.com/lead-capture/embed.js"></script>
<div id="lead-form"></div>
<script>
  EasyLeadCapture.render('#lead-form');
</script>
```

The JS loader (`embed.js`, <2KB gzipped) simply:
1. Creates an iframe pointing to the form endpoint.
2. Listens for `postMessage` events to auto-resize the iframe height.
3. Optionally opens the form in a modal/overlay if `render('#el', { mode: 'modal' })` is used.

This keeps the embed lightweight while maintaining iframe isolation for captcha and styles.

---

## Database

### SQLite3 with WAL Mode

Connection setup in `Database.php`:

```php
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->exec('PRAGMA journal_mode=WAL');
$pdo->exec('PRAGMA foreign_keys=ON');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
```

### Schema (auto-created on first run)

```sql
CREATE TABLE IF NOT EXISTS leads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    data JSON NOT NULL,           -- stores all form field values as JSON
    ip_address TEXT,
    user_agent TEXT,
    captcha_score REAL,           -- reCAPTCHA v3 score (0.0–1.0), NULL if disabled
    created_at TEXT DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_leads_created_at ON leads(created_at);

CREATE TABLE IF NOT EXISTS admin_sessions (
    token TEXT PRIMARY KEY,
    created_at TEXT DEFAULT (datetime('now')),
    expires_at TEXT NOT NULL
);
```

**Why JSON for form data?** The form fields are fully configurable — storing them as a JSON column avoids schema migrations when the developer changes their field config. The admin dashboard and CSV export parse the JSON dynamically based on current config.

DB file location: configurable, defaults to `__DIR__ . '/data/leads.db'`. The `data/` directory should be writable and excluded from web access (`.htaccess` / nginx config guidance in README).

---

## Routing

All requests are routed through a single `index.php` entry point in the developer's chosen directory (e.g., `public/lead-capture/index.php`). URL rewriting (`.htaccess` for Apache, location block for nginx) forwards all requests under that path to this entry point, where Slim handles routing.

The developer configures the base path in their config:

```php
'base_path' => '/lead-capture', // where the app is mounted
```

| Method | Route | Controller | Auth | Description |
|---|---|---|---|---|
| GET | `/form` | `FormController` | — | Renders the lead capture form |
| POST | `/submit` | `SubmitController` | Captcha middleware | Validates + stores lead + sends email |
| GET | `/embed.js` | `EmbedController` | — | Serves the JS embed loader |
| GET | `/assets/styles.css` | `EmbedController` | — | Serves the pre-compiled CSS |
| GET | `/admin` | `AdminController@index` | Admin auth | Leads dashboard |
| GET | `/admin/login` | `AdminController@loginForm` | — | Login page |
| POST | `/admin/login` | `AdminController@login` | — | Authenticate |
| POST | `/admin/logout` | `AdminController@logout` | Admin auth | Destroy session |
| GET | `/admin/export` | `AdminController@export` | Admin auth | CSV download |

All routes above are relative to the configured `base_path`. For example, if `base_path` is `/lead-capture`, the form is served at `/lead-capture/form`.

The package ships a sample `.htaccess` for Apache and documents nginx configuration in the README:

```apache
# .htaccess (place in the lead-capture directory)
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [QSA,L]
```

---

## Form Rendering (Frontend)

### Technology

- **Tailwind CSS** pre-compiled at package development time. The Tailwind CLI scans `src/Views/**/*.php` and outputs `assets/styles.css` (~15KB) containing only the classes actually used. This file is committed to the repo and shipped with the package — consumers never need Node.js or npm.
- **Vanilla JavaScript** for front-end validation and form submission — no jQuery, no framework.
- **Total iframe payload target:** <20KB HTML + CSS (excluding reCAPTCHA script when enabled).

### CSS Build Pipeline (development only)

```bash
# One-time setup (package contributors only)
npm install

# Build CSS (scans views, outputs assets/styles.css)
npm run build:css

# Watch mode during development
npm run watch:css
```

`assets/styles.css` is committed to git. The `input.css` source file contains Tailwind directives and any custom component classes (e.g., form transitions, spinner animation). Contributors must run `npm run build:css` and commit the updated `styles.css` when changing view templates.

### Form Behavior

1. **Render fields** dynamically from the `form.fields` config.
   - Supported field types: `text` (default), `email`, `tel`, `url`, `textarea`, `multi_select` (checkbox group).
   - Required fields show a subtle asterisk.
2. **Client-side validation** on blur and on submit:
   - Required field check.
   - Email format regex.
   - Phone format (permissive, allows international).
   - Inline error messages below each field with smooth transitions.
3. **Submit via `fetch()` (AJAX)**:
   - POST to `/submit` as JSON.
   - Show a spinner on the submit button during request.
   - On success: transition (fade/slide) to the success/CTA view — no page reload.
   - On error: show error message above the form.
4. **reCAPTCHA v3** (when enabled):
   - Load `recaptcha/api.js` with the configured site key.
   - On form submit, call `grecaptcha.execute()` to get a token.
   - Send the token alongside the form data.

### Success / CTA View

After successful submission, the form transitions to a styled CTA:

- **Headline** from `on_submit.success_headline`.
- **Message** from `on_submit.success_message`.
- **Social links** (if configured): LinkedIn and X/Twitter icons linking to the configured URLs, with the `on_submit.social_links.message` text above them. Animated entrance.

### Color Customization

The `form.colors` config supports overrides via CSS custom properties injected into the template:

```php
'colors' => [
    'primary'    => '#4F46E5', // buttons, links, focus rings
    'background' => '#FFFFFF', // form background
    'text'       => '#111827', // body text
    'error'      => '#DC2626', // validation errors
]
```

Defaults to an indigo/white theme if not specified.

---

## Form Submission Flow

```
Client (iframe)                     Server (SubmitController)
─────────────────                   ─────────────────────────
POST /submit {fields, captchaToken}
        ──────────────────────────►
                                    1. CaptchaMiddleware: verify token with Google
                                       → reject if score < 0.5 (configurable threshold)
                                    2. Validate fields against config (required, types)
                                    3. Sanitize all input (htmlspecialchars, trim)
                                    4. Insert into SQLite (JSON blob + metadata)
                                    5. Return JSON {success: true}
                                       with Connection: close + Content-Length headers
        ◄──────────────────────────
Transition to success CTA
                                    6. Flush output buffers, close client connection
                                    7. Send email notification (deferred)
                                    8. POST lead data to ping_api endpoint (deferred)
```

### Deferred Email Sending

Email is sent **after** the HTTP response is delivered to keep form submission fast. The approach uses native PHP across all SAPIs — no Redis or queue system needed.

A `DeferredTaskRunner` utility class handles this reliably on any web server:

```php
class DeferredTaskRunner
{
    private array $tasks = [];

    public function defer(callable $task): void
    {
        $this->tasks[] = $task;
    }

    public function run(): void
    {
        if (empty($this->tasks)) {
            return;
        }

        // Prevent script from aborting when client disconnects
        ignore_user_abort(true);
        set_time_limit(30);

        // Flush all output buffers so the response body is sent
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();

        // SAPI-specific accelerators (close connection immediately)
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();           // PHP-FPM
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();          // LiteSpeed
        }

        // Execute all deferred tasks
        foreach ($this->tasks as $task) {
            try {
                $task();
            } catch (\Throwable $e) {
                // Silently continue — lead is already stored
            }
        }
    }
}
```

**How it works across all environments:**

1. **`Connection: close` + `Content-Length` headers** are set on the response. This tells the client the response is complete and to close the connection — works on every web server.
2. **`ob_end_flush()` + `flush()`** pushes the response body through PHP's output buffers to the web server.
3. **`fastcgi_finish_request()`** (PHP-FPM) or **`litespeed_finish_request()`** (LiteSpeed) are called when available as accelerators to immediately release the connection at the SAPI level.
4. **`ignore_user_abort(true)`** ensures the script keeps running even after the client disconnects (important for Apache mod_php).
5. Deferred tasks execute. Exceptions are caught per-task so one failure doesn't block others.

The `DeferredTaskRunner::run()` is called via Slim's `ResponseEmitter` or `register_shutdown_function()` after the response is emitted. The `SubmitController` only needs to call `$deferredRunner->defer(fn() => $mailer->send(...))`.

This pattern is proven in production across PHP-FPM, Apache mod_php, LiteSpeed, and nginx — no external dependencies required.

### Email Notification

Sent via Symfony Mailer. Template is a simple, clean HTML email:

- **Subject:** `New lead from [name/email]`
- **Body:** Lists all submitted fields in a formatted table.
- **To:** `admin.email` from config.
- **From:** `mail.from` from config.

If no `admin.email` is configured, email sending is skipped silently (logged if logging is added later).

### API Ping (Webhook)

Optionally POST lead data to an external API after submission. Configured in `on_submit.ping_api`:

```php
'on_submit' => [
    // ...
    'ping_api' => [
        'enabled' => true,
        'api_endpoint' => 'https://example.com/api/leads',
        'api_key' => 'sk-...',
    ],
],
```

When enabled, a deferred task sends a POST request to `api_endpoint` with:
- **Body:** JSON-encoded lead data (all submitted fields + `created_at`).
- **Headers:** `Content-Type: application/json`, `Authorization: Bearer {api_key}`.
- **Timeout:** 10 seconds. Failure is silent — the lead is already stored locally.

Uses PHP's `curl` extension (universally available). Registered as a deferred task via `DeferredTaskRunner` alongside email, so it runs after the response is delivered.

---

## Admin Panel

### Authentication

- Single password auth (from `admin.password` config).
- Login form at `/admin/login`.
- Password verified with `password_verify()` — the config value should be a `password_hash()` output, but plain strings are accepted too (with a logged warning recommending hashing).
- On success: generate a random token, store in `admin_sessions` table (expires in 24h), set as HTTP-only cookie.
- `AdminAuthMiddleware` checks the cookie token against the DB on every `/admin` request.

### Dashboard

- Paginated table of leads (25 per page), newest first.
- Columns derived dynamically from the configured form fields + `created_at`.
- Simple search/filter by date range.

### CSV Export

- `GET /admin/export?from=YYYY-MM-DD&to=YYYY-MM-DD` (dates optional).
- Streams a CSV with headers matching the configured field labels + `Date`.
- JSON field data is flattened: multi-select values joined with `;`.

---

## Security

| Concern | Mitigation |
|---|---|
| XSS | All output escaped with `htmlspecialchars()`. CSP headers on iframe responses. |
| CSRF | Submit endpoint validates a CSRF token (generated in the form, stored in session). |
| SQL Injection | PDO prepared statements exclusively. |
| Brute-force admin login | Rate limiting: max 5 attempts per IP per 15 minutes (tracked in SQLite). |
| reCAPTCHA bypass | Server-side token verification; score threshold configurable (default 0.5). |
| Direct DB file access | README documents `.htaccess` / nginx rules to block access to `/data/`. |
| Clickjacking | `X-Frame-Options: SAMEORIGIN` on admin pages. Form pages allow framing (they're designed for it). |

---

## Configuration Validation

`ConfigValidator.php` runs at boot (`App::__construct`). It:

1. Validates required keys exist (`admin.password`).
2. Sets defaults for optional keys (colors, captcha disabled, etc.).
3. Validates field types and structure.
4. Throws a clear `InvalidArgumentException` with a human-readable message if config is malformed.

---

## Developer Experience

### Minimal Setup (3 steps)

1. `composer require iserter/easy-lead-capture`
2. Create a directory (e.g., `public/lead-capture/`) with `index.php` containing the config array + `$app->run()`, and the provided `.htaccess`.
3. Embed the iframe or JS snippet pointing to `/lead-capture/form`.

### Zero Build Step (for consumers)

No Node.js, no npm, no asset compilation required by consumers. Tailwind CSS is pre-compiled and shipped as a ~15KB `styles.css`. Embed JS is a static file served by the package. Node.js is only needed by package contributors to rebuild CSS when view templates change.

### Auto-Migration

SQLite tables are created automatically on first request. No CLI commands or migration steps.

### Sensible Defaults

Everything works with minimal config:

```php
$app = new Iserter\EasyLeadCapture\App([
    'base_path' => '/lead-capture',
    'admin' => ['password' => 'hashed_password_here'],
]);
$app->run();
```

This gives you: name + email form, no captcha, no email notifications, default indigo theme.

---

## Task Checklist

Detailed task specs are in [`tasks/`](tasks/).

### Phase 1 — Core (MVP)
- [x] [01 — Project Scaffold](tasks/01-project-scaffold.md): Composer package, PSR-4 autoloading, Slim 4 bootstrap, sample entry point
- [x] [02 — Config Validation](tasks/02-config-validation.md): Validate config array, merge defaults, throw on invalid input
- [x] [03 — Database Setup](tasks/03-database-setup.md): SQLite connection with WAL mode, auto-create tables on first run
- [x] [04 — Form Rendering](tasks/04-form-rendering.md): FormController + Tailwind-styled form template with all field types
- [x] [05 — Client-Side Validation](tasks/05-client-side-validation.md): Inline validation on blur/submit, AJAX form submission, iframe height reporting
- [x] [06 — Server-Side Validation & Storage](tasks/06-server-side-validation-and-storage.md): SubmitController with input validation, sanitization, and SQLite insert
- [x] [07 — Success/CTA View](tasks/07-success-cta-view.md): Post-submission screen with social media links and animated transition
- [x] [08 — Embed JS Loader](tasks/08-embed-js.md): Lightweight JS loader with inline and modal modes, iframe auto-resize

### Phase 2 — Notifications & Security
- [x] [09 — reCAPTCHA v3](tasks/09-recaptcha-integration.md): Client-side token generation, server-side verification middleware
- [x] [10 — CSRF & Security Headers](tasks/10-csrf-and-security-headers.md): CSRF token flow, CSP/XFO/security headers
- [x] [11 — Email Notifications](tasks/11-email-notifications.md): Symfony Mailer with SMTP/Sendmail/SES, admin notification on new lead
- [x] [15 — API Ping](tasks/15-api-ping.md): POST lead data to external API endpoint after submission (deferred)

### Phase 3 — Admin Panel
- [x] [12 — Admin Authentication](tasks/12-admin-auth.md): Password login, session tokens in SQLite, rate limiting
- [x] [13 — Admin Dashboard](tasks/13-admin-dashboard.md): Paginated leads table with date filtering
- [x] [14 — CSV Export](tasks/14-csv-export.md): Downloadable CSV with date filtering, streamed output

---

## Open Decisions

1. **Logging** — No logging in v1. If needed later, PSR-3 `LoggerInterface` can be accepted via config.
