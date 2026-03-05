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
        $statusColumn = $this->projectStatusColumn();
        $healthColumn = $this->projectHealthColumn();

        $projectsCondition = $whereProjects ?: 'WHERE 1=1';
        $activeFilter = '';
        if ($this->db->columnExists('projects', 'active')) {
            $activeFilter .= ' AND p.active = 1';
        }
        if ($statusColumn !== null) {
            $activeFilter .= " AND p.{$statusColumn} NOT IN ('closed','archived','cancelled')";
        }

        $projectTotals = $this->db->fetchOne(
            "SELECT COUNT(*) AS total,
                    AVG(p.progress) AS avg_progress,
                    SUM(p.planned_hours) AS planned_hours,
                    SUM(p.actual_hours) AS actual_hours,
                    SUM(p.budget) AS total_budget,
                    SUM(p.actual_cost) AS total_cost
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             {$projectsCondition}{$activeFilter}",
            $params
        );

        $atRisk = $healthColumn !== null
            ? $this->db->fetchOne(
                "SELECT COUNT(*) AS total
                 FROM projects p
                 JOIN clients c ON c.id = p.client_id
                 {$projectsCondition}
                 AND p.{$healthColumn} IN ('at_risk','critical','red','yellow')",
                $params
            )
            : ['total' => 0];

        $talentTotals = ['total' => 0];
        if ($this->db->tableExists('talents')) {
            if ($this->isPrivileged($user)) {
                $talentTotals = $this->db->fetchOne('SELECT COUNT(*) AS total FROM talents');
            } elseif ($this->db->tableExists('project_talent_assignments')) {
                $assignmentTalentColumn = $this->db->columnExists('project_talent_assignments', 'talent_id')
                    ? 'talent_id'
                    : 'user_id';
                $talentTotals = $this->db->fetchOne(
                    "SELECT COUNT(DISTINCT a.{$assignmentTalentColumn}) AS total
                     FROM project_talent_assignments a
                     JOIN projects p ON p.id = a.project_id
                     JOIN clients c ON c.id = p.client_id
                     {$projectsCondition}
                     AND (a.assignment_status = 'active' OR (a.assignment_status IS NULL AND a.active = 1))",
                    $params
                );
            }
        }

        $outsourcingTotals = ['total' => 0];
        if ($this->db->tableExists('outsourcing_services')) {
            [$outsourcingWhere, $outsourcingParams] = $this->outsourcingVisibility($user);
            $outsourcingTotals = $this->db->fetchOne(
                "SELECT COUNT(*) AS total
                 FROM outsourcing_services s
                 LEFT JOIN projects p ON p.id = s.project_id
                 {$outsourcingWhere}
                 AND s.service_status = 'active'",
                $outsourcingParams
            );
        }

        return [
            'proyectos_activos' => (int) ($projectTotals['total'] ?? 0),
            'proyectos_riesgo' => (int) ($atRisk['total'] ?? 0),
            'avance_promedio' => round((float) ($projectTotals['avg_progress'] ?? 0), 1),
            'horas_planificadas' => (float) ($projectTotals['planned_hours'] ?? 0),
            'horas_reales' => (float) ($projectTotals['actual_hours'] ?? 0),
            'presupuesto_total' => (float) ($projectTotals['total_budget'] ?? 0),
            'costo_real_total' => (float) ($projectTotals['total_cost'] ?? 0),
            'talentos_activos' => (int) ($talentTotals['total'] ?? 0),
            'outsourcing_activo' => (int) ($outsourcingTotals['total'] ?? 0),
        ];
    }

    public function projectHealth(array $user): array
    {
        [$where, $params] = $this->visibilityForUser($user);
        $statusColumn = $this->projectStatusColumn();
        $healthColumn = $this->projectHealthColumn();

        $projectsCondition = $where ?: 'WHERE 1=1';
        $statusExpr = $statusColumn !== null ? "p.{$statusColumn}" : "''";
        $healthExpr = $healthColumn !== null ? "p.{$healthColumn}" : "''";

        $statusRows = $this->db->fetchAll(
            "SELECT
                CASE
                    WHEN {$statusExpr} IN ('closed','archived','cancelled') THEN 'cerrado'
                    WHEN {$healthExpr} IN ('at_risk','critical','red','yellow') THEN 'en_riesgo'
                    WHEN {$statusExpr} IN ('ideation','planning','draft') THEN 'planning'
                    ELSE 'en_curso'
                END AS bucket,
                COUNT(*) AS total
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             {$projectsCondition}
             GROUP BY bucket",
            $params
        );

        $statusCounts = ['planning' => 0, 'en_curso' => 0, 'en_riesgo' => 0, 'cerrado' => 0];
        foreach ($statusRows as $row) {
            $bucket = $row['bucket'] ?? '';
            if (array_key_exists($bucket, $statusCounts)) {
                $statusCounts[$bucket] = (int) $row['total'];
            }
        }

        $progressByClient = $this->db->fetchAll(
            "SELECT c.name AS client, AVG(p.progress) AS avg_progress
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             {$projectsCondition}
             GROUP BY c.id
             ORDER BY avg_progress DESC, c.name ASC",
            $params
        );

        $staleProjects = $this->db->fetchAll(
            "SELECT p.name, p.updated_at, p.progress, c.name AS client
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             {$projectsCondition}
             AND p.updated_at < DATE_SUB(NOW(), INTERVAL 14 DAY)
             AND p.progress < 100
             AND {$statusExpr} NOT IN ('closed','archived','cancelled')
             ORDER BY p.updated_at ASC
             LIMIT 5",
            $params
        );

        $stageDistribution = [];
        if ($this->db->columnExists('projects', 'project_stage')) {
            $stageDistribution = $this->db->fetchAll(
                "SELECT COALESCE(NULLIF(TRIM(p.project_stage), ''), 'Discovery') AS stage, COUNT(*) AS total
                 FROM projects p
                 JOIN clients c ON c.id = p.client_id
                 {$projectsCondition}
                 GROUP BY stage
                 ORDER BY total DESC, stage ASC",
                $params
            );
        }

        $monthlyProgressTrend = $this->db->fetchAll(
            "SELECT DATE_FORMAT(p.updated_at, '%Y-%m') AS month_key, AVG(p.progress) AS avg_progress
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             {$projectsCondition}
             AND p.updated_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
             GROUP BY month_key
             ORDER BY month_key ASC",
            $params
        );

        return [
            'status_counts' => $statusCounts,
            'progress_by_client' => $progressByClient,
            'stale_projects' => $staleProjects,
            'stale_count' => count($staleProjects),
            'stage_distribution' => $stageDistribution,
            'monthly_progress_trend' => $monthlyProgressTrend,
        ];
    }

    public function executiveIntelligence(array $user): array
    {
        [$where, $params] = $this->visibilityForUser($user);
        $projectsCondition = $where ?: 'WHERE 1=1';
        $statusColumn = $this->projectStatusColumn();
        $healthColumn = $this->projectHealthColumn();
        $statusExpr = $statusColumn !== null ? "p.{$statusColumn}" : "''";

        $staleRow = $this->db->fetchOne(
            "SELECT COUNT(*) AS total
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             {$projectsCondition}
             AND p.updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
             AND p.progress < 100
             AND {$statusExpr} NOT IN ('closed','archived','cancelled')",
            $params
        );

        $highRiskRow = ['total' => 0];
        if ($healthColumn !== null) {
            $highRiskRow = $this->db->fetchOne(
                "SELECT COUNT(*) AS total
                 FROM projects p
                 JOIN clients c ON c.id = p.client_id
                 {$projectsCondition}
                 AND p.{$healthColumn} IN ('critical','red','at_risk')
                 AND {$statusExpr} NOT IN ('closed','archived','cancelled')",
                $params
            );
        }

        $criticalBlockers = 0;
        if ($this->db->tableExists('project_stoppers')) {
            $criticalRow = $this->db->fetchOne(
                "SELECT COUNT(*) AS total
                 FROM project_stoppers s
                 JOIN projects p ON p.id = s.project_id
                 JOIN clients c ON c.id = p.client_id
                 {$projectsCondition}
                 AND s.status IN ('abierto','en_gestion','escalado','resuelto')
                 AND s.impact_level = 'critico'",
                $params
            );
            $criticalBlockers = (int) ($criticalRow['total'] ?? 0);
        }

        $billingPending = 0;
        $totalInvoiced = 0.0;
        $totalCollected = 0.0;
        if ($this->db->tableExists('project_invoices')) {
            $invoiceRows = $this->db->fetchAll(
                "SELECT i.amount, i.status
                 FROM project_invoices i
                 JOIN projects p ON p.id = i.project_id
                 JOIN clients c ON c.id = p.client_id
                 {$projectsCondition}",
                $params
            );
            foreach ($invoiceRows as $invoice) {
                $amount = (float) ($invoice['amount'] ?? 0);
                $status = (string) ($invoice['status'] ?? '');
                if (in_array($status, ['issued', 'sent', 'overdue'], true)) {
                    $billingPending += 1;
                }
                if ($status !== 'void') {
                    $totalInvoiced += $amount;
                }
                if ($status === 'paid') {
                    $totalCollected += $amount;
                }
            }
        }

        $totalContracted = (float) (($this->db->fetchOne(
            "SELECT SUM(p.contract_value) AS total
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             {$projectsCondition}",
            $params
        )['total'] ?? 0));

        $financialExecution = $totalContracted > 0 ? ($totalInvoiced / $totalContracted) * 100 : 0;
        $budgetDeviation = $totalContracted > 0 ? (($totalInvoiced - $totalContracted) / $totalContracted) * 100 : 0;

        $scoreMovement = ['current' => 0.0, 'previous' => 0.0, 'delta_pct' => 0.0];
        if ($this->db->tableExists('project_health_history')) {
            $scoreMovement = $this->movementBetweenMonths(
                "SELECT DATE_FORMAT(h.calculated_at, '%Y-%m') AS month_key, AVG(h.score) AS metric
                 FROM project_health_history h
                 JOIN projects p ON p.id = h.project_id
                 JOIN clients c ON c.id = p.client_id
                 {$projectsCondition}
                 AND h.calculated_at >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH)
                 GROUP BY month_key
                 ORDER BY month_key DESC
                 LIMIT 2",
                $params
            );
        }

        $riskMovement = $this->movementBetweenMonths(
            "SELECT DATE_FORMAT(p.updated_at, '%Y-%m') AS month_key,
                    SUM(CASE WHEN " . ($healthColumn !== null ? "p.{$healthColumn}" : "''") . " IN ('critical','red','at_risk') THEN 1 ELSE 0 END) AS metric
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             {$projectsCondition}
             AND p.updated_at >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH)
             GROUP BY month_key
             ORDER BY month_key DESC
             LIMIT 2",
            $params
        );

        $blockerMovement = $this->movementBetweenMonths(
            "SELECT DATE_FORMAT(s.created_at, '%Y-%m') AS month_key, COUNT(*) AS metric
             FROM project_stoppers s
             JOIN projects p ON p.id = s.project_id
             JOIN clients c ON c.id = p.client_id
             {$projectsCondition}
             AND s.status IN ('abierto','en_gestion','escalado','resuelto')
             AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH)
             GROUP BY month_key
             ORDER BY month_key DESC
             LIMIT 2",
            $params,
            !$this->db->tableExists('project_stoppers')
        );

        $billingMovement = $this->movementBetweenMonths(
            "SELECT DATE_FORMAT(i.issued_at, '%Y-%m') AS month_key,
                    SUM(CASE WHEN i.status IN ('issued','sent','overdue') THEN i.amount ELSE 0 END) AS metric
             FROM project_invoices i
             JOIN projects p ON p.id = i.project_id
             JOIN clients c ON c.id = p.client_id
             {$projectsCondition}
             AND i.issued_at >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH)
             GROUP BY month_key
             ORDER BY month_key DESC
             LIMIT 2",
            $params,
            !$this->db->tableExists('project_invoices')
        );

        $riskExposure = ($criticalBlockers * 4) + ((int) ($highRiskRow['total'] ?? 0) * 3) + ((int) ($staleRow['total'] ?? 0) * 2);

        $portfolioTotalRow = $this->db->fetchOne(
            "SELECT COUNT(*) AS total
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             {$projectsCondition}
             AND {$statusExpr} NOT IN ('closed','archived','cancelled')",
            $params
        );
        $portfolioTotal = max(1, (int) ($portfolioTotalRow['total'] ?? 0));
        $highRiskProjects = (int) ($highRiskRow['total'] ?? 0);
        $highRiskPct = ($highRiskProjects / $portfolioTotal) * 100;

        $progressMovement = $this->movementBetweenMonths(
            "SELECT DATE_FORMAT(p.updated_at, '%Y-%m') AS month_key, AVG(p.progress) AS metric
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             {$projectsCondition}
             AND p.updated_at >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH)
             GROUP BY month_key
             ORDER BY month_key DESC
             LIMIT 2",
            $params
        );

        $scoreGeneral = (float) ($scoreMovement['current'] ?? 0);
        if ($scoreGeneral <= 0) {
            $scoreGeneral = (float) ($this->portfolioHealthAverage($user)['average_score'] ?? 0);
        }

        $activeBlockers = (int) round((float) ($blockerMovement['current'] ?? 0));
        $monthlyTrend = (float) ($scoreMovement['delta_pct'] ?? 0);
        $avgProgress = (float) ($progressMovement['current'] ?? 0);
        $billingPlanPct = (float) $financialExecution;

        $flags = [];
        if ($criticalBlockers > 0) {
            $flags[] = [
                'type' => 'prioritaria',
                'title' => 'Alerta prioritaria por bloqueos críticos',
                'detail' => "Se identificaron {$criticalBlockers} bloqueos críticos activos que requieren escalamiento inmediato.",
            ];
        }
        if ($highRiskPct > 20) {
            $flags[] = [
                'type' => 'advertencia',
                'title' => 'Advertencia por exposición de riesgo',
                'detail' => 'El porcentaje de proyectos en riesgo alto supera el 20% del portafolio activo.',
            ];
        }
        if ((float) ($progressMovement['delta_pct'] ?? 0) < 0) {
            $flags[] = [
                'type' => 'negativa',
                'title' => 'Señal negativa en avance',
                'detail' => 'El avance promedio cayó frente al mes anterior y puede afectar compromisos de entrega.',
            ];
        }
        if ($billingPlanPct < 70) {
            $flags[] = [
                'type' => 'financiera',
                'title' => 'Alerta financiera',
                'detail' => 'La ejecución de facturación está por debajo del 70% del plan contratado.',
            ];
        }

        $criticalityLevel = 'Baja';
        if ($criticalBlockers > 0 || $billingPlanPct < 70 || $highRiskPct > 20) {
            $criticalityLevel = 'Alta';
        } elseif ((float) ($progressMovement['delta_pct'] ?? 0) < 0 || $highRiskPct > 12) {
            $criticalityLevel = 'Media';
        }

        $diagnosis = 'Portafolio estable y bajo control, sin señales de deterioro relevante.';
        if ($criticalityLevel === 'Alta') {
            $diagnosis = 'El portafolio presenta desviaciones críticas en riesgo, ejecución o continuidad operativa que exigen intervención ejecutiva inmediata.';
        } elseif ($criticalityLevel === 'Media') {
            $diagnosis = 'Se observan señales tempranas de deterioro que requieren seguimiento cercano y planes correctivos preventivos.';
        }

        $recommendations = [];
        if ($criticalBlockers > 0) {
            $recommendations[] = 'Convocar comité de desbloqueo en las próximas 24 horas y asignar dueño ejecutivo por cada bloqueo crítico.';
        }
        if ($highRiskPct > 20) {
            $recommendations[] = 'Repriorizar el portafolio con foco en proyectos de mayor valor y activar planes de mitigación de riesgo por frente.';
        }
        if ((float) ($progressMovement['delta_pct'] ?? 0) < 0) {
            $recommendations[] = 'Implementar plan de recuperación de avance con hitos semanales y revisión PMO en tablero de control.';
        }
        if ($billingPlanPct < 70) {
            $recommendations[] = 'Ejecutar plan de aceleración de facturación: cierre de hitos pendientes, validaciones con cliente y calendario de emisión.';
        }
        if ($recommendations === []) {
            $recommendations = [
                'Mantener cadencia de seguimiento semanal con foco en prevención de bloqueos y riesgos emergentes.',
                'Consolidar lecciones aprendidas de proyectos con mejor desempeño para replicarlas en todo el portafolio.',
                'Sostener disciplina financiera validando avance contra hitos facturables en cada comité mensual.',
            ];
        }
        $recommendations = array_slice($recommendations, 0, 3);

        return [
            'alerts' => [
                'stale_projects' => (int) ($staleRow['total'] ?? 0),
                'high_risk_projects' => (int) ($highRiskRow['total'] ?? 0),
                'critical_blockers' => $criticalBlockers,
                'billing_pending' => $billingPending,
            ],
            'movement' => [
                'score' => $scoreMovement,
                'risk_projects' => $riskMovement,
                'active_blockers' => $blockerMovement,
                'billing_pending' => $billingMovement,
            ],
            'financial_impact' => [
                'total_contracted' => $totalContracted,
                'total_invoiced' => $totalInvoiced,
                'total_collected' => $totalCollected,
                'execution_pct' => $financialExecution,
                'budget_deviation_pct' => $budgetDeviation,
            ],
            'intelligent_analysis' => [
                'inputs' => [
                    'score_general' => round($scoreGeneral, 1),
                    'projects_at_risk' => $highRiskProjects,
                    'projects_at_risk_pct' => round($highRiskPct, 1),
                    'active_blockers' => $activeBlockers,
                    'monthly_trend_pct' => round($monthlyTrend, 1),
                    'billing_execution_pct' => round($billingPlanPct, 1),
                    'average_progress' => round($avgProgress, 1),
                    'progress_delta_pct' => round((float) ($progressMovement['delta_pct'] ?? 0), 1),
                ],
                'flags' => $flags,
                'diagnosis' => $diagnosis,
                'recommendations' => $recommendations,
                'criticality' => $criticalityLevel,
            ],
            'risk_exposure' => $riskExposure,
        ];
    }



    public function portfolioHealthAverage(array $user): array
    {
        [$where, $params] = $this->visibilityForUser($user);
        $projectsCondition = $where ?: 'WHERE 1=1';

        $rows = $this->db->fetchAll(
            "SELECT p.id
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             {$projectsCondition}",
            $params
        );

        $service = new ProjectService($this->db);
        $total = 0;
        $count = 0;

        foreach ($rows as $row) {
            $projectId = (int) ($row['id'] ?? 0);
            if ($projectId <= 0) {
                continue;
            }

            $score = $service->calculateProjectHealthScore($projectId);
            $total += (int) ($score['total_score'] ?? 0);
            $count++;
        }

        $average = $count > 0 ? (int) round($total / $count) : 0;

        return [
            'average_score' => $average,
            'projects_count' => $count,
        ];
    }


    public function portfolioHealthInsights(array $user): array
    {
        [$where, $params] = $this->visibilityForUser($user);
        $projectsCondition = $where ?: 'WHERE 1=1';

        $rows = $this->db->fetchAll(
            "SELECT p.id, p.name, c.name AS client, p.updated_at, p.budget
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             {$projectsCondition}",
            $params
        );

        $service = new ProjectService($this->db);
        $projects = [];
        foreach ($rows as $row) {
            $projectId = (int) ($row['id'] ?? 0);
            if ($projectId <= 0) {
                continue;
            }

            $report = $service->calculateProjectHealthReport($projectId);
            $history = $service->history($projectId, 30);
            $first = $history[0]['score'] ?? ($report['total_score'] ?? 0);
            $last = $history !== [] ? ($history[count($history) - 1]['score'] ?? ($report['total_score'] ?? 0)) : ($report['total_score'] ?? 0);

            $blockersOpen = 0;
            if ($this->db->tableExists('project_stoppers')) {
                $openStopper = $this->db->fetchOne(
                    "SELECT COUNT(*) AS total
                     FROM project_stoppers s
                     WHERE s.project_id = :projectId
                     AND s.status IN ('abierto','en_gestion','escalado','resuelto')",
                    [':projectId' => $projectId]
                );
                $blockersOpen = (int) ($openStopper['total'] ?? 0);
            }

            $projects[] = [
                'id' => $projectId,
                'name' => (string) ($row['name'] ?? ''),
                'client' => (string) ($row['client'] ?? ''),
                'score' => (int) ($report['total_score'] ?? 0),
                'level' => (string) ($report['level'] ?? 'critical'),
                'trend_delta' => (int) $last - (int) $first,
                'risk' => $this->riskLabel((int) ($report['total_score'] ?? 0)),
                'blockers_open' => $blockersOpen,
                'billing' => (float) ($row['budget'] ?? 0),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
            ];
        }

        usort($projects, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        $ranking = $projects;

        usort($projects, static fn (array $a, array $b): int => $a['score'] <=> $b['score']);
        $topRisk = array_slice($projects, 0, 5);

        $avgTrend = 0;
        if ($ranking !== []) {
            $sum = array_reduce($ranking, static fn (int $carry, array $item): int => $carry + (int) ($item['trend_delta'] ?? 0), 0);
            $avgTrend = (int) round($sum / count($ranking));
        }

        $heatmap = [];
        foreach ($ranking as $item) {
            $client = $item['client'] ?: 'Sin cliente';
            if (!isset($heatmap[$client])) {
                $heatmap[$client] = ['client' => $client, 'projects' => 0, 'avg_score' => 0, 'risk_count' => 0];
            }
            $heatmap[$client]['projects']++;
            $heatmap[$client]['avg_score'] += (int) $item['score'];
            if ((int) $item['score'] < 75) {
                $heatmap[$client]['risk_count']++;
            }
        }
        foreach ($heatmap as $client => $item) {
            $heatmap[$client]['avg_score'] = $item['projects'] > 0 ? (int) round($item['avg_score'] / $item['projects']) : 0;
        }

        return [
            'ranking' => $ranking,
            'top_risk' => $topRisk,
            'portfolio_trend_avg' => $avgTrend,
            'client_heatmap' => array_values($heatmap),
        ];
    }

    public function timesheetOverview(array $user): array
    {
        [$where, $params] = $this->visibilityForUser($user);
        $projectsCondition = $where ?: 'WHERE 1=1';

        $period = $this->weeklyPeriod();
        $weeklyHours = 0.0;
        $pendingHours = 0.0;
        $todayHours = 0.0;
        $pendingApprovalsCount = 0;
        $hoursByProject = [];
        $hoursByTalent = [];

        if ($this->db->tableExists('timesheets') && $this->db->tableExists('tasks')) {
            $weeklyHoursRow = $this->db->fetchOne(
                "SELECT SUM(ts.hours) AS total
                 FROM timesheets ts
                 JOIN tasks t ON t.id = ts.task_id
                 JOIN projects p ON p.id = t.project_id
                 JOIN clients c ON c.id = p.client_id
                 {$projectsCondition}
                 AND ts.status = 'approved'
                 AND ts.date BETWEEN :start AND :end",
                array_merge($params, [':start' => $period['start'], ':end' => $period['end']])
            );
            $weeklyHours = (float) ($weeklyHoursRow['total'] ?? 0);

            $pendingRow = $this->db->fetchOne(
                "SELECT SUM(ts.hours) AS total
                 FROM timesheets ts
                 JOIN tasks t ON t.id = ts.task_id
                 JOIN projects p ON p.id = t.project_id
                 JOIN clients c ON c.id = p.client_id
                 {$projectsCondition}
                 AND ts.status IN ('pending','submitted','pending_approval')",
                $params
            );
            $pendingHours = (float) ($pendingRow['total'] ?? 0);

            $pendingCountRow = $this->db->fetchOne(
                "SELECT COUNT(*) AS total
                 FROM timesheets ts
                 JOIN tasks t ON t.id = ts.task_id
                 JOIN projects p ON p.id = t.project_id
                 JOIN clients c ON c.id = p.client_id
                 {$projectsCondition}
                 AND ts.status IN ('pending','submitted','pending_approval')",
                $params
            );
            $pendingApprovalsCount = (int) ($pendingCountRow['total'] ?? 0);

            $todayRow = $this->db->fetchOne(
                "SELECT SUM(ts.hours) AS total
                 FROM timesheets ts
                 JOIN tasks t ON t.id = ts.task_id
                 JOIN projects p ON p.id = t.project_id
                 JOIN clients c ON c.id = p.client_id
                 {$projectsCondition}
                 AND ts.date = :today",
                array_merge($params, [':today' => date('Y-m-d')])
            );
            $todayHours = (float) ($todayRow['total'] ?? 0);

            $hoursByProject = $this->db->fetchAll(
                "SELECT p.name AS project, SUM(ts.hours) AS total_hours
                 FROM timesheets ts
                 JOIN tasks t ON t.id = ts.task_id
                 JOIN projects p ON p.id = t.project_id
                 JOIN clients c ON c.id = p.client_id
                 {$projectsCondition}
                 AND ts.status = 'approved'
                 GROUP BY p.id
                 ORDER BY total_hours DESC
                 LIMIT 5",
                $params
            );

            if ($this->db->tableExists('talents')) {
                $hoursByTalent = $this->db->fetchAll(
                    "SELECT ta.name AS talent, SUM(ts.hours) AS total_hours
                     FROM timesheets ts
                     JOIN tasks t ON t.id = ts.task_id
                     JOIN projects p ON p.id = t.project_id
                     JOIN clients c ON c.id = p.client_id
                     JOIN talents ta ON ta.id = ts.talent_id
                     {$projectsCondition}
                     AND ts.status = 'approved'
                     GROUP BY ta.id
                     ORDER BY total_hours DESC
                     LIMIT 5",
                    $params
                );
            }
        }

        $talentsWithoutReport = 0;
        if ($this->db->tableExists('talents') && $this->db->tableExists('tasks')) {
            $row = $this->db->fetchOne(
                "SELECT COUNT(DISTINCT t.id) AS total
                 FROM talents t
                 JOIN tasks tk ON tk.assignee_id = t.id
                 JOIN projects p ON p.id = tk.project_id
                 JOIN clients c ON c.id = p.client_id
                 LEFT JOIN timesheets ts ON ts.task_id = tk.id AND ts.date BETWEEN :start AND :end
                 {$projectsCondition}
                 AND t.requiere_reporte_horas = 1
                 AND tk.status = 'in_progress'
                 AND ts.id IS NULL",
                array_merge($params, [':start' => $period['start'], ':end' => $period['end']])
            );
            $talentsWithoutReport = (int) ($row['total'] ?? 0);
        }

        $internalTalents = 0;
        $externalTalents = 0;
        if ($this->db->tableExists('talents')) {
            $joins = '';
            $talentConditions = [];
            $talentParams = [];

            if (!$this->isPrivileged($user) && $this->db->tableExists('project_talent_assignments')) {
                $assignmentJoin = $this->db->columnExists('project_talent_assignments', 'talent_id')
                    ? 'a.talent_id = t.id'
                    : 'a.user_id = t.user_id';
                $joins = 'JOIN project_talent_assignments a ON ' . $assignmentJoin . '
                          JOIN projects p ON p.id = a.project_id
                          JOIN clients c ON c.id = p.client_id';
                if ($this->db->columnExists('projects', 'pm_id')) {
                    $talentConditions[] = 'p.pm_id = :pmId';
                    $talentParams[':pmId'] = $user['id'];
                }
                $talentConditions[] = "(a.assignment_status = 'active' OR (a.assignment_status IS NULL AND a.active = 1))";
            }

            $talentWhere = $talentConditions ? ('WHERE ' . implode(' AND ', $talentConditions)) : 'WHERE 1=1';

            if ($this->db->columnExists('talents', 'tipo_talento')) {
                $internalRow = $this->db->fetchOne(
                    "SELECT COUNT(DISTINCT t.id) AS total FROM talents t {$joins} {$talentWhere} AND t.tipo_talento = 'interno'",
                    $talentParams
                );
                $externalRow = $this->db->fetchOne(
                    "SELECT COUNT(DISTINCT t.id) AS total FROM talents t {$joins} {$talentWhere} AND t.tipo_talento = 'externo'",
                    $talentParams
                );
            } else {
                $internalRow = $this->db->fetchOne(
                    "SELECT COUNT(DISTINCT t.id) AS total FROM talents t {$joins} {$talentWhere}",
                    $talentParams
                );
                $externalRow = ['total' => 0];
            }
            $internalTalents = (int) ($internalRow['total'] ?? 0);
            $externalTalents = (int) ($externalRow['total'] ?? 0);
        }

        return [
            'weekly_hours' => $weeklyHours,
            'pending_hours' => $pendingHours,
            'today_hours' => $todayHours,
            'pending_approvals_count' => $pendingApprovalsCount,
            'hours_by_project' => $hoursByProject,
            'hours_by_talent' => $hoursByTalent,
            'talents_without_report' => $talentsWithoutReport,
            'internal_talents' => $internalTalents,
            'external_talents' => $externalTalents,
            'period_start' => $period['start'],
            'period_end' => $period['end'],
        ];
    }

    public function outsourcingOverview(array $user): array
    {
        $activeServices = 0;
        $openFollowups = 0;
        $attentionServices = 0;
        $lastFollowups = [];

        if ($this->db->tableExists('outsourcing_services')) {
            [$where, $params] = $this->outsourcingVisibility($user);

            $activeRow = $this->db->fetchOne(
                "SELECT COUNT(*) AS total
                 FROM outsourcing_services s
                 LEFT JOIN projects p ON p.id = s.project_id
                 {$where}
                 AND s.service_status = 'active'",
                $params
            );
            $activeServices = (int) ($activeRow['total'] ?? 0);

            if ($this->db->tableExists('outsourcing_followups')) {
                $openRow = $this->db->fetchOne(
                    "SELECT COUNT(*) AS total
                     FROM outsourcing_followups f
                     JOIN outsourcing_services s ON s.id = f.service_id
                     LEFT JOIN projects p ON p.id = s.project_id
                     {$where}
                     AND f.followup_status = 'open'",
                    $params
                );
                $openFollowups = (int) ($openRow['total'] ?? 0);

                $attentionRow = $this->db->fetchOne(
                    "SELECT COUNT(*) AS total
                     FROM outsourcing_services s
                     LEFT JOIN projects p ON p.id = s.project_id
                     JOIN outsourcing_followups f ON f.service_id = s.id
                     JOIN (
                        SELECT service_id, MAX(created_at) AS max_created
                        FROM outsourcing_followups
                        GROUP BY service_id
                     ) latest ON latest.service_id = f.service_id AND latest.max_created = f.created_at
                     {$where}
                     AND f.service_health IN ('yellow','red')",
                    $params
                );
                $attentionServices = (int) ($attentionRow['total'] ?? 0);

                $followups = $this->db->fetchAll(
                    "SELECT c.name AS client, f.service_health, f.period_end, f.created_at
                     FROM outsourcing_followups f
                     JOIN outsourcing_services s ON s.id = f.service_id
                     JOIN clients c ON c.id = s.client_id
                     LEFT JOIN projects p ON p.id = s.project_id
                     {$where}
                     ORDER BY f.created_at DESC",
                    $params
                );

                $seenClients = [];
                foreach ($followups as $followup) {
                    $clientName = (string) ($followup['client'] ?? '');
                    if ($clientName === '' || isset($seenClients[$clientName])) {
                        continue;
                    }
                    $seenClients[$clientName] = true;
                    $lastFollowups[] = $followup;
                    if (count($lastFollowups) >= 5) {
                        break;
                    }
                }
            }
        }

        return [
            'active_services' => $activeServices,
            'open_followups' => $openFollowups,
            'attention_services' => $attentionServices,
            'last_followups' => $lastFollowups,
        ];
    }

    public function requirementsOverview(array $user): array
    {
        $repo = new RequirementsRepository($this->db);
        $filters = [
            'start_date' => $_GET['start_date'] ?? date('Y-m-01'),
            'end_date' => $_GET['end_date'] ?? date('Y-m-t'),
            'client_id' => $_GET['client_id'] ?? null,
            'pm_id' => $_GET['pm_id'] ?? null,
        ];

        $projects = $repo->indicatorByProject($filters);
        usort($projects, static fn (array $a, array $b): int => (float) ($b['indicator'] ?? -1) <=> (float) ($a['indicator'] ?? -1));

        $trend = [];
        foreach ($projects as $project) {
            $trend[(string) ($project['project'] ?? '')] = $repo->trendForProject((int) ($project['project_id'] ?? 0), 6);
        }

        return [
            'filters' => $filters,
            'projects' => $projects,
            'ranking' => $projects,
            'trend' => $trend,
        ];
    }

    public function governanceOverview(array $user): array
    {
        [$where, $params] = $this->visibilityForUser($user);
        $projectsCondition = $where ?: 'WHERE 1=1';

        $documentTotals = ['revision' => 0, 'validacion' => 0, 'aprobacion' => 0];
        if ($this->db->tableExists('project_nodes')) {
            $rows = $this->db->fetchAll(
                "SELECT pn.document_status, COUNT(*) AS total
                 FROM project_nodes pn
                 JOIN projects p ON p.id = pn.project_id
                 JOIN clients c ON c.id = p.client_id
                 {$projectsCondition}
                 AND pn.document_status IN ('en_revision','en_validacion','en_aprobacion')
                 GROUP BY pn.document_status",
                $params
            );
            foreach ($rows as $row) {
                $status = $row['document_status'] ?? '';
                if ($status === 'en_revision') {
                    $documentTotals['revision'] = (int) $row['total'];
                } elseif ($status === 'en_validacion') {
                    $documentTotals['validacion'] = (int) $row['total'];
                } elseif ($status === 'en_aprobacion') {
                    $documentTotals['aprobacion'] = (int) $row['total'];
                }
            }
        }

        $scopeChanges = 0;
        if ($this->db->tableExists('project_design_changes')) {
            $row = $this->db->fetchOne(
                "SELECT COUNT(*) AS total
                 FROM project_design_changes dc
                 JOIN projects p ON p.id = dc.project_id
                 JOIN clients c ON c.id = p.client_id
                 {$projectsCondition}
                 AND dc.status = 'pendiente'",
                $params
            );
            $scopeChanges = (int) ($row['total'] ?? 0);
        }

        $criticalRisks = 0;
        if ($this->db->tableExists('project_risk_evaluations')) {
            $row = $this->db->fetchOne(
                "SELECT COUNT(DISTINCT pr.project_id) AS total
                 FROM project_risk_evaluations pr
                 JOIN risk_catalog rc ON rc.code = pr.risk_code
                 JOIN projects p ON p.id = pr.project_id
                 JOIN clients c ON c.id = p.client_id
                 {$projectsCondition}
                 AND pr.selected = 1
                 AND rc.severity_base >= 4",
                $params
            );
            $criticalRisks = (int) ($row['total'] ?? 0);
        }

        $overdueFollowups = 0;
        if ($this->db->tableExists('outsourcing_followups')) {
            [$outsourcingWhere, $outsourcingParams] = $this->outsourcingVisibility($user);
            $row = $this->db->fetchOne(
                "SELECT COUNT(*) AS total
                 FROM outsourcing_followups f
                 JOIN outsourcing_services s ON s.id = f.service_id
                 LEFT JOIN projects p ON p.id = s.project_id
                 {$outsourcingWhere}
                 AND f.followup_status = 'open'
                 AND f.period_end < CURDATE()",
                $outsourcingParams
            );
            $overdueFollowups = (int) ($row['total'] ?? 0);
        }

        return [
            'documents_revision' => $documentTotals['revision'],
            'documents_validacion' => $documentTotals['validacion'],
            'documents_aprobacion' => $documentTotals['aprobacion'],
            'scope_changes_pending' => $scopeChanges,
            'critical_risks' => $criticalRisks,
            'outsourcing_overdue' => $overdueFollowups,
        ];
    }

    public function stoppersOverview(array $user): array
    {
        if (!$this->db->tableExists('project_stoppers')) {
            return [
                'top_active' => [],
                'monthly_trend' => [],
                'critical_projects' => [],
                'severity_counts' => ['critico' => 0, 'alto' => 0, 'medio' => 0, 'bajo' => 0],
                'open_total' => 0,
                'critical_total' => 0,
                'avg_open_days' => 0,
            ];
        }

        [$where, $params] = $this->visibilityForUser($user);
        $projectsCondition = $where ?: 'WHERE 1=1';

        $topActive = $this->db->fetchAll(
            "SELECT p.id AS project_id, p.name AS project, c.name AS client, COUNT(*) AS active_total
             FROM project_stoppers s
             JOIN projects p ON p.id = s.project_id
             JOIN clients c ON c.id = p.client_id
             {$projectsCondition}
             AND s.status IN ('abierto','en_gestion','escalado','resuelto')
             GROUP BY p.id, p.name, c.name
             ORDER BY active_total DESC, p.name ASC
             LIMIT 10",
            $params
        );

        $monthlyTrend = $this->db->fetchAll(
            "SELECT DATE_FORMAT(s.created_at, '%Y-%m') AS month_key, COUNT(*) AS total
             FROM project_stoppers s
             JOIN projects p ON p.id = s.project_id
             JOIN clients c ON c.id = p.client_id
             {$projectsCondition}
             AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
             GROUP BY month_key
             ORDER BY month_key ASC",
            $params
        );

        $criticalProjects = $this->db->fetchAll(
            "SELECT DISTINCT p.id AS project_id, p.name AS project, c.name AS client
             FROM project_stoppers s
             JOIN projects p ON p.id = s.project_id
             JOIN clients c ON c.id = p.client_id
             {$projectsCondition}
             AND s.status IN ('abierto','en_gestion','escalado','resuelto')
             AND s.impact_level = 'critico'
             ORDER BY p.name ASC",
            $params
        );

        $severityRows = $this->db->fetchAll(
            "SELECT COALESCE(NULLIF(TRIM(s.impact_level), ''), 'medio') AS severity, COUNT(*) AS total
             FROM project_stoppers s
             JOIN projects p ON p.id = s.project_id
             JOIN clients c ON c.id = p.client_id
             {$projectsCondition}
             AND s.status IN ('abierto','en_gestion','escalado','resuelto')
             GROUP BY severity",
            $params
        );

        $severityCounts = ['critico' => 0, 'alto' => 0, 'medio' => 0, 'bajo' => 0];
        foreach ($severityRows as $row) {
            $severity = strtolower((string) ($row['severity'] ?? 'medio'));
            if (array_key_exists($severity, $severityCounts)) {
                $severityCounts[$severity] = (int) ($row['total'] ?? 0);
            }
        }

        $openTotalRow = $this->db->fetchOne(
            "SELECT COUNT(*) AS total
             FROM project_stoppers s
             JOIN projects p ON p.id = s.project_id
             JOIN clients c ON c.id = p.client_id
             {$projectsCondition}
             AND s.status IN ('abierto','en_gestion','escalado','resuelto')",
            $params
        );

        $avgDaysRow = $this->db->fetchOne(
            "SELECT AVG(DATEDIFF(CURDATE(), DATE(s.created_at))) AS avg_days
             FROM project_stoppers s
             JOIN projects p ON p.id = s.project_id
             JOIN clients c ON c.id = p.client_id
             {$projectsCondition}
             AND s.status IN ('abierto','en_gestion','escalado','resuelto')",
            $params
        );

        return [
            'top_active' => $topActive,
            'monthly_trend' => $monthlyTrend,
            'critical_projects' => $criticalProjects,
            'severity_counts' => $severityCounts,
            'open_total' => (int) ($openTotalRow['total'] ?? 0),
            'critical_total' => (int) ($severityCounts['critico'] ?? 0),
            'avg_open_days' => (int) round((float) ($avgDaysRow['avg_days'] ?? 0)),
        ];
    }

    private function riskLabel(int $score): string
    {
        if ($score >= 80) {
            return 'Bajo';
        }
        if ($score >= 60) {
            return 'Medio';
        }

        return 'Crítico';
    }

    private function movementBetweenMonths(string $sql, array $params, bool $disabled = false): array
    {
        if ($disabled) {
            return ['current' => 0.0, 'previous' => 0.0, 'delta_pct' => 0.0];
        }

        $rows = $this->db->fetchAll($sql, $params);
        $current = isset($rows[0]['metric']) ? (float) $rows[0]['metric'] : 0.0;
        $previous = isset($rows[1]['metric']) ? (float) $rows[1]['metric'] : 0.0;
        $delta = $previous !== 0.0 ? (($current - $previous) / abs($previous)) * 100 : 0.0;

        return [
            'current' => $current,
            'previous' => $previous,
            'delta_pct' => $delta,
        ];
    }

    public function alerts(array $user): array
    {
        $alerts = [];

        $stale = $this->staleProjects($user, 3);
        foreach ($stale as $project) {
            $alerts[] = sprintf(
                'Proyecto %s sin avance en 14 días (%s).',
                $project['name'] ?? 'sin nombre',
                $project['client'] ?? 'cliente'
            );
        }

        foreach ($this->redOutsourcingServices($user, 2) as $service) {
            $alerts[] = sprintf(
                'Servicio outsourcing %s en rojo (%s).',
                $service['talent_name'] ?? 'sin talento',
                $service['client'] ?? 'cliente'
            );
        }

        foreach ($this->talentsWithoutReport($user, 2) as $talent) {
            $alerts[] = sprintf('Talento %s no reporta horas esta semana.', $talent['name'] ?? 'sin nombre');
        }

        foreach ($this->criticalDocumentsPending($user, 2) as $doc) {
            $alerts[] = sprintf(
                'Documento crítico %s sin aprobación (%s).',
                $doc['title'] ?? 'sin título',
                $doc['project'] ?? 'proyecto'
            );
        }

        if (!$alerts) {
            $alerts[] = 'No hay alertas críticas activas en este momento.';
        }

        return $alerts;
    }

    private function staleProjects(array $user, int $limit): array
    {
        [$where, $params] = $this->visibilityForUser($user);
        $projectsCondition = $where ?: 'WHERE 1=1';
        $statusColumn = $this->projectStatusColumn();
        $statusExpr = $statusColumn !== null ? "p.{$statusColumn}" : "''";

        return $this->db->fetchAll(
            "SELECT p.name, p.updated_at, p.progress, c.name AS client
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             {$projectsCondition}
             AND p.updated_at < DATE_SUB(NOW(), INTERVAL 14 DAY)
             AND p.progress < 100
             AND {$statusExpr} NOT IN ('closed','archived','cancelled')
             ORDER BY p.updated_at ASC
             LIMIT {$limit}",
            $params
        );
    }

    private function redOutsourcingServices(array $user, int $limit): array
    {
        if (!$this->db->tableExists('outsourcing_services') || !$this->db->tableExists('outsourcing_followups')) {
            return [];
        }

        [$where, $params] = $this->outsourcingVisibility($user);

        return $this->db->fetchAll(
            "SELECT s.id, u.name AS talent_name, c.name AS client
             FROM outsourcing_services s
             JOIN users u ON u.id = s.talent_id
             JOIN clients c ON c.id = s.client_id
             LEFT JOIN projects p ON p.id = s.project_id
             JOIN outsourcing_followups f ON f.service_id = s.id
             JOIN (
                SELECT service_id, MAX(created_at) AS max_created
                FROM outsourcing_followups
                GROUP BY service_id
             ) latest ON latest.service_id = f.service_id AND latest.max_created = f.created_at
             {$where}
             AND f.service_health = 'red'
             ORDER BY f.created_at DESC
             LIMIT {$limit}",
            $params
        );
    }

    private function talentsWithoutReport(array $user, int $limit): array
    {
        if (!$this->db->tableExists('talents') || !$this->db->tableExists('tasks')) {
            return [];
        }

        [$where, $params] = $this->visibilityForUser($user);
        $projectsCondition = $where ?: 'WHERE 1=1';
        $period = $this->weeklyPeriod();

        return $this->db->fetchAll(
            "SELECT DISTINCT t.name
             FROM talents t
             JOIN tasks tk ON tk.assignee_id = t.id
             JOIN projects p ON p.id = tk.project_id
             JOIN clients c ON c.id = p.client_id
             LEFT JOIN timesheets ts ON ts.task_id = tk.id AND ts.date BETWEEN :start AND :end
             {$projectsCondition}
             AND t.requiere_reporte_horas = 1
             AND tk.status = 'in_progress'
             AND ts.id IS NULL
             ORDER BY t.name ASC
             LIMIT {$limit}",
            array_merge($params, [':start' => $period['start'], ':end' => $period['end']])
        );
    }

    private function criticalDocumentsPending(array $user, int $limit): array
    {
        if (!$this->db->tableExists('project_nodes')) {
            return [];
        }

        [$where, $params] = $this->visibilityForUser($user);
        $projectsCondition = $where ?: 'WHERE 1=1';

        return $this->db->fetchAll(
            "SELECT pn.title, p.name AS project
             FROM project_nodes pn
             JOIN projects p ON p.id = pn.project_id
             JOIN clients c ON c.id = p.client_id
             {$projectsCondition}
             AND pn.critical = 1
             AND pn.document_status = 'en_aprobacion'
             ORDER BY pn.created_at DESC
             LIMIT {$limit}",
            $params
        );
    }

    private function visibilityForUser(array $user): array
    {
        if ($this->isPrivileged($user)) {
            return ['', []];
        }

        if (!$this->db->columnExists('projects', 'pm_id')) {
            return ['', []];
        }

        return ['WHERE p.pm_id = :pmId', [':pmId' => $user['id']]];
    }

    private function outsourcingVisibility(array $user): array
    {
        if ($this->isPrivileged($user)) {
            return ['WHERE 1=1', []];
        }

        if (!$this->db->columnExists('projects', 'pm_id')) {
            return ['WHERE 1=1', []];
        }

        return ['WHERE p.pm_id = :pmId', [':pmId' => $user['id']]];
    }

    private function projectStatusColumn(): ?string
    {
        if ($this->db->columnExists('projects', 'status_code')) {
            return 'status_code';
        }

        if ($this->db->columnExists('projects', 'status')) {
            return 'status';
        }

        return null;
    }

    private function projectHealthColumn(): ?string
    {
        if ($this->db->columnExists('projects', 'health_code')) {
            return 'health_code';
        }

        if ($this->db->columnExists('projects', 'health')) {
            return 'health';
        }

        return null;
    }

    private function assignmentTalentColumn(): ?string
    {
        if ($this->db->columnExists('project_talent_assignments', 'talent_id')) {
            return 'talent_id';
        }

        if ($this->db->columnExists('project_talent_assignments', 'user_id')) {
            return 'user_id';
        }

        return null;
    }

    private function weeklyPeriod(): array
    {
        $now = new DateTimeImmutable('now');
        $start = $now->modify('monday this week');
        $end = $start->modify('+6 days');

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
        ];
    }

    private function isPrivileged(array $user): bool
    {
        return in_array($user['role'] ?? '', self::ADMIN_ROLES, true);
    }

    /**
     * Análisis automático del portafolio: genera 3-5 conclusiones en lenguaje natural.
     */
    public function automaticPortfolioAnalysis(array $user): array
    {
        $summary = $this->executiveSummary($user);
        $projects = $this->projectHealth($user);
        $stoppers = $this->stoppersOverview($user);
        $execIntel = $this->executiveIntelligence($user);
        $utilization = $this->talentUtilization($user);
        $financial = $execIntel['financial_impact'] ?? [];

        $avgProgress = (float) ($summary['avance_promedio'] ?? 0);
        $riskCount = (int) ($summary['proyectos_riesgo'] ?? 0);
        $activeBlockers = (int) ($stoppers['open_total'] ?? 0);
        $utilPct = (float) ($utilization['avg_utilization'] ?? 0);
        $monthlyTrend = $projects['monthly_progress_trend'] ?? [];
        $billingPct = (float) ($financial['execution_pct'] ?? 0);

        $trendDirection = 'estable';
        if (count($monthlyTrend) >= 2) {
            $last = (float) ($monthlyTrend[count($monthlyTrend) - 1]['avg_progress'] ?? 0);
            $prev = (float) ($monthlyTrend[count($monthlyTrend) - 2]['avg_progress'] ?? 0);
            $trendDirection = $last > $prev ? 'ascendente' : ($last < $prev ? 'descendente' : 'estable');
        }

        $conclusions = [];

        $conclusions[] = sprintf(
            'El portafolio presenta ejecución %s con avance promedio del %s%%.',
            $avgProgress >= 70 ? 'estable' : ($avgProgress >= 50 ? 'moderada' : 'lenta'),
            number_format($avgProgress, 1, ',', '.')
        );

        if ($activeBlockers > 0) {
            $conclusions[] = sprintf(
                'Existe%s %d bloqueo%s activo%s que %s atención operativa.',
                $activeBlockers > 1 ? 'n' : '',
                $activeBlockers,
                $activeBlockers > 1 ? 's' : '',
                $activeBlockers > 1 ? 's requieren' : 'requiere',
                $activeBlockers > 1 ? 's' : ''
            );
        } else {
            $conclusions[] = 'No hay bloqueos activos en el portafolio.';
        }

        if ($utilPct > 90) {
            $conclusions[] = 'El equipo presenta alta utilización; considerar redistribución de carga.';
        } elseif ($utilPct < 60) {
            $conclusions[] = 'El equipo tiene capacidad disponible significativa.';
        } else {
            $conclusions[] = 'La utilización del talento se encuentra en rango óptimo.';
        }

        if ($riskCount > 0) {
            $conclusions[] = sprintf('Se detectan %d proyecto%s en riesgo que requieren seguimiento.', $riskCount, $riskCount > 1 ? 's' : '');
        } else {
            $conclusions[] = 'No se detectan proyectos en riesgo alto.';
        }

        $conclusions[] = sprintf(
            'La tendencia mensual de avance es %s.',
            $trendDirection
        );

        $conclusions[] = sprintf(
            'Facturación vs plan: %s%% %s.',
            number_format($billingPct, 1, ',', '.'),
            $billingPct >= 70 ? '(dentro del objetivo)' : '(por debajo del objetivo)'
        );

        return ['conclusions' => array_slice($conclusions, 0, 5)];
    }

    /**
     * Alertas automáticas con tarjetas de color (verde/amarillo/rojo).
     */
    public function automaticAlerts(array $user): array
    {
        [$where, $params] = $this->visibilityForUser($user);
        $projectsCondition = $where ?: 'WHERE 1=1';
        $statusColumn = $this->projectStatusColumn();
        $statusExpr = $statusColumn !== null ? "p.{$statusColumn}" : "''";

        $alerts = [];

        $projectsAtRisk = $this->db->fetchOne(
            "SELECT COUNT(*) AS total
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             {$projectsCondition}
             AND p.progress < 70
             AND p.progress < 100
             AND {$statusExpr} NOT IN ('closed','archived','cancelled')",
            $params
        );
        $riskCount = (int) ($projectsAtRisk['total'] ?? 0);
        $alerts[] = [
            'id' => 'project_risk',
            'title' => 'Proyecto en riesgo',
            'description' => 'Avance < 70%',
            'value' => $riskCount,
            'status' => $riskCount === 0 ? 'green' : ($riskCount <= 2 ? 'yellow' : 'red'),
            'detail' => $riskCount === 0 ? 'Ningún proyecto con avance bajo' : sprintf('%d proyecto(s) con avance inferior al 70%%', $riskCount),
        ];

        $criticalBlockers = 0;
        $blockerDetail = '';
        if ($this->db->tableExists('project_stoppers')) {
            $dateColumn = $this->db->columnExists('project_stoppers', 'detected_at') ? 'detected_at' : 'created_at';
            $blockersOver5 = $this->db->fetchAll(
                "SELECT s.id, p.name AS project, s.title, DATEDIFF(CURDATE(), s.{$dateColumn}) AS days_open
                 FROM project_stoppers s
                 JOIN projects p ON p.id = s.project_id
                 JOIN clients c ON c.id = p.client_id
                 {$projectsCondition}
                 AND s.status IN ('abierto','en_gestion','escalado','resuelto')
                 AND DATEDIFF(CURDATE(), s.{$dateColumn}) > 5
                 ORDER BY days_open DESC",
                $params
            );
            $criticalBlockers = count($blockersOver5);
            if ($criticalBlockers > 0 && !empty($blockersOver5)) {
                $first = $blockersOver5[0];
                $blockerDetail = sprintf('%s (%d días)', $first['project'] ?? 'Proyecto', (int) ($first['days_open'] ?? 0));
            }
        }
        $alerts[] = [
            'id' => 'critical_blocker',
            'title' => 'Bloqueo crítico',
            'description' => 'Bloqueo abierto > 5 días',
            'value' => $criticalBlockers,
            'status' => $criticalBlockers === 0 ? 'green' : ($criticalBlockers <= 2 ? 'yellow' : 'red'),
            'detail' => $criticalBlockers === 0 ? 'Sin bloqueos prolongados' : $blockerDetail,
        ];

        $utilization = $this->talentUtilization($user);
        $utilPct = (float) ($utilization['avg_utilization'] ?? 0);
        $overloadRisk = $utilPct > 90;
        $alerts[] = [
            'id' => 'overload_risk',
            'title' => 'Riesgo de sobrecarga',
            'description' => 'Utilización de talento > 90%',
            'value' => number_format($utilPct, 1, ',', '.') . '%',
            'status' => $overloadRisk ? 'red' : ($utilPct > 80 ? 'yellow' : 'green'),
            'detail' => $overloadRisk ? 'Equipo sobrecargado' : 'Capacidad dentro del rango',
        ];

        $execIntel = $this->executiveIntelligence($user);
        $billingPct = (float) (($execIntel['financial_impact']['execution_pct'] ?? 0));
        $financialRisk = $billingPct < 70;
        $alerts[] = [
            'id' => 'financial_risk',
            'title' => 'Riesgo financiero',
            'description' => 'Facturación < 70% del plan',
            'value' => number_format($billingPct, 1, ',', '.') . '%',
            'status' => $financialRisk ? 'red' : ($billingPct < 85 ? 'yellow' : 'green'),
            'detail' => $financialRisk ? 'Facturación por debajo del objetivo' : 'Facturación en rango',
        ];

        return ['alerts' => $alerts];
    }

    /**
     * Utilización promedio del talento para el dashboard.
     */
    public function talentUtilization(array $user): array
    {
        if (!$this->db->tableExists('talents')) {
            return ['avg_utilization' => 0, 'total_capacity' => 0, 'total_hours' => 0, 'talents_with_150h_available' => 0];
        }

        [$where, $params] = $this->visibilityForUser($user);
        $projectsCondition = $where ?: 'WHERE 1=1';
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        $weeksInMonth = 4.33;

        $capacityRow = $this->db->fetchOne(
            'SELECT COALESCE(SUM(GREATEST(COALESCE(t.capacidad_horaria, 0), COALESCE(t.weekly_capacity, 0)) * COALESCE(t.availability, 100) / 100), 0) AS total
             FROM talents t
             WHERE 1=1',
            []
        );
        $totalCapacity = (float) ($capacityRow['total'] ?? 0) * $weeksInMonth;

        $hoursParams = array_merge($params, [':start' => $monthStart, ':end' => $monthEnd]);
        $hoursRow = ['total' => 0];
        if ($this->db->tableExists('timesheets')) {
            $hoursSql = "SELECT COALESCE(SUM(ts.hours), 0) AS total
                 FROM timesheets ts
                 JOIN tasks tk ON tk.id = ts.task_id
                 JOIN projects p ON p.id = tk.project_id
                 JOIN clients c ON c.id = p.client_id
                 {$projectsCondition}
                 AND ts.date BETWEEN :start AND :end
                 AND ts.status = 'approved'";
            $hoursRow = $this->db->fetchOne($hoursSql, $hoursParams);
        }
        $totalHours = (float) ($hoursRow['total'] ?? 0);
        $avgUtilization = $totalCapacity > 0 ? min(150, ($totalHours / $totalCapacity) * 100) : 0;

        $talents150h = 0;
        $assignCol = $this->db->columnExists('project_talent_assignments', 'talent_id') ? 'talent_id' : 'user_id';
        if ($this->db->tableExists('project_talent_assignments') && $this->db->columnExists('timesheets', 'talent_id') && $assignCol === 'talent_id') {
            $talentIds = $this->db->fetchAll(
                "SELECT DISTINCT a.{$assignCol} AS talent_id
                 FROM project_talent_assignments a
                 JOIN projects p ON p.id = a.project_id
                 JOIN clients c ON c.id = p.client_id
                 {$projectsCondition}
                 AND (a.assignment_status = 'active' OR (a.assignment_status IS NULL AND a.active = 1))
                 AND a.{$assignCol} IS NOT NULL",
                $params
            );
            foreach ($talentIds as $row) {
                $tid = (int) ($row['talent_id'] ?? 0);
                if ($tid <= 0) {
                    continue;
                }
                $capRow = $this->db->fetchOne(
                    'SELECT COALESCE(capacidad_horaria, weekly_capacity, 40) * 4.33 AS monthly_cap FROM talents WHERE id = :id',
                    [':id' => $tid]
                );
                $cap = (float) ($capRow['monthly_cap'] ?? 0);
                $usedRow = $this->db->fetchOne(
                    'SELECT COALESCE(SUM(hours), 0) AS used FROM timesheets WHERE talent_id = :tid AND date BETWEEN :start AND :end AND status = :status',
                    [':tid' => $tid, ':start' => $monthStart, ':end' => $monthEnd, ':status' => 'approved']
                );
                $used = (float) ($usedRow['used'] ?? 0);
                if (($cap - $used) > 150) {
                    $talents150h++;
                }
            }
        }

        return [
            'avg_utilization' => round($avgUtilization, 1),
            'total_capacity' => round($totalCapacity, 1),
            'total_hours' => round($totalHours, 1),
            'talents_with_150h_available' => $talents150h,
        ];
    }

    /**
     * Recomendaciones automáticas del sistema.
     */
    public function systemRecommendations(array $user): array
    {
        $recommendations = [];
        $utilization = $this->talentUtilization($user);
        $portfolioInsights = $this->portfolioHealthInsights($user);
        $stoppers = $this->stoppersOverview($user);
        $execIntel = $this->executiveIntelligence($user);
        $billingPct = (float) (($execIntel['financial_impact']['execution_pct'] ?? 0));

        if (($utilization['talents_with_150h_available'] ?? 0) > 0) {
            $recommendations[] = 'Redistribuir carga hacia talentos con más de 150h disponibles.';
        }

        $blockersWithProject = [];
        if ($this->db->tableExists('project_stoppers')) {
            [$where, $params] = $this->visibilityForUser($user);
            $projectsCondition = $where ?: 'WHERE 1=1';
            $blockersWithProject = $this->db->fetchAll(
                "SELECT p.name AS project_name
                 FROM project_stoppers s
                 JOIN projects p ON p.id = s.project_id
                 JOIN clients c ON c.id = p.client_id
                 {$projectsCondition}
                 AND s.status IN ('abierto','en_gestion','escalado','resuelto')
                 LIMIT 1",
                $params
            );
        }
        if (!empty($blockersWithProject)) {
            $projectName = $blockersWithProject[0]['project_name'] ?? 'proyecto';
            $recommendations[] = sprintf('Revisar el bloqueo activo en el proyecto %s.', $projectName);
        }

        $highProgressCount = 0;
        foreach ($portfolioInsights['ranking'] ?? [] as $p) {
            if ((float) ($p['score'] ?? 0) >= 70 && (int) ($p['blockers_open'] ?? 0) === 0) {
                $highProgressCount++;
            }
        }
        if ($highProgressCount > 0 && $billingPct < 85) {
            $recommendations[] = 'Acelerar facturación de proyectos con avance superior al 70%.';
        }

        $lowScoreProjects = array_filter($portfolioInsights['ranking'] ?? [], static fn (array $p) => (int) ($p['score'] ?? 0) < 80);
        if (!empty($lowScoreProjects)) {
            $recommendations[] = 'Priorizar proyectos con score menor a 80.';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Mantener la cadencia de seguimiento y monitoreo del portafolio.';
        }

        return ['recommendations' => array_slice($recommendations, 0, 5)];
    }

    /**
     * Desglose del score para tooltip (factores y pesos).
     */
    public function scoreBreakdown(array $user): array
    {
        $config = (new ConfigService($this->db))->getConfig();
        $rules = $config['operational_rules']['health_scoring'] ?? [];
        $weights = $rules['weights'] ?? [];

        $labels = [
            'avance' => 'Avance de proyectos',
            'documental' => 'Documentación',
            'horas' => 'Horas y ejecución',
            'seguimiento' => 'Seguimiento',
            'riesgo' => 'Riesgos activos',
            'calidad_requisitos' => 'Calidad de requisitos',
        ];

        $factors = [];
        foreach ($weights as $key => $weight) {
            $factors[] = [
                'name' => $labels[$key] ?? $key,
                'weight' => (float) $weight,
                'weight_pct' => round((float) $weight * 100, 0),
            ];
        }

        return [
            'factors' => $factors,
            'description' => 'El score del portafolio es el promedio de los scores de salud de cada proyecto. Cada proyecto considera: avance vs plan, documentación, horas, seguimiento, riesgos y calidad de requisitos. Los bloqueos activos aplican penalización al score.',
        ];
    }
}
