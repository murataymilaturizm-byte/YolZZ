<?php
/**
 * ============================================================
 * Database — PDO Wrapper (Singleton)
 * ============================================================
 * Tüm veritabanı işlemleri bu sınıf üzerinden geçer.
 * Prepared statement zorunludur (SQL injection koruması).
 * ============================================================
 */

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (APP_DEBUG) {
                die('DB Bağlantı Hatası: ' . $e->getMessage());
            }
            die('Veritabanı bağlantısı sağlanamadı. Lütfen daha sonra tekrar deneyin.');
        }
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    // --- Kısayol metotlar -----------------------------------

    /** Tek satır çek */
    public function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /** Tüm satırları çek */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Tek değer çek */
    public function fetchColumn(string $sql, array $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /** INSERT - son ID'yi döner */
    public function insert(string $table, array $data): int
    {
        $keys = array_keys($data);
        $cols = '`' . implode('`,`', $keys) . '`';
        $placeholders = ':' . implode(',:', $keys);

        $sql = "INSERT INTO `$table` ($cols) VALUES ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    /** UPDATE - etkilenen satır sayısını döner */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = [];
        foreach (array_keys($data) as $key) {
            $set[] = "`$key` = :$key";
        }
        $setStr = implode(', ', $set);

        $sql = "UPDATE `$table` SET $setStr WHERE $where";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($data, $whereParams));
        return $stmt->rowCount();
    }

    /** DELETE */
    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM `$table` WHERE $where";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** Ham sorgu - özel durumlar için */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Transaction */
    public function beginTransaction(): bool { return $this->pdo->beginTransaction(); }
    public function commit(): bool { return $this->pdo->commit(); }
    public function rollBack(): bool { return $this->pdo->rollBack(); }
}

/** Global kısayol fonksiyon */
function db(): Database
{
    return Database::getInstance();
}
