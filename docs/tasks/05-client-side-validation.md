# Task 05 — Client-Side Validation & Form Submission JS

## Goal
Add inline validation and AJAX form submission logic to the form template using vanilla JavaScript.

## Files to Modify
```
src/Views/form.php  (add inline <script>)
```

## Steps

1. **Validation rules** (triggered on blur + on submit):
   - Required fields: non-empty after trim.
   - Email fields: regex validation for basic format.
   - Phone fields: permissive regex allowing `+`, digits, spaces, dashes, parens.
   - Multi-select with `required`: at least one option checked.

2. **Validation UX**:
   - On blur: validate the field, show/hide error message below it.
   - Error messages: small red text, slide-down animation (`transition-all`).
   - Invalid inputs get a red border ring; valid inputs revert to normal.
   - On submit: validate all fields, focus the first invalid field.

3. **Form submission** (fetch API):
   - Collect all field values into a JSON object.
   - POST to the submit URL (passed from controller as a data attribute).
   - Include CSRF token in the request body.
   - During request: disable button, show a spinner SVG inside the button.
   - On success (`{success: true}`): transition to the success view (Task 07).
   - On error (`{success: false, errors: {...}}`): show server-side errors inline under each field, plus a general error banner above the form.
   - On network error: show a generic "Something went wrong" banner.

4. **Height reporting** (for iframe auto-resize):
   - After form renders and after any validation error shows/hides, post the current `document.body.scrollHeight` to the parent via `window.parent.postMessage({type: 'elc-resize', height: ...}, '*')`.

## Acceptance Criteria
- Submitting an empty required field shows an inline error.
- Invalid email shows a format error.
- Successful submission triggers the success view transition.
- iframe height adjusts when errors appear/disappear.
