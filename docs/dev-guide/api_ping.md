# API Ping Feature

The API Ping feature allows you to send lead data to an external webhook or API endpoint as soon as a form is submitted.

## Configuration

Enable the feature in your `App` configuration:

```php
'on_submit' => [
    'ping_api' => [
        'enabled' => true,
        'api_endpoint' => 'https://your-crm.com/api/webhooks/leads',
        'api_key' => 'your-secret-api-key',
    ],
],
```

## How it Works

1.  **Submission**: A lead submits the form.
2.  **Storage**: The lead is saved to the local SQLite database first.
3.  **Deferred Execution**: To keep the response time fast for the user, the API ping is executed *after* the connection is closed to the client.
4.  **Payload**: The system sends a `POST` request to your `api_endpoint` with a JSON body.

## Payload Structure

The payload includes all submitted form fields plus a `created_at` timestamp and any source tracking data.

```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "555-0123",
  "created_at": "2026-05-30 14:05:22",
  "_source": {
    "utm_source": "google",
    "utm_medium": "cpc"
  }
}
```

## Headers

The request includes the following headers:
- `Content-Type: application/json`
- `X-Api-Key`: The value you provided in the `api_key` configuration.

## Error Handling

API pings fail silently from the user's perspective. If your API is down, the lead remains safely stored in the local SQLite database, but no retry mechanism is currently implemented.
