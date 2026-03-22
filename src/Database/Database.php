<?php

declare(strict_types=1);

namespace Iserter\EasyLeadCapture\Database;

use PDO;
use RuntimeException;

class Database
{
    private ?PDO $pdo = null;
    private string $dbPath;

    public function __construct(string $dbPath)
    {
        $this->dbPath = $dbPath;
    }

    public function getConnection(): PDO
    {
        if ($this->pdo === null) {
            $this->initConnection();
        }

        return $this->pdo;
    }

    private function initConnection(): void
    {
        $dir = dirname($this->dbPath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("Could not create database directory: {$dir}");
        }

        $this->pdo = new PDO('sqlite:' . $this->dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // WAL mode and foreign keys
        $this->pdo->exec('PRAGMA journal_mode=WAL');
        $this->pdo->exec('PRAGMA foreign_keys=ON');

        // Run migrations
        Migrations::run($this->pdo);
    }
}
