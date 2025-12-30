<?php

declare(strict_types=1);

class RiskCatalogRepository
{
    public function __construct(private Database $db)
    {
    }

    public function listAll(bool $onlyActive = false): array
    {
        $sql = 'SELECT * FROM risk_catalog';
        if ($onlyActive) {
            $sql .= ' WHERE active = 1';
        }
        $sql .= ' ORDER BY category ASC, label ASC';

        return $this->db->fetchAll($sql);
    }

    public function find(string $code): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM risk_catalog WHERE code = :code LIMIT 1',
            [':code' => $code]
        );
    }

    public function create(array $payload): void
    {
        $this->db->execute(
            'INSERT INTO risk_catalog (code, category, label, applies_to, impact_scope, impact_time, impact_cost, impact_quality, impact_legal, severity_base, active)
             VALUES (:code, :category, :label, :applies_to, :impact_scope, :impact_time, :impact_cost, :impact_quality, :impact_legal, :severity_base, :active)',
            $this->params($payload)
        );
    }

    public function update(string $code, array $payload): void
    {
        $params = $this->params($payload);
        $params[':code'] = $code;

        $this->db->execute(
            'UPDATE risk_catalog
             SET category = :category,
                 label = :label,
                 applies_to = :applies_to,
                 impact_scope = :impact_scope,
                 impact_time = :impact_time,
                 impact_cost = :impact_cost,
                 impact_quality = :impact_quality,
                 impact_legal = :impact_legal,
                 severity_base = :severity_base,
                 active = :active
             WHERE code = :code',
            $params
        );
    }

    public function delete(string $code): void
    {
        $this->db->execute('DELETE FROM risk_catalog WHERE code = :code', [':code' => $code]);
    }

    private function params(array $payload): array
    {
        return [
            ':category' => trim((string) ($payload['category'] ?? '')),
            ':label' => trim((string) ($payload['label'] ?? '')),
            ':applies_to' => $payload['applies_to'] ?? 'ambos',
            ':impact_scope' => (int) ($payload['impact_scope'] ?? 0),
            ':impact_time' => (int) ($payload['impact_time'] ?? 0),
            ':impact_cost' => (int) ($payload['impact_cost'] ?? 0),
            ':impact_quality' => (int) ($payload['impact_quality'] ?? 0),
            ':impact_legal' => (int) ($payload['impact_legal'] ?? 0),
            ':severity_base' => (int) ($payload['severity_base'] ?? 1),
            ':active' => isset($payload['active']) ? (int) $payload['active'] : 1,
        ];
    }
}
