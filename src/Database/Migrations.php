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
