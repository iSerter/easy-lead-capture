# Task 13 — Admin Dashboard

## Goal
Build the leads dashboard — a paginated table showing all captured leads with date filtering.

## Files to Create
```
src/Views/admin/dashboard.php
```

## Files to Modify
```
src/Controllers/AdminController.php  (add index method)
```

## Steps

1. **`AdminController@index`** — `GET /admin`:
   - Query params: `page` (default 1), `from` (date), `to` (date).
   - Query `leads` table:
     - Filter by `created_at` range if `from`/`to` provided.
     - Order by `created_at DESC`.
     - Paginate: 25 per page, calculate total count for pagination controls.
   - Decode `data` JSON column for each row.
   - Pass leads, pagination info, current filters, and field config to the template.

2. **`dashboard.php`** — dashboard template:
   - Page header: "Leads" title + CSV export button (links to `/admin/export` with current filters).
   - Date filter bar: `from` and `to` date inputs + "Filter" button. Preserves current values.
   - Table:
     - Columns: `#` (row number), one column per configured field (label from config), `Date`.
     - Rows: one per lead, field values extracted from JSON `data` column.
     - Multi-select values displayed comma-separated.
     - Empty fields show a dash.
   - Pagination: Previous/Next links, current page indicator, total count.
   - Styled with Tailwind: clean table with alternating row colors, responsive horizontal scroll on mobile.
   - Logout button in the header/nav area.

## Acceptance Criteria
- Dashboard shows all leads in a paginated table.
- Date filter narrows results correctly.
- Pagination works (next/previous, page numbers).
- Column headers match the configured field labels.
- CSV export button passes current filters to the export URL.
