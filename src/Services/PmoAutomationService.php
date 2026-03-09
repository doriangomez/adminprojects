<?php

declare(strict_types=1);

class PmoAutomationService
{
    public function __construct(private Database $db)
    {
    }

    public function ensureTodaySnapshotForProject(int $projectId): array
    {
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        $existing = $this->latestSnapshotForProject($projectId);
        if ($existing && (string) ($existing['snapshot_date'] ?? '') === $today) {
            return $existing;
        }

        return $this->captureSnapshotForProject($projectId, new DateTimeImmutable('today'));
    }

    public function captureSnapshotForProject(int $projectId, ?DateTimeImmutable $snapshotDate = null): array
    {
        if ($projectId <= 0 || !$this->db->tableExists('projects')) {
            return [];
        }

        $date = ($snapshotDate ?? new DateTimeImmutable('today'))->setTime(0, 0);
        $dateStr = $date->format('Y-m-d');
        $project = $this->db->fetchOne(
            'SELECT id, name, progress, planned_hours, start_date
             FROM projects
             WHERE id = :id
             LIMIT 1',
            [':id' => $projectId]
        );
        if (!$project) {
            return [];
        }

        $plannedHours = (float) ($project['planned_hours'] ?? 0);
        $manualProgress = (float) ($project['progress'] ?? 0);
        $approvedHours = $this->approvedHours($projectId, $dateStr);
        $estimatedHours = $this->estimatedHours($projectId, $plannedHours);
        $taskMetrics = $this->taskMetrics($projectId, $dateStr);
        $blockerMetrics = $this->blockerMetrics($projectId, $dateStr);
        $blockerMentions = $this->blockerMentions($projectId, $dateStr);
        $staleBusinessDays = $this->staleBusinessDays($projectId, $project, $date);

        $hoursConsumption = $estimatedHours > 0 ? round(($approvedHours / $estimatedHours) * 100, 2) : null;
        $progressHours = $estimatedHours > 0 ? min(100.0, $hoursConsumption) : null;
        $progressTasks = (int) ($taskMetrics['total_tasks'] ?? 0) > 0
            ? round((((int) ($taskMetrics['done_tasks'] ?? 0)) / ((int) ($taskMetrics['total_tasks'] ?? 1))) * 100, 2)
            : null;

        $riskScore = $this->riskScore([
            'open_blockers' => (int) ($blockerMetrics['open_blockers'] ?? 0),
            'critical_blockers' => (int) ($blockerMetrics['critical_blockers'] ?? 0),
            'aged_blockers' => (int) ($blockerMetrics['aged_blockers'] ?? 0),
            'blocker_mentions' => $blockerMentions,
            'overdue_tasks' => (int) ($taskMetrics['overdue_tasks'] ?? 0),
            'hours_consumption_percent' => $hoursConsumption,
            'stale_business_days' => $staleBusinessDays,
        ]);

        $snapshot = [
            'project_id' => $projectId,
            'snapshot_date' => $dateStr,
            'project_name' => (string) ($project['name'] ?? 'Proyecto'),
            'progress_manual' => round($manualProgress, 2),
            'progress_hours' => $progressHours,
            'progress_tasks' => $progressTasks,
            'risk_score' => $riskScore,
            'planned_hours' => $estimatedHours,
            'approved_hours' => round($approvedHours, 2),
            'hours_consumption_percent' => $hoursConsumption,
            'total_tasks' => (int) ($taskMetrics['total_tasks'] ?? 0),
            'done_tasks' => (int) ($taskMetrics['done_tasks'] ?? 0),
            'overdue_tasks' => (int) ($taskMetrics['overdue_tasks'] ?? 0),
            'open_blockers' => (int) ($blockerMetrics['open_blockers'] ?? 0),
            'critical_blockers' => (int) ($blockerMetrics['critical_blockers'] ?? 0),
            'aged_blockers' => (int) ($blockerMetrics['aged_blockers'] ?? 0),
            'blocker_mentions' => $blockerMentions,
            'stale_business_days' => $staleBusinessDays,
        ];

        $snapshotId = $this->persistSnapshot($snapshot);
        if ($snapshotId > 0) {
            $snapshot['id'] = $snapshotId;
            $this->syncAlerts($snapshotId, $snapshot);
        }

        return $snapshot;
    }

    public function latestSnapshotForProject(int $projectId): ?array
    {
        if (!$this->db->tableExists('project_pmo_snapshots')) {
            return null;
        }

        $row = $this->db->fetchOne(
            'SELECT *
             FROM project_pmo_snapshots
             WHERE project_id = :project
             ORDER BY snapshot_date DESC, id DESC
             LIMIT 1',
            [':project' => $projectId]
        );

        return $row ?: null;
    }

    public function latestAlertsForProject(int $projectId, int $limit = 20): array
    {
        if (!$this->db->tableExists('project_pmo_alerts')) {
            return [];
        }

        $safeLimit = max(1, (int) $limit);
        return $this->db->fetchAll(
            'SELECT *
             FROM project_pmo_alerts
             WHERE project_id = :project
               AND status = "open"
             ORDER BY FIELD(severity, "red", "yellow", "green"), created_at DESC, id DESC
             LIMIT ' . $safeLimit,
            [
                ':project' => $projectId,
            ]
        );
    }

    public function hoursTrendForProject(int $projectId, int $weeks = 4): array
    {
        if (!$this->db->tableExists('timesheets')) {
            return [];
        }

        $usesProjectColumn = $this->db->columnExists('timesheets', 'project_id');
        $canResolveFromTasks = !$usesProjectColumn
            && $this->db->tableExists('tasks')
            && $this->db->columnExists('timesheets', 'task_id')
            && $this->db->columnExists('tasks', 'project_id');
        if (!$usesProjectColumn && !$canResolveFromTasks) {
            return [];
        }

        $safeWeeks = max(1, (int) $weeks);
        $safeLimit = max(1, (int) $weeks);
        $startDate = (new DateTimeImmutable('today'))->modify('-' . ($safeWeeks * 7) . ' days')->format('Y-m-d');
        $projectFilter = $usesProjectColumn ? 'ts.project_id = :project' : 't.project_id = :project';
        $taskJoin = $usesProjectColumn ? '' : 'JOIN tasks t ON t.id = ts.task_id';
        $rows = $this->db->fetchAll(
            'SELECT DATE_SUB(ts.date, INTERVAL WEEKDAY(ts.date) DAY) AS week_start,
                    COALESCE(SUM(ts.hours), 0) AS approved_hours
             FROM timesheets ts
             ' . $taskJoin . '
             WHERE ' . $projectFilter . '
               AND ts.status = "approved"
               AND ts.date >= :start_date
             GROUP BY DATE_SUB(ts.date, INTERVAL WEEKDAY(ts.date) DAY)
             ORDER BY week_start DESC
             LIMIT ' . $safeLimit,
            [
                ':project' => $projectId,
                ':start_date' => $startDate,
            ]
        );

        $rows = array_reverse($rows);
        foreach ($rows as &$row) {
            $row['label'] = isset($row['week_start']) ? 'Sem ' . date('W', strtotime((string) $row['week_start'])) : 'Semana';
            $row['approved_hours'] = round((float) ($row['approved_hours'] ?? 0), 2);
        }
        unset($row);

        return $rows;
    }

    public function activeBlockersForProject(int $projectId, int $limit = 8): array
    {
        if (!$this->db->tableExists('project_stoppers')) {
            return [];
        }

        $hasDetectedAt = $this->db->columnExists('project_stoppers', 'detected_at');
        $dateSelect = $hasDetectedAt ? 'detected_at' : 'created_at AS detected_at';
        $dateOrder = $hasDetectedAt ? 'detected_at' : 'created_at';

        $safeLimit = max(1, (int) $limit);
        return $this->db->fetchAll(
            'SELECT id, title, description, impact_level, status, ' . $dateSelect . '
             FROM project_stoppers
             WHERE project_id = :project
               AND status IN ("abierto", "en_gestion", "escalado", "resuelto")
             ORDER BY FIELD(impact_level, "critico", "alto", "medio", "bajo"), ' . $dateOrder . ' ASC, id ASC
             LIMIT ' . $safeLimit,
            [
                ':project' => $projectId,
            ]
        );
    }

    public function runDailyForAllProjects(): int
    {
        if (!$this->db->tableExists('projects')) {
            return 0;
        }

        $where = $this->db->columnExists('projects', 'active') ? 'WHERE active = 1' : '';
        $projectIds = $this->db->fetchAll(
            'SELECT id
             FROM projects
             ' . $where . '
             ORDER BY id ASC'
        );

        $total = 0;
        foreach ($projectIds as $projectRow) {
            $projectId = (int) ($projectRow['id'] ?? 0);
            if ($projectId <= 0) {
                continue;
            }
            $this->captureSnapshotForProject($projectId, new DateTimeImmutable('today'));
            $total++;
        }

        return $total;
    }

    public function runCriticalBlockersPulse(): int
    {
        if (!$this->db->tableExists('project_stoppers')) {
            return 0;
        }

        $projectRows = $this->db->fetchAll(
            'SELECT DISTINCT project_id
             FROM project_stoppers
             WHERE status IN ("abierto", "en_gestion", "escalado", "resuelto")
               AND impact_level = "critico"'
        );

        $total = 0;
        foreach ($projectRows as $row) {
            $projectId = (int) ($row['project_id'] ?? 0);
            if ($projectId <= 0) {
                continue;
            }
            $this->captureSnapshotForProject($projectId, new DateTimeImmutable('today'));
            $total++;
        }

        return $total;
    }

    private function approvedHours(int $projectId, string $snapshotDate): float
    {
        if (!$this->db->tableExists('timesheets')) {
            return 0.0;
        }

        $usesProjectColumn = $this->db->columnExists('timesheets', 'project_id');
        $canResolveFromTasks = !$usesProjectColumn
            && $this->db->tableExists('tasks')
            && $this->db->columnExists('timesheets', 'task_id')
            && $this->db->columnExists('tasks', 'project_id');
        if (!$usesProjectColumn && !$canResolveFromTasks) {
            return 0.0;
        }

        $projectFilter = $usesProjectColumn ? 'ts.project_id = :project' : 't.project_id = :project';
        $taskJoin = $usesProjectColumn ? '' : 'JOIN tasks t ON t.id = ts.task_id';
        $row = $this->db->fetchOne(
            'SELECT COALESCE(SUM(ts.hours), 0) AS total
             FROM timesheets ts
             ' . $taskJoin . '
             WHERE ' . $projectFilter . '
               AND ts.date <= :snapshot_date',
            [
                ':project' => $projectId,
                ':snapshot_date' => $snapshotDate,
            ]
        );

        return (float) ($row['total'] ?? 0);
    }

    private function estimatedHours(int $projectId, float $fallback): float
    {
        if (!$this->db->tableExists('tasks') || !$this->db->columnExists('tasks', 'estimated_hours')) {
            return max(0.0, $fallback);
        }

        $row = $this->db->fetchOne(
            'SELECT COALESCE(SUM(COALESCE(estimated_hours, 0)), 0) AS total
             FROM tasks
             WHERE project_id = :project',
            [':project' => $projectId]
        );
        $estimated = (float) ($row['total'] ?? 0);
        if ($estimated > 0) {
            return $estimated;
        }

        return max(0.0, $fallback);
    }

    private function taskMetrics(int $projectId, string $snapshotDate): array
    {
        if (!$this->db->tableExists('tasks')) {
            return ['total_tasks' => 0, 'done_tasks' => 0, 'overdue_tasks' => 0];
        }

        $row = $this->db->fetchOne(
            'SELECT
                COUNT(*) AS total_tasks,
                SUM(CASE WHEN LOWER(TRIM(COALESCE(status, ""))) IN ("done", "completed", "completada") THEN 1 ELSE 0 END) AS done_tasks,
                SUM(CASE WHEN due_date IS NOT NULL AND due_date < :snapshot_date AND LOWER(TRIM(COALESCE(status, ""))) NOT IN ("done", "completed", "completada") THEN 1 ELSE 0 END) AS overdue_tasks
             FROM tasks
             WHERE project_id = :project',
            [
                ':project' => $projectId,
                ':snapshot_date' => $snapshotDate,
            ]
        ) ?? [];

        return [
            'total_tasks' => (int) ($row['total_tasks'] ?? 0),
            'done_tasks' => (int) ($row['done_tasks'] ?? 0),
            'overdue_tasks' => (int) ($row['overdue_tasks'] ?? 0),
        ];
    }

    private function blockerMetrics(int $projectId, string $snapshotDate): array
    {
        if (!$this->db->tableExists('project_stoppers')) {
            return ['open_blockers' => 0, 'critical_blockers' => 0, 'aged_blockers' => 0];
        }

        $dateColumn = $this->db->columnExists('project_stoppers', 'detected_at')
            ? 'COALESCE(detected_at, DATE(created_at))'
            : 'DATE(created_at)';

        $row = $this->db->fetchOne(
            'SELECT
                SUM(CASE WHEN status IN ("abierto", "en_gestion", "escalado", "resuelto") THEN 1 ELSE 0 END) AS open_blockers,
                SUM(CASE WHEN status IN ("abierto", "en_gestion", "escalado", "resuelto") AND impact_level = "critico" THEN 1 ELSE 0 END) AS critical_blockers,
                SUM(CASE WHEN status IN ("abierto", "en_gestion", "escalado", "resuelto") AND DATEDIFF(:snapshot_date, ' . $dateColumn . ') > 7 THEN 1 ELSE 0 END) AS aged_blockers
             FROM project_stoppers
             WHERE project_id = :project',
            [
                ':project' => $projectId,
                ':snapshot_date' => $snapshotDate,
            ]
        ) ?? [];

        return [
            'open_blockers' => (int) ($row['open_blockers'] ?? 0),
            'critical_blockers' => (int) ($row['critical_blockers'] ?? 0),
            'aged_blockers' => (int) ($row['aged_blockers'] ?? 0),
        ];
    }

    private function blockerMentions(int $projectId, string $snapshotDate): int
    {
        if (
            !$this->db->tableExists('timesheets')
            || !$this->db->columnExists('timesheets', 'had_blocker')
        ) {
            return 0;
        }

        $usesProjectColumn = $this->db->columnExists('timesheets', 'project_id');
        $canResolveFromTasks = !$usesProjectColumn
            && $this->db->tableExists('tasks')
            && $this->db->columnExists('timesheets', 'task_id')
            && $this->db->columnExists('tasks', 'project_id');
        if (!$usesProjectColumn && !$canResolveFromTasks) {
            return 0;
        }

        $projectFilter = $usesProjectColumn ? 'ts.project_id = :project' : 't.project_id = :project';
        $taskJoin = $usesProjectColumn ? '' : 'JOIN tasks t ON t.id = ts.task_id';
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total
             FROM timesheets ts
             ' . $taskJoin . '
             WHERE ' . $projectFilter . '
               AND ts.had_blocker = 1
               AND ts.date BETWEEN DATE_SUB(:snapshot_date, INTERVAL 14 DAY) AND :snapshot_date',
            [
                ':project' => $projectId,
                ':snapshot_date' => $snapshotDate,
            ]
        );

        return (int) ($row['total'] ?? 0);
    }

    private function staleBusinessDays(int $projectId, array $project, DateTimeImmutable $snapshotDate): int
    {
        if (!$this->db->tableExists('timesheets')) {
            return 0;
        }

        $usesProjectColumn = $this->db->columnExists('timesheets', 'project_id');
        $canResolveFromTasks = !$usesProjectColumn
            && $this->db->tableExists('tasks')
            && $this->db->columnExists('timesheets', 'task_id')
            && $this->db->columnExists('tasks', 'project_id');
        if (!$usesProjectColumn && !$canResolveFromTasks) {
            return 999;
        }

        $projectFilter = $usesProjectColumn ? 'ts.project_id = :project' : 't.project_id = :project';
        $taskJoin = $usesProjectColumn ? '' : 'JOIN tasks t ON t.id = ts.task_id';
        $last = $this->db->fetchOne(
            'SELECT MAX(ts.date) AS last_date
             FROM timesheets ts
             ' . $taskJoin . '
             WHERE ' . $projectFilter,
            [':project' => $projectId]
        );
        $lastDate = trim((string) ($last['last_date'] ?? ''));
        if ($lastDate === '') {
            $lastDate = trim((string) ($project['start_date'] ?? ''));
        }
        if ($lastDate === '') {
            return 999;
        }

        try {
            $from = new DateTimeImmutable($lastDate);
        } catch (Throwable $e) {
            return 999;
        }

        return $this->businessDaysBetween($from, $snapshotDate);
    }

    private function businessDaysBetween(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        if ($from > $to) {
            return 0;
        }

        $days = 0;
        $cursor = $from->modify('+1 day');
        while ($cursor <= $to) {
            if ((int) $cursor->format('N') <= 5) {
                $days++;
            }
            $cursor = $cursor->modify('+1 day');
        }

        return $days;
    }

    private function riskScore(array $metrics): int
    {
        $score = 0;
        $score += max(0, (int) ($metrics['open_blockers'] ?? 0)) * 10;
        $score += max(0, (int) ($metrics['aged_blockers'] ?? 0)) * 8;
        $score += max(0, (int) ($metrics['critical_blockers'] ?? 0)) * 20;
        $score += max(0, (int) ($metrics['blocker_mentions'] ?? 0)) * 2;
        $score += max(0, (int) ($metrics['overdue_tasks'] ?? 0)) * 8;
        $consumption = (float) ($metrics['hours_consumption_percent'] ?? 0);
        if ($consumption > 100) {
            $score += 30;
        } elseif ($consumption > 80) {
            $score += 15;
        }
        if ((int) ($metrics['stale_business_days'] ?? 0) >= 7) {
            $score += 20;
        }

        return max(0, min(100, $score));
    }

    private function persistSnapshot(array $snapshot): int
    {
        if (!$this->db->tableExists('project_pmo_snapshots')) {
            return 0;
        }

        $this->db->execute(
            'INSERT INTO project_pmo_snapshots (
                project_id, snapshot_date, progress_manual, progress_hours, progress_tasks, risk_score,
                planned_hours, approved_hours, total_tasks, done_tasks, overdue_tasks,
                open_blockers, critical_blockers, aged_blockers, blocker_mentions, stale_business_days,
                payload_json, generated_at
            ) VALUES (
                :project_id, :snapshot_date, :progress_manual, :progress_hours, :progress_tasks, :risk_score,
                :planned_hours, :approved_hours, :total_tasks, :done_tasks, :overdue_tasks,
                :open_blockers, :critical_blockers, :aged_blockers, :blocker_mentions, :stale_business_days,
                :payload_json, NOW()
            )
            ON DUPLICATE KEY UPDATE
                progress_manual = VALUES(progress_manual),
                progress_hours = VALUES(progress_hours),
                progress_tasks = VALUES(progress_tasks),
                risk_score = VALUES(risk_score),
                planned_hours = VALUES(planned_hours),
                approved_hours = VALUES(approved_hours),
                total_tasks = VALUES(total_tasks),
                done_tasks = VALUES(done_tasks),
                overdue_tasks = VALUES(overdue_tasks),
                open_blockers = VALUES(open_blockers),
                critical_blockers = VALUES(critical_blockers),
                aged_blockers = VALUES(aged_blockers),
                blocker_mentions = VALUES(blocker_mentions),
                stale_business_days = VALUES(stale_business_days),
                payload_json = VALUES(payload_json),
                generated_at = NOW()',
            [
                ':project_id' => $snapshot['project_id'],
                ':snapshot_date' => $snapshot['snapshot_date'],
                ':progress_manual' => $snapshot['progress_manual'],
                ':progress_hours' => $snapshot['progress_hours'],
                ':progress_tasks' => $snapshot['progress_tasks'],
                ':risk_score' => $snapshot['risk_score'],
                ':planned_hours' => $snapshot['planned_hours'],
                ':approved_hours' => $snapshot['approved_hours'],
                ':total_tasks' => $snapshot['total_tasks'],
                ':done_tasks' => $snapshot['done_tasks'],
                ':overdue_tasks' => $snapshot['overdue_tasks'],
                ':open_blockers' => $snapshot['open_blockers'],
                ':critical_blockers' => $snapshot['critical_blockers'],
                ':aged_blockers' => $snapshot['aged_blockers'],
                ':blocker_mentions' => $snapshot['blocker_mentions'],
                ':stale_business_days' => $snapshot['stale_business_days'],
                ':payload_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]
        );

        $row = $this->db->fetchOne(
            'SELECT id
             FROM project_pmo_snapshots
             WHERE project_id = :project_id
               AND snapshot_date = :snapshot_date
             LIMIT 1',
            [
                ':project_id' => (int) $snapshot['project_id'],
                ':snapshot_date' => (string) $snapshot['snapshot_date'],
            ]
        );

        return (int) ($row['id'] ?? 0);
    }

    private function syncAlerts(int $snapshotId, array $snapshot): void
    {
        if (!$this->db->tableExists('project_pmo_alerts')) {
            return;
        }

        $projectId = (int) ($snapshot['project_id'] ?? 0);
        if ($projectId <= 0) {
            return;
        }

        $alerts = [];
        $plannedHours = (float) ($snapshot['planned_hours'] ?? 0);
        $approvedHours = (float) ($snapshot['approved_hours'] ?? 0);
        if ($plannedHours > 0 && $approvedHours > ($plannedHours * 0.8)) {
            $severity = $approvedHours > $plannedHours ? 'red' : 'yellow';
            $consumptionPercent = round(($approvedHours / $plannedHours) * 100, 2);
            $alerts[] = [
                'type' => 'hours_overconsumption',
                'severity' => $severity,
                'title' => 'Sobreconsumo de horas',
                'message' => sprintf('Horas registradas %.2f (%.2f%%) frente a estimadas %.2f.', $approvedHours, $consumptionPercent, $plannedHours),
            ];
        }

        $manual = (float) ($snapshot['progress_manual'] ?? 0);
        $hoursProgress = isset($snapshot['progress_hours']) ? (float) $snapshot['progress_hours'] : null;
        $tasksProgress = isset($snapshot['progress_tasks']) ? (float) $snapshot['progress_tasks'] : null;
        $maxGap = 0.0;
        if ($hoursProgress !== null) {
            $maxGap = max($maxGap, abs($manual - $hoursProgress));
        }
        if ($tasksProgress !== null) {
            $maxGap = max($maxGap, abs($manual - $tasksProgress));
        }
        if ($maxGap >= 20) {
            $alerts[] = [
                'type' => 'progress_inconsistency',
                'severity' => $maxGap >= 35 ? 'red' : 'yellow',
                'title' => 'Inconsistencia de avance',
                'message' => sprintf('Brecha de %.1f puntos entre avance manual y automático.', $maxGap),
            ];
        }

        $criticalBlockers = (int) ($snapshot['critical_blockers'] ?? 0);
        $agedBlockers = (int) ($snapshot['aged_blockers'] ?? 0);
        if ($criticalBlockers > 0 || $agedBlockers > 0) {
            $alerts[] = [
                'type' => 'critical_blockers',
                'severity' => 'red',
                'title' => 'Bloqueos críticos',
                'message' => sprintf('%d críticos y %d bloqueos envejecidos activos.', $criticalBlockers, $agedBlockers),
            ];
        }

        $staleDays = (int) ($snapshot['stale_business_days'] ?? 0);
        if ($staleDays >= 7) {
            $alerts[] = [
                'type' => 'inactivity',
                'severity' => $staleDays >= 12 ? 'red' : 'yellow',
                'title' => 'Inactividad operativa',
                'message' => sprintf('Sin actividad registrada en %d días hábiles.', $staleDays),
            ];
        }

        $overdueTasks = (int) ($snapshot['overdue_tasks'] ?? 0);
        if ($overdueTasks > 0) {
            $alerts[] = [
                'type' => 'overdue_tasks',
                'severity' => $overdueTasks > 5 ? 'red' : 'yellow',
                'title' => 'Tareas vencidas',
                'message' => sprintf('Se detectan %d tareas vencidas.', $overdueTasks),
            ];
        }

        $activeTypes = array_map(static fn (array $alert): string => (string) $alert['type'], $alerts);
        if ($activeTypes === []) {
            $this->db->execute(
                'UPDATE project_pmo_alerts
                 SET status = "resolved",
                     resolved_at = NOW()
                 WHERE project_id = :project
                   AND status = "open"',
                [':project' => $projectId]
            );
        } else {
            $quotedTypes = array_map(static fn (string $type): string => '"' . addslashes($type) . '"', $activeTypes);
            $this->db->execute(
                'UPDATE project_pmo_alerts
                 SET status = "resolved",
                     resolved_at = NOW()
                 WHERE project_id = :project
                   AND status = "open"
                   AND alert_type NOT IN (' . implode(', ', $quotedTypes) . ')',
                [':project' => $projectId]
            );
        }

        foreach ($alerts as $alert) {
            $existing = $this->db->fetchOne(
                'SELECT id
                 FROM project_pmo_alerts
                 WHERE project_id = :project
                   AND alert_type = :type
                   AND status = "open"
                 LIMIT 1',
                [
                    ':project' => $projectId,
                    ':type' => $alert['type'],
                ]
            );
            if ($existing) {
                $this->db->execute(
                    'UPDATE project_pmo_alerts
                     SET snapshot_id = :snapshot_id,
                         severity = :severity,
                         title = :title,
                         message = :message
                     WHERE id = :id',
                    [
                        ':snapshot_id' => $snapshotId,
                        ':severity' => $alert['severity'],
                        ':title' => $alert['title'],
                        ':message' => $alert['message'],
                        ':id' => (int) ($existing['id'] ?? 0),
                    ]
                );
                continue;
            }

            $this->db->insert(
                'INSERT INTO project_pmo_alerts (project_id, snapshot_id, alert_type, severity, title, message, status, created_at)
                 VALUES (:project_id, :snapshot_id, :alert_type, :severity, :title, :message, "open", NOW())',
                [
                    ':project_id' => $projectId,
                    ':snapshot_id' => $snapshotId,
                    ':alert_type' => $alert['type'],
                    ':severity' => $alert['severity'],
                    ':title' => $alert['title'],
                    ':message' => $alert['message'],
                ]
            );
        }
    }
}
