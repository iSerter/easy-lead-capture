# Email Notifications Guide

`easy-lead-capture` can automatically notify you via email whenever a new lead is captured.

For thank-you emails sent to the person who submits the form, see [Confirmation Emails Guide](confirmation-emails.md).

## Setup

To enable notifications, you must provide an `admin.email` and configure the `mail` settings.

```php
$app = new App([
    'admin' => [
        'email' => 'admin@example.com', // Notifications sent here
        'password' => '...',
    ],
    'mail' => [
        'mailer' => 'smtp',
        'host' => 'smtp.mailtrap.io',
        'port' => 2525,
        'username' => 'your-user',
        'password' => 'your-pass',
        'from_address' => 'leads@yoursite.com',
        'from_name' => 'Lead Capture Bot',
    ],
]);
```

## Supported Mailers

The system uses **Symfony Mailer** under the hood.

1.  **SMTP**: Most common for external services (SendGrid, Mailgun, Postmark).
2.  **Sendmail**: Uses the local server's `sendmail` binary.
3.  **SES**: Integration for Amazon Simple Email Service (requires host/port/user/pass).

## Notification Content

The email includes:
- A summary table of all captured form fields.
- A "Source Tracking" section if UTM parameters were captured.
- Direct identification in the subject line (uses 'name' or 'email' fields if present).

## Performance

Notifications are **deferred**. They are sent *after* the user sees the success page and the HTTP connection is closed. This ensures that slow mail servers do not delay the user experience.
