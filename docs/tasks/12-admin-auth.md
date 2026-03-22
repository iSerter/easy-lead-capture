# Task 12 — Admin Authentication

## Goal
Implement password-based admin login with session tokens stored in SQLite and login rate limiting.

## Files to Create
```
src/Controllers/AdminController.php
src/Middleware/AdminAuthMiddleware.php
src/Views/admin/login.php
```

## Steps

1. **`AdminController`** — login flow:
   - `loginForm()` — `GET /admin/login`: renders the login form.
   - `login()` — `POST /admin/login`:
     - Rate limit check: query `login_attempts` for this IP in the last 15 minutes. If >= 5, return 429 with "Too many attempts" message.
     - Verify password: use `password_verify()` if config value looks hashed (starts with `$2y$` or `$argon2`), otherwise do constant-time string comparison via `hash_equals()`.
     - On success: generate token (`bin2hex(random_bytes(32))`), insert into `admin_sessions` (expires in 24h), set HTTP-only secure cookie `elc_session`.
     - On failure: record attempt in `login_attempts`, show error on login form.
   - `logout()` — `POST /admin/logout`: delete session from DB, clear cookie, redirect to login.

2. **`AdminAuthMiddleware.php`** (PSR-15):
   - Read `elc_session` cookie.
   - Look up token in `admin_sessions` where `expires_at > datetime('now')`.
   - If not found or expired: redirect to `/admin/login`.
   - Clean up expired sessions occasionally (1-in-10 chance on each request).

3. **`login.php`** — login page template:
   - Simple centered card with password input and submit button.
   - Styled consistently with the form (Tailwind, same color system).
   - Shows error message on failed login.
   - Shows rate limit message if locked out.

4. **Wire into `App.php`**:
   - Apply `AdminAuthMiddleware` to `/admin`, `/admin/export`, `/admin/logout` routes.
   - Leave `/admin/login` (GET and POST) unprotected.

## Acceptance Criteria
- Correct password grants access, sets session cookie.
- Wrong password shows error, does not grant access.
- After 5 failed attempts from same IP within 15 min, login is blocked (429).
- Expired sessions are rejected.
- Logout clears the session.
