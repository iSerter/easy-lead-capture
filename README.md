# Easy Lead Capture

A lightweight, embeddable lead capture system for PHP. Beautiful forms, SQLite storage, email notifications, optional reCAPTCHA v3 — all in a single Composer package with zero build steps for consumers.

## Features

- **Embeddable lead forms** — iframe or JS snippet, works on any website
- **Configurable fields** — text, email, phone, URL, textarea, multi-select checkboxes
- **Pre-compiled Tailwind CSS** — beautiful out of the box (~15KB), customizable colors
- **SQLite storage** — zero database setup, WAL mode enabled
- **Email notifications** — SMTP, Sendmail, or SES (sent after response for fast submissions)
- **API ping** — POST lead data to your own API/webhook on each submission (deferred)
- **reCAPTCHA v3** — optional, configurable score threshold
- **Admin panel** — view leads, filter by date, export CSV
- **Lightweight** — <20KB iframe payload, no frontend framework

## Quick Start

### 1. Install

```bash
composer require iserter/easy-lead-capture
```

### 2. Configure

Create `public/lead-capture/index.php`:

```php
<?php
require __DIR__ . '/../../vendor/autoload.php';

$app = new Iserter\EasyLeadCapture\App([
    'base_path' => '/lead-capture',
    'admin' => [
        'password' => password_hash('your-password', PASSWORD_DEFAULT),
        'email' => 'you@example.com',
        'linkedin_url' => 'https://www.linkedin.com/in/yourprofile',
        'x_url' => 'https://x.com/yourhandle',
    ],
    'form' => [
        'headline' => 'Join the Waitlist',
        'intro_text' => 'Be the first to know when we launch.',
        'fields' => [
            'name'  => ['label' => 'Name', 'required' => true],
            'email' => ['label' => 'E-mail', 'required' => true],
            'phone' => ['label' => 'Phone', 'required' => false],
        ],
    ],
    'on_submit' => [
        'success_headline' => 'Thank you!',
        'success_message' => 'We will get back to you soon.',
        'social_links' => [
            'enabled' => true,
            'message' => 'Follow us for updates.',
        ],
        'ping_api' => [
            'enabled' => false,
            'api_endpoint' => 'https://example.com/api/leads',
            'api_key' => '',
        ],
    ],
    'mail' => [
        'from' => ['address' => 'hello@example.com', 'name' => 'My App'],
        'mailer' => 'smtp',
        'smtp' => [
            'host' => '127.0.0.1',
            'port' => 587,
            'username' => '',
            'password' => '',
        ],
    ],
]);

$app->run();
```

Add the provided `.htaccess` to the same directory for Apache, or configure nginx to route requests to `index.php`.

### 3. Embed

**iframe (recommended):**
```html
<iframe src="https://yoursite.com/lead-capture/form"
        style="border:none; width:100%; height:500px;"
        loading="lazy">
</iframe>
```

**JavaScript loader:**
```html
<script src="https://yoursite.com/lead-capture/embed.js"></script>
<div id="lead-form"></div>
<script>EasyLeadCapture.render('#lead-form');</script>
```

### 4. Admin Panel

Visit `/lead-capture/admin` to view captured leads and export as CSV.

## Requirements

- PHP 8.1+
- SQLite3 extension
- Composer

## License

MIT
