# Easy Lead Capture —  Coding Instructions

## What This Is

Embeddable PHP lead capture system distributed as a Composer package (`iserter/easy-lead-capture`). Slim 4, SQLite, pre-compiled Tailwind CSS, optional reCAPTCHA v3, Symfony Mailer.

## Key Docs

- `docs/PRD.md` — product requirements
- `docs/Technical_PRD.md` — architecture, schema, routing, security decisions
- `docs/tasks/` — numbered task specs (01–15) with acceptance criteria

## Architecture Quick Ref

- **Entry point:** `src/App.php` — boots Slim 4, registers routes, validates config
- **Namespace:** `Iserter\EasyLeadCapture\`
- **Views:** plain PHP templates in `src/Views/`, no Twig/Blade
- **CSS:** pre-compiled Tailwind in `assets/styles.css` (~15KB). Run `npm run build:css` after changing views. Never use Tailwind CDN.
- **DB:** SQLite with WAL mode. Schema auto-creates on first request. Lead data stored as JSON column.
- **Deferred tasks:** email + API ping run via `DeferredTaskRunner` after response is flushed (Connection: close + Content-Length + output buffer flush + SAPI-specific finish functions). No Redis.
- **Routing:** Slim 4 clean URLs, all relative to configurable `base_path`

## Code Conventions

- PHP 8.1+ — use typed properties, union types, named arguments where clear
- PSR-4 autoloading, PSR-7 request/response, PSR-15 middleware
- PDO prepared statements only — never interpolate SQL
- Escape all output with `htmlspecialchars()` in views
- Frontend: vanilla JS only, no frameworks, no jQuery
- Keep the package lightweight — question any new dependency

## When Changing Views

Always run `npm run build:css` and include the updated `assets/styles.css` in the same commit. The `tailwind.config.js` scans `src/Views/**/*.php`.

## Testing

Use PHP's built-in server for manual testing:
```bash
php -S localhost:8080 -t public
```

## Don't

- Don't add Tailwind CDN — we ship pre-compiled CSS
- Don't add Redis/queue dependencies — use DeferredTaskRunner
- Don't use Twig/Blade — plain PHP templates
- Don't add Node.js as a runtime dependency — it's dev-only for CSS compilation
- Don't store form fields as individual DB columns — use the JSON `data` column
