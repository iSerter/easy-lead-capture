# Task 15 — API Ping

## Goal
POST lead data to an external API endpoint after form submission, running as a deferred task so it doesn't slow down the response.

## Files to Create
```
src/Support/ApiPinger.php
```

## Files to Modify
```
src/Config/ConfigValidator.php   (validate ping_api config)
src/Controllers/SubmitController.php  (register deferred ping)
```

## Config

```php
'on_submit' => [
    'ping_api' => [
        'enabled' => true,
        'api_endpoint' => 'https://example.com/api/leads',
        'api_key' => 'sk-...',
    ],
],
```

## Steps

1. **`ApiPinger.php`**:
   - `ping(string $endpoint, string $apiKey, array $leadData): void`
   - POST request via `curl`:
     - URL: `api_endpoint`
     - Body: JSON-encoded lead data (all submitted field values + `created_at`)
     - Headers: `Content-Type: application/json`, `Authorization: Bearer {api_key}`
     - Timeout: 10 seconds (`CURLOPT_TIMEOUT`)
     - Verify SSL: enabled (`CURLOPT_SSL_VERIFYPEER`)
   - Throws on curl error but caller (DeferredTaskRunner) catches it silently.

2. **Config validation** (in `ConfigValidator`):
   - If `on_submit.ping_api.enabled` is true: require `api_endpoint` (valid URL) and `api_key` (non-empty string).
   - If not set or `enabled` is false: skip validation, set `enabled` default to `false`.

3. **Integrate into `SubmitController`**:
   - After storing the lead, if `on_submit.ping_api.enabled` is true:
     ```php
     $this->deferred->defer(fn() => $apiPinger->ping(
         $config['on_submit']['ping_api']['api_endpoint'],
         $config['on_submit']['ping_api']['api_key'],
         $leadData
     ));
     ```
   - Registered alongside the email deferred task — both run after response is flushed.

## Acceptance Criteria
- With `ping_api.enabled: true`: lead data is POSTed to the endpoint after response.
- Request includes `Authorization: Bearer` header and JSON body.
- API failure doesn't affect the form submission response or email sending.
- With `ping_api.enabled: false` or not configured: no request is made.
- Invalid `api_endpoint` (not a URL) is caught at config validation time.
