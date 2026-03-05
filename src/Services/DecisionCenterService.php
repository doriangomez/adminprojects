<?php

declare(strict_types=1);

use App\Repositories\TalentCapacityRepository;

class DecisionCenterService
{
    private const ADMIN_ROLES = ['Administrador', 'PMO'];
    private const AI_CACHE_TTL_SECONDS = 900;
    private const ACTIVE_STOPPER_STATUSES = ['abierto', 'en_gestion', 'escalado', 'resuelto'];

    private ?array $configCache = null;

    /** @var array<string, array<string, mixed>> */
    private array $contextCache = [];

    public function __construct(private Database $db)
    {
    }

    public function getPortfolioSummary(array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);
        $context = $this->buildContext($normalized);
        $current = $this->summaryFromContext($context);

        $previousFilters = $normalized;
        [$previousFilters['from'], $previousFilters['to']] = $this->previousRange($normalized['from'], $normalized['to']);
        $previousContext = $this->buildContext($previousFilters);
        $previous = $this->summaryFromContext($previousContext);

        return [
            'portfolio_score' => $current['portfolio_score'],
            'active_projects' => $current['active_projects'],
            'at_risk_projects' => $current['at_risk_projects'],
            'active_blockers' => $current['active_blockers'],
            'billing_pending' => $current['billing_pending'],
            'billing_currency' => $current['billing_currency'],
            'avg_team_utilization' => $current['avg_team_utilization'],
            'delta_vs_previous' => round($current['portfolio_score'] - $previous['portfolio_score'], 1),
            'risk_threshold' => $context['risk_score_threshold'],
        ];
    }

    public function getAlerts(array $filters): array
    {
        $context = $this->buildContext($this->normalizeFilters($filters));
        $projects = $context['projects'];
        $teamRows = $context['team_capacity']['rows'] ?? [];

        $stale = 0;
        $criticalBlockers = 0;
        $criticalRisks = 0;
        $billingProjects = 0;
        $billingAmount = 0.0;

        foreach ($projects as $project) {
            if (!empty($project['alert_flags']['stale_updates'])) {
                $stale++;
            }
            $criticalBlockers += (int) ($project['critical_open_stoppers'] ?? 0);
            if (!empty($project['alert_flags']['critical_risks'])) {
                $criticalRisks++;
            }
            if (!empty($project['alert_flags']['billing_pending'])) {
                $billingProjects++;
                $billingAmount += (float) ($project['pending_to_invoice'] ?? 0);
            }
        }

        $talentOverload = 0;
        foreach ($teamRows as $row) {
            if ((float) ($row['utilization'] ?? 0) > 90.0) {
                $talentOverload++;
            }
        }

        return [
            ['key' => 'stale_updates', 'label' => 'Sin actualización > 7 días', 'count' => $stale],
            ['key' => 'critical_blockers', 'label' => 'Bloqueos críticos abiertos', 'count' => $criticalBlockers],
            ['key' => 'critical_risks', 'label' => 'Riesgos críticos', 'count' => $criticalRisks],
            [
                'key' => 'billing_pending',
                'label' => 'Facturación pendiente',
                'count' => $billingProjects,
                'amount' => round($billingAmount, 2),
            ],
            ['key' => 'talent_overload', 'label' => 'Sobrecarga de talento (>90%)', 'count' => $talentOverload],
        ];
    }

    public function getRecommendations(array $filters): array
    {
        $context = $this->buildContext($this->normalizeFilters($filters));
        $projects = $context['projects'];
        $teamRows = $context['team_capacity']['rows'] ?? [];
        $recommendations = [];

        $criticalProject = $this->firstProjectByCondition($projects, static fn (array $project): bool => (int) ($project['critical_open_stoppers'] ?? 0) > 0);
        if ($criticalProject !== null) {
            $recommendations[] = [
                'title' => 'Escalar bloqueo crítico en ' . (string) ($criticalProject['name'] ?? 'proyecto'),
                'reason' => 'Tiene bloqueo crítico/alto abierto y riesgo inmediato sobre entregables.',
                'impact' => 'Alto',
                'action_label' => 'Abrir bloqueos',
                'action_url' => '/projects/' . (int) ($criticalProject['id'] ?? 0),
                'priority' => 100,
            ];
        }

        $atRiskProject = $this->firstProjectByCondition($projects, static fn (array $project): bool => !empty($project['is_project_at_risk']));
        if ($atRiskProject !== null) {
            $recommendations[] = [
                'title' => 'Revisión ejecutiva de ' . (string) ($atRiskProject['name'] ?? 'proyecto'),
                'reason' => 'El proyecto cumple regla de riesgo por score, bloqueos, riesgos o seguimiento.',
                'impact' => 'Alto',
                'action_label' => 'Ver proyecto',
                'action_url' => '/projects/' . (int) ($atRiskProject['id'] ?? 0),
                'priority' => 94,
            ];
        }

        $billingProject = $this->firstProjectByCondition($projects, static fn (array $project): bool => (float) ($project['pending_to_invoice'] ?? 0) > 0);
        if ($billingProject !== null) {
            $recommendations[] = [
                'title' => 'Regularizar facturación en ' . (string) ($billingProject['name'] ?? 'proyecto'),
                'reason' => 'Existe monto pendiente por facturar y puede afectar flujo de caja.',
                'impact' => 'Medio',
                'action_label' => 'Ir a facturación',
                'action_url' => '/projects/' . (int) ($billingProject['id'] ?? 0) . '/billing',
                'priority' => 82,
            ];
        }

        $staleProject = $this->firstProjectByCondition($projects, static fn (array $project): bool => !empty($project['alert_flags']['stale_updates']));
        if ($staleProject !== null) {
            $recommendations[] = [
                'title' => 'Retomar seguimiento en ' . (string) ($staleProject['name'] ?? 'proyecto'),
                'reason' => 'No hay notas recientes y se reduce la visibilidad operativa del proyecto.',
                'impact' => 'Medio',
                'action_label' => 'Ver proyecto',
                'action_url' => '/projects/' . (int) ($staleProject['id'] ?? 0),
                'priority' => 76,
            ];
        }

        $overloadedTalent = null;
        foreach ($teamRows as $row) {
            if ((float) ($row['utilization'] ?? 0) > 90.0) {
                $overloadedTalent = $row;
                break;
            }
        }
        if ($overloadedTalent !== null) {
            $recommendations[] = [
                'title' => 'Redistribuir carga de ' . (string) ($overloadedTalent['name'] ?? 'talento'),
                'reason' => 'La utilización supera el 90% y aumenta probabilidad de incumplimientos.',
                'impact' => (float) ($overloadedTalent['utilization'] ?? 0) > 100.0 ? 'Alto' : 'Medio',
                'action_label' => 'Asignar recurso',
                'action_url' => '/talent-capacity/simulation',
                'priority' => 88,
            ];
        }

        if ($recommendations === []) {
            $recommendations[] = [
                'title' => 'Portafolio estable',
                'reason' => 'No se detectaron alertas críticas para acción inmediata.',
                'impact' => 'Bajo',
                'action_label' => 'Ver proyectos',
                'action_url' => '/projects',
                'priority' => 30,
            ];
        }

        usort(
            $recommendations,
            static fn (array $a, array $b): int => ((int) ($b['priority'] ?? 0) <=> (int) ($a['priority'] ?? 0))
        );

        return array_slice($recommendations, 0, 10);
    }

    public function getProjectRanking(array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);
        $projects = $this->buildContext($normalized)['projects'];

        usort(
            $projects,
            static fn (array $a, array $b): int => ((int) ($b['attention_index'] ?? 0) <=> (int) ($a['attention_index'] ?? 0))
                ?: strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''))
        );

        $alert = (string) ($normalized['alert'] ?? '');
        if ($alert !== '') {
            $projects = array_values(array_filter(
                $projects,
                fn (array $project): bool => $this->matchesProjectAlert($project, $alert)
            ));
        }

        return $projects;
    }

    public function getTeamCapacity(array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);
        $teamCapacity = $this->buildContext($normalized)['team_capacity'];
        $alert = (string) ($normalized['alert'] ?? '');

        if ($alert === 'talent_overload') {
            $teamCapacity['rows'] = array_values(array_filter(
                $teamCapacity['rows'] ?? [],
                static fn (array $row): bool => (float) ($row['utilization'] ?? 0) > 90.0
            ));
        }

        return $teamCapacity;
    }

    public function simulateCapacity(array $payload): array
    {
        $estimatedHours = max(0.0, (float) ($payload['estimated_hours'] ?? $payload['new_hours'] ?? 0));
        $requiredResources = max(0, (int) ($payload['required_resources'] ?? 0));
        $role = trim((string) ($payload['role'] ?? ''));
        $area = trim((string) ($payload['area'] ?? ''));

        $filters = [
            'from' => (string) ($payload['from'] ?? ''),
            'to' => (string) ($payload['to'] ?? ''),
            'area' => $area,
            'role' => $role,
            '__user' => is_array($payload['__user'] ?? null) ? $payload['__user'] : [],
        ];

        $team = $this->getTeamCapacity($filters);
        $rows = is_array($team['rows'] ?? null) ? $team['rows'] : [];

        if ($rows === [] || $estimatedHours <= 0.0) {
            $baseUtilization = $this->teamUtilization($rows);
            return [
                'estimated_team_utilization' => round($baseUtilization, 1),
                'talents_at_risk' => [],
                'affected_projects' => [],
                'distribution' => [],
            ];
        }

        $candidates = array_values(array_filter($rows, function (array $row) use ($role, $area): bool {
            if ($role !== '' && strcasecmp((string) ($row['role'] ?? ''), $role) !== 0) {
                return false;
            }
            if ($area !== '' && !$this->rowContainsArea($row, $area)) {
                return false;
            }
            return true;
        }));
        if ($candidates === []) {
            $candidates = $rows;
        }

        usort(
            $candidates,
            static fn (array $a, array $b): int => ((float) ($b['available_hours'] ?? 0) <=> (float) ($a['available_hours'] ?? 0))
                ?: ((float) ($a['utilization'] ?? 0) <=> (float) ($b['utilization'] ?? 0))
        );

        if ($requiredResources > 0) {
            $candidates = array_slice($candidates, 0, max(1, $requiredResources));
        }

        $allocations = [];
        $remaining = $estimatedHours;
        foreach ($candidates as $candidate) {
            $talentId = (int) ($candidate['talent_id'] ?? 0);
            $available = max(0.0, (float) ($candidate['available_hours'] ?? 0));
            $allocated = min($available, $remaining);
            $allocations[$talentId] = $allocated;
            $remaining -= $allocated;
            if ($remaining <= 0.0001) {
                break;
            }
        }

        if ($remaining > 0.0001 && count($candidates) > 0) {
            $extra = $remaining / count($candidates);
            foreach ($candidates as $candidate) {
                $talentId = (int) ($candidate['talent_id'] ?? 0);
                $allocations[$talentId] = ($allocations[$talentId] ?? 0.0) + $extra;
            }
            $remaining = 0.0;
        }

        $talentsAtRisk = [];
        $affectedProjects = [];
        $distribution = [];

        $totalAssigned = 0.0;
        $totalCapacity = 0.0;
        foreach ($rows as $row) {
            $talentId = (int) ($row['talent_id'] ?? 0);
            $currentAssigned = (float) ($row['assigned_hours'] ?? 0);
            $capacity = max(0.0, (float) ($row['capacity_hours'] ?? 0));
            $add = (float) ($allocations[$talentId] ?? 0.0);
            $simulatedAssigned = $currentAssigned + $add;
            $simulatedUtilization = $capacity > 0 ? (($simulatedAssigned / $capacity) * 100) : ($simulatedAssigned > 0 ? 100.0 : 0.0);

            $totalAssigned += $simulatedAssigned;
            $totalCapacity += $capacity;

            if ($add > 0.0) {
                $distribution[] = [
                    'talent_id' => $talentId,
                    'name' => (string) ($row['name'] ?? 'Talento'),
                    'added_hours' => round($add, 1),
                    'simulated_utilization' => round($simulatedUtilization, 1),
                ];
            }

            if ($simulatedUtilization > 90.0) {
                $talentsAtRisk[] = [
                    'talent_id' => $talentId,
                    'name' => (string) ($row['name'] ?? 'Talento'),
                    'utilization' => round($simulatedUtilization, 1),
                ];
                foreach ((array) ($row['projects'] ?? []) as $projectName) {
                    $projectName = trim((string) $projectName);
                    if ($projectName !== '') {
                        $affectedProjects[$projectName] = true;
                    }
                }
            }
        }

        usort(
            $talentsAtRisk,
            static fn (array $a, array $b): int => ((float) ($b['utilization'] ?? 0) <=> (float) ($a['utilization'] ?? 0))
        );

        return [
            'estimated_team_utilization' => round($totalCapacity > 0 ? (($totalAssigned / $totalCapacity) * 100) : 0.0, 1),
            'talents_at_risk' => array_slice($talentsAtRisk, 0, 12),
            'affected_projects' => array_slice(array_keys($affectedProjects), 0, 12),
            'distribution' => $distribution,
        ];
    }

    public function getIntelligentAnalysis(array $filters, bool $forceRefresh = false): array
    {
        $normalized = $this->normalizeFilters($filters);
        $cacheKey = $this->aiCacheKey($normalized);

        if (!$forceRefresh) {
            $cached = $this->readAiCache($cacheKey);
            if ($cached !== null) {
                $cached['cached'] = true;
                return $cached;
            }
        }

        $summary = $this->getPortfolioSummary($normalized);
        $alerts = $this->getAlerts($normalized);
        $recommendations = $this->getRecommendations($normalized);

        $alertLines = [];
        foreach (array_slice($alerts, 0, 3) as $alert) {
            $alertLines[] = '- ' . (string) ($alert['label'] ?? 'Alerta') . ': ' . (int) ($alert['count'] ?? 0);
        }
        while (count($alertLines) < 3) {
            $alertLines[] = '- Sin alertas adicionales en el corte actual.';
        }

        $recommendationLines = [];
        foreach (array_slice($recommendations, 0, 3) as $index => $recommendation) {
            $recommendationLines[] = ($index + 1) . ') ' . (string) ($recommendation['title'] ?? 'Acción recomendada');
        }
        while (count($recommendationLines) < 3) {
            $recommendationLines[] = (count($recommendationLines) + 1) . ') Mantener seguimiento semanal del portafolio.';
        }

        $diagnosis = $summary['at_risk_projects'] > 0
            ? 'Portafolio con focos de riesgo que requieren intervención priorizada.'
            : 'Portafolio estable, sin señales críticas de riesgo inmediato.';

        $text = implode("\n", [
            'Diagnóstico: ' . $diagnosis,
            'Score de portafolio: ' . (int) ($summary['portfolio_score'] ?? 0) . '/100.',
            'Proyectos activos: ' . (int) ($summary['active_projects'] ?? 0) . '; en riesgo: ' . (int) ($summary['at_risk_projects'] ?? 0) . '.',
            'Utilización promedio del equipo: ' . number_format((float) ($summary['avg_team_utilization'] ?? 0), 1) . '%.',
            'Alertas detectadas:',
            $alertLines[0],
            $alertLines[1],
            $alertLines[2],
            'Recomendaciones prioritarias:',
            $recommendationLines[0],
            $recommendationLines[1],
            $recommendationLines[2],
        ]);

        $payload = [
            'text' => $text,
            'generated_at' => date('c'),
            'cached' => false,
        ];

        $this->writeAiCache($cacheKey, $payload);

        return $payload;
    }

    /**
     * @param array<string, mixed> $normalized
     * @return array<string, mixed>
     */
    private function buildContext(array $normalized): array
    {
        $cacheKey = sha1(json_encode([
            'from' => $normalized['from'],
            'to' => $normalized['to'],
            'client_id' => $normalized['client_id'],
            'pm_id' => $normalized['pm_id'],
            'status' => $normalized['status'],
            'area' => $normalized['area'],
            'role' => $normalized['role'],
            'user_id' => (int) (($normalized['__user']['id'] ?? 0)),
            'user_role' => (string) (($normalized['__user']['role'] ?? '')),
        ], JSON_UNESCAPED_UNICODE));

        if (isset($this->contextCache[$cacheKey])) {
            return $this->contextCache[$cacheKey];
        }

        $riskThreshold = $this->riskThreshold();
        $projects = $this->projectDataset($normalized, $riskThreshold);
        $teamCapacity = $this->buildTeamCapacityDataset($normalized);

        $context = [
            'projects' => $projects,
            'team_capacity' => $teamCapacity,
            'risk_score_threshold' => $riskThreshold,
        ];

        $this->contextCache[$cacheKey] = $context;
        return $context;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function summaryFromContext(array $context): array
    {
        $projects = $context['projects'] ?? [];
        $teamRows = $context['team_capacity']['rows'] ?? [];

        $activeProjects = count($projects);
        $atRiskProjects = 0;
        $activeBlockers = 0;
        $billingPending = 0.0;
        $scoreAccumulator = 0.0;
        $currencyAmounts = [];

        foreach ($projects as $project) {
            if (!empty($project['is_project_at_risk'])) {
                $atRiskProjects++;
            }
            $activeBlockers += (int) ($project['open_stoppers'] ?? 0);

            $pending = max(0.0, (float) ($project['pending_to_invoice'] ?? 0));
            if ($pending > 0.0) {
                $billingPending += $pending;
                $currency = (string) ($project['currency_code'] ?? 'USD');
                $currencyAmounts[$currency] = ($currencyAmounts[$currency] ?? 0.0) + $pending;
            }

            $scoreAccumulator += (float) ($project['portfolio_score'] ?? 0);
        }

        $avgTeamUtilization = $this->teamUtilization($teamRows);
        $portfolioScore = $activeProjects > 0 ? ($scoreAccumulator / $activeProjects) : 0.0;
        arsort($currencyAmounts);
        $billingCurrency = array_key_first($currencyAmounts) ?: 'USD';

        return [
            'portfolio_score' => (int) round(max(0.0, min(100.0, $portfolioScore))),
            'active_projects' => $activeProjects,
            'at_risk_projects' => $atRiskProjects,
            'active_blockers' => $activeBlockers,
            'billing_pending' => round($billingPending, 2),
            'billing_currency' => $billingCurrency,
            'avg_team_utilization' => round($avgTeamUtilization, 1),
        ];
    }

    /**
     * @param array<string, mixed> $normalized
     * @return array<int, array<string, mixed>>
     */
    private function projectDataset(array $normalized, int $riskThreshold): array
    {
        if (!$this->db->tableExists('projects')) {
            return [];
        }

        $statusColumn = $this->db->columnExists('projects', 'status_code') ? 'status_code' : 'status';
        $healthColumn = $this->db->columnExists('projects', 'health_code') ? 'health_code' : 'health';
        $hasActiveColumn = $this->db->columnExists('projects', 'active');
        $hasRiskLevelColumn = $this->db->columnExists('projects', 'risk_level');
        $hasRiskScoreColumn = $this->db->columnExists('projects', 'risk_score');
        $user = is_array($normalized['__user'] ?? null) ? $normalized['__user'] : [];

        $conditions = [];
        $params = [];

        if ($hasActiveColumn) {
            $conditions[] = 'p.active = 1';
        }
        if ((int) ($normalized['client_id'] ?? 0) > 0) {
            $conditions[] = 'p.client_id = :client_id';
            $params[':client_id'] = (int) $normalized['client_id'];
        }
        if ((int) ($normalized['pm_id'] ?? 0) > 0 && $this->db->columnExists('projects', 'pm_id')) {
            $conditions[] = 'p.pm_id = :pm_id';
            $params[':pm_id'] = (int) $normalized['pm_id'];
        }
        if ((string) ($normalized['status'] ?? '') !== '') {
            $conditions[] = 'p.' . $statusColumn . ' = :status';
            $params[':status'] = (string) $normalized['status'];
        }
        if ((string) ($normalized['area'] ?? '') !== '' && $this->db->columnExists('clients', 'area_code')) {
            $conditions[] = 'COALESCE(c.area_code, "") = :area';
            $params[':area'] = (string) $normalized['area'];
        }
        if (!$this->isPrivileged($user) && $this->db->columnExists('projects', 'pm_id')) {
            $conditions[] = 'p.pm_id = :visibility_pm';
            $params[':visibility_pm'] = (int) ($user['id'] ?? 0);
        }

        $whereClause = $conditions === [] ? '' : ('WHERE ' . implode(' AND ', $conditions));

        $notesJoin = '';
        $notesSelect = 'NULL AS notes_last_at, 0 AS notes_7d, 0 AS notes_30d';
        if ($this->db->tableExists('audit_log')) {
            $notesJoin = 'LEFT JOIN (
                SELECT entity_id AS project_id,
                       MAX(created_at) AS notes_last_at,
                       SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS notes_7d,
                       SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS notes_30d
                FROM audit_log
                WHERE entity = "project_note" AND action = "project_note_created"
                GROUP BY entity_id
            ) notes ON notes.project_id = p.id';
            $notesSelect = 'notes.notes_last_at, COALESCE(notes.notes_7d, 0) AS notes_7d, COALESCE(notes.notes_30d, 0) AS notes_30d';
        }

        $stoppersJoin = '';
        $stoppersSelect = '0 AS open_stoppers, 0 AS critical_open_stoppers, 0 AS stopper_severity_rank';
        if ($this->db->tableExists('project_stoppers')) {
            $stoppersJoin = 'LEFT JOIN (
                SELECT project_id,
                       SUM(CASE WHEN status IN ("abierto","en_gestion","escalado","resuelto") THEN 1 ELSE 0 END) AS open_stoppers,
                       SUM(CASE WHEN status IN ("abierto","en_gestion","escalado","resuelto") AND impact_level IN ("critico","alto") THEN 1 ELSE 0 END) AS critical_open_stoppers,
                       MAX(CASE impact_level
                           WHEN "critico" THEN 4
                           WHEN "alto" THEN 3
                           WHEN "medio" THEN 2
                           WHEN "bajo" THEN 1
                           ELSE 0 END) AS stopper_severity_rank
                FROM project_stoppers
                GROUP BY project_id
            ) stoppers ON stoppers.project_id = p.id';
            $stoppersSelect = 'COALESCE(stoppers.open_stoppers, 0) AS open_stoppers, COALESCE(stoppers.critical_open_stoppers, 0) AS critical_open_stoppers, COALESCE(stoppers.stopper_severity_rank, 0) AS stopper_severity_rank';
        }

        $risksJoin = '';
        $risksSelect = '0 AS critical_risks, 0 AS total_risks';
        if ($this->db->tableExists('project_risk_evaluations') && $this->db->tableExists('risk_catalog')) {
            $risksJoin = 'LEFT JOIN (
                SELECT pre.project_id,
                       SUM(CASE WHEN pre.selected = 1 THEN 1 ELSE 0 END) AS total_risks,
                       SUM(CASE WHEN pre.selected = 1 AND COALESCE(rc.severity_base, 0) >= 4 THEN 1 ELSE 0 END) AS critical_risks
                FROM project_risk_evaluations pre
                LEFT JOIN risk_catalog rc ON rc.code = pre.risk_code
                GROUP BY pre.project_id
            ) risks ON risks.project_id = p.id';
            $risksSelect = 'COALESCE(risks.critical_risks, 0) AS critical_risks, COALESCE(risks.total_risks, 0) AS total_risks';
        }

        $invoicesJoin = '';
        $invoicesSelect = '0 AS total_invoiced, 0 AS total_paid';
        if ($this->db->tableExists('project_invoices')) {
            $invoicesJoin = 'LEFT JOIN (
                SELECT project_id,
                       SUM(CASE WHEN status NOT IN ("cancelled","void") THEN amount ELSE 0 END) AS total_invoiced,
                       SUM(CASE WHEN status = "paid" THEN amount ELSE 0 END) AS total_paid
                FROM project_invoices
                GROUP BY project_id
            ) invoices ON invoices.project_id = p.id';
            $invoicesSelect = 'COALESCE(invoices.total_invoiced, 0) AS total_invoiced, COALESCE(invoices.total_paid, 0) AS total_paid';
        }

        $timesheetsJoin = '';
        $timesheetsSelect = '0 AS approved_hours_total';
        if ($this->db->tableExists('timesheets')) {
            $timesheetsJoin = 'LEFT JOIN (
                SELECT project_id, SUM(hours) AS approved_hours_total
                FROM timesheets
                WHERE status = "approved" AND project_id IS NOT NULL
                  AND date BETWEEN :period_from AND :period_to
                GROUP BY project_id
            ) ts ON ts.project_id = p.id';
            $timesheetsSelect = 'COALESCE(ts.approved_hours_total, 0) AS approved_hours_total';
            $params[':period_from'] = $normalized['from'];
            $params[':period_to'] = $normalized['to'];
        }

        $rows = $this->db->fetchAll(
            'SELECT
                p.id,
                p.name,
                p.progress,
                p.planned_hours,
                p.actual_hours,
                p.is_billable,
                p.billing_type,
                p.contract_value,
                p.hourly_rate,
                p.currency_code,
                p.updated_at,
                p.' . $statusColumn . ' AS status,
                p.' . $healthColumn . ' AS health,
                ' . ($hasRiskLevelColumn ? 'p.risk_level' : 'NULL AS risk_level') . ',
                ' . ($hasRiskScoreColumn ? 'p.risk_score' : 'NULL AS risk_score') . ',
                c.name AS client_name,
                COALESCE(c.area_code, "") AS area_code,
                u.name AS pm_name,
                ' . $notesSelect . ',
                ' . $stoppersSelect . ',
                ' . $risksSelect . ',
                ' . $invoicesSelect . ',
                ' . $timesheetsSelect . '
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             LEFT JOIN users u ON u.id = p.pm_id
             ' . $notesJoin . '
             ' . $stoppersJoin . '
             ' . $risksJoin . '
             ' . $invoicesJoin . '
             ' . $timesheetsJoin . '
             ' . $whereClause . '
             ORDER BY p.updated_at DESC, p.id DESC',
            $params
        );

        $projectIds = array_values(array_unique(array_map(
            static fn (array $row): int => (int) ($row['id'] ?? 0),
            $rows
        )));
        $latestNotes = $this->notesByProject($projectIds);
        $topStoppers = $this->topStoppersByProject($projectIds);
        $milestonesPending = $this->milestonesPendingByProject($projectIds);

        $projects = [];
        foreach ($rows as $row) {
            $projectId = (int) ($row['id'] ?? 0);
            $lastNoteAt = $latestNotes[$projectId]['created_at'] ?? ($row['notes_last_at'] ?? null);
            $portfolioScore = $this->resolveProjectScore($row);
            $daysWithoutNote = $this->daysSince($lastNoteAt);
            $pendingToInvoice = $this->pendingToInvoice($row, (float) ($milestonesPending[$projectId] ?? 0.0));

            $criticalBlockers = (int) ($row['critical_open_stoppers'] ?? 0);
            $criticalRisks = (int) ($row['critical_risks'] ?? 0);
            $riskLevel = strtolower(trim((string) ($row['risk_level'] ?? '')));
            $health = strtolower(trim((string) ($row['health'] ?? '')));
            $riskByHealth = in_array($health, ['critical', 'red', 'at_risk', 'yellow', 'alto'], true);
            $riskByLevel = in_array($riskLevel, ['alto', 'high', 'critico', 'critical'], true);

            $isAtRisk = $riskByLevel
                || $riskByHealth
                || $portfolioScore < $riskThreshold
                || $criticalBlockers >= 1
                || $daysWithoutNote > 7;

            $riskReasons = [];
            if ($riskByLevel || $riskByHealth) {
                $riskReasons[] = 'Nivel de riesgo/salud alto';
            }
            if ($portfolioScore < $riskThreshold) {
                $riskReasons[] = 'Score bajo umbral (' . $riskThreshold . ')';
            }
            if ($criticalBlockers >= 1) {
                $riskReasons[] = 'Bloqueos críticos/altos abiertos';
            }
            if ($daysWithoutNote > 7) {
                $riskReasons[] = 'Sin seguimiento reciente';
            }
            if ($riskReasons === []) {
                $riskReasons[] = 'Sin señales críticas';
            }

            $alertFlags = [
                'stale_updates' => $daysWithoutNote > 7,
                'critical_blockers' => $criticalBlockers > 0,
                'critical_risks' => $criticalRisks > 0 || $riskByLevel,
                'billing_pending' => $pendingToInvoice > 0,
                'talent_overload' => false,
            ];

            $attentionIndex = 0;
            $attentionIndex += $isAtRisk ? 35 : 0;
            $attentionIndex += min(25, $criticalBlockers * 8);
            $attentionIndex += min(20, $criticalRisks * 6);
            $attentionIndex += $alertFlags['stale_updates'] ? 12 : 0;
            $attentionIndex += $alertFlags['billing_pending'] ? 8 : 0;
            $attentionIndex += (int) round(max(0, 100 - $portfolioScore) * 0.2);

            $projects[] = [
                'id' => $projectId,
                'name' => (string) ($row['name'] ?? ''),
                'client_name' => (string) ($row['client_name'] ?? ''),
                'pm_name' => (string) ($row['pm_name'] ?? ''),
                'area_code' => (string) ($row['area_code'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'health' => (string) ($row['health'] ?? ''),
                'risk_level' => (string) ($row['risk_level'] ?? ''),
                'progress' => round((float) ($row['progress'] ?? 0), 1),
                'portfolio_score' => round($portfolioScore, 1),
                'is_project_at_risk' => $isAtRisk,
                'risk_reasons' => $riskReasons,
                'critical_risks' => $criticalRisks,
                'open_stoppers' => (int) ($row['open_stoppers'] ?? 0),
                'critical_open_stoppers' => $criticalBlockers,
                'max_stopper_severity' => $this->severityLabel((int) ($row['stopper_severity_rank'] ?? 0)),
                'stoppers_top' => $topStoppers[$projectId] ?? [],
                'last_note_at' => $lastNoteAt,
                'last_note_preview' => (string) ($latestNotes[$projectId]['preview'] ?? ''),
                'last_note_author' => (string) ($latestNotes[$projectId]['author'] ?? ''),
                'notes_7d' => (int) ($row['notes_7d'] ?? 0),
                'notes_30d' => (int) ($row['notes_30d'] ?? 0),
                'days_without_note' => $daysWithoutNote,
                'approved_hours_total' => round((float) ($row['approved_hours_total'] ?? 0), 1),
                'planned_hours' => round((float) ($row['planned_hours'] ?? 0), 1),
                'pending_to_invoice' => round($pendingToInvoice, 2),
                'currency_code' => (string) ($row['currency_code'] ?? 'USD'),
                'billing_rule' => (string) ($row['billing_type'] ?? 'fixed'),
                'attention_index' => $attentionIndex,
                'alert_flags' => $alertFlags,
            ];
        }

        return $projects;
    }

    /**
     * @param array<string, mixed> $normalized
     * @return array<string, mixed>
     */
    private function buildTeamCapacityDataset(array $normalized): array
    {
        if (!$this->db->tableExists('talents')) {
            return ['rows' => [], 'talents_without_report' => 0];
        }

        $repo = new TalentCapacityRepository($this->db);
        $dashboard = $repo->dashboard([
            'date_from' => $normalized['from'],
            'date_to' => $normalized['to'],
            'area' => $normalized['area'],
            'role' => $normalized['role'],
        ], is_array($normalized['__user'] ?? null) ? $normalized['__user'] : []);

        $projectMap = $this->projectsByTalent($normalized);
        $rows = [];
        $talents = is_array($dashboard['talents'] ?? null) ? $dashboard['talents'] : [];

        foreach ($talents as $talent) {
            $talentId = (int) ($talent['id'] ?? 0);
            $monthly = is_array($talent['monthly'] ?? null) ? $talent['monthly'] : [];
            $snapshot = $monthly !== [] ? end($monthly) : ['hours' => 0, 'capacity' => 0, 'utilization' => 0];
            $assigned = (float) ($snapshot['hours'] ?? 0);
            $capacity = max(0.0, (float) ($snapshot['capacity'] ?? 0));
            $utilization = $capacity > 0 ? (($assigned / $capacity) * 100) : (float) ($snapshot['utilization'] ?? 0);
            $available = max(0.0, $capacity - $assigned);

            $status = 'available';
            if ($utilization > 100.0) {
                $status = 'critical';
            } elseif ($utilization > 90.0) {
                $status = 'overload';
            } elseif ($utilization >= 70.0) {
                $status = 'balanced';
            }

            $rows[] = [
                'talent_id' => $talentId,
                'name' => (string) ($talent['name'] ?? ''),
                'role' => (string) ($talent['role'] ?? ''),
                'assigned_hours' => round($assigned, 1),
                'capacity_hours' => round($capacity, 1),
                'utilization' => round($utilization, 1),
                'available_hours' => round($available, 1),
                'status' => $status,
                'at_risk' => $utilization > 90.0,
                'projects' => $projectMap[$talentId]['projects'] ?? [],
                'areas' => $projectMap[$talentId]['areas'] ?? [],
            ];
        }

        usort(
            $rows,
            static fn (array $a, array $b): int => ((float) ($b['utilization'] ?? 0) <=> (float) ($a['utilization'] ?? 0))
                ?: strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''))
        );

        return [
            'rows' => $rows,
            'talents_without_report' => $this->talentsWithoutReport($normalized),
        ];
    }

    /**
     * @param array<string, mixed> $normalized
     * @return array<int, array{projects: array<int, string>, areas: array<int, string>}>
     */
    private function projectsByTalent(array $normalized): array
    {
        if (!$this->db->tableExists('project_talent_assignments') || !$this->db->tableExists('projects')) {
            return [];
        }

        $user = is_array($normalized['__user'] ?? null) ? $normalized['__user'] : [];
        $conditions = ['(a.assignment_status = "active" OR (a.assignment_status IS NULL AND a.active = 1))'];
        $params = [
            ':from' => $normalized['from'],
            ':to' => $normalized['to'],
        ];

        if ((string) ($normalized['area'] ?? '') !== '' && $this->db->columnExists('clients', 'area_code')) {
            $conditions[] = 'COALESCE(c.area_code, "") = :area';
            $params[':area'] = (string) $normalized['area'];
        }
        if (!$this->isPrivileged($user) && $this->db->columnExists('projects', 'pm_id')) {
            $conditions[] = 'p.pm_id = :visibility_pm';
            $params[':visibility_pm'] = (int) ($user['id'] ?? 0);
        }
        if ((int) ($normalized['pm_id'] ?? 0) > 0 && $this->db->columnExists('projects', 'pm_id')) {
            $conditions[] = 'p.pm_id = :pm_id';
            $params[':pm_id'] = (int) $normalized['pm_id'];
        }
        if ((int) ($normalized['client_id'] ?? 0) > 0) {
            $conditions[] = 'p.client_id = :client_id';
            $params[':client_id'] = (int) $normalized['client_id'];
        }

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);
        $rows = $this->db->fetchAll(
            'SELECT a.talent_id, p.name AS project_name, COALESCE(c.area_code, "") AS area_code
             FROM project_talent_assignments a
             JOIN projects p ON p.id = a.project_id
             LEFT JOIN clients c ON c.id = p.client_id
             ' . $whereClause . '
               AND (a.start_date IS NULL OR a.start_date <= :to)
               AND (a.end_date IS NULL OR a.end_date >= :from)
             ORDER BY a.talent_id, p.name ASC',
            $params
        );

        $map = [];
        foreach ($rows as $row) {
            $talentId = (int) ($row['talent_id'] ?? 0);
            if ($talentId <= 0) {
                continue;
            }
            $project = trim((string) ($row['project_name'] ?? ''));
            $area = trim((string) ($row['area_code'] ?? ''));
            if ($project !== '') {
                $map[$talentId]['projects'][$project] = $project;
            }
            if ($area !== '') {
                $map[$talentId]['areas'][$area] = $area;
            }
        }

        foreach ($map as $talentId => $entry) {
            $map[$talentId] = [
                'projects' => array_values($entry['projects'] ?? []),
                'areas' => array_values($entry['areas'] ?? []),
            ];
        }

        return $map;
    }

    /**
     * @param array<int, int> $projectIds
     * @return array<int, array{author: string, preview: string, created_at: ?string}>
     */
    private function notesByProject(array $projectIds): array
    {
        if ($projectIds === [] || !$this->db->tableExists('audit_log')) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($projectIds), '?'));
        $rows = $this->db->fetchAll(
            'SELECT al.entity_id AS project_id, al.payload, al.created_at, COALESCE(u.name, "") AS author
             FROM audit_log al
             LEFT JOIN users u ON u.id = al.user_id
             INNER JOIN (
                SELECT entity_id, MAX(created_at) AS max_created
                FROM audit_log
                WHERE entity = "project_note"
                  AND action = "project_note_created"
                  AND entity_id IN (' . $placeholders . ')
                GROUP BY entity_id
             ) latest ON latest.entity_id = al.entity_id AND latest.max_created = al.created_at
             WHERE al.entity = "project_note"
               AND al.action = "project_note_created"',
            $projectIds
        );

        $map = [];
        foreach ($rows as $row) {
            $projectId = (int) ($row['project_id'] ?? 0);
            $payload = json_decode((string) ($row['payload'] ?? ''), true);
            $note = '';
            if (is_array($payload)) {
                $note = trim((string) ($payload['note'] ?? $payload['comment'] ?? ''));
            }
            if ($note === '') {
                $note = 'Sin detalle de nota';
            }
            $preview = function_exists('mb_substr') ? mb_substr($note, 0, 240) : substr($note, 0, 240);
            $map[$projectId] = [
                'author' => trim((string) ($row['author'] ?? '')) ?: 'Sistema',
                'preview' => $preview,
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        return $map;
    }

    /**
     * @param array<int, int> $projectIds
     * @return array<int, array<int, string>>
     */
    private function topStoppersByProject(array $projectIds): array
    {
        if ($projectIds === [] || !$this->db->tableExists('project_stoppers')) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($projectIds), '?'));
        $rows = $this->db->fetchAll(
            'SELECT project_id, title, impact_level
             FROM project_stoppers
             WHERE project_id IN (' . $placeholders . ')
               AND status IN ("abierto", "en_gestion", "escalado", "resuelto")
             ORDER BY project_id,
                      CASE impact_level
                        WHEN "critico" THEN 4
                        WHEN "alto" THEN 3
                        WHEN "medio" THEN 2
                        WHEN "bajo" THEN 1
                        ELSE 0 END DESC,
                      updated_at DESC',
            $projectIds
        );

        $map = [];
        foreach ($rows as $row) {
            $projectId = (int) ($row['project_id'] ?? 0);
            if (!isset($map[$projectId])) {
                $map[$projectId] = [];
            }
            if (count($map[$projectId]) >= 3) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? 'Bloqueo'));
            $severity = ucfirst((string) ($row['impact_level'] ?? 'medio'));
            $map[$projectId][] = $title . ' [' . $severity . ']';
        }

        return $map;
    }

    /**
     * @param array<int, int> $projectIds
     * @return array<int, float>
     */
    private function milestonesPendingByProject(array $projectIds): array
    {
        if ($projectIds === [] || !$this->db->tableExists('project_milestones') || !$this->db->tableExists('project_invoices')) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($projectIds), '?'));
        $rows = $this->db->fetchAll(
            'SELECT m.project_id, COALESCE(SUM(m.amount), 0) AS pending_amount
             FROM project_milestones m
             WHERE m.project_id IN (' . $placeholders . ')
               AND COALESCE(m.approved, 0) = 1
               AND COALESCE(m.invoiced, 0) = 0
             GROUP BY m.project_id',
            $projectIds
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int) ($row['project_id'] ?? 0)] = (float) ($row['pending_amount'] ?? 0);
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $normalized
     */
    private function talentsWithoutReport(array $normalized): int
    {
        if (!$this->db->tableExists('talents') || !$this->db->tableExists('timesheets')) {
            return 0;
        }

        $conditions = ['COALESCE(t.requiere_reporte_horas, 0) = 1'];
        $params = [
            ':from' => $normalized['from'],
            ':to' => $normalized['to'],
        ];
        if ((string) ($normalized['role'] ?? '') !== '') {
            $conditions[] = 't.role = :role';
            $params[':role'] = (string) $normalized['role'];
        }

        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total
             FROM talents t
             WHERE ' . implode(' AND ', $conditions) . '
               AND NOT EXISTS (
                    SELECT 1
                    FROM timesheets ts
                    WHERE ts.talent_id = t.id
                      AND ts.date BETWEEN :from AND :to
               )',
            $params
        );

        return (int) ($row['total'] ?? 0);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function pendingToInvoice(array $row, float $milestonesPending): float
    {
        if ((int) ($row['is_billable'] ?? 0) !== 1) {
            return 0.0;
        }

        $billingType = strtolower((string) ($row['billing_type'] ?? 'fixed'));
        $totalInvoiced = (float) ($row['total_invoiced'] ?? 0);
        $contractValue = (float) ($row['contract_value'] ?? 0);
        $approvedHours = (float) ($row['approved_hours_total'] ?? 0);
        $hourlyRate = (float) ($row['hourly_rate'] ?? 0);

        $fixedPending = max(0.0, $contractValue - $totalInvoiced);
        $hoursPending = $hourlyRate > 0 ? max(0.0, ($approvedHours * $hourlyRate) - $totalInvoiced) : 0.0;

        return match ($billingType) {
            'hours' => $hoursPending,
            'milestones' => max(0.0, $milestonesPending),
            'mixed' => max($fixedPending, $hoursPending),
            'one_time', 'fixed', 'monthly', 'deliverable' => $fixedPending,
            default => $fixedPending,
        };
    }

    /**
     * @param array<string, mixed> $row
     */
    private function resolveProjectScore(array $row): float
    {
        $riskScore = (float) ($row['risk_score'] ?? 0);
        if ($riskScore > 0) {
            return max(0.0, min(100.0, 100.0 - $riskScore));
        }

        $progress = (float) ($row['progress'] ?? 0);
        $score = $progress;
        $score -= min(25.0, ((float) ($row['critical_open_stoppers'] ?? 0) * 6.0));
        $score -= min(20.0, ((float) ($row['critical_risks'] ?? 0) * 4.0));

        $health = strtolower(trim((string) ($row['health'] ?? '')));
        if (in_array($health, ['critical', 'red', 'alto'], true)) {
            $score -= 18.0;
        } elseif (in_array($health, ['at_risk', 'yellow', 'medio'], true)) {
            $score -= 9.0;
        }

        return max(0.0, min(100.0, $score));
    }

    /**
     * @param array<string, mixed> $project
     */
    private function matchesProjectAlert(array $project, string $alert): bool
    {
        return match ($alert) {
            'stale_updates' => !empty($project['alert_flags']['stale_updates']),
            'critical_blockers' => (int) ($project['critical_open_stoppers'] ?? 0) > 0,
            'critical_risks' => !empty($project['alert_flags']['critical_risks']),
            'billing_pending' => (float) ($project['pending_to_invoice'] ?? 0) > 0,
            default => true,
        };
    }

    private function teamUtilization(array $rows): float
    {
        if ($rows === []) {
            return 0.0;
        }

        $sum = 0.0;
        foreach ($rows as $row) {
            $sum += (float) ($row['utilization'] ?? 0);
        }

        return $sum / count($rows);
    }

    private function rowContainsArea(array $row, string $area): bool
    {
        foreach ((array) ($row['areas'] ?? []) as $rowArea) {
            if (strcasecmp((string) $rowArea, $area) === 0) {
                return true;
            }
        }

        return false;
    }

    private function previousRange(string $from, string $to): array
    {
        $start = new DateTimeImmutable($from);
        $end = new DateTimeImmutable($to);
        $days = max(1, (int) $start->diff($end)->days + 1);
        $prevEnd = $start->modify('-1 day');
        $prevStart = $prevEnd->modify('-' . ($days - 1) . ' days');

        return [$prevStart->format('Y-m-d'), $prevEnd->format('Y-m-d')];
    }

    private function isPrivileged(array $user): bool
    {
        return in_array((string) ($user['role'] ?? ''), self::ADMIN_ROLES, true);
    }

    private function daysSince(?string $date): int
    {
        if (!$this->isDateTimeString($date)) {
            return 999;
        }

        $then = new DateTimeImmutable((string) $date);
        $today = new DateTimeImmutable(date('Y-m-d 23:59:59'));
        return max(0, (int) $then->diff($today)->days);
    }

    private function severityLabel(int $rank): string
    {
        return match (true) {
            $rank >= 4 => 'Critico',
            $rank === 3 => 'Alto',
            $rank === 2 => 'Medio',
            $rank === 1 => 'Bajo',
            default => 'N/A',
        };
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $filters): array
    {
        $from = trim((string) ($filters['from'] ?? ''));
        $to = trim((string) ($filters['to'] ?? ''));
        if (!$this->isDate($from)) {
            $from = date('Y-m-01');
        }
        if (!$this->isDate($to)) {
            $to = date('Y-m-t');
        }
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [
            'from' => $from,
            'to' => $to,
            'client_id' => max(0, (int) ($filters['client_id'] ?? 0)),
            'pm_id' => max(0, (int) ($filters['pm_id'] ?? 0)),
            'status' => trim((string) ($filters['status'] ?? '')),
            'alert' => trim((string) ($filters['alert'] ?? '')),
            'area' => trim((string) ($filters['area'] ?? '')),
            'role' => trim((string) ($filters['role'] ?? '')),
            '__user' => is_array($filters['__user'] ?? null) ? $filters['__user'] : [],
        ];
    }

    private function riskThreshold(): int
    {
        $config = $this->config();
        $threshold = (int) ($config['operational_rules']['decision_center']['risk_threshold'] ?? 70);
        if ($threshold <= 0) {
            $threshold = 70;
        }
        return min(100, max(1, $threshold));
    }

    private function config(): array
    {
        if ($this->configCache === null) {
            $this->configCache = (new ConfigService($this->db))->getConfig();
        }

        return $this->configCache;
    }

    /**
     * @param array<int, array<string, mixed>> $projects
     */
    private function firstProjectByCondition(array $projects, callable $condition): ?array
    {
        foreach ($projects as $project) {
            if ($condition($project) === true) {
                return $project;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $normalized
     */
    private function aiCacheKey(array $normalized): string
    {
        $user = is_array($normalized['__user'] ?? null) ? $normalized['__user'] : [];
        return 'decision_center_ai_' . sha1(json_encode([
            'from' => $normalized['from'],
            'to' => $normalized['to'],
            'client_id' => $normalized['client_id'],
            'pm_id' => $normalized['pm_id'],
            'status' => $normalized['status'],
            'area' => $normalized['area'],
            'role' => $normalized['role'],
            'user_id' => (int) ($user['id'] ?? 0),
            'role_name' => (string) ($user['role'] ?? ''),
        ], JSON_UNESCAPED_UNICODE));
    }

    private function readAiCache(string $cacheKey): ?array
    {
        if (!$this->db->tableExists('config_settings')) {
            return null;
        }

        $row = $this->db->fetchOne(
            'SELECT config_value, updated_at
             FROM config_settings
             WHERE config_key = :cache_key
             LIMIT 1',
            [':cache_key' => $cacheKey]
        );

        if (!$row) {
            return null;
        }

        $updatedAt = strtotime((string) ($row['updated_at'] ?? ''));
        if ($updatedAt <= 0 || (time() - $updatedAt) > self::AI_CACHE_TTL_SECONDS) {
            return null;
        }

        $decoded = json_decode((string) ($row['config_value'] ?? ''), true);
        if (!is_array($decoded) || !isset($decoded['text'])) {
            return null;
        }

        return [
            'text' => (string) ($decoded['text'] ?? ''),
            'generated_at' => (string) ($decoded['generated_at'] ?? date('c', $updatedAt)),
            'cached' => true,
        ];
    }

    private function writeAiCache(string $cacheKey, array $payload): void
    {
        if (!$this->db->tableExists('config_settings')) {
            return;
        }

        $json = json_encode([
            'text' => (string) ($payload['text'] ?? ''),
            'generated_at' => (string) ($payload['generated_at'] ?? date('c')),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($json)) {
            return;
        }

        $this->db->execute(
            'INSERT INTO config_settings (config_key, config_value, updated_at)
             VALUES (:cache_key, :cache_value, NOW())
             ON DUPLICATE KEY UPDATE
                 config_value = VALUES(config_value),
                 updated_at = NOW()',
            [
                ':cache_key' => $cacheKey,
                ':cache_value' => $json,
            ]
        );
    }

    private function isDate(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }

    private function isDateTimeString(?string $value): bool
    {
        if (!is_string($value) || trim($value) === '') {
            return false;
        }

        return strtotime($value) !== false;
    }
}
