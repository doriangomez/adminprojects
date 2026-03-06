<?php

declare(strict_types=1);

namespace App\Repositories;

use Database;

class TasksRepository
{
    public function __construct(private Database $db)
    {
    }

    public function kanban(): array
    {
        $tasks = $this->db->fetchAll(
            'SELECT t.id, t.title, t.status, t.priority, t.estimated_hours, t.actual_hours, t.due_date, p.name AS project, ta.name AS assignee
             FROM tasks t JOIN projects p ON p.id = t.project_id LEFT JOIN talents ta ON ta.id = t.assignee_id ORDER BY t.due_date ASC'
        );
        return $this->groupByStatus($tasks);
    }

    public function kanbanAll(): array
    {
        $hasStoppers = $this->db->tableExists('project_stoppers');
        $stopperJoin = '';
        $stopperSelect = "NULL AS stopper_title, NULL AS stopper_impact";
        if ($hasStoppers) {
            $stopperJoin = "LEFT JOIN project_stoppers ps ON ps.task_id = t.id AND ps.status IN ('abierto','en_gestion','escalado')";
            $stopperSelect = "ps.title AS stopper_title, ps.impact_level AS stopper_impact";
        }

        $tasks = $this->db->fetchAll(
            "SELECT t.id, t.title, t.status, t.priority, t.estimated_hours, t.actual_hours, t.due_date,
                    p.id AS project_id, p.name AS project,
                    ta.id AS talent_id, ta.name AS assignee,
                    {$stopperSelect}
             FROM tasks t
             JOIN projects p ON p.id = t.project_id
             LEFT JOIN talents ta ON ta.id = t.assignee_id
             {$stopperJoin}
             ORDER BY ta.name ASC, t.due_date ASC"
        );
        return $this->groupByStatus($tasks);
    }

    public function kanbanForUser(int $userId): array
    {
        $talentId = $this->talentIdForUser($userId);

        $hasStoppers = $this->db->tableExists('project_stoppers');
        $stopperJoin = '';
        $stopperSelect = "NULL AS stopper_title, NULL AS stopper_impact";
        if ($hasStoppers) {
            $stopperJoin = "LEFT JOIN project_stoppers ps ON ps.task_id = t.id AND ps.status IN ('abierto','en_gestion','escalado')";
            $stopperSelect = "ps.title AS stopper_title, ps.impact_level AS stopper_impact";
        }

        if ($talentId !== null) {
            $tasks = $this->db->fetchAll(
                "SELECT t.id, t.title, t.status, t.priority, t.estimated_hours, t.actual_hours, t.due_date,
                        p.id AS project_id, p.name AS project,
                        ta.id AS talent_id, ta.name AS assignee,
                        {$stopperSelect}
                 FROM tasks t
                 JOIN projects p ON p.id = t.project_id
                 LEFT JOIN talents ta ON ta.id = t.assignee_id
                 {$stopperJoin}
                 WHERE t.assignee_id = :talentId
                 ORDER BY t.due_date ASC",
                [':talentId' => $talentId]
            );
        } else {
            $tasks = $this->db->fetchAll(
                "SELECT t.id, t.title, t.status, t.priority, t.estimated_hours, t.actual_hours, t.due_date,
                        p.id AS project_id, p.name AS project,
                        NULL AS talent_id, NULL AS assignee,
                        {$stopperSelect}
                 FROM tasks t
                 JOIN projects p ON p.id = t.project_id
                 {$stopperJoin}
                 WHERE 1=0"
            );
        }

        return $this->groupByStatus($tasks);
    }

    public function talentIdForUser(int $userId): ?int
    {
        if (!$this->db->tableExists('talents')) {
            return null;
        }
        $row = $this->db->fetchOne(
            'SELECT id FROM talents WHERE user_id = :user LIMIT 1',
            [':user' => $userId]
        );
        return $row ? (int) $row['id'] : null;
    }

    public function talentRecordForUser(int $userId): ?array
    {
        if (!$this->db->tableExists('talents')) {
            return null;
        }
        return $this->db->fetchOne(
            'SELECT id, name, weekly_capacity, capacidad_horaria, availability FROM talents WHERE user_id = :user LIMIT 1',
            [':user' => $userId]
        ) ?: null;
    }

    public function activeBlockersForTalent(int $talentId): array
    {
        if (!$this->db->tableExists('project_stoppers')) {
            return [];
        }
        return $this->db->fetchAll(
            "SELECT ps.id, ps.title, ps.impact_level, ps.stopper_type, ps.status, ps.detected_at,
                    t.id AS task_id, t.title AS task_title,
                    p.id AS project_id, p.name AS project_name
             FROM project_stoppers ps
             JOIN tasks t ON t.id = ps.task_id
             JOIN projects p ON p.id = t.project_id
             WHERE t.assignee_id = :talentId
               AND ps.status IN ('abierto','en_gestion','escalado')
             ORDER BY ps.detected_at DESC",
            [':talentId' => $talentId]
        );
    }

    public function weeklyHoursForTalent(int $talentId, \DateTimeImmutable $weekStart): float
    {
        if (!$this->db->tableExists('timesheets')) {
            return 0.0;
        }
        $weekEnd = $weekStart->modify('+6 days')->format('Y-m-d');
        $row = $this->db->fetchOne(
            'SELECT COALESCE(SUM(hours), 0) AS total
             FROM timesheets
             WHERE talent_id = :talentId AND date >= :start AND date <= :end',
            [
                ':talentId' => $talentId,
                ':start' => $weekStart->format('Y-m-d'),
                ':end' => $weekEnd,
            ]
        );
        return (float) ($row['total'] ?? 0);
    }

    public function todayHoursForTalent(int $talentId): float
    {
        if (!$this->db->tableExists('timesheets')) {
            return 0.0;
        }
        $row = $this->db->fetchOne(
            'SELECT COALESCE(SUM(hours), 0) AS total
             FROM timesheets
             WHERE talent_id = :talentId AND date = :today',
            [
                ':talentId' => $talentId,
                ':today' => date('Y-m-d'),
            ]
        );
        return (float) ($row['total'] ?? 0);
    }

    public function findById(int $taskId): ?array
    {
        $hasStoppers = $this->db->tableExists('project_stoppers');
        $stopperJoin = '';
        $stopperSelect = "NULL AS stopper_title";
        if ($hasStoppers) {
            $stopperJoin = "LEFT JOIN project_stoppers ps ON ps.task_id = t.id AND ps.status IN ('abierto','en_gestion','escalado')";
            $stopperSelect = "ps.title AS stopper_title";
        }

        $row = $this->db->fetchOne(
            "SELECT t.id, t.project_id, t.assignee_id, t.status, t.title,
                    p.name AS project, ta.name AS assignee, {$stopperSelect}
             FROM tasks t
             JOIN projects p ON p.id = t.project_id
             LEFT JOIN talents ta ON ta.id = t.assignee_id
             {$stopperJoin}
             WHERE t.id = :id
             LIMIT 1",
            [':id' => $taskId]
        );
        return $row ?: null;
    }

    public function workloadByTalent(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT ta.id AS talent_id, ta.name AS talent_name,
                    COUNT(t.id) AS task_count,
                    SUM(CASE WHEN t.status NOT IN ('done','completed') THEN t.estimated_hours ELSE 0 END) AS pending_hours,
                    SUM(CASE WHEN t.status IN ('blocked') THEN 1 ELSE 0 END) AS blocked_count
             FROM talents ta
             LEFT JOIN tasks t ON t.assignee_id = ta.id AND t.status NOT IN ('done','completed')
             GROUP BY ta.id, ta.name
             ORDER BY task_count DESC"
        );
        return $rows;
    }

    private function groupByStatus(array $tasks): array
    {
        $grouped = [
            'todo' => [],
            'in_progress' => [],
            'review' => [],
            'blocked' => [],
            'done' => [],
        ];
        foreach ($tasks as $task) {
            $status = (string) ($task['status'] ?? 'todo');
            if ($status === 'pending') {
                $status = 'todo';
            } elseif ($status === 'completed') {
                $status = 'done';
            }
            if (!array_key_exists($status, $grouped)) {
                $status = 'todo';
            }
            $grouped[$status][] = $task;
        }
        return $grouped;
    }

    public function listAll(array $user): array
    {
        $conditions = [];
        $params = [];

        $hasPmColumn = $this->db->columnExists('projects', 'pm_id');
        if ($hasPmColumn && !$this->isPrivileged($user)) {
            $conditions[] = 'p.pm_id = :pmId';
            $params[':pmId'] = $user['id'];
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return $this->db->fetchAll(
            'SELECT t.id, t.title, t.status, t.priority, t.estimated_hours, t.actual_hours, t.due_date,
                    p.id AS project_id, p.name AS project, p.phase AS project_phase, ta.name AS assignee
             FROM tasks t
             JOIN projects p ON p.id = t.project_id
             LEFT JOIN talents ta ON ta.id = t.assignee_id
             ' . $where . '
             ORDER BY p.name ASC, t.due_date ASC',
            $params
        );
    }

    public function find(int $taskId, array $user): ?array
    {
        $conditions = ['t.id = :id'];
        $params = [':id' => $taskId];

        $hasPmColumn = $this->db->columnExists('projects', 'pm_id');
        if ($hasPmColumn && !$this->isPrivileged($user)) {
            $conditions[] = 'p.pm_id = :pmId';
            $params[':pmId'] = $user['id'];
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $row = $this->db->fetchOne(
            'SELECT t.id, t.title, t.status, t.priority, t.estimated_hours, t.actual_hours, t.due_date, t.assignee_id,
                    p.id AS project_id, p.name AS project, p.phase AS project_phase
             FROM tasks t
             JOIN projects p ON p.id = t.project_id
             ' . $where . '
             LIMIT 1',
            $params
        );

        return $row ?: null;
    }

    public function updateTask(int $taskId, array $payload): void
    {
        $isCompleted = in_array((string) ($payload['status'] ?? ''), ['done', 'completed'], true);
        $set = [
            'title = :title',
            'status = :status',
            'priority = :priority',
            'estimated_hours = :estimated',
            'due_date = :due_date',
            'assignee_id = :assignee',
            'updated_at = NOW()',
        ];
        $params = [
            ':title' => $payload['title'],
            ':status' => $payload['status'],
            ':priority' => $payload['priority'],
            ':estimated' => $payload['estimated_hours'],
            ':due_date' => $payload['due_date'],
            ':assignee' => $payload['assignee_id'],
            ':id' => $taskId,
        ];
        if ($this->db->columnExists('tasks', 'completed_at')) {
            $set[] = 'completed_at = :completed_at';
            $params[':completed_at'] = $isCompleted ? date('Y-m-d H:i:s') : null;
        }

        $this->db->execute(
            'UPDATE tasks
             SET ' . implode(', ', $set) . '
             WHERE id = :id',
            $params
        );
    }

    public function createForProject(int $projectId, array $payload): int
    {
        return $this->db->insert(
            'INSERT INTO tasks (project_id, title, status, priority, estimated_hours, actual_hours, due_date, assignee_id, created_at, updated_at)
             VALUES (:project_id, :title, :status, :priority, :estimated, :actual, :due_date, :assignee, NOW(), NOW())',
            [
                ':project_id' => $projectId,
                ':title' => $payload['title'],
                ':status' => 'todo',
                ':priority' => $payload['priority'],
                ':estimated' => $payload['estimated_hours'],
                ':actual' => 0,
                ':due_date' => $payload['due_date'],
                ':assignee' => $payload['assignee_id'],
            ]
        );
    }

    public function updateStatus(int $taskId, string $status): void
    {
        $params = [
            ':status' => $status,
            ':id' => $taskId,
        ];
        $set = 'status = :status, updated_at = NOW()';
        if ($this->db->columnExists('tasks', 'completed_at')) {
            $set .= ', completed_at = :completed_at';
            $params[':completed_at'] = in_array($status, ['done', 'completed'], true) ? date('Y-m-d H:i:s') : null;
        }

        $this->db->execute(
            'UPDATE tasks SET ' . $set . ' WHERE id = :id',
            $params
        );
    }

    public function forProject(int $projectId, array $user): array
    {
        $conditions = [];
        $params = [':project' => $projectId];

        $hasPmColumn = $this->db->columnExists('projects', 'pm_id');
        if ($hasPmColumn && !$this->isPrivileged($user)) {
            $conditions[] = 'p.pm_id = :pmId';
            $params[':pmId'] = $user['id'];
        }

        $where = $conditions ? ' AND ' . implode(' AND ', $conditions) : '';

        return $this->db->fetchAll(
            'SELECT t.id, t.title, t.status, t.priority, t.estimated_hours, t.actual_hours, t.due_date, ta.name AS assignee
             FROM tasks t
             JOIN projects p ON p.id = t.project_id
             LEFT JOIN talents ta ON ta.id = t.assignee_id
             WHERE t.project_id = :project' . $where . '
             ORDER BY t.due_date ASC',
            $params
        );
    }

    private function isPrivileged(array $user): bool
    {
        return in_array($user['role'] ?? '', ['Administrador', 'PMO'], true);
    }
}
