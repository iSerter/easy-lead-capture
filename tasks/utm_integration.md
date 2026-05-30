# Task 16 — UTM / Source Tracking

## Goal

Allow developers to pass UTM-style query parameters when embedding the lead capture form (e.g. `utm_source=variant-1`, `utm_medium=promo.sagegrids.com`). Capture those values on form load, persist them with the lead in SQLite, and include them in the deferred `ping_api` webhook payload when enabled.

## Background

Leads are stored in the `leads.data` JSON column (see Task 06). Form submissions only accept fields defined in `form.fields` — extra POST keys are dropped. UTM parameters arrive on the **form page URL**, not from user input, so they need dedicated capture logic separate from configurable form fields.

The embed flow today:

- **iframe:** `<iframe src="https://yoursite.com/lead-capture/form">`
- **JS loader:** `EasyLeadCapture.render('#el')` creates an iframe pointing to `/form`

Both support appending a query string to the form URL. The form page must read those params and carry them through to submission without exposing them as editable fields.

## Developer Usage

### iframe (recommended)

```html
<iframe
  src="https://yoursite.com/lead-capture/form?utm_source=variant-1&utm_medium=promo.sagegrids.com"
  style="border:none; width:100%; height:500px;"
  loading="lazy">
</iframe>
```

### JavaScript loader

Pass the query string via `formUrl`, or use the new `params` option (see Steps):

```html
<script src="https://yoursite.com/lead-capture/embed.js"></script>
<div id="lead-form"></div>
<script>
  EasyLeadCapture.render('#lead-form', {
    params: {
      utm_source: 'variant-1',
      utm_medium: 'promo.sagegrids.com',
    },
  });
</script>
```

Equivalent manual URL:

```js
EasyLeadCapture.render('#lead-form', {
  formUrl: 'https://yoursite.com/lead-capture/form?utm_source=variant-1&utm_medium=promo.sagegrids.com',
});
```

## Design Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Storage | Nested `_source` key inside JSON `data` | Keeps UTM metadata separate from configurable form fields; no schema migration |
| Capture point | `GET /form` reads query params into session | Values come from the embed URL, not user input; session prevents POST-body spoofing |
| Allowed params | Standard UTM set (configurable) | `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content` |
| Config | Optional `source_tracking` block | Defaults to enabled with standard params; allows opt-out |
| DB columns | None | Consistent with existing JSON storage pattern |

### Stored JSON shape

```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "_source": {
    "utm_source": "variant-1",
    "utm_medium": "promo.sagegrids.com"
  }
}
```

If no tracking params are present, omit `_source` entirely (do not store an empty object).

## Files to Create

```
src/Support/SourceTracker.php       # Extract, validate, sanitize UTM params
tests/Unit/SourceTrackerTest.php    # Param validation/sanitization unit tests
tests/Integration/SourceTrackingTest.php  # End-to-end form load + submit flow
```

## Files to Modify

```
src/Config/ConfigValidator.php      # Validate/normalize source_tracking config
src/Controllers/FormController.php    # Capture query params into session on GET /form
src/Controllers/SubmitController.php # Merge session source data into stored lead + ping payload
src/Controllers/AdminController.php # Surface _source in dashboard + CSV export
src/Mail/Mailer.php                 # Include _source rows in notification email
assets/embed.js                     # Optional params → query string helper
README.md                           # Document embedding with UTM query strings
```

## Config

```php
'source_tracking' => [
    'enabled' => true,  // default: true
    'params' => [        // default: standard UTM params listed below
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
    ],
],
```

When `enabled` is `false`, ignore query params entirely (no session write, no `_source` in stored data).

## Steps

### 1. `SourceTracker.php`

Create a small support class with static or instance methods:

- `extractFromQuery(array $queryParams, array $allowedParams): array`
  - Keep only keys in `$allowedParams`.
  - Ignore empty values after `trim()`.
  - Sanitize each value: `trim()` + `htmlspecialchars()` + max length (e.g. 255 chars, truncate or reject — prefer truncate with log-free silent trim).
  - Return associative array of captured params (may be empty).

- `mergeIntoLeadData(array $validatedFormData, array $sourceParams): array`
  - If `$sourceParams` is non-empty, return `$validatedFormData + ['_source' => $sourceParams]`.
  - Otherwise return `$validatedFormData` unchanged.

This class is used by both `FormController` and `SubmitController` to keep logic in one place.

### 2. Config validation (`ConfigValidator.php`)

- Add defaults: `source_tracking.enabled = true`, `source_tracking.params = [utm_* list]`.
- If `enabled` is true, validate `params` is a non-empty array of non-empty strings matching `/^[a-z0-9_]+$/i` (safe query param names).
- Reject duplicate param names after normalization.

### 3. Capture on form load (`FormController.php`)

On `GET /form`:

1. If `source_tracking.enabled` is false, skip.
2. Read `$request->getQueryParams()`.
3. Call `SourceTracker::extractFromQuery(...)`.
4. Store result in `$_SESSION['elc_source']` (overwrite on each form page load so the latest embed URL wins within the session).

No visible form fields needed — the iframe URL is the source of truth.

### 4. Persist on submit (`SubmitController.php`)

After validating form fields and before the DB insert:

1. Read `$_SESSION['elc_source'] ?? []`.
2. Merge into `$validatedData` via `SourceTracker::mergeIntoLeadData()`.
3. `json_encode` the merged array for the `data` column (existing INSERT unchanged).
4. Pass the merged array (not just `$validatedData`) to:
   - `$this->mailer->sendLeadNotification(...)`
   - `$this->apiPinger->ping(..., array_merge($leadData, ['created_at' => ...]))`
5. On successful insert, `unset($_SESSION['elc_source'])`.

**Important:** Do not accept `_source` or individual `utm_*` keys from the POST body. Only trust the session values set at form load. This prevents arbitrary source injection.

### 5. `embed.js` enhancement

Add optional `params` to `EasyLeadCapture.render(selector, options)`:

- If `options.params` is an object, serialize it to a query string and append to the iframe URL (merge with any query string already in `formUrl` using `URLSearchParams`).
- Keep the script under 2KB gzipped — a small helper is fine.

Example internal logic:

```js
function appendParams(url, params) {
  if (!params || !Object.keys(params).length) return url;
  const u = new URL(url, window.location.origin);
  Object.entries(params).forEach(([k, v]) => u.searchParams.set(k, v));
  return u.pathname + u.search; // or full href depending on absolute/relative URL handling
}
```

Handle relative URLs (the common case: `/lead-capture/form`) without breaking existing behavior.

### 6. Admin dashboard (`AdminController.php` + `dashboard.php`)

- After decoding lead JSON, read `$leadData['_source'] ?? []`.
- Add table columns for each configured tracking param (use human-readable headers: `Source`, `Medium`, `Campaign`, etc., derived from param name).
- Display value or `-` when absent.
- Columns appear after form field columns, before `Date`.

### 7. CSV export (`AdminController.php`)

- Append tracking param columns to the CSV header row (same labels as dashboard).
- For each lead, output values from `_source` in param order.
- Tracking columns appear after form field columns, before `Date`.

### 8. Email notification (`Mailer.php`)

After the configured form field rows, if `_source` is present and non-empty:

- Add a separator row or subheading: **Source tracking**
- One table row per captured param (label derived from param name, e.g. `utm_source` → `UTM Source`).

### 9. Tests

**`SourceTrackerTest.php` (unit):**

- Extracts only allowed params; ignores unknown keys.
- Trims and sanitizes values.
- Enforces max length.
- `mergeIntoLeadData` adds `_source` only when non-empty.

**`SourceTrackingTest.php` (integration):**

- `GET /form?utm_source=variant-1&utm_medium=promo.sagegrids.com` then `POST /submit` stores `_source` in DB.
- Submit without prior form load stores no `_source`.
- Unknown params (e.g. `foo=bar`) are ignored.
- With `source_tracking.enabled: false`, params are not stored.
- With `ping_api.enabled: true`, deferred ping payload includes `_source` keys.
- POST body containing `utm_source` is ignored when session has no source data.

### 10. Documentation (`README.md`)

Add a short **Source tracking** subsection under **Embed**:

- Show iframe example with query string.
- Show JS loader `params` option.
- Note that values appear in the admin panel, CSV export, email notifications, and `ping_api` webhook.

## Security Notes

- **No POST trust:** UTM values never come from the submission JSON body.
- **Allowlist only:** Only param names listed in config are captured.
- **Sanitization:** Same `htmlspecialchars` + trim treatment as form fields.
- **Length limit:** Cap values at 255 characters to prevent storage abuse.
- **Session scoped:** Each visitor session gets its own source context from the URL they loaded.

## Acceptance Criteria

- Embedding with `?utm_source=variant-1&utm_medium=promo.sagegrids.com` on the form URL stores those values in `leads.data` under `_source`.
- Leads submitted without tracking params contain no `_source` key.
- Arbitrary query params not in the allowlist are silently ignored.
- With `ping_api.enabled: true`, the webhook JSON body includes `_source` alongside form fields and `created_at`.
- With `ping_api.enabled: false`, behavior is unchanged except for local storage.
- Admin dashboard shows tracking columns; CSV export includes them.
- Email notifications list source tracking values when present.
- `EasyLeadCapture.render('#el', { params: { ... } })` appends params to the iframe URL.
- `source_tracking.enabled: false` disables capture entirely.
- Existing tests pass; new unit and integration tests cover the feature.

## Out of Scope

- Filtering leads by UTM values in the admin panel (future enhancement).
- Persisting the parent page URL or referrer (only explicit query params).
- Custom non-UTM param names beyond the configurable allowlist (supported via `source_tracking.params` config, not hardcoded).
- Database migration to dedicated columns (JSON storage is sufficient for v1).
