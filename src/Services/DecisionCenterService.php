<?php

declare(strict_types=1);

class DecisionCenterService
{
    private const HEALTH_SCORE_THRESHOLD = 70;
    private const STALE_DAYS = 7;
    private const OVERLOAD_THRESHOLD = 90;
    private const CRITICAL_THRESHOLD = 100;

    public function __construct(private Database $db)
    {
    }

    // ─── Public API ──────────────────────────────────────────────────────────

    public function getPortfolioSummary(array $filters): array
    {
        [$where, $params] = $this->buildProjectWhere($filters);
        $statusExpr = $this->statusExpr();
        $healthExpr = $this->healthExpr();

        $baseCondition = $where . " AND {$statusExpr} NOT IN ('closed','archived','cancelled')";

        try {
            $totalsRow = $this->db->fetchOne(
                "SELECT COUNT(*) AS total
                 FROM projects p
                 JOIN clients c ON c.id = p.client_id
                 {$baseCondition}",
                $params
            );
            $activeTotal = (int) ($totalsRow['total'] ?? 0);

            $atRiskCount = $this->countProjectsAtRisk($where, $params);
            $blockers    = $this->countActiveBlockers($where, $params);
            $billing     = $this->billingPendingAmount($where, $params);
            $utilization = $this->teamUtilizationSummary();
            $score       = $this->portfolioScore($where, $params);

            return [
                'score_general'            => $score,
                'proyectos_activos'        => $activeTotal,
                'proyectos_en_riesgo'      => $atRiskCount,
                'bloqueos_activos'         => $blockers['total'],
                'bloqueos_criticos'        => $blockers['criticos'],
                'facturacion_pendiente'    => $billing['amount'],
                'facturacion_pendiente_fmt'=> $billing['formatted'],
                'utilizacion_equipo_pct'   => $utilization['utilization_pct'],
                'sobrecarga_count'         => $utilization['overloaded_count'],
            ];
        } catch (\Throwable $e) {
            error_log('DecisionCenterService::getPortfolioSummary error: ' . $e->getMessage());
            return $this->emptySummary();
        }
    }

    public function getAlerts(array $filters): array
    {
        [$where, $params] = $this->buildProjectWhere($filters);
        $statusExpr = $this->statusExpr();

        $alerts = [];

        try {
            // Sin actualización > 7 días
            $staleCount = $this->countStaleProjects($where, $params, $statusExpr);
            $alerts['sin_actualizacion'] = [
                'count'   => $staleCount,
                'label'   => "Sin actualización > 7 días",
                'type'    => 'stale',
                'level'   => $staleCount >= 3 ? 'red' : ($staleCount > 0 ? 'amber' : 'green'),
            ];

            // Bloqueos críticos abiertos
            $blockers = $this->countActiveBlockers($where, $params);
            $alerts['bloqueos_criticos'] = [
                'count'   => $blockers['criticos'],
                'label'   => "Bloqueos críticos abiertos",
                'type'    => 'blockers',
                'level'   => $blockers['criticos'] >= 2 ? 'red' : ($blockers['criticos'] > 0 ? 'amber' : 'green'),
            ];

            // Riesgos críticos
            $criticalRisks = $this->countCriticalRisks($where, $params);
            $alerts['riesgos_criticos'] = [
                'count'   => $criticalRisks,
                'label'   => "Riesgos críticos",
                'type'    => 'risks',
                'level'   => $criticalRisks >= 3 ? 'red' : ($criticalRisks > 0 ? 'amber' : 'green'),
            ];

            // Facturación pendiente (count of projects)
            $billingAlert = $this->billingPendingProjects($where, $params);
            $alerts['facturacion_pendiente'] = [
                'count'   => $billingAlert['count'],
                'label'   => "Facturación pendiente",
                'type'    => 'billing',
                'level'   => $billingAlert['count'] >= 3 ? 'red' : ($billingAlert['count'] > 0 ? 'amber' : 'green'),
            ];

            // Sobrecarga de talento > 90%
            $utilization = $this->teamUtilizationSummary();
            $overloaded  = $utilization['overloaded_count'];
            $alerts['sobrecarga_talento'] = [
                'count'   => $overloaded,
                'label'   => "Sobrecarga de talento (>90%)",
                'type'    => 'overload',
                'level'   => $overloaded >= 3 ? 'red' : ($overloaded > 0 ? 'amber' : 'green'),
            ];
        } catch (\Throwable $e) {
            error_log('DecisionCenterService::getAlerts error: ' . $e->getMessage());
        }

        return $alerts;
    }

    public function getRecommendations(array $filters): array
    {
        [$where, $params] = $this->buildProjectWhere($filters);
        $statusExpr = $this->statusExpr();

        $decisions = [];

        try {
            // 1. Critical blockers (highest priority)
            if ($this->db->tableExists('project_stoppers')) {
                $criticalBlockerProjects = $this->db->fetchAll(
                    "SELECT p.id, p.name AS project_name, c.name AS client_name,
                            COUNT(s.id) AS blocker_count,
                            MAX(DATEDIFF(CURDATE(), COALESCE(s.detected_at, DATE(s.created_at)))) AS max_days_open
                     FROM project_stoppers s
                     JOIN projects p ON p.id = s.project_id
                     JOIN clients c ON c.id = p.client_id
                     {$where}
                     AND s.status IN ('abierto','en_gestion','escalado')
                     AND s.impact_level IN ('critico','alto')
                     AND {$statusExpr} NOT IN ('closed','archived','cancelled')
                     GROUP BY p.id, p.name, c.name
                     ORDER BY blocker_count DESC, max_days_open DESC
                     LIMIT 5",
                    $params
                );
                foreach ($criticalBlockerProjects as $row) {
                    $days = (int) ($row['max_days_open'] ?? 0);
                    $decisions[] = [
                        'title'      => 'Resolver bloqueo crítico: ' . $this->truncate((string) ($row['project_name'] ?? ''), 40),
                        'reason'     => sprintf(
                            '%d bloqueo(s) activo(s) en %s (%s). El más antiguo lleva %d día(s) abierto.',
                            (int) ($row['blocker_count'] ?? 0),
                            $row['project_name'] ?? '',
                            $row['client_name'] ?? '',
                            $days
                        ),
                        'impact'     => 'Alto',
                        'action'     => 'abrir_bloqueos',
                        'project_id' => (int) ($row['id'] ?? 0),
                        'priority'   => 100 + $days,
                    ];
                }
            }

            // 2. Projects at risk (score < threshold or health critical)
            $atRiskProjects = $this->fetchProjectsAtRisk($where, $params, $statusExpr, 5);
            foreach ($atRiskProjects as $row) {
                $decisions[] = [
                    'title'      => 'Intervenir proyecto en riesgo: ' . $this->truncate((string) ($row['project_name'] ?? ''), 40),
                    'reason'     => sprintf(
                        'Proyecto %s (%s) con salud %s y avance del %d%%.',
                        $row['project_name'] ?? '',
                        $row['client_name'] ?? '',
                        $this->healthLabel((string) ($row['health'] ?? '')),
                        (int) ($row['progress'] ?? 0)
                    ),
                    'impact'     => (string) ($row['health'] ?? '') === 'critical' ? 'Alto' : 'Medio',
                    'action'     => 'ver_proyecto',
                    'project_id' => (int) ($row['id'] ?? 0),
                    'priority'   => 80,
                ];
            }

            // 3. Billing pending projects
            $billingProjects = $this->fetchBillingPendingProjects($where, $params, 5);
            foreach ($billingProjects as $row) {
                $amount = (float) ($row['pending_amount'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }
                $decisions[] = [
                    'title'      => 'Facturar: ' . $this->truncate((string) ($row['project_name'] ?? ''), 40),
                    'reason'     => sprintf(
                        'Pendiente por facturar: %s %s en proyecto %s.',
                        number_format($amount, 0, ',', '.'),
                        $row['currency_code'] ?? '',
                        $row['project_name'] ?? ''
                    ),
                    'impact'     => $amount > 50000 ? 'Alto' : 'Medio',
                    'action'     => 'ir_facturacion',
                    'project_id' => (int) ($row['id'] ?? 0),
                    'priority'   => 70,
                ];
            }

            // 4. Stale projects (no note > 7 days)
            $staleProjects = $this->fetchStaleProjects($where, $params, $statusExpr, 5);
            foreach ($staleProjects as $row) {
                $days = (int) ($row['days_stale'] ?? 0);
                $decisions[] = [
                    'title'      => 'Registrar seguimiento: ' . $this->truncate((string) ($row['project_name'] ?? ''), 40),
                    'reason'     => sprintf(
                        'Sin nota de seguimiento hace %d día(s) en proyecto %s (%s).',
                        $days,
                        $row['project_name'] ?? '',
                        $row['client_name'] ?? ''
                    ),
                    'impact'     => $days > 14 ? 'Alto' : 'Medio',
                    'action'     => 'ver_proyecto',
                    'project_id' => (int) ($row['id'] ?? 0),
                    'priority'   => 50 + min($days, 30),
                ];
            }

            // 5. Overloaded talent → redistribute
            if ($this->db->tableExists('talents') && $this->db->tableExists('project_talent_assignments')) {
                $overloadedTalents = $this->fetchOverloadedTalents(3);
                if ($overloadedTalents !== []) {
                    $names = array_map(
                        static fn (array $t): string => (string) ($t['name'] ?? 'Talento'),
                        array_slice($overloadedTalents, 0, 2)
                    );
                    $decisions[] = [
                        'title'      => 'Redistribuir carga del equipo',
                        'reason'     => sprintf(
                            '%d talento(s) están al %d%%+ de utilización: %s.',
                            count($overloadedTalents),
                            self::OVERLOAD_THRESHOLD,
                            implode(', ', $names)
                        ),
                        'impact'     => count($overloadedTalents) >= 3 ? 'Alto' : 'Medio',
                        'action'     => 'asignar_recurso',
                        'project_id' => null,
                        'priority'   => 60,
                    ];
                }
            }

        } catch (\Throwable $e) {
            error_log('DecisionCenterService::getRecommendations error: ' . $e->getMessage());
        }

        // Sort by priority descending and limit to top 10
        usort($decisions, static fn (array $a, array $b): int => ((int) ($b['priority'] ?? 0)) <=> ((int) ($a['priority'] ?? 0)));

        return array_slice($decisions, 0, 10);
    }

    public function getProjectRanking(array $filters): array
    {
        [$where, $params] = $this->buildProjectWhere($filters);
        $statusExpr = $this->statusExpr();
        $healthExpr = $this->healthExpr();

        try {
            $projects = $this->db->fetchAll(
                "SELECT p.id, p.name AS project_name, c.name AS client_name,
                        COALESCE(u.name, 'Sin PM') AS pm_name,
                        {$statusExpr} AS status,
                        {$healthExpr} AS health,
                        COALESCE(p.progress, 0) AS progress,
                        p.contract_value, p.currency_code,
                        p.is_billable, p.billing_type
                 FROM projects p
                 JOIN clients c ON c.id = p.client_id
                 LEFT JOIN users u ON u.id = p.pm_id
                 {$where}
                 AND {$statusExpr} NOT IN ('closed','archived','cancelled')
                 ORDER BY p.name ASC",
                $params
            );

            if ($projects === []) {
                return [];
            }

            $projectIds = array_column($projects, 'id');

            // Last note per project
            $lastNotes = $this->fetchLastNotesForProjects($projectIds);

            // Blocker summary per project
            $blockerSummary = $this->fetchBlockerSummaryForProjects($projectIds);

            // Billing pending per project
            $billingPending = $this->fetchBillingPendingForProjects($projectIds);

            // Health scores (reuse ProjectService)
            $service = new ProjectService($this->db);

            $result = [];
            foreach ($projects as $row) {
                $pid = (int) ($row['id'] ?? 0);

                $noteData    = $lastNotes[$pid]   ?? null;
                $blockerData = $blockerSummary[$pid] ?? ['count' => 0, 'max_severity' => null, 'list' => []];
                $pending     = $billingPending[$pid] ?? 0.0;

                try {
                    $scoreReport = $service->calculateProjectHealthReport($pid);
                    $healthScore = (int) ($scoreReport['total_score'] ?? 0);
                } catch (\Throwable $e) {
                    $healthScore = 0;
                }

                $isAtRisk = $this->isProjectAtRisk(
                    $healthScore,
                    (string) ($row['health'] ?? ''),
                    (int) ($blockerData['count_critical'] ?? 0),
                    $noteData
                );

                $result[] = [
                    'id'              => $pid,
                    'project_name'    => (string) ($row['project_name'] ?? ''),
                    'client_name'     => (string) ($row['client_name'] ?? ''),
                    'pm_name'         => (string) ($row['pm_name'] ?? 'Sin PM'),
                    'status'          => (string) ($row['status'] ?? ''),
                    'health'          => (string) ($row['health'] ?? ''),
                    'health_score'    => $healthScore,
                    'progress'        => (int) ($row['progress'] ?? 0),
                    'is_at_risk'      => $isAtRisk,
                    'blockers_count'  => (int) ($blockerData['count'] ?? 0),
                    'blockers_max_severity' => (string) ($blockerData['max_severity'] ?? ''),
                    'blockers_top'    => $blockerData['list'] ?? [],
                    'last_note_date'  => $noteData['created_at'] ?? null,
                    'last_note_preview' => $noteData['note'] ?? null,
                    'last_note_author'  => $noteData['author'] ?? null,
                    'days_since_note' => $noteData ? max(0, (int) round((time() - strtotime((string) $noteData['created_at'])) / 86400)) : null,
                    'billing_pending' => $pending,
                    'currency_code'   => (string) ($row['currency_code'] ?? 'USD'),
                    'is_billable'     => (bool) ($row['is_billable'] ?? false),
                ];
            }

            // Sort: at-risk first, then by health_score ascending
            usort($result, static function (array $a, array $b): int {
                if ($a['is_at_risk'] !== $b['is_at_risk']) {
                    return ($b['is_at_risk'] ? 1 : 0) - ($a['is_at_risk'] ? 1 : 0);
                }
                return ($a['health_score'] ?? 0) <=> ($b['health_score'] ?? 0);
            });

            return $result;
        } catch (\Throwable $e) {
            error_log('DecisionCenterService::getProjectRanking error: ' . $e->getMessage());
            return [];
        }
    }

    public function getTeamCapacity(array $filters): array
    {
        if (!$this->db->tableExists('talents')) {
            return [];
        }

        try {
            $capacityColumn    = $this->db->columnExists('talents', 'capacidad_horaria') ? 'capacidad_horaria' : '40';
            $weeklyColumn      = $this->db->columnExists('talents', 'weekly_capacity')   ? 'weekly_capacity'   : '40';
            $availabilityColumn = $this->db->columnExists('talents', 'availability')     ? 'availability'      : '100';

            $from = $filters['from'] ?? date('Y-m-01');
            $to   = $filters['to']   ?? date('Y-m-t');

            $talents = $this->db->fetchAll(
                "SELECT t.id, t.name, t.role,
                        {$capacityColumn} AS capacidad_horaria,
                        {$weeklyColumn} AS weekly_capacity,
                        {$availabilityColumn} AS availability
                 FROM talents t
                 ORDER BY t.name ASC"
            );

            if ($talents === []) {
                return [];
            }

            // Hours used in period from timesheets
            $hoursByTalent = [];
            if ($this->db->tableExists('timesheets') && $this->db->columnExists('timesheets', 'talent_id')) {
                $rows = $this->db->fetchAll(
                    'SELECT talent_id, SUM(hours) AS total
                     FROM timesheets
                     WHERE date BETWEEN :from AND :to
                     GROUP BY talent_id',
                    [':from' => $from, ':to' => $to]
                );
                foreach ($rows as $row) {
                    $hoursByTalent[(int) ($row['talent_id'] ?? 0)] = (float) ($row['total'] ?? 0);
                }
            }

            $workingDays = $this->workingDaysBetween($from, $to);

            $result = [];
            foreach ($talents as $talent) {
                $talentId = (int) ($talent['id'] ?? 0);
                $weekly   = (float) ($talent['capacidad_horaria'] ?? 0);
                if ($weekly <= 0) {
                    $weekly = (float) ($talent['weekly_capacity'] ?? 40);
                }
                if ($weekly <= 0) {
                    $weekly = 40;
                }
                $avail        = max(0.0, min(100.0, (float) ($talent['availability'] ?? 100)));
                $capacity     = ($weekly * ($workingDays / 5)) * ($avail / 100);
                $used         = (float) ($hoursByTalent[$talentId] ?? 0);
                $free         = max(0.0, $capacity - $used);
                $utilization  = $capacity > 0 ? ($used / $capacity) * 100 : 0.0;

                $status = 'disponible';
                if ($utilization >= self::CRITICAL_THRESHOLD) {
                    $status = 'critico';
                } elseif ($utilization >= self::OVERLOAD_THRESHOLD) {
                    $status = 'riesgo';
                } elseif ($utilization >= 60) {
                    $status = 'normal';
                }

                $result[] = [
                    'id'             => $talentId,
                    'name'           => (string) ($talent['name'] ?? ''),
                    'role'           => (string) ($talent['role'] ?? ''),
                    'capacity_hours' => round($capacity, 1),
                    'used_hours'     => round($used, 1),
                    'free_hours'     => round($free, 1),
                    'utilization_pct'=> round($utilization, 1),
                    'status'         => $status,
                ];
            }

            return $result;
        } catch (\Throwable $e) {
            error_log('DecisionCenterService::getTeamCapacity error: ' . $e->getMessage());
            return [];
        }
    }

    public function simulateCapacity(array $payload): array
    {
        $area          = trim((string) ($payload['area'] ?? ''));
        $newHours      = max(0.0, (float) ($payload['hours'] ?? 0));
        $from          = (string) ($payload['from'] ?? date('Y-m-01'));
        $to            = (string) ($payload['to'] ?? date('Y-m-t'));
        $resourcesNeeded = max(1, (int) ($payload['resources_needed'] ?? 1));

        if (!$this->db->tableExists('talents')) {
            return ['error' => 'Módulo de talento no disponible.'];
        }

        try {
            $capacityData = $this->getTeamCapacity(['from' => $from, 'to' => $to]);

            // Filter by role/area if specified
            $eligible = $capacityData;
            if ($area !== '') {
                $eligible = array_filter(
                    $capacityData,
                    static fn (array $t): bool => stripos((string) ($t['role'] ?? ''), $area) !== false
                );
                $eligible = array_values($eligible);
            }

            if ($eligible === []) {
                $eligible = $capacityData;
            }

            // Sort by free hours descending → assign to most available
            usort($eligible, static fn (array $a, array $b): int => ((float) ($b['free_hours'] ?? 0)) <=> ((float) ($a['free_hours'] ?? 0)));

            $available = array_filter($eligible, static fn (array $t): bool => ($t['free_hours'] ?? 0) > 0);
            $available = array_values($available);

            $hoursPerResource = $resourcesNeeded > 0 ? ($newHours / $resourcesNeeded) : $newHours;

            $talentsAtRisk  = [];
            $assigned       = 0;
            $updatedTalents = [];

            foreach ($available as $t) {
                if ($assigned >= $resourcesNeeded) {
                    break;
                }
                $addHours      = min($hoursPerResource, (float) ($t['free_hours'] ?? 0));
                $newUsed       = (float) ($t['used_hours'] ?? 0) + $addHours;
                $newUtil       = $t['capacity_hours'] > 0 ? ($newUsed / $t['capacity_hours']) * 100 : 0.0;
                $updatedTalents[] = array_merge($t, [
                    'simulated_hours'     => round($newUsed, 1),
                    'simulated_util_pct'  => round($newUtil, 1),
                    'hours_added'         => round($addHours, 1),
                ]);
                if ($newUtil >= self::OVERLOAD_THRESHOLD) {
                    $talentsAtRisk[] = [
                        'name'           => $t['name'],
                        'current_util'   => round((float) ($t['utilization_pct'] ?? 0), 1),
                        'simulated_util' => round($newUtil, 1),
                    ];
                }
                $assigned++;
            }

            $totalCapacity = array_sum(array_column($capacityData, 'capacity_hours'));
            $totalUsed     = array_sum(array_column($capacityData, 'used_hours'));
            $simulatedUsed = $totalUsed + $newHours;
            $estimatedUtil = $totalCapacity > 0 ? ($simulatedUsed / $totalCapacity) * 100 : 0.0;

            // Affected projects: projects where selected talents are assigned
            $affectedProjects = [];
            if ($talentsAtRisk !== [] && $this->db->tableExists('project_talent_assignments')) {
                $riskNames = array_column($talentsAtRisk, 'name');
                $talentIds = array_map(
                    static fn (array $t): int => (int) ($t['id'] ?? 0),
                    array_filter($updatedTalents, static fn (array $t): bool => in_array($t['name'] ?? '', $riskNames, true))
                );
                if ($talentIds !== []) {
                    $placeholders = implode(',', array_fill(0, count($talentIds), '?'));
                    $affectedProjects = $this->db->fetchAll(
                        "SELECT DISTINCT p.id, p.name AS project_name, c.name AS client_name
                         FROM project_talent_assignments a
                         JOIN projects p ON p.id = a.project_id
                         JOIN clients c ON c.id = p.client_id
                         WHERE a.talent_id IN ({$placeholders})
                         AND (a.assignment_status = 'active' OR a.assignment_status IS NULL)
                         LIMIT 10",
                        $talentIds
                    );
                }
            }

            return [
                'new_hours'           => $newHours,
                'resources_needed'    => $resourcesNeeded,
                'period_from'         => $from,
                'period_to'           => $to,
                'area_filter'         => $area,
                'total_capacity'      => round($totalCapacity, 1),
                'current_util_pct'    => round($totalCapacity > 0 ? ($totalUsed / $totalCapacity) * 100 : 0, 1),
                'estimated_util_pct'  => round($estimatedUtil, 1),
                'talents_at_risk'     => $talentsAtRisk,
                'affected_projects'   => $affectedProjects,
                'talent_breakdown'    => $updatedTalents,
                'resources_assigned'  => $assigned,
                'insufficient'        => $assigned < $resourcesNeeded,
            ];
        } catch (\Throwable $e) {
            error_log('DecisionCenterService::simulateCapacity error: ' . $e->getMessage());
            return ['error' => 'Error al simular capacidad: ' . $e->getMessage()];
        }
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function buildProjectWhere(array $filters): array
    {
        $conditions = ['WHERE 1=1'];
        $params     = [];

        if (!empty($filters['client_id'])) {
            $conditions[] = 'p.client_id = :client_id';
            $params[':client_id'] = (int) $filters['client_id'];
        }
        if (!empty($filters['pm_id'])) {
            if ($this->db->columnExists('projects', 'pm_id')) {
                $conditions[] = 'p.pm_id = :pm_id';
                $params[':pm_id'] = (int) $filters['pm_id'];
            }
        }

        return [implode(' AND ', $conditions), $params];
    }

    private function statusExpr(): string
    {
        if ($this->db->columnExists('projects', 'status_code')) {
            return 'p.status_code';
        }
        if ($this->db->columnExists('projects', 'status')) {
            return 'p.status';
        }
        return "''";
    }

    private function healthExpr(): string
    {
        if ($this->db->columnExists('projects', 'health_code')) {
            return 'p.health_code';
        }
        if ($this->db->columnExists('projects', 'health')) {
            return 'p.health';
        }
        return "''";
    }

    private function countProjectsAtRisk(string $where, array $params): int
    {
        $statusExpr = $this->statusExpr();
        $healthExpr = $this->healthExpr();

        $count = 0;

        // Rule 1: health = at_risk or critical
        if ($healthExpr !== "''") {
            $row = $this->db->fetchOne(
                "SELECT COUNT(*) AS total
                 FROM projects p JOIN clients c ON c.id = p.client_id
                 {$where}
                 AND {$healthExpr} IN ('at_risk','critical','red')
                 AND {$statusExpr} NOT IN ('closed','archived','cancelled')",
                $params
            );
            $count = (int) ($row['total'] ?? 0);
        }

        // Rule 2: projects with critical open blockers not already counted (approximate)
        if ($this->db->tableExists('project_stoppers')) {
            $row = $this->db->fetchOne(
                "SELECT COUNT(DISTINCT p.id) AS total
                 FROM projects p
                 JOIN clients c ON c.id = p.client_id
                 JOIN project_stoppers s ON s.project_id = p.id
                 {$where}
                 AND s.status IN ('abierto','en_gestion','escalado')
                 AND s.impact_level = 'critico'
                 AND {$statusExpr} NOT IN ('closed','archived','cancelled')",
                $params
            );
            $count = max($count, (int) ($row['total'] ?? 0));
        }

        return $count;
    }

    private function countActiveBlockers(string $where, array $params): array
    {
        if (!$this->db->tableExists('project_stoppers')) {
            return ['total' => 0, 'criticos' => 0, 'altos' => 0];
        }

        $rows = $this->db->fetchAll(
            "SELECT s.impact_level, COUNT(*) AS total
             FROM project_stoppers s
             JOIN projects p ON p.id = s.project_id
             JOIN clients c ON c.id = p.client_id
             {$where}
             AND s.status IN ('abierto','en_gestion','escalado')
             GROUP BY s.impact_level",
            $params
        );

        $counts = ['critico' => 0, 'alto' => 0, 'medio' => 0, 'bajo' => 0];
        foreach ($rows as $row) {
            $level = strtolower((string) ($row['impact_level'] ?? 'medio'));
            if (array_key_exists($level, $counts)) {
                $counts[$level] = (int) ($row['total'] ?? 0);
            }
        }

        return [
            'total'   => array_sum($counts),
            'criticos'=> $counts['critico'],
            'altos'   => $counts['alto'],
        ];
    }

    private function countCriticalRisks(string $where, array $params): int
    {
        if (!$this->db->tableExists('project_risk_evaluations')) {
            return 0;
        }

        $row = $this->db->fetchOne(
            "SELECT COUNT(DISTINCT pr.project_id) AS total
             FROM project_risk_evaluations pr
             JOIN risk_catalog rc ON rc.code = pr.risk_code
             JOIN projects p ON p.id = pr.project_id
             JOIN clients c ON c.id = p.client_id
             {$where}
             AND pr.selected = 1
             AND rc.severity_base >= 4",
            $params
        );

        return (int) ($row['total'] ?? 0);
    }

    private function billingPendingAmount(string $where, array $params): array
    {
        if (!$this->db->tableExists('project_invoices')) {
            return ['amount' => 0.0, 'formatted' => '$0'];
        }

        // Sum of amounts for invoices with status issued/sent/overdue
        $row = $this->db->fetchOne(
            "SELECT SUM(i.amount) AS total
             FROM project_invoices i
             JOIN projects p ON p.id = i.project_id
             JOIN clients c ON c.id = p.client_id
             {$where}
             AND i.status IN ('issued','sent','overdue')",
            $params
        );

        $amount = (float) ($row['total'] ?? 0);

        return [
            'amount'    => $amount,
            'formatted' => '$' . number_format($amount, 0, ',', '.'),
        ];
    }

    private function billingPendingProjects(string $where, array $params): array
    {
        if (!$this->db->tableExists('project_invoices')) {
            return ['count' => 0];
        }

        $row = $this->db->fetchOne(
            "SELECT COUNT(DISTINCT i.project_id) AS total
             FROM project_invoices i
             JOIN projects p ON p.id = i.project_id
             JOIN clients c ON c.id = p.client_id
             {$where}
             AND i.status IN ('issued','sent','overdue')",
            $params
        );

        return ['count' => (int) ($row['total'] ?? 0)];
    }

    private function teamUtilizationSummary(): array
    {
        if (!$this->db->tableExists('talents')) {
            return ['utilization_pct' => 0.0, 'overloaded_count' => 0, 'available_count' => 0];
        }

        $capacityColumn    = $this->db->columnExists('talents', 'capacidad_horaria') ? 'capacidad_horaria' : '40';
        $weeklyColumn      = $this->db->columnExists('talents', 'weekly_capacity')   ? 'weekly_capacity'   : '40';
        $availabilityColumn = $this->db->columnExists('talents', 'availability')     ? 'availability'      : '100';

        $talents = $this->db->fetchAll(
            "SELECT id, {$capacityColumn} AS capacidad_horaria, {$weeklyColumn} AS weekly_capacity,
                    {$availabilityColumn} AS availability
             FROM talents"
        );

        if ($talents === []) {
            return ['utilization_pct' => 0.0, 'overloaded_count' => 0, 'available_count' => 0];
        }

        $hoursByTalent = [];
        if ($this->db->tableExists('timesheets') && $this->db->columnExists('timesheets', 'talent_id')) {
            $rows = $this->db->fetchAll(
                'SELECT talent_id, SUM(hours) AS total FROM timesheets
                 WHERE date BETWEEN :from AND :to GROUP BY talent_id',
                [':from' => date('Y-m-01'), ':to' => date('Y-m-t')]
            );
            foreach ($rows as $row) {
                $hoursByTalent[(int) ($row['talent_id'] ?? 0)] = (float) ($row['total'] ?? 0);
            }
        }

        $workingDays   = $this->workingDaysCurrentMonth();
        $totalCapacity = 0.0;
        $totalUsed     = 0.0;
        $overloaded    = 0;
        $available     = 0;

        foreach ($talents as $talent) {
            $tid     = (int) ($talent['id'] ?? 0);
            $weekly  = (float) ($talent['capacidad_horaria'] ?? 0);
            if ($weekly <= 0) {
                $weekly = (float) ($talent['weekly_capacity'] ?? 40);
            }
            if ($weekly <= 0) {
                $weekly = 40;
            }
            $avail    = max(0.0, min(100.0, (float) ($talent['availability'] ?? 100)));
            $capacity = ($weekly * ($workingDays / 5)) * ($avail / 100);
            $used     = (float) ($hoursByTalent[$tid] ?? 0);
            $util     = $capacity > 0 ? ($used / $capacity) * 100 : 0.0;

            $totalCapacity += $capacity;
            $totalUsed     += $used;

            if ($util >= self::OVERLOAD_THRESHOLD) {
                $overloaded++;
            } elseif ($util < 60) {
                $available++;
            }
        }

        return [
            'utilization_pct'  => $totalCapacity > 0 ? round(($totalUsed / $totalCapacity) * 100, 1) : 0.0,
            'overloaded_count' => $overloaded,
            'available_count'  => $available,
        ];
    }

    private function portfolioScore(string $where, array $params): int
    {
        $statusExpr = $this->statusExpr();
        $healthExpr = $this->healthExpr();

        // Component 1: average progress (30%)
        $progressRow = $this->db->fetchOne(
            "SELECT AVG(p.progress) AS avg_progress FROM projects p JOIN clients c ON c.id = p.client_id
             {$where} AND {$statusExpr} NOT IN ('closed','archived','cancelled')",
            $params
        );
        $avgProgress = min(100.0, max(0.0, (float) ($progressRow['avg_progress'] ?? 0)));

        // Component 2: % at risk → risk score (20%)
        $totalRow = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM projects p JOIN clients c ON c.id = p.client_id
             {$where} AND {$statusExpr} NOT IN ('closed','archived','cancelled')",
            $params
        );
        $total = max(1, (int) ($totalRow['total'] ?? 1));
        $atRisk = $this->countProjectsAtRisk($where, $params);
        $riskPct = ($atRisk / $total) * 100;
        $riskScore = max(0.0, 100.0 - ($riskPct * 1.4));

        // Component 3: blockers (15%)
        $blockers = $this->countActiveBlockers($where, $params);
        $blockerPressure = min(100.0, ($blockers['total'] * 8.0) + ($blockers['criticos'] * 14.0));
        $blockerScore = max(0.0, 100.0 - $blockerPressure);

        // Component 4: billing execution (20%)
        $billingScore = 70.0; // default if not enough data
        if ($this->db->tableExists('project_invoices')) {
            $contractRow = $this->db->fetchOne(
                "SELECT SUM(p.contract_value) AS total FROM projects p JOIN clients c ON c.id = p.client_id
                 {$where} AND {$statusExpr} NOT IN ('closed','archived','cancelled')",
                $params
            );
            $contracted  = (float) ($contractRow['total'] ?? 0);
            $invoicedRow = $this->db->fetchOne(
                "SELECT SUM(i.amount) AS total FROM project_invoices i
                 JOIN projects p ON p.id = i.project_id JOIN clients c ON c.id = p.client_id
                 {$where} AND i.status <> 'void'",
                $params
            );
            $invoiced = (float) ($invoicedRow['total'] ?? 0);
            $billingScore = $contracted > 0 ? min(100.0, ($invoiced / $contracted) * 100) : 70.0;
        }

        // Component 5: team utilization (15%)
        $util = $this->teamUtilizationSummary();
        $utilPct = (float) ($util['utilization_pct'] ?? 50.0);
        $capacityScore = max(0.0, 100.0 - (abs($utilPct - 80.0) * 2.0));

        $score = (int) round(
            ($avgProgress  * 0.30) +
            ($riskScore    * 0.20) +
            ($blockerScore * 0.15) +
            ($billingScore * 0.20) +
            ($capacityScore * 0.15)
        );

        return max(0, min(100, $score));
    }

    private function countStaleProjects(string $where, array $params, string $statusExpr): int
    {
        $staleDays = self::STALE_DAYS;

        if ($this->db->tableExists('audit_log')) {
            // Projects with no note in the last 7 days
            $row = $this->db->fetchOne(
                "SELECT COUNT(DISTINCT p.id) AS total
                 FROM projects p
                 JOIN clients c ON c.id = p.client_id
                 {$where}
                 AND {$statusExpr} NOT IN ('closed','archived','cancelled')
                 AND p.id NOT IN (
                     SELECT entity_id FROM audit_log
                     WHERE entity = 'project_note'
                     AND created_at >= DATE_SUB(NOW(), INTERVAL {$staleDays} DAY)
                 )",
                $params
            );
            return (int) ($row['total'] ?? 0);
        }

        // Fallback: use project updated_at
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM projects p JOIN clients c ON c.id = p.client_id
             {$where}
             AND {$statusExpr} NOT IN ('closed','archived','cancelled')
             AND p.updated_at < DATE_SUB(NOW(), INTERVAL {$staleDays} DAY)",
            $params
        );
        return (int) ($row['total'] ?? 0);
    }

    private function fetchProjectsAtRisk(string $where, array $params, string $statusExpr, int $limit): array
    {
        $healthExpr = $this->healthExpr();

        return $this->db->fetchAll(
            "SELECT p.id, p.name AS project_name, c.name AS client_name,
                    {$healthExpr} AS health, COALESCE(p.progress, 0) AS progress
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             {$where}
             AND {$healthExpr} IN ('at_risk','critical','red')
             AND {$statusExpr} NOT IN ('closed','archived','cancelled')
             ORDER BY p.progress ASC
             LIMIT {$limit}",
            $params
        );
    }

    private function fetchBillingPendingProjects(string $where, array $params, int $limit): array
    {
        if (!$this->db->tableExists('project_invoices')) {
            return [];
        }

        return $this->db->fetchAll(
            "SELECT p.id, p.name AS project_name, p.currency_code,
                    SUM(i.amount) AS pending_amount
             FROM project_invoices i
             JOIN projects p ON p.id = i.project_id
             JOIN clients c ON c.id = p.client_id
             {$where}
             AND i.status IN ('issued','sent','overdue')
             GROUP BY p.id, p.name, p.currency_code
             ORDER BY pending_amount DESC
             LIMIT {$limit}",
            $params
        );
    }

    private function fetchStaleProjects(string $where, array $params, string $statusExpr, int $limit): array
    {
        $staleDays = self::STALE_DAYS;

        if ($this->db->tableExists('audit_log')) {
            return $this->db->fetchAll(
                "SELECT p.id, p.name AS project_name, c.name AS client_name,
                        DATEDIFF(NOW(), COALESCE(
                            (SELECT MAX(al.created_at) FROM audit_log al
                             WHERE al.entity = 'project_note' AND al.entity_id = p.id),
                            p.updated_at
                        )) AS days_stale
                 FROM projects p
                 JOIN clients c ON c.id = p.client_id
                 {$where}
                 AND {$statusExpr} NOT IN ('closed','archived','cancelled')
                 HAVING days_stale > {$staleDays}
                 ORDER BY days_stale DESC
                 LIMIT {$limit}",
                $params
            );
        }

        return $this->db->fetchAll(
            "SELECT p.id, p.name AS project_name, c.name AS client_name,
                    DATEDIFF(NOW(), p.updated_at) AS days_stale
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             {$where}
             AND {$statusExpr} NOT IN ('closed','archived','cancelled')
             AND p.updated_at < DATE_SUB(NOW(), INTERVAL {$staleDays} DAY)
             ORDER BY p.updated_at ASC
             LIMIT {$limit}",
            $params
        );
    }

    private function fetchOverloadedTalents(int $limit): array
    {
        if (!$this->db->tableExists('talents')) {
            return [];
        }

        $capacityColumn    = $this->db->columnExists('talents', 'capacidad_horaria') ? 'capacidad_horaria' : '40';
        $weeklyColumn      = $this->db->columnExists('talents', 'weekly_capacity')   ? 'weekly_capacity'   : '40';
        $availabilityColumn = $this->db->columnExists('talents', 'availability')     ? 'availability'      : '100';

        $talents = $this->db->fetchAll(
            "SELECT id, name, {$capacityColumn} AS capacidad_horaria, {$weeklyColumn} AS weekly_capacity,
                    {$availabilityColumn} AS availability
             FROM talents ORDER BY name ASC"
        );

        $hoursByTalent = [];
        if ($this->db->tableExists('timesheets') && $this->db->columnExists('timesheets', 'talent_id')) {
            $rows = $this->db->fetchAll(
                'SELECT talent_id, SUM(hours) AS total FROM timesheets
                 WHERE date BETWEEN :from AND :to GROUP BY talent_id',
                [':from' => date('Y-m-01'), ':to' => date('Y-m-t')]
            );
            foreach ($rows as $row) {
                $hoursByTalent[(int) ($row['talent_id'] ?? 0)] = (float) ($row['total'] ?? 0);
            }
        }

        $workingDays = $this->workingDaysCurrentMonth();
        $overloaded  = [];

        foreach ($talents as $talent) {
            $tid    = (int) ($talent['id'] ?? 0);
            $weekly = (float) ($talent['capacidad_horaria'] ?? 0);
            if ($weekly <= 0) {
                $weekly = (float) ($talent['weekly_capacity'] ?? 40);
            }
            if ($weekly <= 0) {
                $weekly = 40;
            }
            $avail    = max(0.0, min(100.0, (float) ($talent['availability'] ?? 100)));
            $capacity = ($weekly * ($workingDays / 5)) * ($avail / 100);
            $used     = (float) ($hoursByTalent[$tid] ?? 0);
            $util     = $capacity > 0 ? ($used / $capacity) * 100 : 0.0;

            if ($util >= self::OVERLOAD_THRESHOLD) {
                $overloaded[] = [
                    'id'             => $tid,
                    'name'           => (string) ($talent['name'] ?? ''),
                    'utilization_pct'=> round($util, 1),
                ];
            }
        }

        return array_slice($overloaded, 0, $limit);
    }

    private function fetchLastNotesForProjects(array $projectIds): array
    {
        if ($projectIds === [] || !$this->db->tableExists('audit_log')) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));

        $rows = $this->db->fetchAll(
            "SELECT al.entity_id AS project_id, al.created_at, al.payload,
                    u.name AS author
             FROM audit_log al
             LEFT JOIN users u ON u.id = al.user_id
             JOIN (
                 SELECT entity_id, MAX(created_at) AS max_created_at
                 FROM audit_log
                 WHERE entity = 'project_note' AND entity_id IN ({$placeholders})
                 GROUP BY entity_id
             ) latest ON latest.entity_id = al.entity_id AND latest.max_created_at = al.created_at
             WHERE al.entity = 'project_note'",
            $projectIds
        );

        $result = [];
        foreach ($rows as $row) {
            $pid     = (int) ($row['project_id'] ?? 0);
            $payload = [];
            if (!empty($row['payload'])) {
                $decoded = json_decode((string) $row['payload'], true);
                $payload = is_array($decoded) ? $decoded : [];
            }
            $result[$pid] = [
                'created_at' => (string) ($row['created_at'] ?? ''),
                'note'       => (string) ($payload['note'] ?? ''),
                'author'     => (string) ($row['author'] ?? ''),
            ];
        }

        return $result;
    }

    private function fetchBlockerSummaryForProjects(array $projectIds): array
    {
        if ($projectIds === [] || !$this->db->tableExists('project_stoppers')) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));

        $rows = $this->db->fetchAll(
            "SELECT s.project_id, COUNT(*) AS total_count,
                    MAX(CASE s.impact_level
                        WHEN 'critico' THEN 4 WHEN 'alto' THEN 3
                        WHEN 'medio'  THEN 2 WHEN 'bajo'  THEN 1
                        ELSE 0 END) AS severity_num,
                    SUM(CASE WHEN s.impact_level = 'critico' THEN 1 ELSE 0 END) AS count_critical
             FROM project_stoppers s
             WHERE s.project_id IN ({$placeholders})
             AND s.status IN ('abierto','en_gestion','escalado')
             GROUP BY s.project_id",
            $projectIds
        );

        $severityMap = [4 => 'critico', 3 => 'alto', 2 => 'medio', 1 => 'bajo', 0 => ''];

        $result = [];
        foreach ($rows as $row) {
            $pid = (int) ($row['project_id'] ?? 0);
            $result[$pid] = [
                'count'          => (int) ($row['total_count'] ?? 0),
                'count_critical' => (int) ($row['count_critical'] ?? 0),
                'max_severity'   => $severityMap[(int) ($row['severity_num'] ?? 0)] ?? '',
                'list'           => [],
            ];
        }

        // Fetch top 3 blockers for each project
        if ($result !== []) {
            $topRows = $this->db->fetchAll(
                "SELECT s.project_id, s.title, s.impact_level,
                        DATEDIFF(CURDATE(), COALESCE(s.detected_at, DATE(s.created_at))) AS days_open
                 FROM project_stoppers s
                 WHERE s.project_id IN ({$placeholders})
                 AND s.status IN ('abierto','en_gestion','escalado')
                 ORDER BY s.project_id,
                    CASE s.impact_level WHEN 'critico' THEN 0 WHEN 'alto' THEN 1 WHEN 'medio' THEN 2 ELSE 3 END,
                    s.created_at ASC",
                $projectIds
            );

            $countPerProject = [];
            foreach ($topRows as $tr) {
                $pid = (int) ($tr['project_id'] ?? 0);
                $countPerProject[$pid] = ($countPerProject[$pid] ?? 0) + 1;
                if ($countPerProject[$pid] <= 3) {
                    $result[$pid]['list'][] = [
                        'title'       => (string) ($tr['title'] ?? ''),
                        'impact_level'=> (string) ($tr['impact_level'] ?? ''),
                        'days_open'   => (int) ($tr['days_open'] ?? 0),
                    ];
                }
            }
        }

        return $result;
    }

    private function fetchBillingPendingForProjects(array $projectIds): array
    {
        if ($projectIds === [] || !$this->db->tableExists('project_invoices')) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));

        $rows = $this->db->fetchAll(
            "SELECT project_id, SUM(amount) AS pending_amount
             FROM project_invoices
             WHERE project_id IN ({$placeholders})
             AND status IN ('issued','sent','overdue')
             GROUP BY project_id",
            $projectIds
        );

        $result = [];
        foreach ($rows as $row) {
            $result[(int) ($row['project_id'] ?? 0)] = (float) ($row['pending_amount'] ?? 0);
        }

        return $result;
    }

    private function isProjectAtRisk(int $score, string $health, int $criticalBlockers, ?array $lastNote): bool
    {
        if ($score > 0 && $score < self::HEALTH_SCORE_THRESHOLD) {
            return true;
        }
        if (in_array($health, ['at_risk', 'critical', 'red'], true)) {
            return true;
        }
        if ($criticalBlockers >= 1) {
            return true;
        }
        if ($lastNote !== null) {
            $days = (int) round((time() - strtotime((string) $lastNote['created_at'])) / 86400);
            if ($days > self::STALE_DAYS) {
                return true;
            }
        }
        return false;
    }

    private function healthLabel(string $health): string
    {
        return match($health) {
            'on_track', 'green'  => 'En curso',
            'at_risk',  'yellow' => 'En riesgo',
            'critical', 'red'    => 'Crítico',
            default               => ucfirst($health) ?: 'Desconocido',
        };
    }

    private function workingDaysCurrentMonth(): int
    {
        return $this->workingDaysBetween(date('Y-m-01'), date('Y-m-t'));
    }

    private function workingDaysBetween(string $from, string $to): int
    {
        try {
            $start  = new \DateTimeImmutable($from);
            $end    = new \DateTimeImmutable($to);
            $cursor = $start;
            $days   = 0;
            while ($cursor <= $end) {
                if ((int) $cursor->format('N') <= 5) {
                    $days++;
                }
                $cursor = $cursor->modify('+1 day');
            }
            return max(1, $days);
        } catch (\Throwable $e) {
            return 20;
        }
    }

    private function truncate(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        return mb_substr($text, 0, $max - 1) . '…';
    }

    private function emptySummary(): array
    {
        return [
            'score_general'            => 0,
            'proyectos_activos'        => 0,
            'proyectos_en_riesgo'      => 0,
            'bloqueos_activos'         => 0,
            'bloqueos_criticos'        => 0,
            'facturacion_pendiente'    => 0.0,
            'facturacion_pendiente_fmt'=> '$0',
            'utilizacion_equipo_pct'   => 0.0,
            'sobrecarga_count'         => 0,
        ];
    }
}
