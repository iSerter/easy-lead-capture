# Task 18 — Lead Management (Status & Notes)

## Goal
Transform the system from a read-only capture tool into a lightweight mini-CRM by adding lead statuses and internal admin notes. Admins should be able to update these properties directly from the dashboard via AJAX.

## Background
Currently, the admin dashboard allows viewing and exporting leads. Adding `status` and `notes` fields empowers admins to track their workflow (e.g., marking a lead as 'contacted' or leaving a note about a phone call). To maintain the "easy" philosophy, these updates must happen inline without full page reloads, and database migrations must handle existing data seamlessly.

## Files to Modify
```
src/Database/Migrations.php          (add columns safely)
src/Controllers/SubmitController.php (initialize default status)
src/Controllers/AdminController.php  (add update endpoints, modify index and export)
src/Views/admin/dashboard.php        (UI for statuses and notes, AJAX logic)
src/App.php                          (register new admin routes)
```

## Steps

### 1. Database Schema Update (`Migrations.php`)
- Update the `run()` method to safely add `status` and `notes` columns to the `leads` table if they do not already exist. SQLite requires `ALTER TABLE ... ADD COLUMN ...`.
- `status`: `TEXT DEFAULT 'new'`
- `notes`: `TEXT DEFAULT NULL`
- *Implementation Note:* You may need to catch exceptions or check if the column exists via `PRAGMA table_info(leads)` before altering, as SQLite's `ALTER TABLE` can be restrictive.

### 2. Initialization on Submit (`SubmitController.php`)
- While the DB default handles new rows, ensure any explicit inserts in `SubmitController` account for the new structure if necessary, though relying on the SQLite `DEFAULT 'new'` for the `status` column is the cleanest approach.

### 3. Admin Controller Enhancements (`AdminController.php`)
- **Modify `index()`**:
  - Add support for a `status` query parameter to filter leads (e.g., `?status=new`).
  - Pass the current status filter and a list of available statuses to the view.
- **Modify `export()`**:
  - Include the `status` filter logic.
  - Append `Status` and `Notes` columns to the CSV output.
- **Add `updateStatus(Request, Response)`**:
  - Endpoint: `POST /admin/leads/{id}/status`
  - Validates input (status must be one of: `new`, `in_progress`, `contacted`, `qualified`, `junk`).
  - Updates the database.
  - Returns JSON `{"success": true}`.
- **Add `updateNotes(Request, Response)`**:
  - Endpoint: `POST /admin/leads/{id}/notes`
  - Sanitizes the notes input (`htmlspecialchars`).
  - Updates the database.
  - Returns JSON `{"success": true}`.

### 4. Routing Updates (`App.php`)
- Inside the `/admin` group, register the two new POST routes:
  - `$group->post('/leads/{id}/status', [$adminController, 'updateStatus']);`
  - `$group->post('/leads/{id}/notes', [$adminController, 'updateNotes']);`

### 5. Dashboard UI Updates (`dashboard.php`)
- **Status Filter**: Add a dropdown next to the Date filters to filter by status.
- **Table Columns**: Add columns for "Status" and "Actions/Notes".
- **Status UI**: 
  - Render the current status as a styled badge (e.g., Gray for `new`, Blue for `in_progress`, Green for `qualified`, Red for `junk`).
  - Make the badge a dropdown (or clickable to reveal a dropdown) allowing the admin to change the status inline.
- **Notes UI**: 
  - Add an "Edit Note" button in the Actions column.
  - Clicking opens a simple modal or inline textarea containing the current note.
- **AJAX Logic**: 
  - Write Vanilla JS to handle the `fetch()` calls to `/admin/leads/{id}/status` and `/admin/leads/{id}/notes` when the admin makes changes.
  - Show a small, unobtrusive toast or briefly change the badge state to indicate saving, avoiding disruptive page reloads.

## Acceptance Criteria
- Database successfully migrates existing installations without data loss.
- Admins can change the status of a lead from the dashboard without a page reload.
- Admins can add and edit notes for a lead from the dashboard without a page reload.
- The dashboard can be filtered by the new `status` field.
- Exported CSV files include the `status` and `notes` data.
- UI elements use existing Tailwind design language and look polished.
- Unauthorized POST requests to the new endpoints are blocked by existing middleware.