<?php

namespace App\Services\DataIntegration;

use App\Models\DataConnection;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Salt-okunur dış veri kaynağı tarayıcısı.
 *
 * Güvenlik notları:
 * - PDO bağlantısı her çağrıda yeniden kurulur (pool yok), sırlar sızmaz.
 * - Tablo isimleri INFORMATION_SCHEMA / sistem kataloğundan alınır, kullanıcı input'undan değil.
 * - Önizleme sorguları sabit bir SELECT * FROM "tablo" LIMIT N pattern'i kullanır;
 *   tablo adı whitelist kontrolünden geçirilir.
 */
final class DataSourceBrowser
{
    private const PREVIEW_DEFAULT_LIMIT = 100;
    private const PREVIEW_MAX_LIMIT = 1000;

    public function availableDrivers(): array
    {
        $supported = DataConnection::SUPPORTED_DRIVERS;
        $installed = PDO::getAvailableDrivers();
        return array_values(array_intersect($supported, $installed));
    }

    /**
     * Bağlantıyı test eder. Başarılı ise PDO instance döner, aksi halde RuntimeException.
     */
    public function connect(DataConnection $connection): PDO
    {
        $dsn = $this->buildDsn($connection);

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        if (! in_array($connection->driver, ['sqlsrv'], true)) {
            // sqlsrv (Microsoft PDO) PDO::ATTR_TIMEOUT'u desteklemez (IMSSP).
            // LoginTimeout DSN üzerinden geçirilir; bkz. buildDsn().
            $options[PDO::ATTR_TIMEOUT] = 30;
            $options[PDO::ATTR_EMULATE_PREPARES] = false;
        }

        if ($connection->driver === 'sqlsrv' && defined('PDO::SQLSRV_ATTR_ENCODING')) {
            $options[PDO::SQLSRV_ATTR_ENCODING] = PDO::SQLSRV_ENCODING_UTF8;
        }

        if ($connection->driver === 'sqlsrv' && defined('PDO::SQLSRV_ATTR_QUERY_TIMEOUT')) {
            $options[PDO::SQLSRV_ATTR_QUERY_TIMEOUT] = 60;
        }

        if ($connection->driver === 'mysql') {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8mb4';
            if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
                $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
            }
        }

        try {
            return new PDO($dsn, $connection->username, $connection->password, $options);
        } catch (PDOException $e) {
            if ($this->isTransientMysqlDisconnect($e)) {
                sleep(1);
                return new PDO($dsn, $connection->username, $connection->password, $options);
            }
            throw new RuntimeException('Bağlantı kurulamadı: '.$e->getMessage(), previous: $e);
        }
    }

    private function isTransientMysqlDisconnect(PDOException $e): bool
    {
        $errorCode = $e->errorInfo[1] ?? null;
        return $e->getCode() === 'HY000' && in_array($errorCode, [2006, 2013], true);
    }

    public function testConnection(DataConnection $connection): array
    {
        try {
            $pdo = $this->connect($connection);
            // Basit bir SELECT ile gerçek erişimi doğrula
            $pdo->query($this->serverVersionQuery($connection->driver));

            return [
                'success' => true,
                'message' => 'Bağlantı başarılı.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Tüm tabloları (BASE TABLE) listeler.
     * @return array<int, array{name:string, schema:?string, type:string}>
     */
    public function listTables(DataConnection $connection): array
    {
        $pdo = $this->connect($connection);

        return match ($connection->driver) {
            'mysql' => $this->listMysqlTables($pdo, $connection->database),
            'pgsql' => $this->listPostgresTables($pdo, $connection->extra['schema'] ?? 'public'),
            'sqlsrv', 'dblib' => $this->listSqlServerTables($pdo),
            'sqlite' => $this->listSqliteTables($pdo),
            default => throw new RuntimeException("Desteklenmeyen driver: {$connection->driver}"),
        };
    }

    /**
     * Tablonun kolonlarını + ilk N satırını döner.
     * @return array{columns: array<int, array{name:string,type:string}>, rows: array<int, array>, total: ?int}
     */
    public function previewTable(DataConnection $connection, string $tableName, int $limit = self::PREVIEW_DEFAULT_LIMIT, ?string $schema = null): array
    {
        $limit = max(1, min($limit, self::PREVIEW_MAX_LIMIT));

        // Whitelist: kullanıcının istediği tablo gerçekten var mı?
        $available = $this->listTables($connection);
        $match = collect($available)->first(fn ($t) => $t['name'] === $tableName
            && ($schema === null || ($t['schema'] ?? null) === $schema));
        if (! $match) {
            throw new RuntimeException("Tablo bulunamadı veya erişilemiyor: {$tableName}");
        }

        $pdo = $this->connect($connection);
        $quoted = $this->quoteIdentifier($connection->driver, $match['name'], $match['schema'] ?? null);

        // Önizleme
        $rowsSql = $this->limitedSelect($connection->driver, $quoted, $limit);
        $rows = $pdo->query($rowsSql)->fetchAll() ?: [];

        // Kolon meta (ilk satır varsa keys'den, yoksa INFORMATION_SCHEMA'dan)
        $columns = $this->describeColumns($pdo, $connection, $match['name'], $match['schema'] ?? null);

        // Yaklaşık satır sayısı (mysql/pgsql/sqlsrv için hızlı estimate)
        $total = $this->approximateRowCount($pdo, $connection, $match['name'], $match['schema'] ?? null);

        return compact('columns', 'rows', 'total');
    }

    /**
     * Tablo verisini stream olarak iterator döner (CSV export için).
     * @return \Generator<int, array>
     */
    public function streamTable(DataConnection $connection, string $tableName, ?string $schema = null, int $batchSize = 1000): \Generator
    {
        $available = $this->listTables($connection);
        $match = collect($available)->first(fn ($t) => $t['name'] === $tableName
            && ($schema === null || ($t['schema'] ?? null) === $schema));
        if (! $match) {
            throw new RuntimeException("Tablo bulunamadı: {$tableName}");
        }

        $pdo = $this->connect($connection);
        $quoted = $this->quoteIdentifier($connection->driver, $match['name'], $match['schema'] ?? null);
        $sql = "SELECT * FROM {$quoted}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_ASSOC);

        while ($row = $stmt->fetch()) {
            yield $row;
        }
    }

    // ── Private: driver-specific implementations ────────────────────────

    private function buildDsn(DataConnection $c): string
    {
        $port = $c->port ?: DataConnection::defaultPort($c->driver);

        return match ($c->driver) {
            'mysql' => "mysql:host={$c->host};port={$port};dbname={$c->database};charset=utf8mb4",
            'pgsql' => "pgsql:host={$c->host};port={$port};dbname={$c->database}",
            'sqlsrv' => sprintf(
                'sqlsrv:Server=%s,%d;Database=%s;TrustServerCertificate=%d;Encrypt=%d;LoginTimeout=%d',
                $c->host,
                $port,
                $c->database,
                $c->extra['trust_server_certificate'] ?? 1,
                $c->extra['encrypt'] ?? 0,
                (int) ($c->extra['login_timeout'] ?? 30),
            ),
            'dblib' => sprintf(
                'dblib:host=%s:%d;dbname=%s;charset=UTF-8',
                $c->host,
                $port,
                $c->database,
            ),
            'sqlite' => "sqlite:{$c->database}",
            default => throw new RuntimeException("Desteklenmeyen driver: {$c->driver}"),
        };
    }

    private function serverVersionQuery(string $driver): string
    {
        return match ($driver) {
            'mysql', 'pgsql' => 'SELECT VERSION() AS v',
            'sqlsrv', 'dblib' => 'SELECT @@VERSION AS v',
            'sqlite' => 'SELECT sqlite_version() AS v',
            default => 'SELECT 1',
        };
    }

    private function listMysqlTables(PDO $pdo, string $database): array
    {
        $stmt = $pdo->prepare("SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.tables WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME");
        $stmt->execute([$database]);
        return array_map(fn ($r) => [
            'name' => $r['TABLE_NAME'],
            'schema' => null,
            'type' => $r['TABLE_TYPE'] === 'BASE TABLE' ? 'table' : 'view',
        ], $stmt->fetchAll() ?: []);
    }

    private function listPostgresTables(PDO $pdo, string $schema): array
    {
        $stmt = $pdo->prepare("SELECT tablename AS name, 'table' AS type FROM pg_tables WHERE schemaname = ? UNION ALL SELECT viewname AS name, 'view' AS type FROM pg_views WHERE schemaname = ? ORDER BY name");
        $stmt->execute([$schema, $schema]);
        return array_map(fn ($r) => [
            'name' => $r['name'],
            'schema' => $schema,
            'type' => $r['type'],
        ], $stmt->fetchAll() ?: []);
    }

    private function listSqlServerTables(PDO $pdo): array
    {
        $stmt = $pdo->query("SELECT TABLE_SCHEMA, TABLE_NAME, TABLE_TYPE FROM INFORMATION_SCHEMA.TABLES ORDER BY TABLE_SCHEMA, TABLE_NAME");
        return array_map(fn ($r) => [
            'name' => $r['TABLE_NAME'],
            'schema' => $r['TABLE_SCHEMA'],
            'type' => $r['TABLE_TYPE'] === 'BASE TABLE' ? 'table' : 'view',
        ], $stmt->fetchAll() ?: []);
    }

    private function listSqliteTables(PDO $pdo): array
    {
        $stmt = $pdo->query("SELECT name, type FROM sqlite_master WHERE type IN ('table','view') AND name NOT LIKE 'sqlite_%' ORDER BY name");
        return array_map(fn ($r) => [
            'name' => $r['name'],
            'schema' => null,
            'type' => $r['type'],
        ], $stmt->fetchAll() ?: []);
    }

    private function quoteIdentifier(string $driver, string $name, ?string $schema = null): string
    {
        // Identifier safety: harf, rakam, _, $ ve nokta dışında karakter olmamalı
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_$]*$/', $name)) {
            throw new RuntimeException("Geçersiz tablo adı: {$name}");
        }
        if ($schema !== null && ! preg_match('/^[A-Za-z_][A-Za-z0-9_$]*$/', $schema)) {
            throw new RuntimeException("Geçersiz şema adı: {$schema}");
        }

        return match ($driver) {
            'mysql' => $schema ? "`{$schema}`.`{$name}`" : "`{$name}`",
            'pgsql' => $schema ? "\"{$schema}\".\"{$name}\"" : "\"{$name}\"",
            'sqlsrv', 'dblib' => $schema ? "[{$schema}].[{$name}]" : "[{$name}]",
            'sqlite' => "\"{$name}\"",
            default => $name,
        };
    }

    private function limitedSelect(string $driver, string $quotedTable, int $limit): string
    {
        return match ($driver) {
            'mysql', 'pgsql', 'sqlite' => "SELECT * FROM {$quotedTable} LIMIT {$limit}",
            'sqlsrv', 'dblib' => "SELECT TOP {$limit} * FROM {$quotedTable}",
            default => "SELECT * FROM {$quotedTable}",
        };
    }

    private function describeColumns(PDO $pdo, DataConnection $c, string $table, ?string $schema): array
    {
        try {
            return match ($c->driver) {
                'mysql' => $this->describeMysqlColumns($pdo, $c->database, $table),
                'pgsql' => $this->describePgColumns($pdo, $schema ?? 'public', $table),
                'sqlsrv', 'dblib' => $this->describeSqlServerColumns($pdo, $schema, $table),
                'sqlite' => $this->describeSqliteColumns($pdo, $table),
                default => [],
            };
        } catch (\Throwable) {
            return [];
        }
    }

    private function describeMysqlColumns(PDO $pdo, string $db, string $table): array
    {
        $stmt = $pdo->prepare("SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.columns WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION");
        $stmt->execute([$db, $table]);
        return array_map(fn ($r) => ['name' => $r['COLUMN_NAME'], 'type' => $r['COLUMN_TYPE']], $stmt->fetchAll() ?: []);
    }

    private function describePgColumns(PDO $pdo, string $schema, string $table): array
    {
        $stmt = $pdo->prepare("SELECT column_name, data_type FROM information_schema.columns WHERE table_schema = ? AND table_name = ? ORDER BY ordinal_position");
        $stmt->execute([$schema, $table]);
        return array_map(fn ($r) => ['name' => $r['column_name'], 'type' => $r['data_type']], $stmt->fetchAll() ?: []);
    }

    private function describeSqlServerColumns(PDO $pdo, ?string $schema, string $table): array
    {
        $sql = "SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ?";
        $params = [$table];
        if ($schema) {
            $sql .= ' AND TABLE_SCHEMA = ?';
            $params[] = $schema;
        }
        $sql .= ' ORDER BY ORDINAL_POSITION';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return array_map(fn ($r) => ['name' => $r['COLUMN_NAME'], 'type' => $r['DATA_TYPE']], $stmt->fetchAll() ?: []);
    }

    private function describeSqliteColumns(PDO $pdo, string $table): array
    {
        $quoted = '"'.str_replace('"', '""', $table).'"';
        $stmt = $pdo->query("PRAGMA table_info({$quoted})");
        return array_map(fn ($r) => ['name' => $r['name'], 'type' => $r['type'] ?? ''], $stmt->fetchAll() ?: []);
    }

    private function approximateRowCount(PDO $pdo, DataConnection $c, string $table, ?string $schema): ?int
    {
        try {
            return match ($c->driver) {
                'mysql' => (int) ($pdo->query("SELECT TABLE_ROWS FROM information_schema.tables WHERE TABLE_SCHEMA = ".$pdo->quote($c->database)." AND TABLE_NAME = ".$pdo->quote($table))->fetchColumn() ?: 0),
                'pgsql' => (int) ($pdo->query("SELECT reltuples::bigint FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE c.relname = ".$pdo->quote($table)." AND n.nspname = ".$pdo->quote($schema ?? 'public'))->fetchColumn() ?: 0),
                'sqlsrv', 'dblib' => (int) ($pdo->query("SELECT SUM(p.rows) FROM sys.partitions p JOIN sys.tables t ON p.object_id = t.object_id WHERE t.name = ".$pdo->quote($table)." AND p.index_id IN (0,1)")->fetchColumn() ?: 0),
                'sqlite' => null,
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }
}
