<?php

declare(strict_types=1);

class PmoEngineService
{
    private const ACTIVE_STOPPER_STATUSES = ['abierto', 'en_gestion', 'escalado'];
    private const COMPLETED_TASK_STATUSES = ['done', 'completed'];

    public function __construct(private Database $db)
    {
    }

    public function calculateProjectProgress(int $projectId): array
    {
        $progressHours = $this->calculateProgressByHours($projectId);
        $progressTasks = $this->calculateProgressByTasks($projectId);
        $riskScore = $this->calculateRiskScore($projectId);
        $alerts = $this->generateAlerts($projectId);

        return [
            'project_id' => $projectId,
            'progress_hours' => $progressHours,
            'progress_tasks' => $progressTasks,
            'risk_score' => $riskScore,
            'alerts' => $alerts,
            'calculated_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function calculateProgressByHours(int $projectId): array
    {
        $project = $this->db->fetchOne(
            'SELECT planned_hours, progress FROM projects WHERE id = :id LIMIT 1',
            [':id' => $projectId]
        );

        $plannedHours = (float) ($project['planned_hours'] ?? 0);
        $manualProgress = (float) ($project['progress'] ?? 0);

        $hoursRow = $this->db->fetchOne(
            'SELECT
                COALESCE(SUM(CASE WHEN status = "approved" THEN hours ELSE 0 END), 0) AS approved_hours,
                COALESCE(SUM(hours), 0) AS total_hours
             FROM timesheets
             WHERE project_id = :project_id',
            [':project_id' => $projectId]
        );

        $approvedHours = (float) ($hoursRow['approved_hours'] ?? 0);
        $totalHours = (float) ($hoursRow['total_hours'] ?? 0);
        $progressPercent = $plannedHours > 0 ? round(($approvedHours / $plannedHours) * 100, 2) : 0.0;

        return [
            'planned_hours' => $plannedHours,
            'approved_hours' => $approvedHours,
            'total_hours' => $totalHours,
            'progress_percent' => min(100.0, $progressPercent),
            'manual_progress' => $manualProgress,
        ];
    }

    public function calculateProgressByTasks(int $projectId): array
    {
        $row = $this->db->fetchOne(
            'SELECT
                COUNT(*) AS total_tasks,
                SUM(CASE WHEN status IN ("done", "completed") THEN 1 ELSE 0 END) AS done_tasks
             FROM tasks
             WHERE project_id = :project_id',
            [':project_id' => $projectId]
        );

        $total = (int) ($row['total_tasks'] ?? 0);
        $done = (int) ($row['done_tasks'] ?? 0);
        $progressPercent = $total > 0 ? round(($done / $total) * 100, 2) : 0.0;

        return [
            'total_tasks' => $total,
            'done_tasks' => $done,
            'progress_percent' => $progressPercent,
        ];
    }

    public function calculateRiskScore(int $projectId): array
    {
        $score = 0;
        $factors = [];

        if ($this->db->tableExists('project_stoppers')) {
            $stopperRow = $this->db->fetchOne(
                'SELECT
                    COUNT(*) AS open_count,
                    SUM(CASE WHEN impact_level = "critico" THEN 1 ELSE 0 END) AS critical_count,
                    SUM(CASE WHEN impact_level = "alto" THEN 1 ELSE 0 END) AS high_count,
                    AVG(DATEDIFF(CURDATE(), detected_at)) AS avg_days_open
                 FROM project_stoppers
                 WHERE project_id = :project_id
                   AND status IN ("abierto", "en_gestion", "escalado")',
                [':project_id' => $projectId]
            );

            $openBlockers = (int) ($stopperRow['open_count'] ?? 0);
            $criticalBlockers = (int) ($stopperRow['critical_count'] ?? 0);
            $highBlockers = (int) ($stopperRow['high_count'] ?? 0);
            $avgDaysOpen = (float) ($stopperRow['avg_days_open'] ?? 0);

            $score += $criticalBlockers * 25;
            $score += $highBlockers * 15;
            $score += max(0, $openBlockers - $criticalBlockers - $highBlockers) * 5;

            if ($avgDaysOpen > 14) {
                $score += 10;
                $factors[] = 'Bloqueos abiertos por más de 14 días promedio';
            }

            if ($criticalBlockers > 0) {
                $factors[] = "$criticalBlockers bloqueo(s) crítico(s) activo(s)";
            }
        }

        $blockerMentions = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM timesheets
             WHERE project_id = :project_id AND had_blocker = 1
               AND date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)',
            [':project_id' => $projectId]
        );
        $recentBlockerMentions = (int) ($blockerMentions['total'] ?? 0);
        if ($recentBlockerMentions > 5) {
            $score += 10;
            $factors[] = "$recentBlockerMentions menciones de bloqueo en los últimos 14 días";
        }

        $overdueRow = $this->db->fetchOne(
            'SELECT COUNT(*) AS overdue_tasks
             FROM tasks
             WHERE project_id = :project_id
               AND due_date IS NOT NULL
               AND due_date < CURDATE()
               AND status NOT IN ("done", "completed")',
            [':project_id' => $projectId]
        );
        $overdueTasks = (int) ($overdueRow['overdue_tasks'] ?? 0);
        if ($overdueTasks > 0) {
            $score += min(20, $overdueTasks * 5);
            $factors[] = "$overdueTasks tarea(s) vencida(s)";
        }

        $score = min(100, $score);

        return [
            'score' => $score,
            'level' => $this->riskLevel($score),
            'factors' => $factors,
        ];
    }

    public function generateAlerts(int $projectId): array
    {
        $alerts = [];

        $progressHours = $this->calculateProgressByHours($projectId);
        if ($progressHours['planned_hours'] > 0 && $progressHours['progress_percent'] > 100) {
            $alerts[] = [
                'type' => 'overconsumption',
                'severity' => 'high',
                'message' => sprintf(
                    'Sobreconsumo de horas: %.1f%% del plan (%.1fh aprobadas / %.1fh planificadas)',
                    $progressHours['progress_percent'],
                    $progressHours['approved_hours'],
                    $progressHours['planned_hours']
                ),
            ];
        }

        $progressTasks = $this->calculateProgressByTasks($projectId);
        $manualProgress = $progressHours['manual_progress'];
        $taskProgress = $progressTasks['progress_percent'];
        $hoursProgress = $progressHours['progress_percent'];

        if ($manualProgress > 0 && abs($manualProgress - $taskProgress) > 20 && $progressTasks['total_tasks'] > 0) {
            $alerts[] = [
                'type' => 'progress_inconsistency',
                'severity' => 'medium',
                'message' => sprintf(
                    'Inconsistencia: avance manual %.0f%% vs avance por tareas %.0f%%',
                    $manualProgress,
                    $taskProgress
                ),
            ];
        }

        if ($manualProgress > 0 && $progressHours['planned_hours'] > 0 && abs($manualProgress - $hoursProgress) > 25) {
            $alerts[] = [
                'type' => 'hours_inconsistency',
                'severity' => 'medium',
                'message' => sprintf(
                    'Inconsistencia: avance manual %.0f%% vs avance por horas %.0f%%',
                    $manualProgress,
                    $hoursProgress
                ),
            ];
        }

        if ($this->db->tableExists('project_stoppers')) {
            $criticalRow = $this->db->fetchOne(
                'SELECT COUNT(*) AS total FROM project_stoppers
                 WHERE project_id = :project_id AND impact_level = "critico"
                   AND status IN ("abierto", "en_gestion", "escalado")',
                [':project_id' => $projectId]
            );
            $criticalCount = (int) ($criticalRow['total'] ?? 0);
            if ($criticalCount > 0) {
                $alerts[] = [
                    'type' => 'critical_blockers',
                    'severity' => 'critical',
                    'message' => "$criticalCount bloqueo(s) crítico(s) activo(s)",
                ];
            }
        }

        $lastActivityRow = $this->db->fetchOne(
            'SELECT MAX(date) AS last_date FROM timesheets WHERE project_id = :project_id',
            [':project_id' => $projectId]
        );
        $lastDate = (string) ($lastActivityRow['last_date'] ?? '');
        if ($lastDate !== '') {
            $daysSince = $this->businessDaysSince($lastDate);
            if ($daysSince >= 7) {
                $alerts[] = [
                    'type' => 'inactivity',
                    'severity' => 'high',
                    'message' => "Sin actividad registrada por $daysSince días hábiles",
                ];
            }
        }

        $overdueRow = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM tasks
             WHERE project_id = :project_id
               AND due_date IS NOT NULL AND due_date < CURDATE()
               AND status NOT IN ("done", "completed")',
            [':project_id' => $projectId]
        );
        $overdueTasks = (int) ($overdueRow['total'] ?? 0);
        if ($overdueTasks > 0) {
            $alerts[] = [
                'type' => 'overdue_tasks',
                'severity' => $overdueTasks >= 5 ? 'high' : 'medium',
                'message' => "$overdueTasks tarea(s) vencida(s) sin completar",
            ];
        }

        return $alerts;
    }

    public function saveSnapshot(int $projectId): void
    {
        $this->ensureSnapshotTable();

        $data = $this->calculateProjectProgress($projectId);
        $progressHours = $data['progress_hours'];
        $progressTasks = $data['progress_tasks'];
        $risk = $data['risk_score'];

        $this->db->insert(
            'INSERT INTO pmo_project_snapshots (
                project_id, progress_hours, progress_tasks, risk_score, risk_level,
                approved_hours, planned_hours, total_tasks, done_tasks, manual_progress,
                snapshot_date, created_at
            ) VALUES (
                :project_id, :progress_hours, :progress_tasks, :risk_score, :risk_level,
                :approved_hours, :planned_hours, :total_tasks, :done_tasks, :manual_progress,
                CURDATE(), NOW()
            )',
            [
                ':project_id' => $projectId,
                ':progress_hours' => $progressHours['progress_percent'],
                ':progress_tasks' => $progressTasks['progress_percent'],
                ':risk_score' => $risk['score'],
                ':risk_level' => $risk['level'],
                ':approved_hours' => $progressHours['approved_hours'],
                ':planned_hours' => $progressHours['planned_hours'],
                ':total_tasks' => $progressTasks['total_tasks'],
                ':done_tasks' => $progressTasks['done_tasks'],
                ':manual_progress' => $progressHours['manual_progress'],
            ]
        );
    }

    public function saveAlerts(int $projectId): void
    {
        $this->ensureAlertsTable();

        $alerts = $this->generateAlerts($projectId);

        $this->db->execute(
            'UPDATE pmo_project_alerts SET resolved_at = NOW()
             WHERE project_id = :project_id AND resolved_at IS NULL',
            [':project_id' => $projectId]
        );

        foreach ($alerts as $alert) {
            $this->db->insert(
                'INSERT INTO pmo_project_alerts (
                    project_id, alert_type, severity, message, created_at
                ) VALUES (
                    :project_id, :alert_type, :severity, :message, NOW()
                )',
                [
                    ':project_id' => $projectId,
                    ':alert_type' => $alert['type'],
                    ':severity' => $alert['severity'],
                    ':message' => $alert['message'],
                ]
            );
        }
    }

    public function runForAllActiveProjects(): array
    {
        $projects = $this->db->fetchAll(
            'SELECT id FROM projects WHERE active = 1'
        );

        $results = [];
        foreach ($projects as $project) {
            $projectId = (int) ($project['id'] ?? 0);
            if ($projectId <= 0) continue;

            try {
                $this->saveSnapshot($projectId);
                $this->saveAlerts($projectId);
                $results[] = ['project_id' => $projectId, 'status' => 'ok'];
            } catch (\Throwable $e) {
                error_log("PMO Engine error for project $projectId: " . $e->getMessage());
                $results[] = ['project_id' => $projectId, 'status' => 'error', 'message' => $e->getMessage()];
            }
        }

        return $results;
    }

    public function getProjectPmoData(int $projectId): array
    {
        $current = $this->calculateProjectProgress($projectId);

        $snapshots = [];
        if ($this->db->tableExists('pmo_project_snapshots')) {
            $snapshots = $this->db->fetchAll(
                'SELECT * FROM pmo_project_snapshots
                 WHERE project_id = :project_id
                 ORDER BY snapshot_date DESC
                 LIMIT 28',
                [':project_id' => $projectId]
            );
        }

        $activeAlerts = [];
        if ($this->db->tableExists('pmo_project_alerts')) {
            $activeAlerts = $this->db->fetchAll(
                'SELECT * FROM pmo_project_alerts
                 WHERE project_id = :project_id AND resolved_at IS NULL
                 ORDER BY FIELD(severity, "critical", "high", "medium", "low") ASC, created_at DESC',
                [':project_id' => $projectId]
            );
        }

        $hoursHistory = [];
        if ($this->db->tableExists('timesheets')) {
            $hoursHistory = $this->db->fetchAll(
                'SELECT
                    YEARWEEK(date, 1) AS yw,
                    MIN(date) AS week_start,
                    SUM(hours) AS total_hours,
                    SUM(CASE WHEN status = "approved" THEN hours ELSE 0 END) AS approved_hours
                 FROM timesheets
                 WHERE project_id = :project_id
                   AND date >= DATE_SUB(CURDATE(), INTERVAL 28 DAY)
                 GROUP BY YEARWEEK(date, 1)
                 ORDER BY yw ASC',
                [':project_id' => $projectId]
            );
        }

        $activeBlockers = [];
        if ($this->db->tableExists('project_stoppers')) {
            $activeBlockers = $this->db->fetchAll(
                'SELECT s.*, u.name AS responsible_name
                 FROM project_stoppers s
                 LEFT JOIN users u ON u.id = s.responsible_id
                 WHERE s.project_id = :project_id
                   AND s.status IN ("abierto", "en_gestion", "escalado")
                 ORDER BY FIELD(s.impact_level, "critico", "alto", "medio", "bajo"), s.detected_at ASC',
                [':project_id' => $projectId]
            );
        }

        return [
            'current' => $current,
            'snapshots' => $snapshots,
            'active_alerts' => $activeAlerts,
            'hours_history' => $hoursHistory,
            'active_blockers' => $activeBlockers,
        ];
    }

    private function riskLevel(int $score): string
    {
        if ($score >= 70) return 'critical';
        if ($score >= 40) return 'high';
        if ($score >= 20) return 'medium';
        return 'low';
    }

    private function businessDaysSince(string $dateStr): int
    {
        try {
            $date = new \DateTimeImmutable($dateStr);
            $today = new \DateTimeImmutable('today');
            $count = 0;
            $current = $date->modify('+1 day');
            while ($current <= $today) {
                $dayOfWeek = (int) $current->format('N');
                if ($dayOfWeek <= 5) {
                    $count++;
                }
                $current = $current->modify('+1 day');
            }
            return $count;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function ensureSnapshotTable(): void
    {
        if ($this->db->tableExists('pmo_project_snapshots')) {
            return;
        }

        $this->db->execute('
            CREATE TABLE pmo_project_snapshots (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                progress_hours DECIMAL(6,2) NOT NULL DEFAULT 0,
                progress_tasks DECIMAL(6,2) NOT NULL DEFAULT 0,
                risk_score INT NOT NULL DEFAULT 0,
                risk_level VARCHAR(20) NOT NULL DEFAULT "low",
                approved_hours DECIMAL(10,2) NOT NULL DEFAULT 0,
                planned_hours DECIMAL(10,2) NOT NULL DEFAULT 0,
                total_tasks INT NOT NULL DEFAULT 0,
                done_tasks INT NOT NULL DEFAULT 0,
                manual_progress DECIMAL(6,2) NOT NULL DEFAULT 0,
                snapshot_date DATE NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_project_date (project_id, snapshot_date),
                CONSTRAINT fk_pmo_snapshots_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');
    }

    private function ensureAlertsTable(): void
    {
        if ($this->db->tableExists('pmo_project_alerts')) {
            return;
        }

        $this->db->execute('
            CREATE TABLE pmo_project_alerts (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                alert_type VARCHAR(60) NOT NULL,
                severity VARCHAR(20) NOT NULL DEFAULT "medium",
                message TEXT NOT NULL,
                resolved_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_project_active (project_id, resolved_at),
                CONSTRAINT fk_pmo_alerts_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');
    }
}
