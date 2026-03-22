# Task 08 — Embed JS Loader

## Goal
Create the lightweight JavaScript embed loader that developers can use to add the lead form to any page.

## Files to Create
```
assets/embed.js
src/Controllers/EmbedController.php
```

## Steps

1. **`embed.js`** (<2KB gzipped):
   - Exposes a global `EasyLeadCapture` object.
   - `EasyLeadCapture.render(selector, options)`:
     - `selector`: CSS selector for the target container element.
     - `options.mode`: `'inline'` (default) or `'modal'`.
     - `options.formUrl`: overrides the auto-detected form URL (defaults to the script's own origin + `/form`).
   - **Inline mode**: creates an `<iframe>` inside the target element, `width: 100%`, no border.
   - **Modal mode**: creates a fixed-position overlay with a centered iframe, close button (X in top-right), click-outside-to-close, escape-key-to-close.
   - **Auto-resize listener**: listens for `postMessage` events with `{type: 'elc-resize', height: N}` and sets the iframe height accordingly.
   - Script auto-detects its own `src` URL to derive the base URL for the form endpoint.

2. **`EmbedController.php`** — serves static assets:
   - `GET /embed.js`: reads `assets/embed.js`, serves with `Content-Type: application/javascript`.
   - `GET /assets/styles.css`: reads `assets/styles.css`, serves with `Content-Type: text/css`.
   - Both set cache headers: `Cache-Control: public, max-age=86400`.

## Acceptance Criteria
- Adding the `<script>` tag + `EasyLeadCapture.render('#el')` creates a working iframe.
- iframe auto-resizes based on content height.
- Modal mode works: overlay appears, close button works, escape key works.
- Script is under 2KB gzipped.
