<?php

declare(strict_types=1);

class DecisionCenterService
{
    private const ADMIN_ROLES = ['Administrador', 'PMO'];
    private const RISK_SCORE_THRESHOLD = 70;
    private const STALE_DAYS = 7;
    private const CRITICAL_STOPPER_LEVELS = ['critico', 'alto'];

    public function __construct(private Database $db)
    {
    }

    public function getPortfolioSummary(array $filters, array $user): array
    {
        [$where, $params] = $this->visibilityForUser($user);
        $projectsCondition = $where ?: 'WHERE 1=1';
        $statusExpr = $this->projectStatusExpr();
        $healthColumn = $this->projectHealthColumn();

        $activeFilter = " AND p.active = 1 AND {$statusExpr} NOT IN ('closed','archived','cancelled')";

        $summary = $this->db->fetchOne(
            "SELECT COUNT(*) AS active_projects
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             {$projectsCondition}{$activeFilter}",
            $params
        );

        $healthCond = $healthColumn ? "p.{$healthColumn} IN ('at_risk','critical','red')" : '0=1';
        $stopperCond = $this->db->tableExists('project_stoppers')
            ? "EXISTS (SELECT 1 FROM project_stoppers s WHERE s.project_id = p.id AND s.status IN ('abierto','en_gestion','escalado') AND s.impact_level IN ('critico','alto'))"
            : '0=1';

        $atRisk = $this->db->fetchOne(
            "SELECT COUNT(*) AS total
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             LEFT JOIN (SELECT entity_id AS pid, MAX(created_at) AS ln FROM audit_log WHERE entity = 'project_note' AND action = 'project_note_created' GROUP BY entity_id) n ON n.pid = p.id
             {$projectsCondition}{$activeFilter}
             AND ({$healthCond} OR {$stopperCond} OR (n.ln IS NULL OR n.ln < DATE_SUB(NOW(), INTERVAL " . self::STALE_DAYS . " DAY)))",
            $params
        );

        $openStoppers = 0;
        if ($this->db->tableExists('project_stoppers')) {
            $row = $this->db->fetchOne(
                "SELECT COUNT(*) AS total
                 FROM project_stoppers s
                 JOIN projects p ON p.id = s.project_id
                 JOIN clients c ON c.id = p.client_id
                 {$projectsCondition}
                 AND s.status IN ('abierto','en_gestion','escalado')",
                $params
            );
            $openStoppers = (int) ($row['total'] ?? 0);
        }

        $pendingBilling = $this->pendingBillingAmount($where, $params);

        $utilization = $this->teamUtilizationSnapshot();

        $portfolioScore = $this->portfolioScore($where, $params, $user);

        return [
            'score_general' => (int) ($portfolioScore['score'] ?? 0),
            'proyectos_activos' => (int) ($summary['active_projects'] ?? 0),
            'proyectos_riesgo' => (int) ($atRisk['total'] ?? 0),
            'bloqueos_activos' => $openStoppers,
            'facturacion_pendiente' => round($pendingBilling, 2),
            'utilizacion_promedio' => round($utilization['utilization_pct'] ?? 0, 1),
        ];
    }

    public function getAlerts(array $filters, array $user): array
    {
        [$where, $params] = $this->visibilityForUser($user);
        $projectsCondition = $where ?: 'WHERE 1=1';
        $statusExpr = $this->projectStatusExpr();
        $activeFilter = " AND p.active = 1 AND {$statusExpr} NOT IN ('closed','archived','cancelled')";

        $alerts = [];

        $staleRow = $this->db->fetchOne(
            "SELECT COUNT(*) AS total
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             LEFT JOIN (
                SELECT entity_id AS project_id, MAX(created_at) AS last_note
                FROM audit_log
                WHERE entity = 'project_note' AND action = 'project_note_created'
                GROUP BY entity_id
             ) notes ON notes.project_id = p.id
             {$projectsCondition}{$activeFilter}
             AND (notes.last_note IS NULL OR notes.last_note < DATE_SUB(NOW(), INTERVAL " . self::STALE_DAYS . " DAY))",
            $params
        );
        $staleCount = (int) ($staleRow['total'] ?? 0);
        if ($staleCount > 0) {
            $alerts[] = ['key' => 'stale', 'label' => 'Sin actualización > 7 días', 'count' => $staleCount];
        }

        if ($this->db->tableExists('project_stoppers')) {
            $criticalRow = $this->db->fetchOne(
                "SELECT COUNT(*) AS total
                 FROM project_stoppers s
                 JOIN projects p ON p.id = s.project_id
                 JOIN clients c ON c.id = p.client_id
                 {$projectsCondition}
                 AND s.status IN ('abierto','en_gestion','escalado')
                 AND s.impact_level IN ('critico','alto')",
                $params
            );
            $criticalCount = (int) ($criticalRow['total'] ?? 0);
            if ($criticalCount > 0) {
                $alerts[] = ['key' => 'blockers', 'label' => 'Bloqueos críticos abiertos', 'count' => $criticalCount];
            }
        }

        if ($this->db->tableExists('project_risk_evaluations') && $this->db->tableExists('risk_catalog')) {
            $riskRow = $this->db->fetchOne(
                "SELECT COUNT(DISTINCT pr.project_id) AS total
                 FROM project_risk_evaluations pr
                 JOIN risk_catalog rc ON rc.code = pr.risk_code
                 JOIN projects p ON p.id = pr.project_id
                 JOIN clients c ON c.id = p.client_id
                 {$projectsCondition}{$activeFilter}
                 AND pr.selected = 1 AND rc.severity_base >= 4",
                $params
            );
            $riskCount = (int) ($riskRow['total'] ?? 0);
            if ($riskCount > 0) {
                $alerts[] = ['key' => 'risks', 'label' => 'Riesgos críticos', 'count' => $riskCount];
            }
        }

        $pendingAmount = $this->pendingBillingAmount($where, $params);
        if ($pendingAmount > 0) {
            $alerts[] = ['key' => 'billing', 'label' => 'Facturación pendiente', 'count' => 1, 'amount' => $pendingAmount];
        }

        $utilization = $this->teamUtilizationSnapshot();
        $overloaded = (int) ($utilization['talents_over_90'] ?? 0);
        if ($overloaded > 0) {
            $alerts[] = ['key' => 'overload', 'label' => 'Sobrecarga de talento (>90%)', 'count' => $overloaded];
        }

        return $alerts;
    }

    public function getRecommendations(array $filters, array $user): array
    {
        [$where, $params] = $this->visibilityForUser($user);
        $projectsCondition = $where ?: 'WHERE 1=1';
        $statusExpr = $this->projectStatusExpr();
        $activeFilter = " AND p.active = 1 AND {$statusExpr} NOT IN ('closed','archived','cancelled')";

        $recommendations = [];

        if ($this->db->tableExists('project_stoppers')) {
            $blockerProjects = $this->db->fetchAll(
                "SELECT p.id, p.name, COUNT(*) AS cnt
                 FROM project_stoppers s
                 JOIN projects p ON p.id = s.project_id
                 JOIN clients c ON c.id = p.client_id
                 {$projectsCondition}{$activeFilter}
                 AND s.status IN ('abierto','en_gestion','escalado')
                 AND s.impact_level IN ('critico','alto')
                 GROUP BY p.id, p.name
                 ORDER BY cnt DESC
                 LIMIT 3",
                $params
            );
            foreach ($blockerProjects as $row) {
                $recommendations[] = [
                    'title' => 'Revisar bloqueos en ' . ($row['name'] ?? 'Proyecto'),
                    'reason' => (int) ($row['cnt'] ?? 0) . ' bloqueo(s) crítico(s) abierto(s)',
                    'impact' => 'Alto',
                    'action' => 'open_blockers',
                    'project_id' => (int) ($row['id'] ?? 0),
                ];
            }
        }

        $staleProjects = $this->db->fetchAll(
            "SELECT p.id, p.name
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             LEFT JOIN (
                SELECT entity_id AS project_id, MAX(created_at) AS last_note
                FROM audit_log
                WHERE entity = 'project_note' AND action = 'project_note_created'
                GROUP BY entity_id
             ) notes ON notes.project_id = p.id
             {$projectsCondition}{$activeFilter}
             AND (notes.last_note IS NULL OR notes.last_note < DATE_SUB(NOW(), INTERVAL " . self::STALE_DAYS . " DAY))
             ORDER BY COALESCE(notes.last_note, p.updated_at) ASC
             LIMIT 3",
            $params
        );
        foreach ($staleProjects as $row) {
            $recommendations[] = [
                'title' => 'Actualizar seguimiento: ' . ($row['name'] ?? 'Proyecto'),
                'reason' => 'Sin nota de seguimiento en más de 7 días',
                'impact' => 'Medio',
                'action' => 'view_project',
                'project_id' => (int) ($row['id'] ?? 0),
            ];
        }

        $pendingBilling = $this->projectsWithPendingBilling($where, $params);
        foreach (array_slice($pendingBilling, 0, 2) as $row) {
            $recommendations[] = [
                'title' => 'Facturar: ' . ($row['name'] ?? 'Proyecto'),
                'reason' => 'Pendiente por facturar: ' . ($row['currency_code'] ?? 'USD') . ' ' . number_format((float) ($row['pending'] ?? 0), 2),
                'impact' => 'Alto',
                'action' => 'go_billing',
                'project_id' => (int) ($row['id'] ?? 0),
            ];
        }

        $overloaded = $this->teamUtilizationSnapshot();
        $talentsOver90 = $overloaded['talents_over_90'] ?? 0;
        if ($talentsOver90 > 0) {
            $recommendations[] = [
                'title' => 'Redistribuir carga de talento',
                'reason' => $talentsOver90 . ' talento(s) con utilización > 90%',
                'impact' => 'Medio',
                'action' => 'assign_resource',
                'project_id' => 0,
            ];
        }

        return array_slice($recommendations, 0, 10);
    }

    public function getProjectRanking(array $filters, array $user): array
    {
        [$where, $params] = $this->visibilityForUser($user);
        $projectsCondition = $where ?: 'WHERE 1=1';
        $statusExpr = $this->projectStatusExpr();
        $activeFilter = " AND p.active = 1 AND {$statusExpr} NOT IN ('closed','archived','cancelled')";

        $projectService = new ProjectService($this->db);
        $projects = $this->db->fetchAll(
            "SELECT p.id, p.name, p.progress, p.health, c.name AS client_name, u.name AS pm_name
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             LEFT JOIN users u ON u.id = p.pm_id
             {$projectsCondition}{$activeFilter}
             ORDER BY p.updated_at DESC",
            $params
        );

        $projectIds = array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $projects);
        $latestNotes = $this->latestNotesByProject($projectIds);
        $stoppersByProject = $this->stoppersByProject($projectIds);

        $ranking = [];
        foreach ($projects as $project) {
            $projectId = (int) ($project['id'] ?? 0);
            $report = $projectService->calculateProjectHealthReport($projectId);
            $note = $latestNotes[$projectId] ?? null;
            $stoppers = $stoppersByProject[$projectId] ?? ['count' => 0, 'max_severity' => '', 'top3' => []];

            $ranking[] = [
                'id' => $projectId,
                'name' => $project['name'] ?? '',
                'client' => $project['client_name'] ?? '',
                'pm' => $project['pm_name'] ?? '',
                'progress' => (float) ($project['progress'] ?? 0),
                'health' => $project['health'] ?? '',
                'score' => (int) ($report['total_score'] ?? 0),
                'last_note' => $note ? [
                    'preview' => mb_substr($note['text'] ?? '', 0, 100),
                    'date' => $note['created_at'] ?? null,
                    'author' => $note['author'] ?? '',
                ] : null,
                'stoppers' => $stoppers,
            ];
        }

        usort($ranking, static fn (array $a, array $b): int => ($a['score'] ?? 0) <=> ($b['score'] ?? 0));

        return array_reverse($ranking);
    }

    public function getTeamCapacity(array $filters, array $user): array
    {
        $range = $this->resolveRange($filters['date_from'] ?? null, $filters['date_to'] ?? null);

        if (!$this->db->tableExists('talents')) {
            return ['range' => $range, 'talents' => [], 'summary' => []];
        }

        $capacityCol = $this->db->columnExists('talents', 'capacidad_horaria') ? 'capacidad_horaria' : 'weekly_capacity';
        $talents = $this->db->fetchAll(
            "SELECT t.id, t.name, t.role, t.{$capacityCol} AS capacity, t.availability
             FROM talents t
             ORDER BY t.name ASC"
        );

        $talentIds = array_map(static fn (array $t): int => (int) ($t['id'] ?? 0), $talents);
        if (empty($talentIds)) {
            return ['range' => $range, 'talents' => [], 'summary' => []];
        }

        $hoursByTalent = $this->db->fetchAll(
            'SELECT talent_id, SUM(hours) AS total
             FROM timesheets
             WHERE talent_id IN (' . implode(',', array_map('intval', $talentIds)) . ')
               AND date BETWEEN :from AND :to
             GROUP BY talent_id',
            [':from' => $range['start'], ':to' => $range['end']]
        );
        $hoursMap = [];
        foreach ($hoursByTalent as $row) {
            $hoursMap[(int) ($row['talent_id'] ?? 0)] = (float) ($row['total'] ?? 0);
        }

        $workingDays = $this->workingDays($range['start'], $range['end']);
        $totalCapacity = 0.0;
        $totalUsed = 0.0;
        $result = [];

        foreach ($talents as $talent) {
            $talentId = (int) ($talent['id'] ?? 0);
            $weeklyCap = (float) ($talent['capacity'] ?? 40);
            if ($weeklyCap <= 0) {
                $weeklyCap = 40.0;
            }
            $availability = min(100, max(0, (float) ($talent['availability'] ?? 100)));
            $periodCapacity = ($weeklyCap * ($workingDays / 5)) * ($availability / 100);
            $used = (float) ($hoursMap[$talentId] ?? 0);
            $utilization = $periodCapacity > 0 ? ($used / $periodCapacity) * 100 : 0;
            $status = $utilization > 100 ? 'critical' : ($utilization > 90 ? 'overload' : 'ok');

            $totalCapacity += $periodCapacity;
            $totalUsed += $used;

            $result[] = [
                'id' => $talentId,
                'name' => $talent['name'] ?? '',
                'role' => $talent['role'] ?? '',
                'capacity_hours' => round($periodCapacity, 1),
                'assigned_hours' => round($used, 1),
                'utilization_pct' => round($utilization, 1),
                'status' => $status,
                'available_hours' => round(max(0, $periodCapacity - $used), 1),
            ];
        }

        return [
            'range' => $range,
            'talents' => $result,
            'summary' => [
                'total_capacity' => round($totalCapacity, 1),
                'total_assigned' => round($totalUsed, 1),
                'utilization_pct' => $totalCapacity > 0 ? round(($totalUsed / $totalCapacity) * 100, 1) : 0,
                'overloaded_count' => count(array_filter($result, static fn (array $t): bool => ($t['utilization_pct'] ?? 0) > 90),
                'available_count' => count(array_filter($result, static fn (array $t): bool => ($t['utilization_pct'] ?? 0) < 90)),
            ],
        ];
    }

    public function simulateCapacity(array $payload, array $user): array
    {
        $hours = max(0, (float) ($payload['hours'] ?? 0));
        $roleFilter = trim((string) ($payload['role'] ?? ''));
        $periodStart = $payload['period_start'] ?? date('Y-m-01');
        $periodEnd = $payload['period_end'] ?? date('Y-m-t');
        $resourcesCount = max(1, (int) ($payload['resources_count'] ?? 1));

        $capacity = $this->getTeamCapacity([
            'date_from' => $periodStart,
            'date_to' => $periodEnd,
        ], $user);

        $talents = $capacity['talents'] ?? [];
        if ($roleFilter !== '') {
            $talents = array_filter($talents, static fn (array $t): bool => ($t['role'] ?? '') === $roleFilter);
        }

        $available = array_filter($talents, static fn (array $t): bool => ($t['utilization_pct'] ?? 0) < 90 && ($t['available_hours'] ?? 0) > 0);
        usort($available, static fn (array $a, array $b): int => (int) (($b['available_hours'] ?? 0) * 10) <=> (int) (($a['available_hours'] ?? 0) * 10));

        $hoursPerResource = $resourcesCount > 0 ? $hours / $resourcesCount : $hours;
        $atRisk = [];
        $affected = [];

        foreach (array_slice($available, 0, $resourcesCount) as $talent) {
            $currentUtil = (float) ($talent['utilization_pct'] ?? 0);
            $currentAssigned = (float) ($talent['assigned_hours'] ?? 0);
            $capacityHours = (float) ($talent['capacity_hours'] ?? 0);
            $newAssigned = $currentAssigned + $hoursPerResource;
            $newUtil = $capacityHours > 0 ? ($newAssigned / $capacityHours) * 100 : 0;

            if ($newUtil > 90) {
                $atRisk[] = [
                    'name' => $talent['name'] ?? '',
                    'current_util' => $currentUtil,
                    'estimated_util' => round($newUtil, 1),
                ];
            }
            $affected[] = $talent['name'] ?? '';
        }

        $totalCapacity = (float) ($capacity['summary']['total_capacity'] ?? 0);
        $totalAssigned = (float) ($capacity['summary']['total_assigned'] ?? 0);
        $newTotalAssigned = $totalAssigned + $hours;
        $estimatedUtilization = $totalCapacity > 0 ? ($newTotalAssigned / $totalCapacity) * 100 : 0;

        return [
            'estimated_utilization_pct' => round($estimatedUtilization, 1),
            'talents_at_risk' => $atRisk,
            'affected_talents' => $affected,
            'hours_to_distribute' => $hours,
            'resources_requested' => $resourcesCount,
        ];
    }

    private function pendingBillingAmount(string $where, array $params): float
    {
        if (!$this->db->tableExists('project_invoices') || !$this->db->tableExists('projects')) {
            return 0.0;
        }

        $projectsCondition = $where ?: 'WHERE 1=1';

        $rows = $this->db->fetchAll(
            "SELECT p.id, p.billing_type, p.contract_value, p.hourly_rate, p.currency_code,
                    (SELECT COALESCE(SUM(i.amount), 0) FROM project_invoices i WHERE i.project_id = p.id AND i.status != 'void') AS total_invoiced
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             {$projectsCondition}
             AND p.is_billable = 1",
            $params
        );

        $pending = 0.0;
        foreach ($rows as $row) {
            $contracted = (float) ($row['contract_value'] ?? 0);
            $invoiced = (float) ($row['total_invoiced'] ?? 0);
            $billingType = $row['billing_type'] ?? 'fixed';
            if ($billingType === 'fixed' || $billingType === 'one_time') {
                $pending += max(0, $contracted - $invoiced);
            } elseif ($billingType === 'hours') {
                $hourlyRate = (float) ($row['hourly_rate'] ?? 0);
                $approvedHours = $this->approvedHoursForProject((int) ($row['id'] ?? 0));
                $pending += max(0, ($approvedHours * $hourlyRate) - $invoiced);
            }
        }

        return $pending;
    }

    private function approvedHoursForProject(int $projectId): float
    {
        if (!$this->db->tableExists('timesheets')) {
            return 0.0;
        }
        $row = $this->db->fetchOne(
            'SELECT COALESCE(SUM(hours), 0) AS total
             FROM timesheets
             WHERE project_id = :pid AND billable = 1 AND status = :status',
            [':pid' => $projectId, ':status' => 'approved']
        );
        return (float) ($row['total'] ?? 0);
    }

    private function projectsWithPendingBilling(string $where, array $params): array
    {
        if (!$this->db->tableExists('project_invoices') || !$this->db->tableExists('projects')) {
            return [];
        }

        $projectsCondition = $where ?: 'WHERE 1=1';
        $rows = $this->db->fetchAll(
            "SELECT p.id, p.name, p.billing_type, p.contract_value, p.hourly_rate, p.currency_code,
                    (SELECT COALESCE(SUM(i.amount), 0) FROM project_invoices i WHERE i.project_id = p.id AND i.status != 'void') AS total_invoiced
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             {$projectsCondition}
             AND p.is_billable = 1",
            $params
        );

        $result = [];
        foreach ($rows as $row) {
            $contracted = (float) ($row['contract_value'] ?? 0);
            $invoiced = (float) ($row['total_invoiced'] ?? 0);
            $billingType = $row['billing_type'] ?? 'fixed';
            $pending = 0.0;
            if ($billingType === 'fixed' || $billingType === 'one_time') {
                $pending = max(0, $contracted - $invoiced);
            } elseif ($billingType === 'hours') {
                $hourlyRate = (float) ($row['hourly_rate'] ?? 0);
                $approvedHours = $this->approvedHoursForProject((int) ($row['id'] ?? 0));
                $pending = max(0, ($approvedHours * $hourlyRate) - $invoiced);
            }
            if ($pending > 0) {
                $result[] = array_merge($row, ['pending' => $pending]);
            }
        }

        usort($result, static fn (array $a, array $b): int => (int) (($b['pending'] ?? 0) * 100) <=> (int) (($a['pending'] ?? 0) * 100));

        return $result;
    }

    private function teamUtilizationSnapshot(): array
    {
        if (!$this->db->tableExists('talents')) {
            return ['utilization_pct' => 0.0, 'talents_over_90' => 0, 'available_hours' => 0.0];
        }

        $capacityCol = $this->db->columnExists('talents', 'capacidad_horaria') ? 'capacidad_horaria' : 'weekly_capacity';
        $talents = $this->db->fetchAll(
            "SELECT id, name, {$capacityCol} AS cap, availability FROM talents ORDER BY name"
        );

        $talentIds = array_map(static fn (array $t): int => (int) ($t['id'] ?? 0), $talents);
        $hoursByTalent = [];
        if ($this->db->tableExists('timesheets') && !empty($talentIds)) {
            $rows = $this->db->fetchAll(
                'SELECT talent_id, SUM(hours) AS total FROM timesheets WHERE date BETWEEN :s AND :e GROUP BY talent_id',
                [':s' => date('Y-m-01'), ':e' => date('Y-m-t')]
            );
            foreach ($rows as $r) {
                $hoursByTalent[(int) ($r['talent_id'] ?? 0)] = (float) ($r['total'] ?? 0);
            }
        }

        $workingDays = $this->workingDays(date('Y-m-01'), date('Y-m-t'));
        $totalCapacity = 0.0;
        $totalUsed = 0.0;
        $talentsOver90 = 0;
        $availableHours = 0.0;

        foreach ($talents as $t) {
            $weeklyCap = (float) ($t['cap'] ?? 40);
            if ($weeklyCap <= 0) {
                $weeklyCap = 40.0;
            }
            $avail = min(100, max(0, (float) ($t['availability'] ?? 100)));
            $monthlyCap = ($weeklyCap * ($workingDays / 5)) * ($avail / 100);
            $used = (float) ($hoursByTalent[(int) ($t['id'] ?? 0)] ?? 0);
            $util = $monthlyCap > 0 ? ($used / $monthlyCap) * 100 : 0;

            $totalCapacity += $monthlyCap;
            $totalUsed += $used;
            $availableHours += max(0, $monthlyCap - $used);
            if ($util > 90) {
                $talentsOver90++;
            }
        }

        return [
            'utilization_pct' => $totalCapacity > 0 ? ($totalUsed / $totalCapacity) * 100 : 0.0,
            'talents_over_90' => $talentsOver90,
            'available_hours' => $availableHours,
        ];
    }

    private function portfolioScore(string $where, array $params, array $user): array
    {
        $dashboard = new DashboardService($this->db);
        $avg = $dashboard->portfolioHealthAverage($user);
        $insights = $dashboard->portfolioHealthInsights($user);
        $stoppers = $dashboard->stoppersOverview($user);
        $utilization = $this->teamUtilizationSnapshot();
        $pendingBilling = $this->pendingBillingAmount($where, $params);

        $score = (int) ($avg['average_score'] ?? 0);
        $openBlockers = (int) ($stoppers['open_total'] ?? 0);
        $criticalBlockers = (int) ($stoppers['critical_total'] ?? 0);
        $utilPct = (float) ($utilization['utilization_pct'] ?? 0);

        $blockerPenalty = min(30, ($openBlockers * 3) + ($criticalBlockers * 8));
        $score = max(0, min(100, $score - $blockerPenalty));

        if ($utilPct > 100) {
            $score = max(0, $score - 10);
        } elseif ($utilPct > 90) {
            $score = max(0, $score - 5);
        }

        return ['score' => $score];
    }

    private function latestNotesByProject(array $projectIds): array
    {
        if (empty($projectIds) || !$this->db->tableExists('audit_log')) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($projectIds), '?'));
        $rows = $this->db->fetchAll(
            "SELECT al.entity_id AS project_id, al.created_at, al.payload, u.name AS author
             FROM audit_log al
             LEFT JOIN users u ON u.id = al.user_id
             INNER JOIN (
                SELECT entity_id, MAX(created_at) AS max_created_at
                FROM audit_log
                WHERE entity = 'project_note' AND action = 'project_note_created' AND entity_id IN ($placeholders)
                GROUP BY entity_id
             ) latest ON latest.entity_id = al.entity_id AND latest.max_created_at = al.created_at
             WHERE al.entity = 'project_note' AND al.action = 'project_note_created'",
            $projectIds
        );

        $index = [];
        foreach ($rows as $row) {
            $payload = json_decode((string) ($row['payload'] ?? ''), true);
            $text = is_array($payload) ? trim((string) ($payload['note'] ?? '')) : '';
            $projectId = (int) ($row['project_id'] ?? 0);
            $index[$projectId] = [
                'text' => $text,
                'author' => $row['author'] ?? 'Sistema',
                'created_at' => $row['created_at'] ?? null,
            ];
        }

        return $index;
    }

    private function stoppersByProject(array $projectIds): array
    {
        if (empty($projectIds) || !$this->db->tableExists('project_stoppers')) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($projectIds), '?'));
        $rows = $this->db->fetchAll(
            "SELECT project_id, title, impact_level
             FROM project_stoppers
             WHERE status IN ('abierto','en_gestion','escalado') AND project_id IN ($placeholders)
             ORDER BY CASE impact_level WHEN 'critico' THEN 1 WHEN 'alto' THEN 2 WHEN 'medio' THEN 3 ELSE 4 END, created_at DESC",
            $projectIds
        );

        $index = [];
        foreach ($rows as $row) {
            $projectId = (int) ($row['project_id'] ?? 0);
            if (!isset($index[$projectId])) {
                $index[$projectId] = ['count' => 0, 'max_severity' => '', 'top3' => []];
            }
            $index[$projectId]['count']++;
            if (in_array($row['impact_level'] ?? '', ['critico', 'alto'], true) && $index[$projectId]['max_severity'] === '') {
                $index[$projectId]['max_severity'] = $row['impact_level'];
            }
            if (count($index[$projectId]['top3']) < 3) {
                $index[$projectId]['top3'][] = $row['title'] ?? '';
            }
        }

        return $index;
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

    private function projectStatusExpr(): string
    {
        if ($this->db->columnExists('projects', 'status_code')) {
            return 'p.status_code';
        }
        if ($this->db->columnExists('projects', 'status')) {
            return 'p.status';
        }
        return "''";
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

    private function resolveRange(?string $from, ?string $to): array
    {
        $from = $from ?: date('Y-m-01');
        $to = $to ?: date('Y-m-t');
        return ['start' => $from, 'end' => $to];
    }

    private function workingDays(string $start, string $end): int
    {
        $cursor = new DateTimeImmutable($start);
        $limit = new DateTimeImmutable($end);
        $days = 0;
        while ($cursor <= $limit) {
            if ((int) $cursor->format('N') <= 5) {
                $days++;
            }
            $cursor = $cursor->modify('+1 day');
        }
        return max(1, $days);
    }
}
