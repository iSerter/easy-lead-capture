# Quick Start Guide

This guide will help you get `easy-lead-capture` up and running in minutes.

## 1. Installation

Install the package via Composer:

```bash
composer require iserter/easy-lead-capture
```

## 2. Basic Setup

Create an entry point for the application (e.g., `public/lead-capture/index.php`):

```php
<?php
require __DIR__ . '/../../vendor/autoload.php';

use Iserter\EasyLeadCapture\App;

$app = new App([
    'base_path' => '/lead-capture',
    'admin' => [
        'password' => 'your-secure-password', // Change this!
        'email' => 'admin@example.com',
    ],
    'data_dir' => __DIR__ . '/../../data', // Ensure this directory is writable
]);

$app->run();
```

## 3. Server Configuration

Ensure your web server routes all requests for the base path to your `index.php`.

### Apache (`.htaccess`)
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

### Nginx
```nginx
location /lead-capture {
    try_files $uri $uri/ /lead-capture/index.php?$query_string;
}
```

## 4. Embedding the Form

### Option A: Iframe (Fastest)
```html
<iframe src="https://yoursite.com/lead-capture/form"
        style="border:none; width:100%; height:500px;"
        loading="lazy">
</iframe>
```

### Option B: JavaScript Loader (Recommended)
This method allows the form to automatically resize to its content.

```html
<script src="https://yoursite.com/lead-capture/embed.js"></script>
<div id="lead-form"></div>
<script>
  EasyLeadCapture.render('#lead-form');
</script>
```

## 5. Accessing the Admin Panel

Visit `/lead-capture/admin` and log in with the password you set in the configuration. From here, you can view leads, filter them, and export them to CSV.
