# Task 02 — Config Validation & Defaults

## Goal
Create `ConfigValidator` that validates the developer-provided config array at boot and merges sensible defaults for all optional keys.

## Files to Create
```
src/Config/ConfigValidator.php
```

## Steps

1. **`ConfigValidator::validate(array $config): array`** — static method:
   - Validates required keys: `admin.password` must exist and be non-empty.
   - Validates `base_path` if provided (must start with `/`).
   - Validates `form.fields` structure if provided: each field must have a `label`, optional `required` (bool), optional `field_type`.
   - Validates `captcha` config if enabled: `recaptcha.site_key` and `recaptcha.secret_key` must exist.
   - Validates `mail` config if `admin.email` is set: `mail.mailer` must be one of `smtp`, `sendmail`, `ses`.
   - Throws `InvalidArgumentException` with clear, human-readable messages on failure (e.g., `"Config error: 'admin.password' is required."`).

2. **Merge defaults** — apply defaults for all optional keys:
   ```php
   'base_path' => '',
   'database' => ['path' => __DIR__ . '/../../data/leads.db'],
   'form.fields' => [
       'name'  => ['label' => 'Name', 'required' => true],
       'email' => ['label' => 'E-mail', 'required' => true],
   ],
   'form.colors' => [
       'primary' => '#4F46E5', 'background' => '#FFFFFF',
       'text' => '#111827', 'error' => '#DC2626',
   ],
   'on_submit.success_headline' => 'Thank you!',
   'on_submit.success_message' => 'We will be in touch soon.',
   'captcha.enabled' => false,
   ```

3. **Integrate into `App.php`** — call `ConfigValidator::validate($config)` in the constructor and store the normalized result.

## Acceptance Criteria
- Missing `admin.password` throws `InvalidArgumentException`.
- Providing only `admin.password` returns a fully populated config with all defaults.
- Invalid field structures are caught and reported clearly.
