<?php

declare(strict_types=1);

class Database
{
    private \PDO $pdo;
    private string $databaseName;
    private array $columnCache = [];

    public function __construct(array $config)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset'] ?? 'utf8mb4'
        );

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $this->pdo = new \PDO($dsn, $config['username'], $config['password'], $options);
        $this->databaseName = $config['database'];
    }

    public function connection(): \PDO
    {
        return $this->pdo;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result === false ? null : $result;
    }

    public function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function insert(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $this->pdo->lastInsertId();
    }

    public function databaseName(): string
    {
        return $this->databaseName;
    }

    public function columnExists(string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, $this->columnCache)) {
            return $this->columnCache[$cacheKey];
        }

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = :schema AND table_name = :table AND column_name = :column'
        );

        $stmt->execute([
            ':schema' => $this->databaseName,
            ':table' => $table,
            ':column' => $column,
        ]);

        $exists = (bool) $stmt->fetchColumn();
        $this->columnCache[$cacheKey] = $exists;

        return $exists;
    }

    public function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :schema AND table_name = :table'
        );

        $stmt->execute([
            ':schema' => $this->databaseName,
            ':table' => $table,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function foreignKeyExists(string $table, string $column, string $referencedTable): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND COLUMN_NAME = :column AND REFERENCED_TABLE_NAME = :referenced'
        );

        $stmt->execute([
            ':schema' => $this->databaseName,
            ':table' => $table,
            ':column' => $column,
            ':referenced' => $referencedTable,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function foreignKeyDetails(string $table, string $column): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT k.CONSTRAINT_NAME, k.REFERENCED_TABLE_NAME, rc.UPDATE_RULE, rc.DELETE_RULE
             FROM information_schema.KEY_COLUMN_USAGE k
             LEFT JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
                ON rc.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
                AND rc.CONSTRAINT_NAME = k.CONSTRAINT_NAME
                AND rc.TABLE_NAME = k.TABLE_NAME
             WHERE k.TABLE_SCHEMA = :schema
               AND k.TABLE_NAME = :table
               AND k.COLUMN_NAME = :column
               AND k.REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1'
        );

        $stmt->execute([
            ':schema' => $this->databaseName,
            ':table' => $table,
            ':column' => $column,
        ]);

        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    public function dropForeignKey(string $table, string $constraintName): void
    {
        $this->execute(sprintf(
            'ALTER TABLE `%s` DROP FOREIGN KEY `%s`',
            $table,
            $constraintName
        ));
    }

    public function clearColumnCache(): void
    {
        $this->columnCache = [];
    }
}
