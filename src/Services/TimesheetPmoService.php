<?php

declare(strict_types=1);

class TimesheetPmoService
{
    public function __construct(private Database $db)
    {
    }

    /**
     * SUM(timesheets.hours) / SUM(tasks.estimated_hours) per project.
     */
    public function progressByHours(int $projectId): ?float
    {
        $estimatedRow = $this->db->fetchOne(
            'SELECT COALESCE(SUM(estimated_hours), 0) AS total
             FROM tasks
             WHERE project_id = :project',
            [':project' => $projectId]
        );
        $estimatedHours = (float) ($estimatedRow['total'] ?? 0);

        if ($estimatedHours <= 0) {
            return null;
        }

        $registeredHours = $this->registeredHours($projectId);

        return min(100.0, round(($registeredHours / $estimatedHours) * 100, 2));
    }

    /**
     * Completed tasks / total tasks per project.
     */
    public function progressByTasks(int $projectId): ?float
    {
        $row = $this->db->fetchOne(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status IN ("done", "completed", "completada") THEN 1 ELSE 0 END) AS completed
             FROM tasks
             WHERE project_id = :project',
            [':project' => $projectId]
        );

        $total = (int) ($row['total'] ?? 0);
        if ($total <= 0) {
            return null;
        }

        $completed = (int) ($row['completed'] ?? 0);

        return round(($completed / $total) * 100, 2);
    }

    /**
     * Hours consumption: registered hours vs project planned_hours.
     */
    public function hoursConsumption(int $projectId): array
    {
        $projectRow = $this->db->fetchOne(
            'SELECT planned_hours FROM projects WHERE id = :id LIMIT 1',
            [':id' => $projectId]
        );
        $plannedHours = (float) ($projectRow['planned_hours'] ?? 0);
        $registeredHours = $this->registeredHours($projectId);
        $percentage = $plannedHours > 0 ? round(($registeredHours / $plannedHours) * 100, 2) : 0.0;

        $alert = 'normal';
        if ($plannedHours > 0) {
            if ($registeredHours > $plannedHours) {
                $alert = 'overconsumed';
            } elseif ($registeredHours > ($plannedHours * 0.80)) {
                $alert = 'warning';
            }
        }

        return [
            'registered_hours' => $registeredHours,
            'planned_hours' => $plannedHours,
            'percentage' => $percentage,
            'deviation' => round($registeredHours - $plannedHours, 2),
            'alert' => $alert,
        ];
    }

    /**
     * PMO risk level based on blockers, overdue tasks, and hour overconsumption.
     * Returns on_track | warning | critical
     */
    public function pmoRisk(int $projectId): array
    {
        $factors = [];
        $level = 'on_track';

        $blockerMetrics = $this->blockerMetrics($projectId);
        $openBlockers = (int) ($blockerMetrics['open_blockers'] ?? 0);
        $criticalBlockers = (int) ($blockerMetrics['critical_blockers'] ?? 0);

        if ($criticalBlockers > 0) {
            $level = 'critical';
            $factors[] = sprintf('%d bloqueos críticos activos', $criticalBlockers);
        } elseif ($openBlockers > 2) {
            if ($level !== 'critical') {
                $level = 'warning';
            }
            $factors[] = sprintf('%d bloqueos activos', $openBlockers);
        }

        $taskMetrics = $this->taskMetrics($projectId);
        $overdueTasks = (int) ($taskMetrics['overdue_tasks'] ?? 0);
        if ($overdueTasks > 3) {
            if ($level !== 'critical') {
                $level = 'critical';
            }
            $factors[] = sprintf('%d tareas atrasadas', $overdueTasks);
        } elseif ($overdueTasks > 0) {
            if ($level === 'on_track') {
                $level = 'warning';
            }
            $factors[] = sprintf('%d tareas atrasadas', $overdueTasks);
        }

        $consumption = $this->hoursConsumption($projectId);
        if ($consumption['alert'] === 'overconsumed') {
            if ($level !== 'critical') {
                $level = 'critical';
            }
            $factors[] = sprintf('Sobreconsumo de horas (%.1f%%)', $consumption['percentage']);
        } elseif ($consumption['alert'] === 'warning') {
            if ($level === 'on_track') {
                $level = 'warning';
            }
            $factors[] = sprintf('Consumo alto de horas (%.1f%%)', $consumption['percentage']);
        }

        return [
            'level' => $level,
            'factors' => $factors,
            'blocker_metrics' => $blockerMetrics,
            'task_metrics' => $taskMetrics,
            'consumption' => $consumption,
        ];
    }

    /**
     * Full PMO indicators for a project (used for project panel integration).
     */
    public function projectPmoIndicators(int $projectId): array
    {
        $consumption = $this->hoursConsumption($projectId);
        $risk = $this->pmoRisk($projectId);
        $progressHours = $this->progressByHours($projectId);
        $progressTasks = $this->progressByTasks($projectId);

        return [
            'registered_hours' => $consumption['registered_hours'],
            'planned_hours' => $consumption['planned_hours'],
            'hours_percentage' => $consumption['percentage'],
            'hours_deviation' => $consumption['deviation'],
            'hours_alert' => $consumption['alert'],
            'progress_hours' => $progressHours,
            'progress_tasks' => $progressTasks,
            'pmo_risk_level' => $risk['level'],
            'pmo_risk_factors' => $risk['factors'],
        ];
    }

    /**
     * Total hours registered for a project (all statuses).
     */
    public function registeredHours(int $projectId): float
    {
        $row = $this->db->fetchOne(
            'SELECT COALESCE(SUM(hours), 0) AS total
             FROM timesheets
             WHERE project_id = :project',
            [':project' => $projectId]
        );

        return round((float) ($row['total'] ?? 0), 2);
    }

    private function blockerMetrics(int $projectId): array
    {
        if (!$this->db->tableExists('project_stoppers')) {
            return ['open_blockers' => 0, 'critical_blockers' => 0, 'aged_blockers' => 0];
        }

        $row = $this->db->fetchOne(
            'SELECT
                SUM(CASE WHEN status IN ("abierto", "en_gestion", "escalado") THEN 1 ELSE 0 END) AS open_blockers,
                SUM(CASE WHEN status IN ("abierto", "en_gestion", "escalado") AND impact_level = "critico" THEN 1 ELSE 0 END) AS critical_blockers,
                SUM(CASE WHEN status IN ("abierto", "en_gestion", "escalado") AND DATEDIFF(CURDATE(), detected_at) > 7 THEN 1 ELSE 0 END) AS aged_blockers
             FROM project_stoppers
             WHERE project_id = :project',
            [':project' => $projectId]
        ) ?? [];

        return [
            'open_blockers' => (int) ($row['open_blockers'] ?? 0),
            'critical_blockers' => (int) ($row['critical_blockers'] ?? 0),
            'aged_blockers' => (int) ($row['aged_blockers'] ?? 0),
        ];
    }

    private function taskMetrics(int $projectId): array
    {
        $row = $this->db->fetchOne(
            'SELECT
                COUNT(*) AS total_tasks,
                SUM(CASE WHEN status IN ("done", "completed", "completada") THEN 1 ELSE 0 END) AS done_tasks,
                SUM(CASE WHEN status IN ("en_progreso", "in_progress", "todo") THEN 1 ELSE 0 END) AS active_tasks,
                SUM(CASE WHEN status IN ("pendiente", "todo", "backlog") THEN 1 ELSE 0 END) AS pending_tasks,
                SUM(CASE WHEN due_date IS NOT NULL AND due_date < CURDATE() AND status NOT IN ("done", "completed", "completada") THEN 1 ELSE 0 END) AS overdue_tasks
             FROM tasks
             WHERE project_id = :project',
            [':project' => $projectId]
        ) ?? [];

        return [
            'total_tasks' => (int) ($row['total_tasks'] ?? 0),
            'done_tasks' => (int) ($row['done_tasks'] ?? 0),
            'active_tasks' => (int) ($row['active_tasks'] ?? 0),
            'pending_tasks' => (int) ($row['pending_tasks'] ?? 0),
            'overdue_tasks' => (int) ($row['overdue_tasks'] ?? 0),
        ];
    }
}
