<?php

declare(strict_types=1);

class ProjectService
{
    private const FOLLOWUP_EXPECTED_DAYS = 7;
    private const FOLLOWUP_EVALUATION_DAYS = 28;
    private const STOPPER_HIGH_OVERDUE_DAYS = 7;

    /** @var array<int, array<string, mixed>> */
    private static array $healthCache = [];

    /** @var array<int, array<string, mixed>> */
    private array $seguimientoMeta = [];

    public function __construct(private Database $db)
    {
    }

    public function calculateProjectHealthScore(int $projectId): array
    {
        return $this->calculateProjectHealthReport($projectId);
    }

    public function calculateProjectHealthReport(int $projectId): array
    {
        if (isset(self::$healthCache[$projectId])) {
            return self::$healthCache[$projectId];
        }

        $project = $this->db->fetchOne('SELECT * FROM projects WHERE id = :id LIMIT 1', [':id' => $projectId]);
        if (!$project) {
            $empty = [
                'total_score' => 0,
                'level' => 'critical',
                'label' => 'Crítico',
                'documental_score' => 0,
                'avance_score' => 0,
                'horas_score' => 0,
                'seguimiento_score' => 0,
                'riesgo_score' => 0,
                'calidad_requisitos_score' => 0,
                'breakdown' => [],
                'recommendations' => [],
            ];
            self::$healthCache[$projectId] = $empty;

            return $empty;
        }

        $config = (new ConfigService($this->db))->getConfig();
        $rules = $config['operational_rules']['health_scoring'] ?? [];

        $weights = [
            'documental' => (float) ($rules['weights']['documental'] ?? 0.25),
            'avance' => (float) ($rules['weights']['avance'] ?? 0.25),
            'horas' => (float) ($rules['weights']['horas'] ?? 0.20),
            'seguimiento' => (float) ($rules['weights']['seguimiento'] ?? 0.15),
            'riesgo' => (float) ($rules['weights']['riesgo'] ?? 0.10),
            'calidad_requisitos' => (float) ($rules['weights']['calidad_requisitos'] ?? 0.15),
        ];

        $thresholds = [
            'optimal' => (int) ($rules['thresholds']['optimal'] ?? 90),
            'attention' => (int) ($rules['thresholds']['attention'] ?? 75),
        ];

        $maxPoints = [
            'documental' => (int) ($rules['max_points']['documental'] ?? 25),
            'avance' => (int) ($rules['max_points']['avance'] ?? 25),
            'horas' => (int) ($rules['max_points']['horas'] ?? 20),
            'seguimiento' => (int) ($rules['max_points']['seguimiento'] ?? 15),
            'riesgo' => (int) ($rules['max_points']['riesgo'] ?? 10),
            'calidad_requisitos' => (int) ($rules['max_points']['calidad_requisitos'] ?? 15),
        ];

        $rawScores = [
            'documental' => $this->calculateDocumentalScore($projectId),
            'avance' => $this->calculateAvanceScore($project),
            'horas' => $this->calculateHorasScore($projectId, $project),
            'seguimiento' => $this->calculateSeguimientoScore($projectId, $project),
            'riesgo' => $this->calculateRiesgoScore($projectId, $project),
            'calidad_requisitos' => $this->calculateRequisitosScore($projectId),
        ];

        $stopperPenalty = $this->stopperPenalty($projectId);

        $breakdown = [];
        $weightedTotal = 0.0;
        foreach ($rawScores as $dimension => $rawScore) {
            $max = max(0, $maxPoints[$dimension] ?? 0);
            $score = (int) round(($rawScore / 100) * $max);
            $weightedTotal += $rawScore * ($weights[$dimension] ?? 0);
            $breakdown[$dimension] = [
                'score' => $score,
                'max' => $max,
                'percentage' => $rawScore,
                'issues' => $this->dimensionIssues($dimension, $projectId, $project, $rawScore, $config),
                'justification' => $this->dimensionJustification($dimension, $rawScore),
            ];

            if ($dimension === 'seguimiento' && isset($this->seguimientoMeta[$projectId])) {
                $breakdown[$dimension]['meta'] = $this->seguimientoMeta[$projectId];
            }
        }

        $total = $this->clampScore((int) round($weightedTotal) - (int) ($stopperPenalty['points'] ?? 0));
        $level = $total >= $thresholds['optimal'] ? 'optimal' : ($total >= $thresholds['attention'] ? 'attention' : 'critical');
        if ((int) ($stopperPenalty['critical_open'] ?? 0) > 0 && $level === 'optimal') {
            $level = 'attention';
        }
        $label = $level === 'optimal' ? 'Salud óptima' : ($level === 'attention' ? 'Atención' : 'Crítico');

        $result = [
            'total_score' => $total,
            'level' => $level,
            'label' => $label,
            'documental_score' => $rawScores['documental'],
            'avance_score' => $rawScores['avance'],
            'horas_score' => $rawScores['horas'],
            'seguimiento_score' => $rawScores['seguimiento'],
            'riesgo_score' => $rawScores['riesgo'],
            'calidad_requisitos_score' => $rawScores['calidad_requisitos'],
            'breakdown' => $breakdown,
            'recommendations' => $this->buildRecommendations($breakdown),
            'stoppers_penalty' => $stopperPenalty,
            'calculated_at' => date('Y-m-d H:i:s'),
        ];

        self::$healthCache[$projectId] = $result;

        return $result;
    }

    public function recordHealthSnapshot(int $projectId): void
    {
        if (!$this->db->tableExists('project_health_history')) {
            return;
        }

        unset(self::$healthCache[$projectId]);
        $report = $this->calculateProjectHealthReport($projectId);

        $this->db->insert(
            'INSERT INTO project_health_history (project_id, score, breakdown_json, calculated_at) VALUES (:project_id, :score, :breakdown, NOW())',
            [
                ':project_id' => $projectId,
                ':score' => (int) ($report['total_score'] ?? 0),
                ':breakdown' => json_encode($report, JSON_UNESCAPED_UNICODE),
            ]
        );
    }

    public function history(int $projectId, int $days = 30): array
    {
        if (!$this->db->tableExists('project_health_history')) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT score, calculated_at FROM project_health_history WHERE project_id = :project_id AND calculated_at >= DATE_SUB(NOW(), INTERVAL :days DAY) ORDER BY calculated_at ASC',
            [':project_id' => $projectId, ':days' => max(1, $days)]
        );
    }

    private function dimensionJustification(string $dimension, int $score): string
    {
        if ($score >= 85) {
            return 'Dentro del rango esperado.';
        }

        return match ($dimension) {
            'documental' => 'Faltan documentos obligatorios de la fase actual.',
            'avance' => 'El avance real se desvía del plan esperado.',
            'horas' => 'Existe desviación de horas respecto al avance.',
            'seguimiento' => 'Proyecto sin actualización reciente.',
            'riesgo' => 'Riesgos críticos sin mitigación.',
            'calidad_requisitos' => 'Requisitos con baja aprobación en primera entrega.',
            default => 'Requiere atención ejecutiva.',
        };
    }

    private function dimensionIssues(string $dimension, int $projectId, array $project, int $score, array $config): array
    {
        $issues = [];
        if ($score >= 85) {
            return ['Sin hallazgos críticos.'];
        }

        if ($dimension === 'documental' && $this->db->tableExists('project_nodes')) {
            $requiredCondition = '1 = 1';
            if ($this->db->columnExists('project_nodes', 'is_required')) {
                $requiredCondition = 'is_required = 1';
            } elseif ($this->db->columnExists('project_nodes', 'critical')) {
                $requiredCondition = 'critical = 1';
            }

            $row = $this->db->fetchOne(
                "SELECT
                    SUM(CASE WHEN node_type = 'file' AND {$requiredCondition} AND document_status NOT IN ('final','publicado','aprobado') THEN 1 ELSE 0 END) AS pending_required,
                    SUM(CASE WHEN node_type = 'file' AND {$requiredCondition} THEN 1 ELSE 0 END) AS total_required
                FROM project_nodes
                WHERE project_id = :projectId",
                [':projectId' => $projectId]
            );
            $pending = (int) ($row['pending_required'] ?? 0);
            if ($pending > 0) {
                $issues[] = sprintf('%d nodos obligatorios sin documento final.', $pending);
            }
        }

        if ($dimension === 'horas' && $this->db->tableExists('timesheets')) {
            $row = $this->db->fetchOne(
                "SELECT
                    SUM(CASE WHEN status IN ('pending','submitted','pending_approval') THEN hours ELSE 0 END) AS pending_hours,
                    SUM(hours) AS total_hours
                FROM timesheets
                WHERE project_id = :projectId",
                [':projectId' => $projectId]
            );
            $pending = (float) ($row['pending_hours'] ?? 0);
            $total = (float) ($row['total_hours'] ?? 0);
            $ratio = $total > 0 ? $pending / $total : 0.0;
            if ($pending > 0) {
                $issues[] = sprintf('%.1f horas pendientes de aprobación.', $pending);
            }
            $maxPending = (float) ($config['operational_rules']['health_scoring']['max_pending_hours_ratio'] ?? 0.20);
            if ($ratio > $maxPending) {
                $issues[] = 'Alto volumen de horas sin aprobación.';
            }
        }

        if ($dimension === 'seguimiento') {
            $status = $this->normalizeProjectStatus($project);
            if ($status === 'closed') {
                return ['Seguimiento no exigible por proyecto cerrado.'];
            }

            $meta = $this->seguimientoMeta[$projectId] ?? [];
            $daysWithoutUpdate = (int) ($meta['days_since_last_note'] ?? 999);
            $maxDays = (int) ($meta['expected_days_threshold'] ?? self::FOLLOWUP_EXPECTED_DAYS);

            if ($daysWithoutUpdate > $maxDays) {
                $issues[] = sprintf('Última nota de seguimiento hace %d días.', $daysWithoutUpdate);
            }

            $notesInPeriod = (int) ($meta['notes_in_period'] ?? 0);
            if ($notesInPeriod <= 0) {
                $issues[] = sprintf('No hay notas en los últimos %d días.', (int) ($meta['evaluation_period_days'] ?? self::FOLLOWUP_EVALUATION_DAYS));
            }
        }

        if ($dimension === 'riesgo') {
            $riskLevel = strtolower((string) ($project['risk_level'] ?? ''));
            if ($riskLevel === 'alto') {
                $issues[] = 'Riesgos críticos sin mitigación.';
            }
        }

        if ($dimension === 'avance') {
            $issues[] = 'El avance real requiere alineación con el plan base.';
        }

        return $issues === [] ? ['Revisar dimensión para mejorar desempeño.'] : $issues;
    }

    private function calculateRequisitosScore(int $projectId): int
    {
        if (!$this->db->tableExists('project_requirements')) {
            return 100;
        }

        $repo = new RequirementsRepository($this->db);
        $start = date('Y-m-01');
        $end = date('Y-m-t');
        $indicator = $repo->indicatorForProject($projectId, $start, $end);

        if (!(bool) ($indicator['applicable'] ?? false)) {
            return 100;
        }

        return $this->clampScore((int) round((float) ($indicator['value'] ?? 0)));
    }

    private function stopperPenalty(int $projectId): array
    {
        if (!$this->db->tableExists('project_stoppers')) {
            return ['points' => 0, 'critical_open' => 0, 'high_overdue' => 0, 'active_total' => 0];
        }

        $row = $this->db->fetchOne(
            'SELECT
                SUM(CASE WHEN status IN ("abierto", "en_gestion", "escalado", "resuelto") THEN 1 ELSE 0 END) AS active_total,
                SUM(CASE WHEN status IN ("abierto", "en_gestion", "escalado", "resuelto") AND impact_level = "critico" THEN 1 ELSE 0 END) AS critical_open,
                SUM(CASE WHEN status IN ("abierto", "en_gestion", "escalado", "resuelto") AND impact_level = "alto" AND DATEDIFF(CURDATE(), detected_at) > :high_days THEN 1 ELSE 0 END) AS high_overdue
             FROM project_stoppers
             WHERE project_id = :project_id',
            [
                ':project_id' => $projectId,
                ':high_days' => self::STOPPER_HIGH_OVERDUE_DAYS,
            ]
        ) ?? [];

        $active = (int) ($row['active_total'] ?? 0);
        $critical = (int) ($row['critical_open'] ?? 0);
        $highOverdue = (int) ($row['high_overdue'] ?? 0);

        $points = 0;
        if ($highOverdue > 0) {
            $points += min(12, $highOverdue * 4);
        }
        if ($active > 3) {
            $points += 8;
        }

        return [
            'points' => $points,
            'critical_open' => $critical,
            'high_overdue' => $highOverdue,
            'active_total' => $active,
        ];
    }

    private function buildRecommendations(array $breakdown): array
    {
        $recommendations = [];
        if ((int) ($breakdown['documental']['percentage'] ?? 0) < 85) {
            $recommendations[] = 'Completar documentos obligatorios fase actual';
        }
        $needsFollowupUpdate = (bool) ($breakdown['seguimiento']['meta']['needs_followup_update'] ?? false);
        if ((int) ($breakdown['seguimiento']['percentage'] ?? 0) < 85 && $needsFollowupUpdate) {
            $recommendations[] = 'Actualizar seguimiento semanal';
        }
        if ((int) ($breakdown['riesgo']['percentage'] ?? 0) < 85) {
            $recommendations[] = 'Revisar riesgos críticos abiertos';
        }
        if ((int) ($breakdown['horas']['percentage'] ?? 0) < 85) {
            $recommendations[] = 'Reducir horas pendientes y validar imputaciones';
        }

        return $recommendations;
    }

    private function calculateDocumentalScore(int $projectId): int
    {
        if (!$this->db->tableExists('project_nodes')) {
            return 50;
        }

        $row = $this->db->fetchOne(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN document_status IN ('final', 'publicado', 'aprobado') THEN 1 ELSE 0 END) AS approved
             FROM project_nodes
             WHERE project_id = :projectId
             AND node_type = 'file'",
            [':projectId' => $projectId]
        );

        $total = (int) ($row['total'] ?? 0);
        $approved = (int) ($row['approved'] ?? 0);

        if ($total <= 0) {
            return 50;
        }

        return $this->clampScore((int) round(($approved / $total) * 100));
    }

    private function calculateAvanceScore(array $project): int
    {
        $realProgress = max(0, min(100, (float) ($project['progress'] ?? 0)));
        $startDate = $project['start_date'] ?? null;
        $endDate = $project['end_date'] ?? null;

        if (!$startDate || !$endDate) {
            return (int) round($realProgress);
        }

        $startTs = strtotime((string) $startDate);
        $endTs = strtotime((string) $endDate);
        $now = time();

        if (!$startTs || !$endTs || $endTs <= $startTs) {
            return (int) round($realProgress);
        }

        if ($now <= $startTs) {
            $expected = 0.0;
        } elseif ($now >= $endTs) {
            $expected = 100.0;
        } else {
            $expected = (($now - $startTs) / ($endTs - $startTs)) * 100;
        }

        $deviation = abs($realProgress - $expected);

        return $this->clampScore((int) round(100 - $deviation));
    }

    private function calculateHorasScore(int $projectId, array $project): int
    {
        $plannedHours = (float) ($project['planned_hours'] ?? 0);
        $actualHours = $this->projectActualHours($projectId, (float) ($project['actual_hours'] ?? 0));
        $progress = max(0, min(100, (float) ($project['progress'] ?? 0)));

        if ($plannedHours <= 0) {
            return $actualHours <= 0 ? 100 : 60;
        }

        $expectedHours = max(1.0, $plannedHours * ($progress / 100));
        $deviationRatio = abs($actualHours - $expectedHours) / $expectedHours;

        return $this->clampScore((int) round(100 - ($deviationRatio * 100)));
    }

    private function calculateSeguimientoScore(int $projectId, array $project): int
    {
        $status = $this->normalizeProjectStatus($project);
        if ($status === 'closed') {
            $this->seguimientoMeta[$projectId] = [
                'project_status' => $status,
                'expected_days_threshold' => self::FOLLOWUP_EXPECTED_DAYS,
                'evaluation_period_days' => self::FOLLOWUP_EVALUATION_DAYS,
                'needs_followup_update' => false,
                'notes_in_period' => 0,
                'last_note_at' => null,
                'days_since_last_note' => null,
            ];

            return 100;
        }

        $expectedDays = self::FOLLOWUP_EXPECTED_DAYS;
        $evaluationDays = self::FOLLOWUP_EVALUATION_DAYS;
        $noteActivity = $this->projectNoteActivity($projectId, $evaluationDays);

        $lastNoteAt = $noteActivity['last_note_at'];
        $daysWithoutUpdate = $lastNoteAt ? (int) floor((time() - strtotime($lastNoteAt)) / 86400) : 999;
        $notesInPeriod = (int) ($noteActivity['notes_in_period'] ?? 0);

        error_log(sprintf(
            'ProjectService Seguimiento project_id=%d last_note_at=%s notes_in_period=%d expected_days=%d',
            $projectId,
            $lastNoteAt ?? 'NULL',
            $notesInPeriod,
            $expectedDays
        ));

        $recencyScore = match (true) {
            $daysWithoutUpdate <= $expectedDays => 100,
            $daysWithoutUpdate <= ($expectedDays * 2) => 65,
            $daysWithoutUpdate <= ($expectedDays * 3) => 40,
            default => 20,
        };

        $expectedNotes = max(1, (int) ceil($evaluationDays / $expectedDays));
        $frequencyScore = $this->clampScore((int) round(min(1, $notesInPeriod / $expectedNotes) * 100));

        $needsFollowupUpdate = $daysWithoutUpdate > $expectedDays;
        $this->seguimientoMeta[$projectId] = [
            'project_status' => $status,
            'expected_days_threshold' => $expectedDays,
            'evaluation_period_days' => $evaluationDays,
            'needs_followup_update' => $needsFollowupUpdate,
            'notes_in_period' => $notesInPeriod,
            'last_note_at' => $lastNoteAt,
            'days_since_last_note' => $lastNoteAt ? $daysWithoutUpdate : null,
        ];

        return $this->clampScore((int) round(($recencyScore * 0.65) + ($frequencyScore * 0.35)));
    }

    /** @return array{last_note_at: ?string, notes_in_period: int} */
    private function projectNoteActivity(int $projectId, int $evaluationDays): array
    {
        if (!$this->db->tableExists('audit_log')) {
            return ['last_note_at' => null, 'notes_in_period' => 0];
        }

        $row = $this->db->fetchOne(
            'SELECT
                MAX(created_at) AS last_note_at,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL :evaluationDays DAY) THEN 1 ELSE 0 END) AS notes_in_period
             FROM audit_log
             WHERE entity = :entity
               AND entity_id = :projectId
               AND action = :action',
            [
                ':entity' => 'project_note',
                ':projectId' => $projectId,
                ':action' => 'project_note_created',
                ':evaluationDays' => $evaluationDays,
            ]
        );

        return [
            'last_note_at' => isset($row['last_note_at']) && $row['last_note_at'] !== '' ? (string) $row['last_note_at'] : null,
            'notes_in_period' => (int) ($row['notes_in_period'] ?? 0),
        ];
    }

    private function normalizeProjectStatus(array $project): string
    {
        $status = strtolower(trim((string) ($project['status'] ?? '')));
        return in_array($status, ['closed', 'cerrado', 'completado', 'finalizado', 'archived', 'cancelled'], true)
            ? 'closed'
            : $status;
    }

    private function calculateRiesgoScore(int $projectId, array $project): int
    {
        $riskLevel = strtolower(trim((string) ($project['risk_level'] ?? '')));
        if ($riskLevel !== '') {
            return match ($riskLevel) {
                'bajo' => 90,
                'medio' => 60,
                'alto' => 25,
                default => 70,
            };
        }

        $riskScore = isset($project['risk_score']) ? (float) $project['risk_score'] : null;
        if ($riskScore !== null && $riskScore > 0) {
            return $this->clampScore((int) round(100 - $riskScore));
        }

        if (!$this->db->tableExists('project_risk_evaluations')) {
            return 70;
        }

        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM project_risk_evaluations WHERE project_id = :projectId AND selected = 1',
            [':projectId' => $projectId]
        );
        $selectedRisks = (int) ($row['total'] ?? 0);

        if ($selectedRisks <= 0) {
            return 85;
        }

        return $this->clampScore(100 - ($selectedRisks * 10));
    }

    private function projectActualHours(int $projectId, float $fallback): float
    {
        if (!$this->db->tableExists('timesheets')) {
            return $fallback;
        }

        if ($this->db->columnExists('timesheets', 'project_id')) {
            $row = $this->db->fetchOne(
                'SELECT SUM(hours) AS total FROM timesheets WHERE project_id = :projectId',
                [':projectId' => $projectId]
            );
        } elseif ($this->db->tableExists('tasks')) {
            $row = $this->db->fetchOne(
                'SELECT SUM(ts.hours) AS total
                 FROM timesheets ts
                 JOIN tasks t ON t.id = ts.task_id
                 WHERE t.project_id = :projectId',
                [':projectId' => $projectId]
            );
        } else {
            $row = null;
        }

        $hours = (float) ($row['total'] ?? 0);

        return $hours > 0 ? $hours : $fallback;
    }

    private function clampScore(int $score): int
    {
        return max(0, min(100, $score));
    }
}
