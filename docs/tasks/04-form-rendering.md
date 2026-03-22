# Task 04 — Form Rendering (Frontend)

## Goal
Build `FormController` and the form view template — a beautiful, responsive lead capture form rendered from config, styled with Tailwind CSS.

## Files to Create
```
src/Controllers/FormController.php
src/Views/form.php
src/Views/layouts/base.php
```

## Steps

1. **`base.php`** — Minimal HTML shell:
   - HTML5 doctype, viewport meta, UTF-8.
   - Links the pre-compiled CSS: `<link rel="stylesheet" href="{base_path}/assets/styles.css">`.
   - Injects CSS custom properties from `form.colors` config onto `:root` via an inline `<style>` block.
   - Yields a `$content` variable for the page body.
   - Body has `bg-transparent` so the iframe blends with the host page.

2. **`FormController.php`**:
   - `GET /form` handler.
   - Extracts form config (fields, colors, headline, intro_text, logo_url).
   - Renders `form.php` inside `base.php`.
   - Passes the submit URL (`base_path + /submit`) and CSRF token (Task 10) to the template.

3. **`form.php`** — The lead form template:
   - Optional logo at top (from `form.logo_url`), centered, max-height constrained.
   - Optional headline (`form.headline`) — large, bold.
   - Optional intro text (`form.intro_text`) — smaller, muted color.
   - Iterates `form.fields` config and renders each field:
     - `text`, `email`, `tel`, `url` → `<input>` with appropriate `type` attribute.
     - `textarea` (for `message` field) → `<textarea>`.
     - `multi_select` → group of styled checkboxes with the field label as group heading.
   - Required fields show a red asterisk after the label.
   - Submit button — styled with the `primary` color, full width, rounded.
   - Form submits via `fetch()` (see Task 05 for JS behavior).
   - All output escaped with `htmlspecialchars()`.

4. **Design guidelines**:
   - Card-style container: white bg, rounded-xl, subtle shadow, max-w-md centered.
   - Generous padding and spacing between fields.
   - Inputs: rounded-lg, border with focus ring in primary color, smooth transitions.
   - Mobile-first responsive layout.

5. **After creating/modifying views**: run `npm run build:css` to regenerate `assets/styles.css` with any new Tailwind classes used in the templates.

## Acceptance Criteria
- `GET /form` renders a complete, styled form matching the field config.
- Fields render correctly for all types (text, email, tel, url, textarea, multi_select).
- Colors from config are applied.
- Form looks polished on mobile and desktop.
- CSS is loaded from `assets/styles.css` (no CDN requests to tailwindcss.com).
