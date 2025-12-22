<?php

declare(strict_types=1);

class ClientsRepository
{
    private const ADMIN_ROLES = ['Administrador', 'PMO'];

    public function __construct(private Database $db)
    {
    }

    public function listForUser(array $user): array
    {
        $params = [];
        $where = '';
        $hasPmColumn = $this->db->columnExists('clients', 'pm_id');

        if ($hasPmColumn && !$this->isPrivileged($user)) {
            $where = 'WHERE c.pm_id = :pmId';
            $params[':pmId'] = $user['id'];
        }

        $pmFields = $hasPmColumn ? 'u.name AS pm_name' : 'NULL AS pm_name';
        $pmJoin = $hasPmColumn ? 'LEFT JOIN users u ON u.id = c.pm_id' : '';

        return $this->db->fetchAll(
            'SELECT c.*, c.priority_code AS priority, sec.label AS sector_label, cat.label AS category_label, pr.label AS priority_label, st.label AS status_label, risk.label AS risk_label, area.label AS area_label, ' . $pmFields . '
             FROM clients c
             LEFT JOIN client_sectors sec ON sec.code = c.sector_code
             LEFT JOIN client_categories cat ON cat.code = c.category_code
             LEFT JOIN priorities pr ON pr.code = c.priority_code
             LEFT JOIN client_status st ON st.code = c.status_code
             LEFT JOIN client_risk risk ON risk.code = c.risk_level
             LEFT JOIN client_areas area ON area.code = c.area
             ' . $pmJoin . '
             ' . $where . '
             ORDER BY c.created_at DESC',
            $params
        );
    }

    public function findForUser(int $id, array $user): ?array
    {
        $params = [':id' => $id];
        $conditions = ['c.id = :id'];
        $hasPmColumn = $this->db->columnExists('clients', 'pm_id');

        if ($hasPmColumn && !$this->isPrivileged($user)) {
            $conditions[] = 'c.pm_id = :pmId';
            $params[':pmId'] = $user['id'];
        }

        $pmFields = $hasPmColumn ? 'u.name AS pm_name, u.email AS pm_email' : 'NULL AS pm_name, NULL AS pm_email';
        $pmJoin = $hasPmColumn ? 'LEFT JOIN users u ON u.id = c.pm_id' : '';

        $client = $this->db->fetchOne(
            'SELECT c.*, c.priority_code AS priority, sec.label AS sector_label, cat.label AS category_label, pr.label AS priority_label, st.label AS status_label, risk.label AS risk_label, area.label AS area_label, ' . $pmFields . '
             FROM clients c
             LEFT JOIN client_sectors sec ON sec.code = c.sector_code
             LEFT JOIN client_categories cat ON cat.code = c.category_code
             LEFT JOIN priorities pr ON pr.code = c.priority_code
             LEFT JOIN client_status st ON st.code = c.status_code
             LEFT JOIN client_risk risk ON risk.code = c.risk_level
             LEFT JOIN client_areas area ON area.code = c.area
             ' . $pmJoin . '
             WHERE ' . implode(' AND ', $conditions),
            $params
        );

        return $client ?: null;
    }

    public function projectsForClient(int $clientId, array $user = []): array
    {
        $params = [':clientId' => $clientId];
        $where = 'WHERE p.client_id = :clientId';
        $hasPmColumn = $this->db->columnExists('projects', 'pm_id');
        $hasPriorityColumn = $this->db->columnExists('projects', 'priority_code');
        $hasStatusColumn = $this->db->columnExists('projects', 'status_code');
        $hasHealthColumn = $this->db->columnExists('projects', 'health_code');

        if ($hasPmColumn && !$this->isPrivileged($user)) {
            $where .= ' AND p.pm_id = :pmId';
            $params[':pmId'] = $user['id'];
        }

        $select = [
            'p.id',
            'p.name',
            'p.progress',
            'p.pm_id',
            'p.client_id',
            'p.project_type',
            'p.created_at',
            'p.updated_at',
        ];

        $joins = [];

        if ($hasPriorityColumn) {
            $select[] = 'p.priority_code AS priority';
            $select[] = 'pr.label AS priority_label';
            $joins[] = 'LEFT JOIN priorities pr ON pr.code = p.priority_code';
        } else {
            $select[] = 'NULL AS priority';
            $select[] = 'NULL AS priority_label';
        }

        if ($hasStatusColumn) {
            $select[] = 'p.status_code AS status';
            $select[] = 'st.label AS status_label';
            $joins[] = 'LEFT JOIN project_status st ON st.code = p.status_code';
        } else {
            $select[] = "'' AS status";
            $select[] = 'NULL AS status_label';
        }

        if ($hasHealthColumn) {
            $select[] = 'p.health_code AS health';
            $select[] = 'h.label AS health_label';
            $joins[] = 'LEFT JOIN project_health h ON h.code = p.health_code';
        } else {
            $select[] = 'NULL AS health';
            $select[] = 'NULL AS health_label';
        }

        $sql = sprintf(
            'SELECT %s FROM projects p %s %s ORDER BY p.created_at DESC',
            implode(', ', $select),
            implode(' ', $joins),
            $where
        );

        return $this->db->fetchAll($sql, $params);
    }

    public function projectSnapshot(int $clientId, array $user = []): array
    {
        $params = [':clientId' => $clientId];
        $conditions = ['client_id = :clientId'];
        $hasPmColumn = $this->db->columnExists('projects', 'pm_id');

        if ($hasPmColumn && !$this->isPrivileged($user)) {
            $conditions[] = 'pm_id = :pmId';
            $params[':pmId'] = $user['id'];
        }

        $snapshot = $this->db->fetchOne(
            "SELECT COUNT(*) AS total, AVG(progress) AS avg_progress,
                    SUM(CASE WHEN health_code IN ('at_risk','critical','red','yellow') THEN 1 ELSE 0 END) AS at_risk,
                    SUM(CASE WHEN status_code = 'closed' THEN 1 ELSE 0 END) AS closed
             FROM projects WHERE " . implode(' AND ', $conditions),
            $params
        );

        return [
            'total' => (int) ($snapshot['total'] ?? 0),
            'avg_progress' => round((float) ($snapshot['avg_progress'] ?? 0), 1),
            'at_risk' => (int) ($snapshot['at_risk'] ?? 0),
            'closed' => (int) ($snapshot['closed'] ?? 0),
        ];
    }

    public function create(array $payload): int
    {
        return $this->db->insert(
            'INSERT INTO clients (name, sector_code, category_code, priority_code, status_code, pm_id, satisfaction, nps, risk_level, tags, area, feedback_notes, feedback_history, operational_context, logo_path, created_at, updated_at)
             VALUES (:name, :sector_code, :category_code, :priority_code, :status_code, :pm_id, :satisfaction, :nps, :risk_level, :tags, :area, :feedback_notes, :feedback_history, :operational_context, :logo_path, NOW(), NOW())',
            [
                ':name' => $payload['name'],
                ':sector_code' => $payload['sector_code'],
                ':category_code' => $payload['category_code'],
                ':priority_code' => $payload['priority'],
                ':status_code' => $payload['status_code'],
                ':pm_id' => (int) $payload['pm_id'],
                ':satisfaction' => $payload['satisfaction'] ?? null,
                ':nps' => $payload['nps'] ?? null,
                ':risk_level' => $payload['risk_level'] ?? null,
                ':tags' => $payload['tags'] ?? null,
                ':area' => $payload['area'] ?? null,
                ':feedback_notes' => $payload['feedback_notes'] ?? null,
                ':feedback_history' => $payload['feedback_history'] ?? null,
                ':operational_context' => $payload['operational_context'] ?? null,
                ':logo_path' => $payload['logo_path'] ?? null,
            ]
        );
    }

    public function update(int $id, array $payload): void
    {
        $this->db->execute(
            'UPDATE clients SET name = :name, sector_code = :sector_code, category_code = :category_code, priority_code = :priority_code, status_code = :status_code, pm_id = :pm_id, satisfaction = :satisfaction, nps = :nps, risk_level = :risk_level, tags = :tags, area = :area, feedback_notes = :feedback_notes, feedback_history = :feedback_history, operational_context = :operational_context, logo_path = :logo_path, updated_at = NOW() WHERE id = :id',
            [
                ':name' => $payload['name'],
                ':sector_code' => $payload['sector_code'],
                ':category_code' => $payload['category_code'],
                ':priority_code' => $payload['priority'],
                ':status_code' => $payload['status_code'],
                ':pm_id' => (int) $payload['pm_id'],
                ':satisfaction' => $payload['satisfaction'] ?? null,
                ':nps' => $payload['nps'] ?? null,
                ':risk_level' => $payload['risk_level'] ?? null,
                ':tags' => $payload['tags'] ?? null,
                ':area' => $payload['area'] ?? null,
                ':feedback_notes' => $payload['feedback_notes'] ?? null,
                ':feedback_history' => $payload['feedback_history'] ?? null,
                ':operational_context' => $payload['operational_context'] ?? null,
                ':logo_path' => $payload['logo_path'] ?? null,
                ':id' => $id,
            ]
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM clients WHERE id = :id', [':id' => $id]);
    }

    private function isPrivileged(array $user): bool
    {
        return in_array($user['role'] ?? '', self::ADMIN_ROLES, true);
    }
}
