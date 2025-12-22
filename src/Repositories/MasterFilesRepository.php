<?php

declare(strict_types=1);

class MasterFilesRepository
{
    private const ALLOWED_TABLES = [
        'priorities' => ['code', 'label'],
        'project_status' => ['code', 'label'],
        'project_health' => ['code', 'label'],
        'client_sectors' => ['code', 'label'],
        'client_categories' => ['code', 'label'],
        'client_status' => ['code', 'label'],
        'client_risk' => ['code', 'label'],
        'client_areas' => ['code', 'label'],
    ];

    public function __construct(private Database $db)
    {
    }

    public function list(string $table): array
    {
        $this->ensureAllowed($table);
        return $this->db->fetchAll('SELECT * FROM ' . $table . ' ORDER BY id DESC');
    }

    public function create(string $table, array $payload): int
    {
        $this->ensureAllowed($table);
        $columns = self::ALLOWED_TABLES[$table];
        return $this->db->insert(
            'INSERT INTO ' . $table . ' (' . implode(',', $columns) . ') VALUES (:code, :label)',
            [
                ':code' => $payload['code'],
                ':label' => $payload['label'],
            ]
        );
    }

    public function update(string $table, int $id, array $payload): void
    {
        $this->ensureAllowed($table);
        $this->db->execute(
            'UPDATE ' . $table . ' SET code = :code, label = :label WHERE id = :id',
            [
                ':code' => $payload['code'],
                ':label' => $payload['label'],
                ':id' => $id,
            ]
        );
    }

    public function delete(string $table, int $id): void
    {
        $this->ensureAllowed($table);
        $this->db->execute('DELETE FROM ' . $table . ' WHERE id = :id', [':id' => $id]);
    }

    public function allowedTables(): array
    {
        return array_keys(self::ALLOWED_TABLES);
    }

    private function ensureAllowed(string $table): void
    {
        if (!array_key_exists($table, self::ALLOWED_TABLES)) {
            throw new \InvalidArgumentException('Tabla no permitida');
        }
    }
}
