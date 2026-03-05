<?php

declare(strict_types=1);

class DecisionCenterService
{
    private const ADMIN_ROLES = ['Administrador', 'PMO'];
    private const RISK_SCORE_THRESHOLD = 70;
    private const OVERLOAD_THRESHOLD = 90;
    private const CRITICAL_OVERLOAD_THRESHOLD = 100;
    private const STALE_DAYS = 7;
    private const CACHE_TTL_MINUTES = 15;

    public function __construct(private Database $db)
    {
    }

    public function getPortfolioSummary(array $filters, array $user): array
    {
        [$where, $params] = $this->buildFilters($filters, $user);
        $statusColumn = $this->projectStatusColumn();
        $healthColumn = $this->projectHealthColumn();

        $condition = $where ?: 'WHERE 1=1';
        $activeFilter = '';
        if ($this->db->columnExists('projects', 'active')) {
            $activeFilter .= ' AND p.active = 1';
        }
        if ($statusColumn !== null) {
            $activeFilter .= " AND p.{$statusColumn} NOT IN ('closed','archived','cancelled')";
        }

        $totals = $this->db->fetchOne(
            "SELECT COUNT(*) AS total,
                    AVG(p.progress) AS avg_progress
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             {$condition}{$activeFilter}",
            $params
        ) ?: [];

        $activeProjects = (int) ($totals['total'] ?? 0);

        $atRiskCount = $this->countProjectsAtRisk($condition, $activeFilter, $params, $healthColumn);

        $openBlockers = 0;
        if ($this->db->tableExists('project_stoppers')) {
            $row = $this->db->fetchOne(
                "SELECT COUNT(*) AS total
                 FROM project_stoppers s
                 JOIN projects p ON p.id = s.project_id
                 JOIN clients c ON c.id = p.client_id
                 {$condition}{$activeFilter}
                 AND s.status IN ('abierto','en_gestion','escalado')",
                $params
            );
            $openBlockers = (int) ($row['total'] ?? 0);
        }

        $billingPending = $this->calculateBillingPending($condition, $activeFilter, $params);

        $utilizationData = $this->calculateTeamUtilization($filters);

        $portfolioScore = $this->calculatePortfolioScore(
            (float) ($totals['avg_progress'] ?? 0),
            $activeProjects,
            $atRiskCount,
            $openBlockers,
            $billingPending['amount'],
            $utilizationData['utilization_pct']
        );

        return [
            'portfolio_score' => $portfolioScore,
            'active_projects' => $activeProjects,
            'at_risk_projects' => $atRiskCount,
            'open_blockers' => $openBlockers,
            'billing_pending_amount' => $billingPending['amount'],
            'billing_pending_projects' => $billingPending['project_count'],
            'utilization_pct' => $utilizationData['utilization_pct'],
            'avg_progress' => round((float) ($totals['avg_progress'] ?? 0), 1),
        ];
    }

    public function getAlerts(array $filters, array $user): array
    {
        [$where, $params] = $this->buildFilters($filters, $user);
        $condition = $where ?: 'WHERE 1=1';
        $statusColumn = $this->projectStatusColumn();
        $statusExpr = $statusColumn !== null ? "p.{$statusColumn}" : "''";
        $activeFilter = '';
        if ($this->db->columnExists('projects', 'active')) {
            $activeFilter .= ' AND p.active = 1';
        }
        if ($statusColumn !== null) {
            $activeFilter .= " AND p.{$statusColumn} NOT IN ('closed','archived','cancelled')";
        }

        $alerts = [];

        $staleRow = $this->db->fetchOne(
            "SELECT COUNT(*) AS total
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             {$condition}{$activeFilter}
             AND p.updated_at < DATE_SUB(NOW(), INTERVAL " . self::STALE_DAYS . " DAY)
             AND p.progress < 100",
            $params
        );
        $alerts[] = [
            'key' => 'stale',
            'label' => 'Sin actualización > ' . self::STALE_DAYS . ' días',
            'count' => (int) ($staleRow['total'] ?? 0),
            'status' => ((int) ($staleRow['total'] ?? 0)) > 0 ? 'warning' : 'green',
        ];

        $criticalBlockers = 0;
        if ($this->db->tableExists('project_stoppers')) {
            $blockerRow = $this->db->fetchOne(
                "SELECT COUNT(*) AS total
                 FROM project_stoppers s
                 JOIN projects p ON p.id = s.project_id
                 JOIN clients c ON c.id = p.client_id
                 {$condition}{$activeFilter}
                 AND s.status IN ('abierto','en_gestion','escalado')
                 AND s.impact_level IN ('critico','alto')",
                $params
            );
            $criticalBlockers = (int) ($blockerRow['total'] ?? 0);
        }
        $alerts[] = [
            'key' => 'critical_blockers',
            'label' => 'Bloqueos críticos abiertos',
            'count' => $criticalBlockers,
            'status' => $criticalBlockers > 0 ? 'danger' : 'green',
        ];

        $criticalRisks = 0;
        if ($this->db->tableExists('project_risk_evaluations') && $this->db->tableExists('risk_catalog')) {
            $riskRow = $this->db->fetchOne(
                "SELECT COUNT(*) AS total
                 FROM project_risk_evaluations pr
                 JOIN risk_catalog rc ON rc.code = pr.risk_code
                 JOIN projects p ON p.id = pr.project_id
                 JOIN clients c ON c.id = p.client_id
                 {$condition}{$activeFilter}
                 AND pr.selected = 1
                 AND rc.severity_base >= 4",
                $params
            );
            $criticalRisks = (int) ($riskRow['total'] ?? 0);
        }
        $alerts[] = [
            'key' => 'critical_risks',
            'label' => 'Riesgos críticos',
            'count' => $criticalRisks,
            'status' => $criticalRisks > 0 ? 'danger' : 'green',
        ];

        $billingPending = $this->calculateBillingPending($condition, $activeFilter, $params);
        $alerts[] = [
            'key' => 'billing_pending',
            'label' => 'Facturación pendiente',
            'count' => $billingPending['project_count'],
            'status' => $billingPending['project_count'] > 0 ? 'warning' : 'green',
        ];

        $utilizationData = $this->calculateTeamUtilization($filters);
        $alerts[] = [
            'key' => 'overloaded_talent',
            'label' => 'Sobrecarga de talento (>90%)',
            'count' => $utilizationData['overloaded_count'],
            'status' => $utilizationData['overloaded_count'] > 0 ? 'danger' : 'green',
        ];

        return $alerts;
    }

    public function getRecommendations(array $filters, array $user): array
    {
        [$where, $params] = $this->buildFilters($filters, $user);
        $condition = $where ?: 'WHERE 1=1';
        $statusColumn = $this->projectStatusColumn();
        $healthColumn = $this->projectHealthColumn();
        $activeFilter = '';
        if ($this->db->columnExists('projects', 'active')) {
            $activeFilter .= ' AND p.active = 1';
        }
        if ($statusColumn !== null) {
            $activeFilter .= " AND p.{$statusColumn} NOT IN ('closed','archived','cancelled')";
        }

        $recommendations = [];

        if ($this->db->tableExists('project_stoppers')) {
            $criticalStoppers = $this->db->fetchAll(
                "SELECT p.id AS project_id, p.name AS project_name, COUNT(*) AS blocker_count
                 FROM project_stoppers s
                 JOIN projects p ON p.id = s.project_id
                 JOIN clients c ON c.id = p.client_id
                 {$condition}{$activeFilter}
                 AND s.status IN ('abierto','en_gestion','escalado')
                 AND s.impact_level IN ('critico','alto')
                 GROUP BY p.id, p.name
                 ORDER BY blocker_count DESC
                 LIMIT 3",
                $params
            );
            foreach ($criticalStoppers as $stopper) {
                $recommendations[] = [
                    'title' => 'Resolver bloqueos en ' . ($stopper['project_name'] ?? ''),
                    'reason' => ($stopper['blocker_count'] ?? 0) . ' bloqueo(s) crítico(s)/alto(s) abierto(s)',
                    'impact' => 'Alto',
                    'action_label' => 'Abrir bloqueos',
                    'action_url' => '/projects/' . ($stopper['project_id'] ?? 0) . '#stoppers',
                ];
            }
        }

        $staleProjects = $this->db->fetchAll(
            "SELECT p.id, p.name, DATEDIFF(NOW(), p.updated_at) AS days_stale
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             {$condition}{$activeFilter}
             AND p.updated_at < DATE_SUB(NOW(), INTERVAL " . self::STALE_DAYS . " DAY)
             AND p.progress < 100
             ORDER BY days_stale DESC
             LIMIT 3",
            $params
        );
        foreach ($staleProjects as $project) {
            $recommendations[] = [
                'title' => 'Actualizar seguimiento: ' . ($project['name'] ?? ''),
                'reason' => ($project['days_stale'] ?? 0) . ' días sin actualización',
                'impact' => (int) ($project['days_stale'] ?? 0) > 14 ? 'Alto' : 'Medio',
                'action_label' => 'Ver proyecto',
                'action_url' => '/projects/' . ($project['id'] ?? 0),
            ];
        }

        if ($this->db->tableExists('project_invoices')) {
            $pendingBilling = $this->db->fetchAll(
                "SELECT p.id AS project_id, p.name AS project_name,
                        COALESCE(p.contract_value, 0) AS contract_value,
                        COALESCE(SUM(CASE WHEN i.status <> 'cancelled' THEN i.amount ELSE 0 END), 0) AS total_invoiced
                 FROM projects p
                 JOIN clients c ON c.id = p.client_id
                 LEFT JOIN project_invoices i ON i.project_id = p.id
                 {$condition}{$activeFilter}
                 AND p.is_billable = 1
                 GROUP BY p.id, p.name, p.contract_value
                 HAVING contract_value > 0 AND total_invoiced < contract_value * 0.5 AND p.progress > 50
                 ORDER BY (contract_value - total_invoiced) DESC
                 LIMIT 2",
                $params
            );
            foreach ($pendingBilling as $project) {
                $pending = (float) $project['contract_value'] - (float) $project['total_invoiced'];
                $recommendations[] = [
                    'title' => 'Facturar: ' . ($project['project_name'] ?? ''),
                    'reason' => 'Avance > 50% con $' . number_format($pending, 0) . ' pendiente',
                    'impact' => 'Alto',
                    'action_label' => 'Ir a facturación',
                    'action_url' => '/projects/' . ($project['project_id'] ?? 0) . '/billing',
                ];
            }
        }

        $utilizationData = $this->calculateTeamUtilization($filters);
        if ($utilizationData['overloaded_count'] > 0) {
            $recommendations[] = [
                'title' => 'Redistribuir carga del equipo',
                'reason' => $utilizationData['overloaded_count'] . ' talento(s) con utilización > 90%',
                'impact' => 'Alto',
                'action_label' => 'Asignar recurso',
                'action_url' => '/talent-capacity',
            ];
        }

        if ($healthColumn !== null) {
            $lowHealthProjects = $this->db->fetchAll(
                "SELECT p.id, p.name
                 FROM projects p
                 JOIN clients c ON c.id = p.client_id
                 {$condition}{$activeFilter}
                 AND p.{$healthColumn} IN ('critical','red')
                 ORDER BY p.name ASC
                 LIMIT 2",
                $params
            );
            foreach ($lowHealthProjects as $project) {
                $recommendations[] = [
                    'title' => 'Atención urgente: ' . ($project['name'] ?? ''),
                    'reason' => 'Salud del proyecto en estado crítico',
                    'impact' => 'Alto',
                    'action_label' => 'Ver proyecto',
                    'action_url' => '/projects/' . ($project['id'] ?? 0),
                ];
            }
        }

        usort($recommendations, static function (array $a, array $b): int {
            $impactOrder = ['Alto' => 0, 'Medio' => 1, 'Bajo' => 2];
            return ($impactOrder[$a['impact']] ?? 2) <=> ($impactOrder[$b['impact']] ?? 2);
        });

        return array_slice($recommendations, 0, 10);
    }

    public function getProjectRanking(array $filters, array $user): array
    {
        [$where, $params] = $this->buildFilters($filters, $user);
        $condition = $where ?: 'WHERE 1=1';
        $statusColumn = $this->projectStatusColumn();
        $healthColumn = $this->projectHealthColumn();
        $activeFilter = '';
        if ($this->db->columnExists('projects', 'active')) {
            $activeFilter .= ' AND p.active = 1';
        }
        if ($statusColumn !== null) {
            $activeFilter .= " AND p.{$statusColumn} NOT IN ('closed','archived','cancelled')";
        }

        $statusExpr = $statusColumn !== null ? "p.{$statusColumn}" : "'active'";
        $healthExpr = $healthColumn !== null ? "p.{$healthColumn}" : "'unknown'";

        $projects = $this->db->fetchAll(
            "SELECT p.id, p.name, c.name AS client,
                    COALESCE(u.name, 'Sin PM') AS pm_name,
                    p.progress,
                    {$statusExpr} AS status,
                    {$healthExpr} AS health,
                    p.updated_at,
                    p.contract_value,
                    p.is_billable
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             LEFT JOIN users u ON u.id = p.pm_id
             {$condition}{$activeFilter}
             ORDER BY p.progress ASC, p.name ASC",
            $params
        );

        $result = [];
        foreach ($projects as $project) {
            $projectId = (int) ($project['id'] ?? 0);

            $blockersCount = 0;
            $maxSeverity = '';
            $topBlockers = [];
            if ($this->db->tableExists('project_stoppers')) {
                $blockerData = $this->db->fetchOne(
                    "SELECT COUNT(*) AS total,
                            MAX(CASE WHEN impact_level = 'critico' THEN 4
                                     WHEN impact_level = 'alto' THEN 3
                                     WHEN impact_level = 'medio' THEN 2
                                     ELSE 1 END) AS max_severity_num
                     FROM project_stoppers
                     WHERE project_id = :pid AND status IN ('abierto','en_gestion','escalado')",
                    [':pid' => $projectId]
                );
                $blockersCount = (int) ($blockerData['total'] ?? 0);
                $sevNum = (int) ($blockerData['max_severity_num'] ?? 0);
                $maxSeverity = match ($sevNum) {
                    4 => 'Crítico', 3 => 'Alto', 2 => 'Medio', 1 => 'Bajo', default => ''
                };

                if ($blockersCount > 0) {
                    $topBlockers = $this->db->fetchAll(
                        "SELECT title, impact_level FROM project_stoppers
                         WHERE project_id = :pid AND status IN ('abierto','en_gestion','escalado')
                         ORDER BY FIELD(impact_level, 'critico', 'alto', 'medio', 'bajo') ASC
                         LIMIT 3",
                        [':pid' => $projectId]
                    );
                }
            }

            $lastNote = null;
            $noteData = $this->db->fetchOne(
                "SELECT payload, created_at, user_id
                 FROM audit_log
                 WHERE entity = 'project_note' AND entity_id = :pid
                 ORDER BY created_at DESC LIMIT 1",
                [':pid' => $projectId]
            );
            if ($noteData) {
                $payload = json_decode((string) ($noteData['payload'] ?? '{}'), true) ?: [];
                $authorRow = $this->db->fetchOne(
                    'SELECT name FROM users WHERE id = :uid LIMIT 1',
                    [':uid' => (int) ($noteData['user_id'] ?? 0)]
                );
                $lastNote = [
                    'text' => mb_substr((string) ($payload['content'] ?? $payload['note'] ?? ''), 0, 150),
                    'date' => (string) ($noteData['created_at'] ?? ''),
                    'author' => (string) ($authorRow['name'] ?? 'Sistema'),
                ];
            }

            $result[] = [
                'id' => $projectId,
                'name' => (string) ($project['name'] ?? ''),
                'client' => (string) ($project['client'] ?? ''),
                'pm' => (string) ($project['pm_name'] ?? ''),
                'progress' => (int) ($project['progress'] ?? 0),
                'status' => (string) ($project['status'] ?? ''),
                'health' => (string) ($project['health'] ?? ''),
                'blockers_count' => $blockersCount,
                'blockers_max_severity' => $maxSeverity,
                'top_blockers' => $topBlockers,
                'last_note' => $lastNote,
                'updated_at' => (string) ($project['updated_at'] ?? ''),
                'contract_value' => (float) ($project['contract_value'] ?? 0),
                'is_billable' => (int) ($project['is_billable'] ?? 0),
            ];
        }

        return $result;
    }

    public function getTeamCapacity(array $filters): array
    {
        if (!$this->db->tableExists('talents')) {
            return ['talents' => [], 'summary' => ['total' => 0, 'available' => 0, 'overloaded' => 0, 'critical' => 0, 'utilization_pct' => 0]];
        }

        $capacityColumn = $this->db->columnExists('talents', 'capacidad_horaria') ? 'capacidad_horaria' : '0';
        $weeklyColumn = $this->db->columnExists('talents', 'weekly_capacity') ? 'weekly_capacity' : '0';
        $availabilityColumn = $this->db->columnExists('talents', 'availability') ? 'availability' : '100';
        $roleColumn = $this->db->columnExists('talents', 'role') ? 'role' : "'' AS role";
        $areaColumn = $this->db->columnExists('talents', 'area') ? 'area' : "'' AS area";

        $talents = $this->db->fetchAll(
            "SELECT id, name, {$capacityColumn} AS capacidad_horaria, {$weeklyColumn} AS weekly_capacity,
                    {$availabilityColumn} AS availability, {$roleColumn}, {$areaColumn}
             FROM talents ORDER BY name ASC"
        );

        $from = $filters['from'] ?? date('Y-m-01');
        $to = $filters['to'] ?? date('Y-m-t');

        $hoursByTalent = [];
        if ($this->db->tableExists('timesheets') && $this->db->columnExists('timesheets', 'talent_id')) {
            $rows = $this->db->fetchAll(
                'SELECT talent_id, SUM(hours) AS total
                 FROM timesheets
                 WHERE date BETWEEN :start AND :end
                 GROUP BY talent_id',
                [':start' => $from, ':end' => $to]
            );
            foreach ($rows as $row) {
                $hoursByTalent[(int) ($row['talent_id'] ?? 0)] = (float) ($row['total'] ?? 0);
            }
        }

        $workingDays = $this->workingDaysInRange($from, $to);
        $talentRows = [];
        $totalCapacity = 0.0;
        $totalUsed = 0.0;
        $overloadedCount = 0;
        $criticalCount = 0;
        $availableCount = 0;

        foreach ($talents as $talent) {
            $talentId = (int) ($talent['id'] ?? 0);
            $weeklyCapacity = (float) ($talent['capacidad_horaria'] ?? 0);
            if ($weeklyCapacity <= 0) {
                $weeklyCapacity = (float) ($talent['weekly_capacity'] ?? 0);
            }
            if ($weeklyCapacity <= 0) {
                $weeklyCapacity = 40.0;
            }

            $availability = max(0.0, min(100.0, (float) ($talent['availability'] ?? 100)));
            $periodCapacity = ($weeklyCapacity * ($workingDays / 5)) * ($availability / 100);
            $usedHours = (float) ($hoursByTalent[$talentId] ?? 0);
            $utilization = $periodCapacity > 0 ? ($usedHours / $periodCapacity) * 100 : 0.0;
            $freeHours = max(0.0, $periodCapacity - $usedHours);

            $totalCapacity += $periodCapacity;
            $totalUsed += $usedHours;

            $status = 'available';
            if ($utilization > self::CRITICAL_OVERLOAD_THRESHOLD) {
                $status = 'critical';
                $criticalCount++;
                $overloadedCount++;
            } elseif ($utilization > self::OVERLOAD_THRESHOLD) {
                $status = 'overloaded';
                $overloadedCount++;
            } else {
                $availableCount++;
            }

            $talentRows[] = [
                'id' => $talentId,
                'name' => (string) ($talent['name'] ?? ''),
                'role' => (string) ($talent['role'] ?? ''),
                'area' => (string) ($talent['area'] ?? ''),
                'capacity' => round($periodCapacity, 1),
                'used_hours' => round($usedHours, 1),
                'free_hours' => round($freeHours, 1),
                'utilization' => round($utilization, 1),
                'status' => $status,
            ];
        }

        usort($talentRows, static fn (array $a, array $b): int => (float) $b['utilization'] <=> (float) $a['utilization']);

        return [
            'talents' => $talentRows,
            'summary' => [
                'total' => count($talentRows),
                'available' => $availableCount,
                'overloaded' => $overloadedCount,
                'critical' => $criticalCount,
                'utilization_pct' => $totalCapacity > 0 ? round(($totalUsed / $totalCapacity) * 100, 1) : 0.0,
                'total_capacity' => round($totalCapacity, 1),
                'total_used' => round($totalUsed, 1),
            ],
        ];
    }

    public function simulateCapacity(array $payload): array
    {
        $estimatedHours = max(0.0, (float) ($payload['estimated_hours'] ?? 0));
        $area = trim((string) ($payload['area'] ?? ''));
        $role = trim((string) ($payload['role'] ?? ''));
        $from = $payload['date_from'] ?? date('Y-m-01');
        $to = $payload['date_to'] ?? date('Y-m-t');
        $requiredResources = max(0, (int) ($payload['required_resources'] ?? 0));

        $capacityData = $this->getTeamCapacity(['from' => $from, 'to' => $to]);
        $talents = $capacityData['talents'];

        if ($area !== '') {
            $talents = array_values(array_filter($talents, fn (array $t) => stripos($t['area'], $area) !== false));
        }
        if ($role !== '') {
            $talents = array_values(array_filter($talents, fn (array $t) => stripos($t['role'], $role) !== false));
        }

        $rows = [];
        $totalAvailable = 0.0;
        foreach ($talents as $talent) {
            $totalAvailable += (float) $talent['free_hours'];
        }

        $remaining = $estimatedHours;
        $riskTalents = [];
        $affectedProjects = [];

        foreach ($talents as $index => $talent) {
            $isLast = $index === count($talents) - 1;
            if ($isLast) {
                $assigned = max(0.0, $remaining);
            } else {
                $share = $totalAvailable > 0
                    ? ($estimatedHours * ((float) $talent['free_hours'] / $totalAvailable))
                    : (count($talents) > 0 ? $estimatedHours / count($talents) : 0);
                $assigned = min(max(0.0, round($share, 2)), max(0.0, $remaining));
                $remaining -= $assigned;
            }

            $simulatedUsed = (float) $talent['used_hours'] + $assigned;
            $capacity = (float) $talent['capacity'];
            $newUtil = $capacity > 0 ? ($simulatedUsed / $capacity) * 100 : ($simulatedUsed > 0 ? 100.0 : 0.0);
            $risk = $newUtil > self::OVERLOAD_THRESHOLD;

            $status = 'available';
            if ($newUtil > self::CRITICAL_OVERLOAD_THRESHOLD) {
                $status = 'critical';
            } elseif ($newUtil > self::OVERLOAD_THRESHOLD) {
                $status = 'overloaded';
            }

            $row = [
                'name' => $talent['name'],
                'role' => $talent['role'],
                'current_hours' => $talent['used_hours'],
                'capacity' => $talent['capacity'],
                'extra_hours' => round($assigned, 1),
                'simulated_hours' => round($simulatedUsed, 1),
                'utilization' => round($newUtil, 1),
                'risk' => $risk,
                'status' => $status,
            ];
            $rows[] = $row;

            if ($risk) {
                $riskTalents[] = $talent['name'];
            }
        }

        usort($rows, static fn (array $a, array $b): int => (float) $b['utilization'] <=> (float) $a['utilization']);

        if (!empty($riskTalents) && $this->db->tableExists('project_talent_assignments')) {
            $talentNames = array_slice($riskTalents, 0, 5);
            $placeholders = [];
            $affParams = [];
            foreach ($talentNames as $i => $name) {
                $placeholders[] = ":tn{$i}";
                $affParams[":tn{$i}"] = $name;
            }
            $inClause = implode(',', $placeholders);
            $talentCol = $this->db->columnExists('project_talent_assignments', 'talent_id') ? 'talent_id' : 'user_id';
            $affectedProjects = $this->db->fetchAll(
                "SELECT DISTINCT p.name
                 FROM project_talent_assignments a
                 JOIN talents t ON t.id = a.{$talentCol}
                 JOIN projects p ON p.id = a.project_id
                 WHERE t.name IN ({$inClause})
                 AND (a.assignment_status = 'active' OR (a.assignment_status IS NULL AND a.active = 1))
                 LIMIT 10",
                $affParams
            );
        }

        $teamCapacity = array_sum(array_column($rows, 'capacity'));
        $simulatedCommitted = array_sum(array_column($rows, 'simulated_hours'));

        return [
            'rows' => $rows,
            'kpis' => [
                'team_capacity' => round($teamCapacity, 1),
                'estimated_utilization' => $teamCapacity > 0 ? round(($simulatedCommitted / $teamCapacity) * 100, 1) : 0,
                'risk_talent_count' => count($riskTalents),
                'remaining_capacity' => round($teamCapacity - $simulatedCommitted, 1),
            ],
            'risk_talents' => $riskTalents,
            'affected_projects' => array_column($affectedProjects, 'name'),
        ];
    }

    public function getAiAnalysis(array $filters, array $user): ?array
    {
        $cacheKey = $this->buildCacheKey($filters, $user);

        $cached = $this->getCachedAnalysis($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $summary = $this->getPortfolioSummary($filters, $user);
        $alerts = $this->getAlerts($filters, $user);

        $analysis = $this->generateAnalysisText($summary, $alerts);

        $this->cacheAnalysis($cacheKey, $analysis);

        return $analysis;
    }

    private function generateAnalysisText(array $summary, array $alerts): array
    {
        $score = (int) ($summary['portfolio_score'] ?? 0);
        $activeProjects = (int) ($summary['active_projects'] ?? 0);
        $atRisk = (int) ($summary['at_risk_projects'] ?? 0);
        $blockers = (int) ($summary['open_blockers'] ?? 0);
        $utilization = (float) ($summary['utilization_pct'] ?? 0);
        $billingPending = (float) ($summary['billing_pending_amount'] ?? 0);

        $diagLevel = $score >= 80 ? 'estable' : ($score >= 60 ? 'atención moderada' : 'intervención urgente');

        $lines = [];
        $lines[] = "DIAGNÓSTICO DEL PORTAFOLIO: El portafolio presenta un score de {$score}/100, en estado de {$diagLevel}.";
        $lines[] = "Se monitorean {$activeProjects} proyectos activos, de los cuales {$atRisk} se encuentran en riesgo.";

        if ($blockers > 0) {
            $lines[] = "Existen {$blockers} bloqueos abiertos que requieren atención inmediata.";
        }
        if ($utilization > 90) {
            $lines[] = "La utilización del equipo ({$utilization}%) se encuentra en zona de sobrecarga.";
        } elseif ($utilization < 60) {
            $lines[] = "La utilización del equipo ({$utilization}%) indica capacidad disponible significativa.";
        } else {
            $lines[] = "La utilización del equipo ({$utilization}%) se mantiene en rango operativo.";
        }

        $recs = [];
        if ($blockers > 0) {
            $recs[] = 'Priorizar la resolución de bloqueos críticos en las próximas 24-48 horas.';
        }
        if ($atRisk > 0) {
            $recs[] = 'Activar plan de mitigación para los ' . $atRisk . ' proyectos en riesgo.';
        }
        if ($billingPending > 0) {
            $recs[] = 'Acelerar facturación pendiente ($' . number_format($billingPending, 0) . ') para cerrar brecha financiera.';
        }
        if (empty($recs)) {
            $recs[] = 'Mantener cadencia de seguimiento semanal para sostener desempeño.';
            $recs[] = 'Consolidar lecciones aprendidas de proyectos exitosos.';
            $recs[] = 'Evaluar oportunidades de optimización en la distribución de carga.';
        }

        $detectedAlerts = [];
        foreach ($alerts as $alert) {
            if ((int) ($alert['count'] ?? 0) > 0) {
                $detectedAlerts[] = ($alert['label'] ?? '') . ': ' . ($alert['count'] ?? 0);
            }
        }
        if (empty($detectedAlerts)) {
            $detectedAlerts[] = 'Sin alertas activas en este momento.';
        }

        return [
            'diagnosis' => implode(' ', $lines),
            'recommendations' => array_slice($recs, 0, 3),
            'detected_alerts' => array_slice($detectedAlerts, 0, 3),
            'generated_at' => date('Y-m-d H:i:s'),
            'cached' => false,
        ];
    }

    private function buildCacheKey(array $filters, array $user): string
    {
        $parts = [
            'dc',
            $filters['from'] ?? '',
            $filters['to'] ?? '',
            $filters['client_id'] ?? '',
            $filters['pm_id'] ?? '',
            $filters['status'] ?? '',
            $user['role_id'] ?? '',
        ];
        return md5(implode('|', $parts));
    }

    private function getCachedAnalysis(string $cacheKey): ?array
    {
        if (!$this->db->tableExists('decision_center_ai_cache')) {
            return null;
        }

        $row = $this->db->fetchOne(
            'SELECT content FROM decision_center_ai_cache WHERE cache_key = :key AND expires_at > NOW() LIMIT 1',
            [':key' => $cacheKey]
        );

        if (!$row) {
            return null;
        }

        $data = json_decode((string) ($row['content'] ?? ''), true);
        if (!is_array($data)) {
            return null;
        }

        $data['cached'] = true;
        return $data;
    }

    private function cacheAnalysis(string $cacheKey, array $analysis): void
    {
        if (!$this->db->tableExists('decision_center_ai_cache')) {
            return;
        }

        try {
            $this->db->execute('DELETE FROM decision_center_ai_cache WHERE cache_key = :key', [':key' => $cacheKey]);
            $this->db->insert(
                'INSERT INTO decision_center_ai_cache (cache_key, content, expires_at) VALUES (:key, :content, DATE_ADD(NOW(), INTERVAL ' . self::CACHE_TTL_MINUTES . ' MINUTE))',
                [
                    ':key' => $cacheKey,
                    ':content' => json_encode($analysis, JSON_UNESCAPED_UNICODE),
                ]
            );
        } catch (\Throwable $e) {
            error_log('Error caching decision center analysis: ' . $e->getMessage());
        }
    }

    private function countProjectsAtRisk(string $condition, string $activeFilter, array $params, ?string $healthColumn): int
    {
        $count = 0;

        if ($healthColumn !== null) {
            $healthRisk = $this->db->fetchOne(
                "SELECT COUNT(*) AS total
                 FROM projects p
                 JOIN clients c ON c.id = p.client_id
                 {$condition}{$activeFilter}
                 AND p.{$healthColumn} IN ('at_risk','critical','red')",
                $params
            );
            $count = (int) ($healthRisk['total'] ?? 0);
        }

        $scoreService = new ProjectService($this->db);
        $allProjects = $this->db->fetchAll(
            "SELECT p.id
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             {$condition}{$activeFilter}",
            $params
        );

        $scoreBelowThreshold = 0;
        foreach ($allProjects as $project) {
            $pid = (int) ($project['id'] ?? 0);
            if ($pid <= 0) continue;
            try {
                $scoreData = $scoreService->calculateProjectHealthScore($pid);
                if ((int) ($scoreData['total_score'] ?? 100) < self::RISK_SCORE_THRESHOLD) {
                    $scoreBelowThreshold++;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        $criticalBlockerProjects = 0;
        if ($this->db->tableExists('project_stoppers')) {
            $blockerRow = $this->db->fetchOne(
                "SELECT COUNT(DISTINCT s.project_id) AS total
                 FROM project_stoppers s
                 JOIN projects p ON p.id = s.project_id
                 JOIN clients c ON c.id = p.client_id
                 {$condition}{$activeFilter}
                 AND s.status IN ('abierto','en_gestion','escalado')
                 AND s.impact_level = 'critico'",
                $params
            );
            $criticalBlockerProjects = (int) ($blockerRow['total'] ?? 0);
        }

        return max($count, $scoreBelowThreshold, $criticalBlockerProjects);
    }

    private function calculateBillingPending(string $condition, string $activeFilter, array $params): array
    {
        $totalPending = 0.0;
        $projectCount = 0;

        if (!$this->db->tableExists('project_invoices')) {
            return ['amount' => 0.0, 'project_count' => 0];
        }

        $projects = $this->db->fetchAll(
            "SELECT p.id, p.billing_type, p.contract_value, p.hourly_rate, p.is_billable
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             {$condition}{$activeFilter}
             AND p.is_billable = 1",
            $params
        );

        foreach ($projects as $project) {
            $pid = (int) ($project['id'] ?? 0);
            $billingType = (string) ($project['billing_type'] ?? 'fixed');
            $contractValue = (float) ($project['contract_value'] ?? 0);
            $hourlyRate = (float) ($project['hourly_rate'] ?? 0);

            $invoicedRow = $this->db->fetchOne(
                "SELECT COALESCE(SUM(amount), 0) AS total
                 FROM project_invoices
                 WHERE project_id = :pid AND status <> 'cancelled'",
                [':pid' => $pid]
            );
            $totalInvoiced = (float) ($invoicedRow['total'] ?? 0);

            $pending = 0.0;
            if ($billingType === 'fixed' || $billingType === 'one_time') {
                $pending = max(0, $contractValue - $totalInvoiced);
            } elseif ($billingType === 'hours') {
                $approvedRow = $this->db->fetchOne(
                    "SELECT COALESCE(SUM(hours), 0) AS total
                     FROM timesheets
                     WHERE project_id = :pid AND status = 'approved'",
                    [':pid' => $pid]
                );
                $approvedHours = (float) ($approvedRow['total'] ?? 0);
                $pending = max(0, ($approvedHours * $hourlyRate) - $totalInvoiced);
            } else {
                $pending = max(0, $contractValue - $totalInvoiced);
            }

            if ($pending > 0) {
                $totalPending += $pending;
                $projectCount++;
            }
        }

        return ['amount' => round($totalPending, 2), 'project_count' => $projectCount];
    }

    private function calculateTeamUtilization(array $filters): array
    {
        if (!$this->db->tableExists('talents')) {
            return ['utilization_pct' => 0.0, 'overloaded_count' => 0, 'critical_count' => 0];
        }

        $capacityColumn = $this->db->columnExists('talents', 'capacidad_horaria') ? 'capacidad_horaria' : '0';
        $weeklyColumn = $this->db->columnExists('talents', 'weekly_capacity') ? 'weekly_capacity' : '0';
        $availabilityColumn = $this->db->columnExists('talents', 'availability') ? 'availability' : '100';

        $talents = $this->db->fetchAll(
            "SELECT id, {$capacityColumn} AS capacidad_horaria, {$weeklyColumn} AS weekly_capacity,
                    {$availabilityColumn} AS availability
             FROM talents"
        );

        $from = $filters['from'] ?? date('Y-m-01');
        $to = $filters['to'] ?? date('Y-m-t');

        $hoursByTalent = [];
        if ($this->db->tableExists('timesheets') && $this->db->columnExists('timesheets', 'talent_id')) {
            $rows = $this->db->fetchAll(
                'SELECT talent_id, SUM(hours) AS total FROM timesheets WHERE date BETWEEN :start AND :end GROUP BY talent_id',
                [':start' => $from, ':end' => $to]
            );
            foreach ($rows as $row) {
                $hoursByTalent[(int) ($row['talent_id'] ?? 0)] = (float) ($row['total'] ?? 0);
            }
        }

        $workingDays = $this->workingDaysInRange($from, $to);
        $totalCapacity = 0.0;
        $totalUsed = 0.0;
        $overloaded = 0;
        $critical = 0;

        foreach ($talents as $talent) {
            $wc = (float) ($talent['capacidad_horaria'] ?? 0);
            if ($wc <= 0) $wc = (float) ($talent['weekly_capacity'] ?? 0);
            if ($wc <= 0) $wc = 40.0;

            $avail = max(0.0, min(100.0, (float) ($talent['availability'] ?? 100)));
            $cap = ($wc * ($workingDays / 5)) * ($avail / 100);
            $used = (float) ($hoursByTalent[(int) ($talent['id'] ?? 0)] ?? 0);
            $util = $cap > 0 ? ($used / $cap) * 100 : 0;

            $totalCapacity += $cap;
            $totalUsed += $used;

            if ($util > self::CRITICAL_OVERLOAD_THRESHOLD) {
                $critical++;
                $overloaded++;
            } elseif ($util > self::OVERLOAD_THRESHOLD) {
                $overloaded++;
            }
        }

        return [
            'utilization_pct' => $totalCapacity > 0 ? round(($totalUsed / $totalCapacity) * 100, 1) : 0.0,
            'overloaded_count' => $overloaded,
            'critical_count' => $critical,
        ];
    }

    private function calculatePortfolioScore(float $avgProgress, int $activeProjects, int $atRisk, int $blockers, float $billingPending, float $utilization): int
    {
        $riskPct = $activeProjects > 0 ? ($atRisk / $activeProjects) * 100 : 0;
        $riskScore = max(0.0, min(100.0, 100.0 - ($riskPct * 1.4)));
        $blockersPressure = min(100.0, $blockers * 10.0);
        $blockersScore = max(0.0, 100.0 - $blockersPressure);
        $capacityScore = max(0.0, 100.0 - (abs($utilization - 80.0) * 2.0));

        $score = ($avgProgress * 0.30)
               + ($riskScore * 0.20)
               + ($blockersScore * 0.15)
               + (min(100, $utilization > 0 ? 100.0 : 0.0) * 0.20)
               + ($capacityScore * 0.15);

        return max(0, min(100, (int) round($score)));
    }

    private function buildFilters(array $filters, array $user): array
    {
        $conditions = [];
        $params = [];

        if (!$this->isPrivileged($user) && $this->db->columnExists('projects', 'pm_id')) {
            $conditions[] = 'p.pm_id = :pmId';
            $params[':pmId'] = $user['id'];
        }

        if (!empty($filters['client_id'])) {
            $conditions[] = 'p.client_id = :clientId';
            $params[':clientId'] = (int) $filters['client_id'];
        }

        if (!empty($filters['pm_id'])) {
            $conditions[] = 'p.pm_id = :filtPmId';
            $params[':filtPmId'] = (int) $filters['pm_id'];
        }

        if (empty($conditions)) {
            return ['', []];
        }

        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }

    private function isPrivileged(array $user): bool
    {
        return in_array($user['role'] ?? '', self::ADMIN_ROLES, true);
    }

    private function projectStatusColumn(): ?string
    {
        if ($this->db->columnExists('projects', 'status_code')) return 'status_code';
        if ($this->db->columnExists('projects', 'status')) return 'status';
        return null;
    }

    private function projectHealthColumn(): ?string
    {
        if ($this->db->columnExists('projects', 'health_code')) return 'health_code';
        if ($this->db->columnExists('projects', 'health')) return 'health';
        return null;
    }

    private function workingDaysInRange(string $from, string $to): int
    {
        try {
            $start = new \DateTimeImmutable($from);
            $end = new \DateTimeImmutable($to);
        } catch (\Throwable $e) {
            return 22;
        }

        $cursor = $start;
        $days = 0;
        while ($cursor <= $end) {
            if ((int) $cursor->format('N') <= 5) {
                $days++;
            }
            $cursor = $cursor->modify('+1 day');
        }

        return max(1, $days);
    }
}
