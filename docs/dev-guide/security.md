# Security Guide

`easy-lead-capture` is designed with security as a priority. This guide covers the main security features and how to configure them.

## CSRF Protection

The system includes built-in Cross-Site Request Forgery (CSRF) protection. 

- **Mechanism**: A unique token is generated for each session and stored in `$_SESSION['csrf_token']`.
- **Validation**: Every `POST /submit` request must include this token in the `_csrf_token` field.
- **Handling**: If the token is missing or incorrect, the system returns a `403 Forbidden` response.

No configuration is required as CSRF protection is always enabled.

## Content Security Policy (CSP)

The form controller automatically emits a strict Content Security Policy header to prevent Cross-Site Scripting (XSS).

- **Strict Policies**: By default, it disallows inline scripts and styles unless they are explicitly permitted.
- **Nonce-based scripts**: The system uses a random nonce for required internal scripts.
- **Whitelist**: External domains for Google reCAPTCHA are pre-whitelisted.

## reCAPTCHA v3 Integration

To protect your forms from bot submissions, you can enable Google reCAPTCHA v3.

### Configuration
```php
'captcha' => [
    'enabled' => true,
    'recaptcha_site_key' => 'your-site-key',
    'recaptcha_secret_key' => 'your-secret-key',
    'threshold' => 0.5, // 1.0 is very strict, 0.1 is very loose
],
```

When enabled, the form will verify the reCAPTCHA token on the server side. Submissions with a score below the threshold will be rejected.

## Security Headers

The `SecurityHeadersMiddleware` adds several standard security headers to all responses:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN` (Can be overridden for the form page)
- `Referrer-Policy: strict-origin-when-cross-origin`

## Admin Authentication

- **Password Storage**: We recommend using `password_hash()` when setting the password in config, though plain strings are supported (and hashed internally if they don't look like hashes).
- **Rate Limiting**: The admin login is protected by simple IP-based rate limiting (max 5 attempts per 15 minutes).
- **Session Security**: Admin sessions are stored in the SQLite database and use HttpOnly, SameSite=Lax cookies.
