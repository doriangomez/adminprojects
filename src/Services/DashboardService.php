<?php

declare(strict_types=1);

class DashboardService
{
    public function __construct(private Database $db)
    {
    }

    public function executiveSummary(): array
    {
        $clients = $this->db->fetchOne('SELECT COUNT(*) AS total FROM clients WHERE active = 1');
        $projects = $this->db->fetchOne("SELECT COUNT(*) AS total FROM projects WHERE status NOT IN ('archived','cancelled')");
        $income = $this->db->fetchOne('SELECT SUM(amount) AS total FROM revenues');
        $atRisk = $this->db->fetchOne("SELECT COUNT(*) AS total FROM projects WHERE health IN ('at_risk','red','yellow')");

        return [
            'clientes_activos' => (int) ($clients['total'] ?? 0),
            'proyectos_activos' => (int) ($projects['total'] ?? 0),
            'ingresos_totales' => (float) ($income['total'] ?? 0),
            'proyectos_riesgo' => (int) ($atRisk['total'] ?? 0),
        ];
    }

    public function profitability(): array
    {
        return $this->db->fetchAll(
            'SELECT p.id, p.name, p.budget, p.actual_cost, (p.budget - p.actual_cost) AS margin, p.actual_hours
             FROM projects p ORDER BY p.created_at DESC'
        );
    }
}
