<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;
use Throwable;
use RuntimeException;

final class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct(array $cfg)
    {
        $port = !empty($cfg['port']) ? ";port={$cfg['port']}" : '';
        $dsn  = "mysql:host={$cfg['host']}{$port};dbname={$cfg['name']};charset={$cfg['charset']}";
        try {
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ];
            if (!empty($cfg['ssl'])) {
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }
            $this->pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], $options);
        } catch (Throwable $e) {
            Logger::error($e);
            throw new RuntimeException('Database connection failure: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            $cfg = require BASE_PATH . '/config/database.php';
            self::$instance = new self($cfg);
        }
        return self::$instance;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Executes work inside a DB transaction with automatic rollback on error.
     */
    public function transaction(callable $work): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $work($this->pdo);
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
