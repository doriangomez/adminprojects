<?php

declare(strict_types=1);

class ProjectsRepository
{
    public function __construct(private Database $db)
    {
    }

    public function summary(): array
    {
        return $this->db->fetchAll(
            'SELECT p.id, p.name, p.status, p.health, p.priority, p.progress, p.budget, p.actual_cost, p.planned_hours, p.actual_hours, c.name AS client
             FROM projects p JOIN clients c ON c.id = p.client_id ORDER BY p.created_at DESC'
        );
    }

    public function portfolioKpis(): array
    {
        $totals = $this->db->fetchOne('SELECT COUNT(*) AS total, SUM(planned_hours) AS planned_hours, SUM(actual_hours) AS actual_hours, AVG(progress) AS avg_progress FROM projects');
        $atRisk = $this->db->fetchOne("SELECT COUNT(*) AS total FROM projects WHERE health IN ('at_risk','red','yellow')");

        return [
            'total_projects' => (int) ($totals['total'] ?? 0),
            'avg_progress' => round((float) ($totals['avg_progress'] ?? 0), 1),
            'planned_hours' => (int) ($totals['planned_hours'] ?? 0),
            'actual_hours' => (int) ($totals['actual_hours'] ?? 0),
            'at_risk' => (int) ($atRisk['total'] ?? 0),
        ];
    }
}
