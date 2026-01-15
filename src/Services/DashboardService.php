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
                $talentTotals = $this->db->fetchOne(
                    "SELECT COUNT(DISTINCT a.talent_id) AS total
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

        return [
            'status_counts' => $statusCounts,
            'progress_by_client' => $progressByClient,
            'stale_projects' => $staleProjects,
            'stale_count' => count($staleProjects),
        ];
    }

    public function timesheetOverview(array $user): array
    {
        [$where, $params] = $this->visibilityForUser($user);
        $projectsCondition = $where ?: 'WHERE 1=1';

        $period = $this->weeklyPeriod();
        $weeklyHours = 0.0;
        $pendingHours = 0.0;

        if ($this->db->tableExists('timesheets') && $this->db->tableExists('tasks')) {
            $weeklyHoursRow = $this->db->fetchOne(
                "SELECT SUM(ts.hours) AS total
                 FROM timesheets ts
                 JOIN tasks t ON t.id = ts.task_id
                 JOIN projects p ON p.id = t.project_id
                 JOIN clients c ON c.id = p.client_id
                 {$projectsCondition}
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
                 AND ts.status IN ('pending','submitted')",
                $params
            );
            $pendingHours = (float) ($pendingRow['total'] ?? 0);
        }

        $talentsWithoutReport = 0;
        if ($this->db->tableExists('project_talent_assignments')) {
            $row = $this->db->fetchOne(
                "SELECT COUNT(DISTINCT a.talent_id) AS total
                 FROM project_talent_assignments a
                 JOIN projects p ON p.id = a.project_id
                 JOIN clients c ON c.id = p.client_id
                 LEFT JOIN timesheets ts ON ts.assignment_id = a.id AND ts.date BETWEEN :start AND :end
                 {$projectsCondition}
                 AND a.requires_timesheet = 1
                 AND (a.assignment_status = 'active' OR (a.assignment_status IS NULL AND a.active = 1))
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
                $joins = 'JOIN project_talent_assignments a ON a.talent_id = t.id
                          JOIN projects p ON p.id = a.project_id
                          JOIN clients c ON c.id = p.client_id';
                if ($this->db->columnExists('projects', 'pm_id')) {
                    $talentConditions[] = 'p.pm_id = :pmId';
                    $talentParams[':pmId'] = $user['id'];
                }
                $talentConditions[] = "(a.assignment_status = 'active' OR (a.assignment_status IS NULL AND a.active = 1))";
            }

            $talentWhere = $talentConditions ? ('WHERE ' . implode(' AND ', $talentConditions)) : 'WHERE 1=1';

            if ($this->db->columnExists('talents', 'is_outsourcing')) {
                $internalRow = $this->db->fetchOne(
                    "SELECT COUNT(DISTINCT t.id) AS total FROM talents t {$joins} {$talentWhere} AND t.is_outsourcing = 0",
                    $talentParams
                );
                $externalRow = $this->db->fetchOne(
                    "SELECT COUNT(DISTINCT t.id) AS total FROM talents t {$joins} {$talentWhere} AND t.is_outsourcing = 1",
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
        if (!$this->db->tableExists('project_talent_assignments')) {
            return [];
        }

        [$where, $params] = $this->visibilityForUser($user);
        $projectsCondition = $where ?: 'WHERE 1=1';
        $period = $this->weeklyPeriod();

        return $this->db->fetchAll(
            "SELECT DISTINCT t.name
             FROM project_talent_assignments a
             JOIN talents t ON t.id = a.talent_id
             JOIN projects p ON p.id = a.project_id
             JOIN clients c ON c.id = p.client_id
             LEFT JOIN timesheets ts ON ts.assignment_id = a.id AND ts.date BETWEEN :start AND :end
             {$projectsCondition}
             AND a.requires_timesheet = 1
             AND (a.assignment_status = 'active' OR (a.assignment_status IS NULL AND a.active = 1))
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
}
