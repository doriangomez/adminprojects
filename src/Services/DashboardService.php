<?php

declare(strict_types=1);

class DashboardService
{
    private const ADMIN_ROLES = ['Administrador', 'PMO'];

    public function __construct(private Database $db)
    {
    }

    public function executiveSummary(array $user): array
    {
        [$whereProjects, $params] = $this->visibilityForUser($user);

        $clients = $this->db->fetchOne(
            'SELECT COUNT(DISTINCT c.id) AS total FROM clients c JOIN projects p ON p.client_id = c.id ' . ($whereProjects ?: ''),
            $params
        );

        $projectsCondition = $whereProjects ?: 'WHERE 1=1';
        $projects = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM projects p JOIN clients c ON c.id = p.client_id $projectsCondition AND p.status NOT IN ('archived','cancelled')",
            $params
        );

        $income = $this->db->fetchOne(
            'SELECT SUM(r.amount) AS total FROM revenues r JOIN projects p ON p.id = r.project_id JOIN clients c ON c.id = p.client_id ' . ($whereProjects ?: ''),
            $params
        );

        $atRisk = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM projects p JOIN clients c ON c.id = p.client_id $projectsCondition AND p.health IN ('at_risk','critical','red','yellow')",
            $params
        );

        return [
            'clientes_activos' => (int) ($clients['total'] ?? 0),
            'proyectos_activos' => (int) ($projects['total'] ?? 0),
            'ingresos_totales' => (float) ($income['total'] ?? 0),
            'proyectos_riesgo' => (int) ($atRisk['total'] ?? 0),
        ];
    }

    public function profitability(array $user): array
    {
        [$where, $params] = $this->visibilityForUser($user);

        return $this->db->fetchAll(
            'SELECT p.id, p.name, p.budget, p.actual_cost, (p.budget - p.actual_cost) AS margin, p.actual_hours
             FROM projects p JOIN clients c ON c.id = p.client_id ' . $where . ' ORDER BY p.created_at DESC',
            $params
        );
    }

    private function visibilityForUser(array $user): array
    {
        if (in_array($user['role'] ?? '', self::ADMIN_ROLES, true)) {
            return ['', []];
        }

        if (!$this->db->columnExists('projects', 'pm_id')) {
            return ['', []];
        }

        return ['WHERE p.pm_id = :pmId', [':pmId' => $user['id']]];
    }
}
