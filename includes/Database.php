<?php
/**
 * WHMVM - Veritabanı Bağlantı Sınıfı
 * PHP 8.1+ PDO ile MySQL bağlantısı
 */

declare(strict_types=1);

class Database
{
    private static ?PDO $instance = null;
    private static array $config = [];

    /**
     * Singleton PDO bağlantısı
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::connect();
        }
        return self::$instance;
    }

    /**
     * Veritabanı bağlantısı oluştur
     */
    private static function connect(): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            self::$config['host'] ?? DB_HOST,
            self::$config['port'] ?? DB_PORT,
            self::$config['name'] ?? DB_NAME,
            self::$config['charset'] ?? DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'"
        ];

        try {
            self::$instance = new PDO(
                $dsn,
                self::$config['user'] ?? DB_USER,
                self::$config['pass'] ?? DB_PASS,
                $options
            );
        } catch (PDOException $e) {
            throw new Exception('Veritabanı bağlantı hatası: ' . $e->getMessage());
        }
    }

    /**
     * Kurulum için özel bağlantı (veritabanı adı olmadan)
     */
    public static function connectForInstall(string $host, int $port, string $user, string $pass): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port);
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        return new PDO($dsn, $user, $pass, $options);
    }

    /**
     * Belirli veritabanına bağlan (kurulum için)
     */
    public static function connectToDatabase(string $host, int $port, string $user, string $pass, string $dbName): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbName);
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        return new PDO($dsn, $user, $pass, $options);
    }

    /**
     * Config ayarla (kurulum için)
     */
    public static function setConfig(array $config): void
    {
        self::$config = $config;
    }

    /**
     * Bağlantıyı kapat
     */
    public static function close(): void
    {
        self::$instance = null;
    }

    /**
     * Prepared statement ile sorgu çalıştır
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $pdo = self::getInstance();
        
        if (empty($params)) {
            return $pdo->query($sql);
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Tek satır getir
     */
    public static function fetch(string $sql, array $params = []): ?array
    {
        $result = self::query($sql, $params)->fetch();
        return $result ?: null;
    }

    /**
     * Tüm satırları getir
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * Tek değer getir
     */
    public static function fetchColumn(string $sql, array $params = []): mixed
    {
        return self::query($sql, $params)->fetchColumn();
    }

    /**
     * Insert işlemi ve son ID'yi döndür
     */
    public static function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        self::query($sql, array_values($data));
        
        return (int) self::getInstance()->lastInsertId();
    }

    /**
     * Update işlemi
     */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(' = ?, ', array_keys($data)) . ' = ?';
        $sql = "UPDATE {$table} SET {$set} WHERE {$where}";
        
        $params = array_merge(array_values($data), $whereParams);
        return self::query($sql, $params)->rowCount();
    }

    /**
     * Delete işlemi
     */
    public static function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return self::query($sql, $params)->rowCount();
    }
}

