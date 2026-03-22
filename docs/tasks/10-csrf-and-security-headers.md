# Task 10 — CSRF Protection & Security Headers

## Goal
Add CSRF token validation to the form submission endpoint and set appropriate security headers on all responses.

## Files to Modify
```
src/App.php
src/Views/form.php
src/Views/layouts/base.php
src/Controllers/FormController.php
src/Controllers/SubmitController.php
```

## Steps

1. **CSRF token generation** (in `FormController`):
   - Start a PHP session (if not already started).
   - Generate a random CSRF token (`bin2hex(random_bytes(32))`), store in `$_SESSION['csrf_token']`.
   - Pass the token to the form template.

2. **CSRF token in form** (in `form.php`):
   - Include the token as a hidden field or as a `data-csrf` attribute on the form element.
   - The JS submission code includes it in the POST body as `_csrf_token`.

3. **CSRF validation** (in `SubmitController`):
   - Before processing, compare `_csrf_token` from the request body to `$_SESSION['csrf_token']`.
   - If mismatch or missing: return 403 `{"success": false, "message": "Invalid request."}`.
   - After successful validation, regenerate the token (prevent reuse).

4. **Security headers** (add as Slim middleware in `App.php`):
   - All responses:
     - `X-Content-Type-Options: nosniff`
     - `X-XSS-Protection: 1; mode=block`
     - `Referrer-Policy: strict-origin-when-cross-origin`
   - Form/embed responses (non-admin):
     - No `X-Frame-Options` (must be embeddable).
     - `Content-Security-Policy` allowing Tailwind CDN, Google reCAPTCHA domains, and `unsafe-inline` for styles.
   - Admin responses:
     - `X-Frame-Options: SAMEORIGIN`

## Acceptance Criteria
- Form submission without a valid CSRF token is rejected with 403.
- CSRF token is regenerated after each successful submission.
- Security headers are present on all responses.
- Admin pages cannot be iframed from other origins.
