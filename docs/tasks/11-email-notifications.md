# Task 11 — Email Notifications

## Goal
Send an email to the admin when a new lead is captured, using Symfony Mailer with configurable transport (SMTP, Sendmail, SES).

## Files to Create
```
src/Mail/Mailer.php
src/Mail/MailerFactory.php
```

## Files to Modify
```
src/Controllers/SubmitController.php  (call mailer after storing lead)
```

## Steps

1. **`MailerFactory.php`** — builds a Symfony `MailerInterface` from config:
   - `smtp`: create `Smtp\EsmtpTransport` from `mail.smtp.*` config values.
   - `sendmail`: create `Sendmail\SendmailTransport`.
   - `ses`: create `Smtp\EsmtpTransport` pointing to SES SMTP endpoint (uses same smtp config structure).
   - Returns `null` if `admin.email` is not configured (skip email entirely).

2. **`Mailer.php`** — wraps the sending logic:
   - `sendLeadNotification(array $leadData, array $config): void`
   - Builds a Symfony `Email`:
     - From: `mail.from.address` / `mail.from.name`.
     - To: `admin.email`.
     - Subject: `"New lead from {name or email}"` — use the first available identifier.
     - Body (HTML): clean table layout listing each submitted field with its label and value.
   - Catches transport exceptions and fails silently (the lead is already stored — don't lose it over a mail error).

3. **Integrate into `SubmitController`** (deferred sending):
   - Email is sent **after** the HTTP response is delivered to the client for fast response times.
   - `SubmitController` uses the `DeferredTaskRunner` (created in Task 06) to register the email as a deferred task:
     ```php
     // In SubmitController, after storing the lead:
     $this->deferred->defer(function () use ($mailer, $leadData, $config) {
         $mailer->sendLeadNotification($leadData, $config);
     });
     ```
   - The `DeferredTaskRunner` handles all the connection-closing mechanics (output buffer flushing, `Connection: close` + `Content-Length` headers, `fastcgi_finish_request()` / `litespeed_finish_request()` / `ignore_user_abort(true)`) so the email code doesn't need to know about any of that.
   - Works reliably on PHP-FPM, Apache mod_php, LiteSpeed, and nginx.
   - Only register the deferred task if `admin.email` is configured and `mail` config exists.

## Acceptance Criteria
- With valid SMTP config and `admin.email`: email is sent after the response is delivered.
- The client receives the success response before the email is sent (verify with timing on PHP-FPM, mod_php, and LiteSpeed if possible).
- Without `admin.email`: no email attempt, no errors, no deferred task registered.
- Mail transport failure doesn't cause errors visible to the client (lead is already stored and response already sent).
- Email body lists all submitted fields in a readable format.
