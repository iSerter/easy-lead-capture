# Task 20 — IP Geolocation (Admin Panel)

## Goal
Add IP geolocation to leads by allowing admins to click a button on the dashboard to look up the geographic location of a lead's IP address. Results are stored in dedicated DB columns and displayed inline. Support multiple geo providers with a clean interface.

## Background
The `leads` table already records `ip_address` on form submission. This task adds a provider-agnostic geolocation layer so admins can resolve an IP to a country code, region, and city on demand. Two providers ship: **IP Sage** (local go service at `http://127.0.0.1:8040`) and **IpApiCo** (`https://ipapi.co`). The active provider and IP Sage endpoint are configurable via environment variables read in `public/index.php`.

## Architecture
```
src/
├── IpGeo/
│   ├── IpGeoProvider.php          # Interface
│   ├── IpSageProvider.php          # IP Sage (local go service)
│   └── IpApiCoProvider.php         # ipapi.co
├── Database/Migrations.php         # Add ip_country_code, ip_region, ip_city
├── Controllers/AdminController.php # New lookupGeo endpoint
├── Views/admin/dashboard.php       # "Locate" button + geo display per lead
└── App.php                         # Register route, wire provider
```

## API Reference

### IP Sage (local)
- **Endpoint:** `GET http://127.0.0.1:8040/lookup/{ip}`
- **Response:**
  ```json
  {
    "success": true,
    "data": {
      "ip": "8.8.8.8",
      "country_code": "US",
      "region": "California",
      "city": "Mountain View"
    }
  }
  ```
- Configurable via `IPSAGE_ENDPOINT` env var (default `http://127.0.0.1:8040`).

### IpApiCo
- **Endpoint:** `GET https://ipapi.co/{ip}/json/`
- **Response:**
  ```json
  {
    "ip": "8.8.8.8",
    "country_code": "US",
    "region": "California",
    "city": "Mountain View"
  }
  ```
- Rate-limited to 1000 requests/day on the free tier. The free tier is enough for manual admin lookups.
- API key can be configured via `IPAPI_CO_API_KEY` env var for higher limits.

## Steps

### 1. Provider Interface (`src/IpGeo/IpGeoProvider.php`)
- Namespace: `Iserter\EasyLeadCapture\IpGeo`
- Define interface with a single method:
  ```php
  public function lookup(string $ip): ?array;
  ```
- Return value must be an associative array with keys `country_code`, `region`, `city`, or `null` on failure.

### 2. IP Sage Provider (`src/IpGeo/IpSageProvider.php`)
- Implements `IpGeoProvider`.
- Constructor accepts `string $endpoint` (default `'http://127.0.0.1:8040'`).
- `lookup()`: Sends `GET {endpoint}/lookup/{ip}` via `file_get_contents` or `curl`.
- Parses JSON response. On `success: true && data`, maps `data.country_code`, `data.region`, `data.city` to the return array.
- Returns `null` on connection error, non-success response, or parse failure.
- Use a short timeout (e.g. 3s via stream context or curl).

### 3. IpApiCo Provider (`src/IpGeo/IpApiCoProvider.php`)
- Implements `IpGeoProvider`.
- Constructor accepts `?string $apiKey` (optional, null by default).
- `lookup()`: Sends `GET https://ipapi.co/{ip}/json/` with `User-Agent` header.
  - If `$apiKey` is set, append `?key={apiKey}` to the URL.
- Parses JSON. Maps `country_code`, `region`, `city` to the return array.
- Returns `null` on error or if the response contains `"error": true`.

### 4. Database Migration (`src/Database/Migrations.php`)
- Using the existing `PRAGMA table_info(leads)` pattern (lines 27-35), add three columns after the existing status/notes checks:
  - `ip_country_code TEXT DEFAULT NULL`
  - `ip_region TEXT DEFAULT NULL`
  - `ip_city TEXT DEFAULT NULL`

### 5. Config & Env Wiring
- This package does not ship a .env loader. The provider config is passed through the existing `$config` array.
- Add default config in `ConfigValidator.php`:
  ```php
  'ip_geo' => [
      'provider' => 'ipsage',             // 'ipsage' or 'ipapico'
      'ipsage_endpoint' => 'http://127.0.0.1:8040',
      'ipapico_api_key' => null,
  ],
  ```
- In `public/index.php`, populate from environment:
  ```php
  'ip_geo' => [
      'provider' => getenv('IP_GEO_PROVIDER') ?: 'ipsage',
      'ipsage_endpoint' => getenv('IPSAGE_ENDPOINT') ?: 'http://127.0.0.1:8040',
      'ipapico_api_key' => getenv('IPAPI_CO_API_KEY') ?: null,
  ],
  ```
- The admin should be able to choose the provider at runtime too (via URL param), but the default comes from config.

### 6. Admin Route (`src/App.php`)
- Inside the `/admin` group, register a new POST route:
  ```php
  $group->post('/leads/{id}/geo', [$adminController, 'lookupGeo']);
  ```
- The `AdminController` constructor will need access to the configured `IpGeoProvider`. Pass it as a third constructor argument or via a setter.
  - In `App.php`, instantiate the correct provider based on config before building `AdminController`:
    ```php
    $ipGeoConfig = $config['ip_geo'];
    if ($ipGeoConfig['provider'] === 'ipapico') {
        $ipGeoProvider = new IpGeo\IpApiCoProvider($ipGeoConfig['ipapico_api_key']);
    } else {
        $ipGeoProvider = new IpGeo\IpSageProvider($ipGeoConfig['ipsage_endpoint']);
    }
    $adminController = new Controllers\AdminController($config, $db, $ipGeoProvider);
    ```

### 7. Admin Controller — `lookupGeo` Method (`src/Controllers/AdminController.php`)
- **Signature:** `lookupGeo(Request $request, Response $response, array $args): Response`
- Fetches the lead by `$args['id']` from the database.
- If lead not found, return `404` JSON.
- Gets `ip_address` from the lead row. If empty/null, return `400` JSON with `"error": "No IP address"`.
- Calls `$this->ipGeoProvider->lookup($ipAddress)`.
- If the provider returns `null`, return `502` JSON with `"error": "Geolocation failed"`.
- Otherwise, update the lead row:
  ```sql
  UPDATE leads SET ip_country_code = :country_code, ip_region = :region, ip_city = :city WHERE id = :id
  ```
- Return JSON:
  ```json
  { "success": true, "country_code": "US", "region": "California", "city": "Mountain View" }
  ```
- The provider parameter can be overridden via a query param `?provider=ipapico` to switch providers at runtime (useful for testing or fallback).

### 8. Dashboard View — "Locate" Button + Geo Display (`src/Views/admin/dashboard.php`)
- **New column in the table header** (after the Notes column): Add a `Geo` column.
- **Per-row cell**: Show a "Locate" button if `ip_address` is present and geo columns are empty. If geo data already exists, show the result (e.g. `US — California, Mountain View`) with a small refresh button.
- **Button styling**: Follow the existing secondary button pattern:
  ```html
  <button onclick="locateGeo(<?= $lead['id'] ?>, this)" 
          class="px-2 py-1 bg-white border border-gray-300 text-gray-700 rounded text-xs font-medium hover:bg-gray-50 transition-colors">
      Locate
  </button>
  ```
- **Geo display** (when data exists):
  ```html
  <span class="text-xs text-gray-600">
      <?= htmlspecialchars($lead['ip_country_code']) ?>
      <?= $lead['ip_region'] ? '— ' . htmlspecialchars($lead['ip_region']) : '' ?>
      <?= $lead['ip_city'] ? ', ' . htmlspecialchars($lead['ip_city']) : '' ?>
  </span>
  <button onclick="locateGeo(<?= $lead['id'] ?>, this)" class="ml-1 text-gray-400 hover:text-gray-600" title="Refresh">
      <svg class="w-3 h-3 inline" ...>...</svg>
  </button>
  ```
- **AJAX function** `locateGeo(id, btn)` in the existing `<script>` block (alongside `updateStatus` and `editNotes`):
  ```javascript
  async function locateGeo(id, btn) {
      btn.disabled = true;
      btn.textContent = '...';
      try {
          const response = await fetch('<?= $base_path ?>/admin/leads/' + id + '/geo', {
              method: 'POST',
          });
          const result = await response.json();
          if (result.success) {
              const parts = [result.country_code];
              if (result.region) parts.push('— ' + result.region);
              if (result.city) parts.push(', ' + result.city);
              btn.outerHTML = '<span class="text-xs text-gray-600">' + parts.join(' ') + '</span>';
              showToast('Location resolved');
          } else {
              alert(result.error || 'Lookup failed');
              btn.disabled = false;
              btn.textContent = 'Locate';
          }
      } catch (err) {
          console.error(err);
          btn.disabled = false;
          btn.textContent = 'Locate';
      }
  }
  ```

### 9. CSV Export Update (`src/Controllers/AdminController.php`)
- In the `export()` method, add three columns to the CSV header: `"Country Code"`, `"Region"`, `"City"`.
- For each exported row, append `$row['ip_country_code']`, `$row['ip_region']`, `$row['ip_city']`.

### 10. CSS
- Run `npm run build:css` after editing the dashboard template. Include the updated `assets/styles.css`.

## Files to Modify
```
src/IpGeo/IpGeoProvider.php          (new — interface)
src/IpGeo/IpSageProvider.php         (new — IP Sage implementation)
src/IpGeo/IpApiCoProvider.php        (new — IpApiCo implementation)
src/Database/Migrations.php          (add geo columns)
src/Config/ConfigValidator.php       (add ip_geo defaults)
src/Controllers/AdminController.php  (add lookupGeo, pass provider, update export)
src/App.php                          (register route, wire provider)
src/Views/admin/dashboard.php        ("Locate" button, geo display, AJAX)
assets/styles.css                    (rebuilt after view change)
public/index.php                     (example .env wiring)
```

## Acceptance Criteria
- Existing leads table migrates safely (no data loss, PRAGMA check pattern).
- Admin dashboard shows a "Locate" button for leads with an IP address and no geo data yet.
- Clicking "Locate" resolves the IP via the configured provider and updates the row in-place via AJAX.
- Geo data (country_code, region, city) is displayed inline after resolution.
- Admin can switch providers at runtime via `?provider=ipapico` query param on the lookup endpoint.
- The IP Sage default endpoint (`http://127.0.0.1:8040`) is configurable via `IPSAGE_ENDPOINT` env var.
- The IpApiCo API key is configurable via `IPAPI_CO_API_KEY` env var.
- CSV exports include Country Code, Region, and City columns.
- Error states are handled gracefully: no IP, provider failure, network error.
- All existing tests continue to pass.
