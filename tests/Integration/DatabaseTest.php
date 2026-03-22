<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Iserter\EasyLeadCapture\Database\Database;

class DatabaseTest extends TestCase
{
    private string $tempDb;

    protected function setUp(): void
    {
        $this->tempDb = __DIR__ . '/test_leads.db';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempDb)) {
            @unlink($this->tempDb);
        }
    }

    public function test_it_creates_db_and_tables(): void
    {
        $db = new Database($this->tempDb);
        $pdo = $db->getConnection();

        $this->assertTrue(file_exists($this->tempDb));

        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(\PDO::FETCH_COLUMN);
        
        $this->assertContains('leads', $tables);
        $this->assertContains('admin_sessions', $tables);
        $this->assertContains('login_attempts', $tables);
    }

    public function test_it_uses_wal_mode(): void
    {
        $db = new Database($this->tempDb);
        $pdo = $db->getConnection();

        $mode = $pdo->query('PRAGMA journal_mode')->fetchColumn();
        $this->assertEquals('wal', strtolower($mode));
    }
}
