<?php

declare(strict_types=1);

namespace App\Repositories;

use Database;

class ProjectSchedulesRepository
{
    public function __construct(private Database $db)
    {
    }

    public function activitiesForProject(int $projectId): array
    {
        if (!$this->db->tableExists('project_schedule_activities')) {
            return [];
        }

        $rows = $this->db->fetchAll(
            'SELECT a.id, a.project_id, a.sort_order, a.name, a.item_type, a.start_date, a.end_date,
                    a.duration_days, a.responsible_name, a.progress_percent, a.linked_task_id,
                    a.created_at, a.updated_at,
                    COUNT(t.id) AS linked_tasks_total,
                    SUM(CASE WHEN LOWER(COALESCE(t.status, \'\')) IN (\'done\', \'completed\') THEN 1 ELSE 0 END) AS linked_tasks_completed
             FROM project_schedule_activities a
             LEFT JOIN tasks t ON t.schedule_activity_id = a.id
             WHERE a.project_id = :project
             GROUP BY a.id, a.project_id, a.sort_order, a.name, a.item_type, a.start_date, a.end_date,
                      a.duration_days, a.responsible_name, a.progress_percent, a.linked_task_id, a.created_at, a.updated_at
             ORDER BY a.sort_order ASC, a.id ASC',
            [':project' => $projectId]
        );

        foreach ($rows as &$row) {
            $linkedTotal = (int) ($row['linked_tasks_total'] ?? 0);
            $linkedCompleted = (int) ($row['linked_tasks_completed'] ?? 0);
            $row['progress_locked'] = $linkedTotal > 0;
            if ($linkedTotal > 0) {
                $row['progress_percent'] = round(($linkedCompleted / max(1, $linkedTotal)) * 100, 1);
            }
            $row['derived_status'] = $this->activityStatus(
                (string) ($row['start_date'] ?? ''),
                (string) ($row['end_date'] ?? ''),
                (float) ($row['progress_percent'] ?? 0)
            );
        }
        unset($row);

        return $rows;
    }

    public function hasActivities(int $projectId): bool
    {
        if (!$this->db->tableExists('project_schedule_activities')) {
            return false;
        }

        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM project_schedule_activities WHERE project_id = :project',
            [':project' => $projectId]
        );

        return (int) ($row['total'] ?? 0) > 0;
    }

    public function replaceActivities(int $projectId, array $activities): void
    {
        $pdo = $this->db->connection();
        $pdo->beginTransaction();

        try {
            $this->db->execute('DELETE FROM project_schedule_activities WHERE project_id = :project', [':project' => $projectId]);
            $this->insertActivities($projectId, $activities);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function mergeActivities(int $projectId, array $activities): void
    {
        $existing = $this->activitiesForProject($projectId);
        $maxOrder = 0;
        foreach ($existing as $activity) {
            $maxOrder = max($maxOrder, (int) ($activity['sort_order'] ?? 0));
        }

        foreach ($activities as $index => &$activity) {
            $activity['sort_order'] = $maxOrder + $index + 1;
        }
        unset($activity);

        $this->insertActivities($projectId, $activities);
    }

    public function summary(int $projectId): array
    {
        $activities = $this->activitiesForProject($projectId);
        if (empty($activities)) {
            return [
                'start_date' => null,
                'end_date' => null,
                'days_elapsed' => 0,
                'days_total' => 0,
                'progress' => 0,
                'red' => 0,
                'yellow' => 0,
                'green' => 0,
            ];
        }

        $start = null;
        $end = null;
        $red = 0;
        $yellow = 0;
        $green = 0;
        $progressValues = [];
        foreach ($activities as $activity) {
            $startDate = (string) ($activity['start_date'] ?? '');
            $endDate = (string) ($activity['end_date'] ?? '');
            if ($startDate !== '' && ($start === null || $startDate < $start)) {
                $start = $startDate;
            }
            if ($endDate !== '' && ($end === null || $endDate > $end)) {
                $end = $endDate;
            }

            $status = (string) ($activity['derived_status'] ?? 'green');
            if ($status === 'red') {
                $red++;
            } elseif ($status === 'yellow') {
                $yellow++;
            } else {
                $green++;
            }

            if (($activity['item_type'] ?? 'activity') !== 'milestone') {
                $progressValues[] = (float) ($activity['progress_percent'] ?? 0);
            }
        }

        $today = strtotime(date('Y-m-d')) ?: 0;
        $startTs = $start ? (strtotime($start) ?: null) : null;
        $endTs = $end ? (strtotime($end) ?: null) : null;
        $totalDays = ($startTs && $endTs && $endTs >= $startTs) ? (int) floor(($endTs - $startTs) / 86400) + 1 : 0;
        $elapsedDays = ($startTs && $today >= $startTs)
            ? min($totalDays, (int) floor((min($today, $endTs ?: $today) - $startTs) / 86400) + 1)
            : 0;

        return [
            'start_date' => $start,
            'end_date' => $end,
            'days_elapsed' => max(0, $elapsedDays),
            'days_total' => max(0, $totalDays),
            'progress' => !empty($progressValues) ? round(array_sum($progressValues) / count($progressValues), 1) : 0,
            'red' => $red,
            'yellow' => $yellow,
            'green' => $green,
        ];
    }

    private function insertActivities(int $projectId, array $activities): void
    {
        foreach ($activities as $position => $activity) {
            $this->db->insert(
                'INSERT INTO project_schedule_activities
                    (project_id, sort_order, name, item_type, start_date, end_date, duration_days, responsible_name, progress_percent, linked_task_id, created_at, updated_at)
                 VALUES
                    (:project, :sort_order, :name, :item_type, :start_date, :end_date, :duration_days, :responsible_name, :progress_percent, :linked_task_id, NOW(), NOW())',
                [
                    ':project' => $projectId,
                    ':sort_order' => (int) ($activity['sort_order'] ?? ($position + 1)),
                    ':name' => trim((string) ($activity['name'] ?? 'Actividad')),
                    ':item_type' => (string) ($activity['item_type'] ?? 'activity'),
                    ':start_date' => $activity['start_date'] ?? null,
                    ':end_date' => $activity['end_date'] ?? null,
                    ':duration_days' => (int) ($activity['duration_days'] ?? 0),
                    ':responsible_name' => trim((string) ($activity['responsible_name'] ?? '')),
                    ':progress_percent' => (float) ($activity['progress_percent'] ?? 0),
                    ':linked_task_id' => (int) ($activity['linked_task_id'] ?? 0) ?: null,
                ]
            );
        }
    }

    private function activityStatus(string $startDate, string $endDate, float $progress): string
    {
        $today = strtotime(date('Y-m-d')) ?: 0;
        $startTs = strtotime($startDate) ?: $today;
        $endTs = strtotime($endDate) ?: $startTs;

        if ($today > $endTs && $progress < 100) {
            return 'red';
        }

        $total = max(1, (int) floor(($endTs - $startTs) / 86400) + 1);
        $elapsed = max(0, min($total, (int) floor(($today - $startTs) / 86400) + 1));
        $expected = min(100, ($elapsed / $total) * 100);

        $daysToDeadline = (int) floor(($endTs - $today) / 86400);
        if (($daysToDeadline < 3 && $progress < 80) || ($expected - $progress > 20)) {
            return 'yellow';
        }

        return 'green';
    }
}
