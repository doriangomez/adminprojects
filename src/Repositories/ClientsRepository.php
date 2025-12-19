<?php

declare(strict_types=1);

class ClientsRepository
{
    public function __construct(private Database $db)
    {
    }

    public function all(): array
    {
        return $this->db->fetchAll('SELECT * FROM clients ORDER BY created_at DESC');
    }

    public function kpis(): array
    {
        return $this->db->fetchAll(
            'SELECT c.id, c.name, c.industry, c.priority, c.nps, c.satisfaction, SUM(pr.budget) AS budget, SUM(pr.actual_cost) AS cost
             FROM clients c LEFT JOIN projects pr ON pr.client_id = c.id GROUP BY c.id'
        );
    }

    public function create(array $payload): int
    {
        return $this->db->insert(
            'INSERT INTO clients (name, industry, priority, satisfaction, nps, created_at, updated_at) VALUES (:name, :industry, :priority, :satisfaction, :nps, NOW(), NOW())',
            [
                ':name' => $payload['name'],
                ':industry' => $payload['industry'],
                ':priority' => $payload['priority'],
                ':satisfaction' => $payload['satisfaction'] ?? null,
                ':nps' => $payload['nps'] ?? null,
            ]
        );
    }

    public function update(int $id, array $payload): void
    {
        $this->db->execute(
            'UPDATE clients SET name = :name, industry = :industry, priority = :priority, satisfaction = :satisfaction, nps = :nps, updated_at = NOW() WHERE id = :id',
            [
                ':name' => $payload['name'],
                ':industry' => $payload['industry'],
                ':priority' => $payload['priority'],
                ':satisfaction' => $payload['satisfaction'] ?? null,
                ':nps' => $payload['nps'] ?? null,
                ':id' => $id,
            ]
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM clients WHERE id = :id', [':id' => $id]);
    }
}
