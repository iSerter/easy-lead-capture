<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\Database;

use PDO;

class Migrations
{
    public static function run(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS leads (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                data JSON NOT NULL,
                ip_address TEXT,
                user_agent TEXT,
                captcha_score REAL,
                created_at TEXT DEFAULT (datetime('now'))
            )
        ");

        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_leads_created_at ON leads(created_at)");

        // Add status and notes columns to leads table if they don't exist
        $columns = $pdo->query("PRAGMA table_info(leads)")->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'name');

        if (!in_array('status', $columnNames)) {
            $pdo->exec("ALTER TABLE leads ADD COLUMN status TEXT DEFAULT 'new'");
        }
        if (!in_array('notes', $columnNames)) {
            $pdo->exec("ALTER TABLE leads ADD COLUMN notes TEXT DEFAULT NULL");
        }
        if (!in_array('ip_country_code', $columnNames)) {
            $pdo->exec("ALTER TABLE leads ADD COLUMN ip_country_code TEXT DEFAULT NULL");
        }
        if (!in_array('ip_region', $columnNames)) {
            $pdo->exec("ALTER TABLE leads ADD COLUMN ip_region TEXT DEFAULT NULL");
        }
        if (!in_array('ip_city', $columnNames)) {
            $pdo->exec("ALTER TABLE leads ADD COLUMN ip_city TEXT DEFAULT NULL");
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS admin_sessions (
                token TEXT PRIMARY KEY,
                created_at TEXT DEFAULT (datetime('now')),
                expires_at TEXT NOT NULL
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
                ip_address TEXT NOT NULL,
                attempted_at TEXT DEFAULT (datetime('now'))
            )
        ");

        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_login_attempts_ip_at ON login_attempts(ip_address, attempted_at)");
    }
}
