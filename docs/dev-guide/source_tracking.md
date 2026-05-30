# Source Tracking Developer Guide

The Source Tracking feature allows developers to capture UTM-style query parameters from the form embed URL. These values are automatically persisted with the lead data and surfaced in the admin dashboard, CSV exports, email notifications, and API webhooks.

## How it Works

1.  **Capture**: When the form page (`GET /form`) is loaded, the system checks for allowed query parameters.
2.  **Session Storage**: Validated and sanitized parameters are stored in the user's session (`$_SESSION['elc_source']`).
3.  **Persistence**: Upon form submission, the session data is merged into the lead's JSON `data` column under the `_source` key.
4.  **Cleanup**: The session data is cleared after a successful submission.

## Configuration

Tracking is enabled by default with standard UTM parameters. You can customize this in your `App` configuration:

```php
$app = new Iserter\EasyLeadCapture\App([
    // ... other config
    'source_tracking' => [
        'enabled' => true,
        'params' => [
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_term',
            'utm_content',
            'ref', // You can add custom parameters here
        ],
    ],
]);
```

### Constraints
- **Validation**: Parameter names must be alphanumeric or underscores (`/^[a-z0-9_]+$/i`).
- **Sanitization**: Values are trimmed and HTML-encoded.
- **Length Limit**: Values are truncated to 255 characters to prevent storage abuse.

## Embedding with Tracking

### 1. Iframe Embedding
Simply append the query parameters to the `src` attribute of your iframe:

```html
<iframe 
  src="https://yoursite.com/lead-capture/form?utm_source=spring_sale&utm_medium=banner"
  style="border:none; width:100%; height:500px;"
  loading="lazy">
</iframe>
```

### 2. JavaScript Loader
Use the `params` option in the `render` method. The loader will automatically serialize these into a query string for the iframe.

```html
<script src="https://yoursite.com/lead-capture/embed.js"></script>
<div id="lead-form"></div>
<script>
  EasyLeadCapture.render('#lead-form', {
    params: {
      utm_source: 'newsletter',
      utm_medium: 'email',
      utm_campaign: 'june_updates'
    }
  });
</script>
```

## Data Access

### Database Schema
Leads are stored in the `leads` table. The `data` column contains the JSON payload:

```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "_source": {
    "utm_source": "spring_sale",
    "utm_medium": "banner"
  }
}
```

### API Webhooks (Ping API)
If `on_submit.ping_api` is enabled, the `_source` key is included in the POST payload:

```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "created_at": "2026-05-30 14:00:00",
  "_source": {
    "utm_source": "spring_sale",
    "utm_medium": "banner"
  }
}
```

### Admin Dashboard & CSV
The Admin Dashboard dynamically creates columns for each parameter defined in `source_tracking.params`. Column headers are automatically humanized (e.g., `utm_source` becomes `Source`).
