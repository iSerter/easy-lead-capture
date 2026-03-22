# Task 03 — SQLite Database & Auto-Migration

## Goal
Create the database connection manager with WAL mode and auto-create tables on first run.

## Files to Create
```
src/Database/Database.php
src/Database/Migrations.php
```

## Steps

1. **`Database.php`** — Singleton-style connection manager:
   - Constructor takes the DB file path from config (`database.path`).
   - Creates the `data/` directory if it doesn't exist.
   - Opens PDO connection with: WAL mode, foreign keys ON, exception error mode.
   - Provides `getConnection(): PDO` accessor.
   - Calls `Migrations::run()` on first connection.

2. **`Migrations.php`** — Auto-migration:
   - `run(PDO $pdo)` — executes `CREATE TABLE IF NOT EXISTS` for:
     - `leads` table (id, data JSON, ip_address, user_agent, captcha_score, created_at).
     - `admin_sessions` table (token, created_at, expires_at).
     - `login_attempts` table (ip_address, attempted_at) — for rate limiting (Task 12).
   - Index on `leads.created_at`.

3. **Integrate into `App.php`** — instantiate `Database` during boot, make it available to controllers.

## Acceptance Criteria
- First request auto-creates the SQLite file and all tables.
- WAL mode is active (`PRAGMA journal_mode` returns `wal`).
- Subsequent requests reuse the existing DB without re-running migrations.
