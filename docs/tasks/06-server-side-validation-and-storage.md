# Task 06 — Server-Side Validation & Lead Storage

## Goal
Build `SubmitController` to validate form input against the config, sanitize it, and store the lead in SQLite.

## Files to Create
```
src/Controllers/SubmitController.php
```

## Steps

1. **`SubmitController`** — handles `POST /submit`:
   - Parse JSON request body.
   - Validate CSRF token (Task 10 adds the token — for now, skip or stub).
   - Iterate configured `form.fields`:
     - Check required fields are present and non-empty.
     - Validate email format with `filter_var(FILTER_VALIDATE_EMAIL)`.
     - Validate `multi_select` values are within the configured `options`.
     - Reject any submitted fields not in the config (prevent injection of extra data).
   - Sanitize all string values: `trim()` + `htmlspecialchars()`.

2. **Store lead**:
   - Insert into `leads` table:
     - `data`: JSON-encode all validated field values.
     - `ip_address`: from request (respect `X-Forwarded-For` if present, take first IP).
     - `user_agent`: from `User-Agent` header.
     - `captcha_score`: NULL for now (populated in Task 09).
     - `created_at`: auto-set by SQLite default.
   - Use PDO prepared statements.

3. **Response**:
   - Success: `{"success": true}` with 200 status.
   - Validation errors: `{"success": false, "errors": {"field_name": "Error message"}}` with 422 status.
   - Server error: `{"success": false, "message": "..."}` with 500 status.

4. **Deferred email hook** (used by Task 11):
   - `SubmitController` receives a `DeferredTaskRunner` instance (injected via `App.php`).
   - After storing the lead, register the email callback: `$deferred->defer(fn() => $mailer->send(...))`.
   - The success response must include `Connection: close` and `Content-Length` headers so the client disconnects immediately.
   - `DeferredTaskRunner::run()` is called after Slim emits the response (via `register_shutdown_function()`). It flushes output buffers, calls `fastcgi_finish_request()` / `litespeed_finish_request()` when available, then executes deferred tasks.
   - This works reliably on PHP-FPM, Apache mod_php, LiteSpeed, and nginx.

5. **Create `src/Support/DeferredTaskRunner.php`**:
   - `defer(callable $task): void` — collects callbacks.
   - `run(): void` — sets `ignore_user_abort(true)`, flushes output buffers, calls SAPI-specific finish functions, then executes all tasks in try/catch.

## Acceptance Criteria
- Valid submission stores a row in `leads` with correct JSON data.
- Missing required field returns 422 with field-specific error.
- Invalid email returns 422.
- Extra fields not in config are silently dropped.
- SQL injection attempts are harmless (prepared statements).
