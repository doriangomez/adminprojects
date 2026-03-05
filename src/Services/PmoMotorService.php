<?php

declare(strict_types=1);

/**
 * Motor PMO Automático
 *
 * Calcula diariamente por proyecto:
 *   - Avance por horas (aprobadas / planificadas)
 *   - Avance por tareas (cerradas / total)
 *   - Score de riesgo (0-100)
 *   - Alertas PMO
 *
 * Los resultados se persisten en project_pmo_snapshots y project_pmo_alerts
 * para no recalcular en cada carga de página.
 */
class PmoMotorService
{
    private const STALE_HOURS = 4;

    public function __construct(private Database $db)
    {
    }

    /**
     * Recalculate PMO snapshot for a single project if stale (older than STALE_HOURS).
     * Returns the snapshot array.
     */
    public function getOrRefreshSnapshot(int $projectId): array
    {
        if (!$this->db->tableExists('project_pmo_snapshots')) {
            return $this->buildLiveSnapshot($projectId);
        }

        $today = date('Y-m-d');
        $existing = $this->db->fetchOne(
            'SELECT * FROM project_pmo_snapshots WHERE project_id = :p AND snapshot_date = :d LIMIT 1',
            [':p' => $projectId, ':d' => $today]
        );

        if ($existing) {
            $calculatedAt = (string) ($existing['calculated_at'] ?? '');
            $ageHours = $calculatedAt !== ''
                ? (time() - strtotime($calculatedAt)) / 3600
                : self::STALE_HOURS + 1;

            if ($ageHours < self::STALE_HOURS) {
                return $existing;
            }
        }

        return $this->recalculate($projectId);
    }

    /**
     * Force full recalculation and persist for a project.
     */
    public function recalculate(int $projectId): array
    {
        $snapshot = $this->buildLiveSnapshot($projectId);

        if ($this->db->tableExists('project_pmo_snapshots')) {
            $this->persistSnapshot($projectId, $snapshot);
        }

        if ($this->db->tableExists('project_pmo_alerts')) {
            $this->generateAlerts($projectId, $snapshot);
        }

        return $snapshot;
    }

    /**
     * Recalculate PMO for all active projects (for daily cron / batch trigger).
     */
    public function recalculateAll(): int
    {
        if (!$this->db->tableExists('projects')) {
            return 0;
        }

        $projects = $this->db->fetchAll(
            "SELECT id FROM projects WHERE active = 1 OR active IS NULL"
        );

        $count = 0;
        foreach ($projects as $project) {
            $projectId = (int) ($project['id'] ?? 0);
            if ($projectId <= 0) {
                continue;
            }
            try {
                $this->recalculate($projectId);
                $count++;
            } catch (\Throwable $e) {
                error_log("PmoMotorService: error en proyecto {$projectId}: " . $e->getMessage());
            }
        }

        return $count;
    }

    /**
     * Return active alerts for a project, ordered by severity.
     */
    public function activeAlerts(int $projectId): array
    {
        if (!$this->db->tableExists('project_pmo_alerts')) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT * FROM project_pmo_alerts
             WHERE project_id = :p AND is_active = 1
             ORDER BY FIELD(severity, "critical","high","medium","low"), created_at DESC',
            [':p' => $projectId]
        );
    }

    /**
     * Return the last 4 weekly real_hours totals for a project (trend).
     */
    public function hoursTrend(int $projectId): array
    {
        if (!$this->db->tableExists('timesheets')) {
            return [];
        }

        return $this->db->fetchAll(
            "SELECT
                YEAR(date) AS yr,
                WEEK(date, 3) AS wk,
                MIN(date) AS week_start,
                SUM(CASE WHEN status = 'approved' THEN hours ELSE 0 END) AS approved_hours,
                SUM(hours) AS total_hours
             FROM timesheets
             WHERE project_id = :p
               AND date >= DATE_SUB(CURDATE(), INTERVAL 28 DAY)
             GROUP BY YEAR(date), WEEK(date, 3)
             ORDER BY yr ASC, wk ASC",
            [':p' => $projectId]
        );
    }

    // ─── Private Calculation Logic ────────────────────────────────────────────

    private function buildLiveSnapshot(int $projectId): array
    {
        $project = $this->fetchProject($projectId);
        if (!$project) {
            return $this->emptySnapshot($projectId);
        }

        $progressManual = (float) ($project['progress'] ?? 0);
        $plannedHours = (float) ($project['planned_hours'] ?? 0);

        $realHours = $this->fetchRealHours($projectId);
        $progressHours = ($plannedHours > 0)
            ? min(100.0, round(($realHours / $plannedHours) * 100, 2))
            : null;

        [$totalTasks, $doneTasks, $overdueTasks] = $this->fetchTaskMetrics($projectId);
        $progressTasks = $totalTasks > 0
            ? round(($doneTasks / $totalTasks) * 100, 2)
            : null;

        [$openBlockers, $criticalBlockers] = $this->fetchBlockerMetrics($projectId);
        $inactiveDays = $this->fetchInactiveBusinessDays($projectId);

        $riskScore = $this->computeRiskScore(
            $progressManual,
            $progressHours,
            $progressTasks,
            $plannedHours,
            $realHours,
            $overdueTasks,
            $totalTasks,
            $openBlockers,
            $criticalBlockers,
            $inactiveDays
        );

        return [
            'project_id' => $projectId,
            'snapshot_date' => date('Y-m-d'),
            'progress_manual' => $progressManual,
            'progress_hours' => $progressHours,
            'progress_tasks' => $progressTasks,
            'real_hours' => $realHours,
            'planned_hours' => $plannedHours,
            'total_tasks' => $totalTasks,
            'done_tasks' => $doneTasks,
            'overdue_tasks' => $overdueTasks,
            'open_blockers' => $openBlockers,
            'critical_blockers' => $criticalBlockers,
            'risk_score' => $riskScore,
            'inactive_business_days' => $inactiveDays,
            'calculated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function fetchProject(int $projectId): ?array
    {
        if (!$this->db->tableExists('projects')) {
            return null;
        }

        $hasPlanned = $this->db->columnExists('projects', 'planned_hours');
        $hasActual = $this->db->columnExists('projects', 'actual_hours');
        $hasProgress = $this->db->columnExists('projects', 'progress');

        $select = 'id';
        if ($hasProgress) $select .= ', progress';
        if ($hasPlanned) $select .= ', planned_hours';
        if ($hasActual) $select .= ', actual_hours';

        return $this->db->fetchOne(
            "SELECT {$select} FROM projects WHERE id = :p LIMIT 1",
            [':p' => $projectId]
        ) ?: null;
    }

    private function fetchRealHours(int $projectId): float
    {
        if (!$this->db->tableExists('timesheets')) {
            return 0.0;
        }

        $row = $this->db->fetchOne(
            "SELECT COALESCE(SUM(hours), 0) AS total
             FROM timesheets
             WHERE project_id = :p AND status = 'approved'",
            [':p' => $projectId]
        );

        return (float) ($row['total'] ?? 0);
    }

    private function fetchTaskMetrics(int $projectId): array
    {
        if (!$this->db->tableExists('tasks')) {
            return [0, 0, 0];
        }

        $row = $this->db->fetchOne(
            "SELECT
                COUNT(*) AS total_tasks,
                SUM(CASE WHEN status IN ('done','completed') THEN 1 ELSE 0 END) AS done_tasks,
                SUM(CASE WHEN due_date IS NOT NULL AND due_date < CURDATE() AND status NOT IN ('done','completed') THEN 1 ELSE 0 END) AS overdue_tasks
             FROM tasks
             WHERE project_id = :p",
            [':p' => $projectId]
        );

        return [
            (int) ($row['total_tasks'] ?? 0),
            (int) ($row['done_tasks'] ?? 0),
            (int) ($row['overdue_tasks'] ?? 0),
        ];
    }

    private function fetchBlockerMetrics(int $projectId): array
    {
        if (!$this->db->tableExists('project_stoppers')) {
            return [0, 0];
        }

        $row = $this->db->fetchOne(
            "SELECT
                SUM(CASE WHEN status IN ('abierto','en_gestion','escalado','resuelto') THEN 1 ELSE 0 END) AS open_blockers,
                SUM(CASE WHEN status IN ('abierto','en_gestion','escalado','resuelto') AND impact_level = 'critico' THEN 1 ELSE 0 END) AS critical_blockers
             FROM project_stoppers
             WHERE project_id = :p",
            [':p' => $projectId]
        );

        return [
            (int) ($row['open_blockers'] ?? 0),
            (int) ($row['critical_blockers'] ?? 0),
        ];
    }

    /**
     * Count business days (Mon-Fri) since last approved timesheet entry for this project.
     */
    private function fetchInactiveBusinessDays(int $projectId): int
    {
        if (!$this->db->tableExists('timesheets')) {
            return 0;
        }

        $row = $this->db->fetchOne(
            "SELECT MAX(date) AS last_date
             FROM timesheets
             WHERE project_id = :p AND status = 'approved'",
            [':p' => $projectId]
        );

        $lastDateStr = $row['last_date'] ?? null;
        if (!$lastDateStr) {
            return 0;
        }

        try {
            $lastDate = new \DateTimeImmutable($lastDateStr);
            $today = new \DateTimeImmutable('today');
            $businessDays = 0;
            $current = $lastDate->modify('+1 day');
            while ($current <= $today) {
                $dow = (int) $current->format('N');
                if ($dow < 6) {
                    $businessDays++;
                }
                $current = $current->modify('+1 day');
            }
            return $businessDays;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function computeRiskScore(
        float $progressManual,
        ?float $progressHours,
        ?float $progressTasks,
        float $plannedHours,
        float $realHours,
        int $overdueTasks,
        int $totalTasks,
        int $openBlockers,
        int $criticalBlockers,
        int $inactiveDays
    ): float {
        $score = 0.0;

        // Overconsumption of hours (up to 25 pts)
        if ($plannedHours > 0 && $realHours > $plannedHours * 1.1) {
            $overageRatio = ($realHours - $plannedHours) / $plannedHours;
            $score += min(25.0, $overageRatio * 50);
        }

        // Gap between manual progress and tasks progress (up to 20 pts)
        if ($progressTasks !== null && $progressManual > 0) {
            $gap = abs($progressManual - $progressTasks);
            if ($gap > 20) {
                $score += min(20.0, $gap * 0.4);
            }
        }

        // Overdue tasks ratio (up to 25 pts)
        if ($totalTasks > 0 && $overdueTasks > 0) {
            $score += min(25.0, ($overdueTasks / $totalTasks) * 50);
        }

        // Open blockers (up to 20 pts)
        $score += min(15.0, $openBlockers * 3);
        $score += min(5.0, $criticalBlockers * 5);

        // Inactivity (up to 10 pts)
        if ($inactiveDays >= 7) {
            $score += min(10.0, ($inactiveDays - 6) * 2);
        }

        return min(100.0, round($score, 2));
    }

    private function persistSnapshot(int $projectId, array $snapshot): void
    {
        $this->db->execute(
            'INSERT INTO project_pmo_snapshots
                (project_id, snapshot_date, progress_manual, progress_hours, progress_tasks,
                 real_hours, planned_hours, total_tasks, done_tasks, overdue_tasks,
                 open_blockers, critical_blockers, risk_score, inactive_business_days, calculated_at)
             VALUES
                (:p, :d, :pm, :ph, :pt, :rh, :plh, :tt, :dt, :ot, :ob, :cb, :rs, :ibd, NOW())
             ON DUPLICATE KEY UPDATE
                progress_manual = VALUES(progress_manual),
                progress_hours = VALUES(progress_hours),
                progress_tasks = VALUES(progress_tasks),
                real_hours = VALUES(real_hours),
                planned_hours = VALUES(planned_hours),
                total_tasks = VALUES(total_tasks),
                done_tasks = VALUES(done_tasks),
                overdue_tasks = VALUES(overdue_tasks),
                open_blockers = VALUES(open_blockers),
                critical_blockers = VALUES(critical_blockers),
                risk_score = VALUES(risk_score),
                inactive_business_days = VALUES(inactive_business_days),
                calculated_at = NOW()',
            [
                ':p' => $projectId,
                ':d' => date('Y-m-d'),
                ':pm' => $snapshot['progress_manual'],
                ':ph' => $snapshot['progress_hours'],
                ':pt' => $snapshot['progress_tasks'],
                ':rh' => $snapshot['real_hours'],
                ':plh' => $snapshot['planned_hours'],
                ':tt' => $snapshot['total_tasks'],
                ':dt' => $snapshot['done_tasks'],
                ':ot' => $snapshot['overdue_tasks'],
                ':ob' => $snapshot['open_blockers'],
                ':cb' => $snapshot['critical_blockers'],
                ':rs' => $snapshot['risk_score'],
                ':ibd' => $snapshot['inactive_business_days'],
            ]
        );
    }

    private function generateAlerts(int $projectId, array $snapshot): void
    {
        // Deactivate stale alerts first
        $this->db->execute(
            'UPDATE project_pmo_alerts SET is_active = 0, resolved_at = NOW()
             WHERE project_id = :p AND is_active = 1',
            [':p' => $projectId]
        );

        $alerts = [];

        // Overconsumption
        $plannedHours = (float) ($snapshot['planned_hours'] ?? 0);
        $realHours = (float) ($snapshot['real_hours'] ?? 0);
        if ($plannedHours > 0 && $realHours > $plannedHours * 1.1) {
            $pct = round(($realHours / $plannedHours) * 100, 1);
            $alerts[] = [
                'type' => 'overconsumption',
                'severity' => $pct > 150 ? 'critical' : ($pct > 120 ? 'high' : 'medium'),
                'message' => "Sobreconsumo de horas: {$pct}% del plan consumido.",
                'detail' => "Horas reales: {$realHours} / Planificadas: {$plannedHours}",
            ];
        }

        // Progress inconsistency
        $progressManual = (float) ($snapshot['progress_manual'] ?? 0);
        $progressTasks = $snapshot['progress_tasks'] ?? null;
        if ($progressTasks !== null) {
            $gap = abs($progressManual - (float) $progressTasks);
            if ($gap >= 25) {
                $alerts[] = [
                    'type' => 'progress_inconsistency',
                    'severity' => $gap >= 40 ? 'high' : 'medium',
                    'message' => "Inconsistencia entre avance manual ({$progressManual}%) y avance por tareas ({$progressTasks}%).",
                    'detail' => "Diferencia: {$gap}%. Revisar estado de tareas.",
                ];
            }
        }

        // Critical blockers
        $criticalBlockers = (int) ($snapshot['critical_blockers'] ?? 0);
        if ($criticalBlockers > 0) {
            $alerts[] = [
                'type' => 'critical_blocker',
                'severity' => 'critical',
                'message' => "{$criticalBlockers} bloqueo(s) crítico(s) activo(s).",
                'detail' => 'Revisar sección de bloqueos del proyecto.',
            ];
        }

        // Overdue tasks
        $overdueTasks = (int) ($snapshot['overdue_tasks'] ?? 0);
        if ($overdueTasks > 0) {
            $alerts[] = [
                'type' => 'overdue_tasks',
                'severity' => $overdueTasks >= 5 ? 'high' : 'medium',
                'message' => "{$overdueTasks} tarea(s) vencida(s) sin cerrar.",
                'detail' => 'Tareas con fecha límite pasada y estado pendiente.',
            ];
        }

        // Inactivity
        $inactiveDays = (int) ($snapshot['inactive_business_days'] ?? 0);
        if ($inactiveDays >= 7) {
            $alerts[] = [
                'type' => 'inactivity',
                'severity' => $inactiveDays >= 14 ? 'high' : 'medium',
                'message' => "Sin horas aprobadas hace {$inactiveDays} días hábiles.",
                'detail' => 'No se han registrado ni aprobado horas en este período.',
            ];
        }

        foreach ($alerts as $alert) {
            $this->db->execute(
                'INSERT INTO project_pmo_alerts
                    (project_id, alert_type, severity, message, detail, is_active, created_at, updated_at)
                 VALUES (:p, :t, :s, :m, :d, 1, NOW(), NOW())',
                [
                    ':p' => $projectId,
                    ':t' => $alert['type'],
                    ':s' => $alert['severity'],
                    ':m' => $alert['message'],
                    ':d' => $alert['detail'] ?? null,
                ]
            );
        }
    }

    private function emptySnapshot(int $projectId): array
    {
        return [
            'project_id' => $projectId,
            'snapshot_date' => date('Y-m-d'),
            'progress_manual' => 0,
            'progress_hours' => null,
            'progress_tasks' => null,
            'real_hours' => 0,
            'planned_hours' => 0,
            'total_tasks' => 0,
            'done_tasks' => 0,
            'overdue_tasks' => 0,
            'open_blockers' => 0,
            'critical_blockers' => 0,
            'risk_score' => 0,
            'inactive_business_days' => 0,
            'calculated_at' => date('Y-m-d H:i:s'),
        ];
    }
}
