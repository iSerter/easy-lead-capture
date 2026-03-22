# Task 07 — Success / CTA View

## Goal
Build the post-submission success screen with social media CTAs. Displayed inline (replaces the form) after a successful AJAX submission.

## Files to Create
```
src/Views/success.php
```

## Steps

1. **`success.php`** — Success CTA template:
   - Rendered as a hidden `<div>` within the form page (same HTML document as the form).
   - After successful submission, JS hides the form and shows this div with a fade/slide-up transition.
   - Content:
     - Checkmark icon (inline SVG, green circle with white check).
     - Headline from `on_submit.success_headline` (large, bold).
     - Message from `on_submit.success_message` (body text).
     - Social links section (if any social URLs are configured in `admin`):
       - Message from `on_submit.social_links.message` (small text above icons).
       - LinkedIn icon/button linking to `admin.linkedin_url` (if set).
       - X/Twitter icon/button linking to `admin.x_url` (if set).
       - Icons: inline SVGs, styled as rounded pill buttons with labels.
       - Animated entrance: staggered fade-in for each social link.

2. **Transition logic** (in the form page JS):
   - On success response, fade out the form container, then fade in the success container.
   - Post updated height to parent via `postMessage` for iframe resize.

3. **When social links are not configured**:
   - If neither `linkedin_url` nor `x_url` is set, the social links section is hidden entirely.
   - If `on_submit.social_links.enabled` is `false`, hide social links even if URLs exist.

## Acceptance Criteria
- After successful submission, form transitions smoothly to the success view.
- Social links appear only when configured and enabled.
- Looks polished — consistent with the form's design language.
- iframe height adjusts after transition.
