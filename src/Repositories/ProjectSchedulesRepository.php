<?php

declare(strict_types=1);

namespace App\Repositories;

use Database;
use InvalidArgumentException;

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

        $hasStatus = $this->db->columnExists('project_schedule_activities', 'status');
        $hasCode = $this->db->columnExists('project_schedule_activities', 'code');
        $hasPhase = $this->db->columnExists('project_schedule_activities', 'phase_code');
        $hasFront = $this->db->columnExists('project_schedule_activities', 'front_label');
        $hasCritAuto = $this->db->columnExists('project_schedule_activities', 'is_critical_auto');
        $hasCritManual = $this->db->columnExists('project_schedule_activities', 'is_critical_manual');
        $hasResponsibleUser = $this->db->columnExists('project_schedule_activities', 'responsible_user_id');

        $extra = [];
        if ($hasStatus) {
            $extra[] = 'a.status';
        }
        if ($hasCode) {
            $extra[] = 'a.code';
        }
        if ($hasPhase) {
            $extra[] = 'a.phase_code';
        }
        if ($hasFront) {
            $extra[] = 'a.front_label';
        }
        if ($hasCritAuto) {
            $extra[] = 'a.is_critical_auto';
        }
        if ($hasCritManual) {
            $extra[] = 'a.is_critical_manual';
        }
        if ($hasResponsibleUser) {
            $extra[] = 'a.responsible_user_id';
            $extra[] = 'ru.name AS responsible_user_name';
        }

        $extraSql = $extra === [] ? '' : ', ' . implode(', ', $extra);
        $joinUser = $hasResponsibleUser
            ? 'LEFT JOIN users ru ON ru.id = a.responsible_user_id'
            : '';

        $rows = $this->db->fetchAll(
            "SELECT a.id, a.project_id, a.sort_order, a.name, a.item_type, a.start_date, a.end_date,
                    a.duration_days, a.responsible_name, a.progress_percent, a.linked_task_id,
                    a.created_at, a.updated_at
                    {$extraSql}
             FROM project_schedule_activities a
             {$joinUser}
             WHERE a.project_id = :project
             ORDER BY a.sort_order ASC, a.id ASC",
            [':project' => $projectId]
        );

        foreach ($rows as &$row) {
            if (!$hasStatus) {
                $row['status'] = 'todo';
            }
            $row['progress_locked'] = false;
            $row['derived_status'] = $this->activityStatus(
                (string) ($row['start_date'] ?? ''),
                (string) ($row['end_date'] ?? ''),
                (float) ($row['progress_percent'] ?? 0)
            );
        }
        unset($row);

        return $rows;
    }

    /**
     * @param int[] $projectIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function activitiesForProjects(array $projectIds): array
    {
        $projectIds = array_values(array_filter(array_map(static fn ($id): int => (int) $id, $projectIds), static fn (int $id): bool => $id > 0));
        if ($projectIds === [] || !$this->db->tableExists('project_schedule_activities')) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($projectIds as $index => $projectId) {
            $key = ':project_' . $index;
            $placeholders[] = $key;
            $params[$key] = $projectId;
        }

        $rows = $this->db->fetchAll(
            'SELECT a.id, a.project_id, a.sort_order, a.name, a.item_type, a.start_date, a.end_date,
                    a.duration_days, a.responsible_name, a.progress_percent, a.linked_task_id,
                    a.created_at, a.updated_at
             FROM project_schedule_activities a
             WHERE a.project_id IN (' . implode(', ', $placeholders) . ')
             ORDER BY a.project_id ASC, a.sort_order ASC, a.id ASC',
            $params
        );

        $grouped = [];
        foreach ($rows as $row) {
            $projectId = (int) ($row['project_id'] ?? 0);
            if ($projectId <= 0) {
                continue;
            }
            $grouped[$projectId][] = $row;
        }

        return $grouped;
    }

    public function findActivity(int $projectId, int $activityId): ?array
    {
        $rows = $this->activitiesForProject($projectId);
        foreach ($rows as $row) {
            if ((int) ($row['id'] ?? 0) === $activityId) {
                return $row;
            }
        }

        return null;
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

    /**
     * @return array{before: array<string, mixed>, after: array<string, mixed>}
     */
    public function updateProgressAndStatus(int $projectId, int $activityId, float $progress, string $status): array
    {
        $before = $this->findActivity($projectId, $activityId);
        if ($before === null) {
            throw new InvalidArgumentException('Actividad no encontrada.');
        }

        $status = strtolower(trim($status));
        if (!in_array($status, ['todo', 'in_progress', 'review', 'blocked', 'done', 'cancelled'], true)) {
            throw new InvalidArgumentException('Estado inválido.');
        }

        $progress = max(0.0, min(100.0, $progress));
        if ($status === 'done') {
            $progress = 100.0;
        }

        $sets = ['progress_percent = :progress', 'updated_at = NOW()'];
        $params = [
            ':progress' => $progress,
            ':project' => $projectId,
            ':activity' => $activityId,
        ];

        if ($this->db->columnExists('project_schedule_activities', 'status')) {
            $sets[] = 'status = :status';
            $params[':status'] = $status;
        }

        $this->db->execute(
            'UPDATE project_schedule_activities SET ' . implode(', ', $sets) . '
             WHERE project_id = :project AND id = :activity',
            $params
        );

        $after = $this->findActivity($projectId, $activityId);
        if ($after === null) {
            throw new InvalidArgumentException('Actividad no encontrada tras actualizar.');
        }

        return ['before' => $before, 'after' => $after];
    }

    public function updateActivityDates(int $projectId, int $activityId, string $startDate, string $endDate): ?array
    {
        if (
            $projectId <= 0
            || $activityId <= 0
            || !$this->db->tableExists('project_schedule_activities')
        ) {
            return null;
        }

        $activity = $this->db->fetchOne(
            'SELECT id, project_id, item_type, start_date, end_date
             FROM project_schedule_activities
             WHERE project_id = :project
               AND id = :activity
             LIMIT 1',
            [
                ':project' => $projectId,
                ':activity' => $activityId,
            ]
        );

        if (!$activity) {
            return null;
        }

        if ((string) ($activity['item_type'] ?? 'activity') === 'milestone') {
            $start = strtotime($startDate) ?: null;
            if ($start === null) {
                throw new InvalidArgumentException('Fecha inválida para el hito.');
            }
            $milestoneDate = date('Y-m-d', $start);
            $this->db->execute(
                'UPDATE project_schedule_activities
                 SET start_date = :date_value,
                     end_date = :date_value,
                     duration_days = 0,
                     updated_at = NOW()
                 WHERE project_id = :project
                   AND id = :activity',
                [
                    ':date_value' => $milestoneDate,
                    ':project' => $projectId,
                    ':activity' => $activityId,
                ]
            );

            return $this->findActivity($projectId, $activityId);
        }

        $start = strtotime($startDate) ?: null;
        $end = strtotime($endDate) ?: null;
        if ($start === null || $end === null || $end < $start) {
            throw new InvalidArgumentException('Rango de fechas inválido para la actividad.');
        }

        $duration = (int) floor(($end - $start) / 86400) + 1;

        $this->db->execute(
            'UPDATE project_schedule_activities
             SET start_date = :start_date,
                 end_date = :end_date,
                 duration_days = :duration_days,
                 updated_at = NOW()
             WHERE project_id = :project
               AND id = :activity',
            [
                ':start_date' => date('Y-m-d', $start),
                ':end_date' => date('Y-m-d', $end),
                ':duration_days' => $duration,
                ':project' => $projectId,
                ':activity' => $activityId,
            ]
        );

        return $this->findActivity($projectId, $activityId);
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
            if (($activity['status'] ?? '') === 'cancelled') {
                continue;
            }
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

            $progressValues[] = (float) ($activity['progress_percent'] ?? 0);
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
        $hasStatus = $this->db->columnExists('project_schedule_activities', 'status');
        $hasCode = $this->db->columnExists('project_schedule_activities', 'code');
        $hasPhase = $this->db->columnExists('project_schedule_activities', 'phase_code');
        $hasFront = $this->db->columnExists('project_schedule_activities', 'front_label');
        $hasCritAuto = $this->db->columnExists('project_schedule_activities', 'is_critical_auto');
        $hasCritManual = $this->db->columnExists('project_schedule_activities', 'is_critical_manual');
        $hasResponsibleUser = $this->db->columnExists('project_schedule_activities', 'responsible_user_id');

        foreach ($activities as $position => $activity) {
            $status = strtolower(trim((string) ($activity['status'] ?? 'todo')));
            if (!in_array($status, ['todo', 'in_progress', 'review', 'blocked', 'done', 'cancelled'], true)) {
                $status = 'todo';
            }
            $progress = max(0.0, min(100.0, (float) ($activity['progress_percent'] ?? 0)));
            if ($status === 'done') {
                $progress = 100.0;
            }

            $columns = [
                'project_id', 'sort_order', 'name', 'item_type', 'start_date', 'end_date',
                'duration_days', 'responsible_name', 'progress_percent', 'linked_task_id',
                'created_at', 'updated_at',
            ];
            $values = [
                ':project', ':sort_order', ':name', ':item_type', ':start_date', ':end_date',
                ':duration_days', ':responsible_name', ':progress_percent', ':linked_task_id',
                'NOW()', 'NOW()',
            ];
            $params = [
                ':project' => $projectId,
                ':sort_order' => (int) ($activity['sort_order'] ?? ($position + 1)),
                ':name' => trim((string) ($activity['name'] ?? 'Actividad')),
                ':item_type' => (string) ($activity['item_type'] ?? 'activity'),
                ':start_date' => $activity['start_date'] ?? null,
                ':end_date' => $activity['end_date'] ?? null,
                ':duration_days' => (int) ($activity['duration_days'] ?? 0),
                ':responsible_name' => trim((string) ($activity['responsible_name'] ?? '')),
                ':progress_percent' => $progress,
                ':linked_task_id' => (int) ($activity['linked_task_id'] ?? 0) ?: null,
            ];

            if ($hasStatus) {
                $columns[] = 'status';
                $values[] = ':status';
                $params[':status'] = $status;
            }
            if ($hasCode) {
                $columns[] = 'code';
                $values[] = ':code';
                $code = trim((string) ($activity['code'] ?? ''));
                $params[':code'] = $code !== '' ? $code : null;
            }
            if ($hasPhase) {
                $columns[] = 'phase_code';
                $values[] = ':phase_code';
                $phase = trim((string) ($activity['phase_code'] ?? ''));
                $params[':phase_code'] = $phase !== '' ? $phase : null;
            }
            if ($hasFront) {
                $columns[] = 'front_label';
                $values[] = ':front_label';
                $front = trim((string) ($activity['front_label'] ?? ''));
                $params[':front_label'] = $front !== '' ? $front : null;
            }
            if ($hasCritAuto) {
                $columns[] = 'is_critical_auto';
                $values[] = ':is_critical_auto';
                $params[':is_critical_auto'] = (int) ((bool) ($activity['is_critical_auto'] ?? false));
            }
            if ($hasCritManual) {
                $columns[] = 'is_critical_manual';
                $values[] = ':is_critical_manual';
                $manual = $activity['is_critical_manual'] ?? null;
                $params[':is_critical_manual'] = ($manual === null || $manual === '') ? null : (int) ((bool) $manual);
            }
            if ($hasResponsibleUser) {
                $columns[] = 'responsible_user_id';
                $values[] = ':responsible_user_id';
                $uid = (int) ($activity['responsible_user_id'] ?? 0);
                $params[':responsible_user_id'] = $uid > 0 ? $uid : null;
            }

            $this->db->insert(
                'INSERT INTO project_schedule_activities (' . implode(', ', $columns) . ')
                 VALUES (' . implode(', ', $values) . ')',
                $params
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
