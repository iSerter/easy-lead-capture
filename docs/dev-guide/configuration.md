# Configuration Reference

This document provides a full reference of all available configuration options for the `App` constructor.

## Top-Level Options

| Key | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `base_path` | `string` | `''` | The URL prefix for the application (e.g., `/lead-capture`). |
| `data_dir` | `string` | `null` | Shorthand for setting the SQLite database path. |
| `database.path` | `string` | `data/leads.db` | Full path to the SQLite database file. |

## Form Configuration (`form`)

| Key | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `headline` | `string` | `''` | The main title of the form. |
| `intro_text` | `string` | `''` | Subtext displayed below the headline. |
| `fields` | `array` | *(see below)* | Definition of form fields. |
| `colors` | `array` | *(see below)* | UI color customization. |

### Fields (`form.fields`)
Each field is defined by an ID and an array of options:
```php
'fields' => [
    'email' => [
        'label' => 'Your Email',
        'required' => true,
        'field_type' => 'email', // text, email, tel, url, textarea, multi_select
        'placeholder' => 'jane@example.com',
    ]
]
```

### Colors (`form.colors`)
- `primary`: Used for buttons and highlights (default: `#4F46E5`).
- `background`: Form background (default: `#FFFFFF`).
- `text`: Main text color (default: `#111827`).
- `error`: Error message color (default: `#DC2626`).

## Submission Actions (`on_submit`)

| Key | Type | Description |
| :--- | :--- | :--- |
| `success_headline` | `string` | Headline on the success page. |
| `success_message` | `string` | Message body on the success page. |
| `social_links` | `array` | Optional links to social profiles. |
| `ping_api` | `array` | Webhook configuration (see API Ping guide). |

## Security & Protection

### Captcha (`captcha`)
- `enabled`: `boolean`
- `recaptcha_site_key`: `string`
- `recaptcha_secret_key`: `string`
- `threshold`: `float` (0.0 - 1.0)

### Source Tracking (`source_tracking`)
- `enabled`: `boolean`
- `params`: `array` (list of query params to capture)

## Admin & Mail

### Admin (`admin`)
- `password`: `string` (Required)
- `email`: `string` (Recipient for notifications)

### Mail (`mail`)
Used when `admin.email` is set or `confirmation_email.enabled` is `true`.
- `mailer`: `smtp`, `sendmail`, or `ses`.
- `from_address`: `string`
- `from_name`: `string`
- `host`, `port`, `username`, `password`, `encryption`: SMTP/SES specific settings.

### Confirmation Email (`confirmation_email`)
- `enabled`: `boolean` (default: `false`)
- `subject`: `string`
- `body_template`: `string|null`
- `from_address`: `string|null`
- `from_name`: `string|null`

See [Confirmation Emails Guide](confirmation-emails.md) for setup, placeholders, and delivery behavior.
