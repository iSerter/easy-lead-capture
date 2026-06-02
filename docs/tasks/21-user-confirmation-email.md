# Task 21 — User Confirmation Email

## Goal
Send a configurable thank-you/confirmation email to the user who submits the lead capture form. The email subject, body, and enable/disable toggle should all be configurable.

## Files to Create
```
src/Views/emails/confirmation.php       (plain PHP email template)
```

## Files to Modify
```
src/Mail/Mailer.php                     (add sendConfirmationEmail method)
src/Controllers/SubmitController.php    (call confirmation email after storing lead, deferred)
src/Config/ConfigValidator.php          (validate confirmation email config)
docs/Technical_PRD.md                   (document new config keys)
```

## Configuration

Add a new `confirmation_email` section to the config:

```php
'confirmation_email' => [
    'enabled' => false,                          // default off
    'subject' => 'Thank you for joining our waitlist!',
    'body_template' => null,                     // null = use default template; string = custom HTML body
    'from_address' => null,                      // defaults to mail.from_address if null
    'from_name' => null,                         // defaults to mail.from_name if null
],
```

### Custom Body Template

When `body_template` is set, it is used as the HTML body directly. Support simple placeholders:
- `{name}` — the lead's name field value (falls back to first name-like field)
- `{email}` — the lead's email field value
- `{fields}` — HTML table of all submitted fields (same format as admin notification)

If `body_template` is `null`, use the default template at `src/Views/emails/confirmation.php`.

## Steps

1. **Default email template** (`src/Views/emails/confirmation.php`):
   - Beautiful, sleek, and minimal — think premium SaaS onboarding email, not generic newsletter.
   - Clean typography hierarchy with generous whitespace. Use system font stack (`-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif`) for native feel.
   - Centered single-column layout with a subtle card-like container (soft border-radius, light shadow or border).
   - Warm, conversational greeting using the lead's name if available (e.g., "Hey {name}," or "You're in, {name}!").
   - Thoughtful thank-you copy that makes the user feel valued — not robotic. Something like "Thanks for joining us — we'll be in touch soon."
   - Optional subtle summary of what they submitted, styled as a clean detail list (no heavy table borders).
   - Muted footer with a quiet sign-off (e.g., "— The {brand_name} team") and a small note like "You're receiving this because you signed up at {site_url}."
   - All styles must be inline for email client compatibility. Use table-based layout structure but keep it visually light and modern.
   - Color palette: neutral grays with one accent color pulled from the form's theme if possible, otherwise a tasteful default (e.g., soft indigo or slate).

2. **`Mailer.php`** — add new method:
   - `sendConfirmationEmail(array $leadData, array $config): void`
   - Only sends if `confirmation_email.enabled` is `true`.
   - Resolves `from_address` / `from_name` from confirmation config or falls back to global mail config.
   - Resolves the email body:
     - If `body_template` is set: render it as a string, replace placeholders.
     - If `body_template` is `null`: include and capture output of `src/Views/emails/confirmation.php`, passing `$leadData` and `$config` as variables.
   - To: the lead's email address (find the field with `field_type === 'email'` in config).
   - Subject: from `confirmation_email.subject`.
   - Catches exceptions and fails silently (same as admin notification).

3. **Integrate into `SubmitController`** (deferred sending):
   - Register the confirmation email as a deferred task alongside the admin notification:
     ```php
     if ($this->config['confirmation_email']['enabled']) {
         $this->deferred->defer(fn() => $this->mailer->sendConfirmationEmail($validatedData, $this->config));
     }
     ```
   - Only register if an email field exists in the form and the lead provided an email value.

4. **`ConfigValidator.php`**:
   - Add validation for the `confirmation_email` section.
   - `enabled` must be boolean.
   - `subject` must be a non-empty string if enabled.
   - `from_address` if set must be a valid email.
   - If enabled but no email field exists in the form, log a warning (do not fail — just skip sending).

## Acceptance Criteria
- With `confirmation_email.enabled = true` and a valid email field: the user receives a confirmation email after form submission.
- The email uses the default template when `body_template` is null.
- The email uses the custom body when `body_template` is set, with placeholders replaced.
- With `confirmation_email.enabled = false`: no email is sent, no errors.
- If the form has no email field: no email is attempted, no errors.
- Mail transport failure doesn't cause errors visible to the client (lead is already stored and response already sent).
- Both admin notification and user confirmation emails are sent as deferred tasks (response reaches client before either email is sent).
- All config values are validated at boot time.
