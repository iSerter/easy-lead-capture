# Admin Dashboard Guide

The Admin Dashboard is a built-in interface for managing your captured leads.

## Accessing the Dashboard

By default, the dashboard is located at `{base_path}/admin`.

1.  **Login**: Enter the password defined in your `admin.password` configuration.
2.  **Session**: Once logged in, a secure cookie (`elc_session`) keeps you authenticated for 24 hours.

## Key Features

### Lead List
- **Paginated View**: Leads are displayed 25 per page, sorted by newest first.
- **Dynamic Columns**: The table automatically adapts to display the fields you have defined in your form configuration.
- **Source Tracking**: If enabled, UTM parameters like Source, Medium, and Campaign are shown in dedicated columns.

### Filtering
- **Date Range**: Filter leads by their submission date using the "From" and "To" date pickers.

### CSV Export
- Click the **"Export CSV"** button to download your currently filtered view.
- The CSV includes all form data, tracking parameters, and the submission timestamp.
- Encoded in UTF-8 with a BOM for maximum compatibility with Excel.

## Security

- **Rate Limiting**: The login form is protected against brute-force attacks (5 failed attempts per 15 minutes leads to a temporary IP lockout).
- **Session Storage**: Sessions are stored in the SQLite database (`admin_sessions` table) and are automatically pruned when they expire.
- **CSRF**: All destructive actions (like Logout) are protected by CSRF tokens.

## Customization

The dashboard colors follow the same `form.colors` settings defined in your configuration, ensuring a consistent brand experience even in the admin area.
