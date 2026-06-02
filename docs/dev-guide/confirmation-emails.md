# Confirmation Emails Guide

`easy-lead-capture` can send a thank-you email to the person who submits your lead capture form.

Confirmation emails are separate from admin email notifications. Admin notifications go to `admin.email`; confirmation emails go to the submitted lead email address.

## Setup

Enable `confirmation_email` and configure your mail transport.

```php
$app = new App([
    'admin' => [
        'password' => '...',
    ],
    'mail' => [
        'mailer' => 'smtp',
        'host' => 'smtp.example.com',
        'port' => 587,
        'username' => 'your-user',
        'password' => 'your-pass',
        'encryption' => 'tls',
        'from_address' => 'hello@yoursite.com',
        'from_name' => 'Your Product',
    ],
    'confirmation_email' => [
        'enabled' => true,
        'subject' => 'Thank you for joining our waitlist!',
        'body_template' => null,
        'from_address' => null,
        'from_name' => null,
    ],
]);
```

The form must include at least one field with `field_type => 'email'`. The confirmation email is sent to the submitted value for that field.

```php
'form' => [
    'fields' => [
        'name' => [
            'label' => 'Name',
            'required' => true,
            'field_type' => 'text',
        ],
        'email' => [
            'label' => 'Email',
            'required' => true,
            'field_type' => 'email',
        ],
    ],
],
```

## Configuration

| Key | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `enabled` | `boolean` | `false` | Turns confirmation emails on or off. |
| `subject` | `string` | `Thank you for joining our waitlist!` | Email subject. Required when enabled. |
| `body_template` | `?string` | `null` | Custom HTML body. `null` uses the built-in template. |
| `from_address` | `?string` | `null` | Optional sender address override. Falls back to `mail.from_address`. |
| `from_name` | `?string` | `null` | Optional sender name override. Falls back to `mail.from_name`. |

## Default Template

When `body_template` is `null`, the built-in template at `src/Views/emails/confirmation.php` is used.

The default template:
- Uses the submitted name when available.
- Includes a light summary of submitted fields.
- Uses `form.colors.primary` as the accent color.
- Uses `confirmation_email.from_name` or `mail.from_name` as the brand/team name.
- Uses inline styles for email client compatibility.

## Custom Body Template

Set `body_template` to a string to fully control the HTML body.

```php
'confirmation_email' => [
    'enabled' => true,
    'subject' => 'You are on the list',
    'body_template' => '
        <h1>Thanks, {name}</h1>
        <p>We received your signup for {email}.</p>
        <h2>Your submission</h2>
        {fields}
    ',
],
```

Supported placeholders:
- `{name}`: The submitted `name` field, or the first field whose ID or label looks name-like.
- `{email}`: The submitted email field value.
- `{fields}`: An HTML table containing all configured submitted fields.

Placeholder values are HTML-escaped before insertion. `{fields}` is rendered as HTML.

## Delivery Behavior

Confirmation emails are deferred. The lead is saved and the browser receives the success response before email sending runs.

Sending is skipped silently when:
- `confirmation_email.enabled` is `false`.
- The form has no field with `field_type => 'email'`.
- The lead did not submit a valid email value.
- The mail transport cannot be created.

Mail transport failures are caught silently because the lead has already been stored.

## Validation

Config validation runs at boot:
- `enabled` must be a boolean.
- `subject` must be a non-empty string when enabled.
- `from_address`, when set, must be a valid email address.
- `from_name`, when set, must be a string.
- `body_template`, when set, must be a string.

If confirmation email is enabled but no email field exists in the form config, the app logs a warning and skips sending confirmation emails.

## Troubleshooting

If the user does not receive the email:
1. Confirm `confirmation_email.enabled` is `true`.
2. Confirm the form field uses `field_type => 'email'`.
3. Confirm the submitted email value passes validation.
4. Confirm `mail` settings are valid for your provider.
5. Check server mail logs or your provider dashboard.

For admin notification setup and supported mailers, see [Email Notifications Guide](email_notifications.md).
