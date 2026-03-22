# Task 14 — CSV Export

## Goal
Allow admins to download captured leads as a CSV file, with optional date filtering.

## Files to Modify
```
src/Controllers/AdminController.php  (add export method)
```

## Steps

1. **`AdminController@export`** — `GET /admin/export`:
   - Query params: `from` (date, optional), `to` (date, optional).
   - Query `leads` table with same date filtering logic as the dashboard.
   - Order by `created_at ASC` (chronological for export).

2. **Build CSV**:
   - Headers row: configured field labels (from `form.fields` config) + `"Date"`.
   - Data rows: for each lead, extract values from JSON `data` column in field config order.
     - Multi-select values joined with `; ` (semicolon + space).
     - Missing/null fields output as empty string.
     - `created_at` formatted as `Y-m-d H:i:s`.
   - Use `fputcsv()` writing to `php://output`.

3. **Response**:
   - `Content-Type: text/csv; charset=utf-8`
   - `Content-Disposition: attachment; filename="leads-YYYY-MM-DD.csv"`
   - UTF-8 BOM (`\xEF\xBB\xBF`) at the start for Excel compatibility.
   - Stream directly — no buffering the entire file in memory.

## Acceptance Criteria
- Clicking export downloads a `.csv` file.
- CSV opens correctly in Excel with proper column headers.
- Date filters from the dashboard carry through to the export.
- Multi-select values are semicolon-separated in the CSV.
- Large exports stream without memory issues.
