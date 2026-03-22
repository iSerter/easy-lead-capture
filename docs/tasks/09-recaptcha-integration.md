# Task 09 — reCAPTCHA v3 Integration

## Goal
Add optional reCAPTCHA v3 protection to the form — client-side token generation and server-side verification.

## Files to Create
```
src/Captcha/RecaptchaVerifier.php
src/Middleware/CaptchaMiddleware.php
```

## Files to Modify
```
src/Views/form.php         (add reCAPTCHA script + token generation)
src/App.php                (attach middleware to /submit route)
```

## Steps

1. **Client-side** (in `form.php`):
   - If `captcha.enabled` is true, load `https://www.google.com/recaptcha/api.js?render=SITE_KEY`.
   - Before form submission (in the fetch call), call `grecaptcha.execute(siteKey, {action: 'submit'})` to get a token.
   - Include the token in the POST body as `captcha_token`.

2. **`RecaptchaVerifier.php`**:
   - Uses `google/recaptcha` library.
   - `verify(string $token, string $ip): RecaptchaResult` — calls Google's siteverify API.
   - Returns an object/array with: `success` (bool), `score` (float), `action` (string).

3. **`CaptchaMiddleware.php`** (PSR-15 middleware):
   - Only active when `captcha.enabled` is true.
   - Extracts `captcha_token` from the request body.
   - Calls `RecaptchaVerifier::verify()`.
   - If score < threshold (default 0.5, configurable via `captcha.recaptcha.threshold`): return 403 JSON error `{"success": false, "message": "Captcha verification failed."}`.
   - If valid: attach the score to the request attributes so `SubmitController` can store it in the `captcha_score` column.

4. **Wire into `App.php`**:
   - Add `CaptchaMiddleware` to the `POST /submit` route only (not globally).
   - Only instantiate if `captcha.enabled` is true.

## Acceptance Criteria
- With captcha enabled: form loads reCAPTCHA script, submission includes token, server verifies.
- Low-score requests are rejected with 403.
- With captcha disabled: no reCAPTCHA script loads, no middleware runs, submission works normally.
- Captcha score is stored in the `leads.captcha_score` column.
