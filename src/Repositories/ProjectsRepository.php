<?php

declare(strict_types=1);

class ProjectsRepository
{
    private const ADMIN_ROLES = ['Administrador', 'PMO'];

    public function __construct(private Database $db)
    {
    }

    public function summary(array $user): array
    {
        $conditions = [];
        $params = [];
        $hasPmColumn = $this->db->columnExists('projects', 'pm_id');

        if ($hasPmColumn && !$this->isPrivileged($user)) {
            $conditions[] = 'p.pm_id = :pmId';
            $params[':pmId'] = $user['id'];
        }

        $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return $this->db->fetchAll(
            'SELECT p.id, p.name, p.status, st.label AS status_label, p.health, h.label AS health_label, p.priority, pr.label AS priority_label, p.progress, p.budget, p.actual_cost, p.planned_hours, p.actual_hours, c.name AS client
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             LEFT JOIN priorities pr ON pr.code = p.priority
             LEFT JOIN project_status st ON st.code = p.status
             LEFT JOIN project_health h ON h.code = p.health
             ' . $whereClause . '
             ORDER BY p.created_at DESC',
            $params
        );
    }

    public function portfolioKpis(array $user): array
    {
        $conditions = [];
        $params = [];
        $hasPmColumn = $this->db->columnExists('projects', 'pm_id');

        if ($hasPmColumn && !$this->isPrivileged($user)) {
            $conditions[] = 'p.pm_id = :pmId';
            $params[':pmId'] = $user['id'];
        }

        $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $totals = $this->db->fetchOne(
            'SELECT COUNT(*) AS total, SUM(p.planned_hours) AS planned_hours, SUM(p.actual_hours) AS actual_hours, AVG(p.progress) AS avg_progress
             FROM projects p JOIN clients c ON c.id = p.client_id ' . $whereClause,
            $params
        );
        $atRisk = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM projects p JOIN clients c ON c.id = p.client_id $whereClause AND p.health IN ('at_risk','critical','red','yellow')",
            $params
        );

        return [
            'total_projects' => (int) ($totals['total'] ?? 0),
            'avg_progress' => round((float) ($totals['avg_progress'] ?? 0), 1),
            'planned_hours' => (int) ($totals['planned_hours'] ?? 0),
            'actual_hours' => (int) ($totals['actual_hours'] ?? 0),
            'at_risk' => (int) ($atRisk['total'] ?? 0),
        ];
    }

    private function isPrivileged(array $user): bool
    {
        return in_array($user['role'] ?? '', self::ADMIN_ROLES, true);
    }
}
