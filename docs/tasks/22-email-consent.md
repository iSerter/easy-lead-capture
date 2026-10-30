# Task 22 — Email Consent (GDPR)

## Goal
Collect and record consent for promotional email so the system complies with GDPR, PECR, ePrivacy, CASL and similar laws. Let admins record withdrawals.

## Background
The form had no consent controls. Promotional email needs explicit, freely given, recorded and withdrawable consent. Transactional email (the confirmation email) needs only a transparent notice, so it must **not** be gated on marketing consent.

## Files to Create
```
docs/dev-guide/consent.md
```

## Files to Modify
```
src/Config/ConfigValidator.php        (consent defaults + validation)
src/Controllers/FormController.php    (pass consent config to view)
src/Views/form.php                    (checkboxes, privacy link, JS validation)
src/Controllers/SubmitController.php  (terms validation, _consent record)
src/Controllers/AdminController.php   (consent filter, CSV columns, withdrawConsent)
src/Views/admin/dashboard.php         (Marketing column, filter, withdraw toggle)
src/Mail/Mailer.php                   (consent table in admin notification)
src/App.php                           (POST /admin/leads/{id}/consent/withdraw)
assets/styles.css                     (rebuilt)
```

## Configuration
```php
'consent' => [
    'marketing' => ['enabled' => true, 'label' => '...'],
    'terms' => ['enabled' => false, 'label' => '...', 'error_message' => '...'],
    'privacy_policy_url' => null,
],
```

## Acceptance Criteria
- [ ] The marketing checkbox is shown by default. It is unticked and optional, and never blocks submission.
- [ ] When `terms.enabled` is true, a submission without `_consent_terms` returns 422 with `errors._consent_terms`, and nothing is stored.
- [ ] Each lead stores `data._consent` containing `marketing`, `marketing_text` (from server config), `marketing_withdrawn_at`, `captured_at`, plus `terms`/`terms_text`/`privacy_policy_url` when applicable.
- [ ] Only `true`, `1`, `"1"`, `"on"` and `"true"` count as ticked.
- [ ] No `_consent` record is written when every consent type is disabled.
- [ ] The admin dashboard shows Opted in / No / Withdrawn / — and filters by yes / no / unknown.
- [ ] Clicking Opted in records `marketing_withdrawn_at`. Withdrawing a lead without active consent returns 400, and a missing lead returns 404.
- [ ] The CSV export includes the Marketing Consent, Consent Text, Consent Date and Consent Withdrawn At columns.
- [ ] The admin notification email includes a Consent table, and the API ping payload includes `_consent`.
- [ ] Leads created before this feature show as Unknown, never as No or Yes.
