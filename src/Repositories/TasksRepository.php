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

        $role = $user['role'] ?? '';
        if ($role === 'Talento') {
            $talentRow = $this->db->fetchOne(
                'SELECT id FROM talents WHERE user_id = :uid LIMIT 1',
                [':uid' => (int) ($user['id'] ?? 0)]
            );
            if ($talentRow) {
                $conditions[] = 't.assignee_id = :talentId';
                $params[':talentId'] = (int) $talentRow['id'];
            }
        } else {
            $hasPmColumn = $this->db->columnExists('projects', 'pm_id');
            if ($hasPmColumn && !$this->isPrivileged($user)) {
                $conditions[] = 'p.pm_id = :pmId';
                $params[':pmId'] = $user['id'];
            }
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

        $role = $user['role'] ?? '';
        if ($role === 'Talento') {
            $talentRow = $this->db->fetchOne(
                'SELECT id FROM talents WHERE user_id = :uid LIMIT 1',
                [':uid' => (int) ($user['id'] ?? 0)]
            );
            if ($talentRow) {
                $conditions[] = 't.assignee_id = :talentId';
                $params[':talentId'] = (int) $talentRow['id'];
            }
        } elseif (!$this->isPrivileged($user)) {
            $hasPmColumn = $this->db->columnExists('projects', 'pm_id');
            if ($hasPmColumn) {
                $conditions[] = 'p.pm_id = :pmId';
                $params[':pmId'] = $user['id'];
            }
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
        $columns = ['project_id', 'title', 'status', 'priority', 'estimated_hours', 'actual_hours', 'due_date', 'assignee_id', 'created_at', 'updated_at'];
        $values = [':project_id', ':title', ':status', ':priority', ':estimated', ':actual', ':due_date', ':assignee', 'NOW()', 'NOW()'];
        $params = [
            ':project_id' => $projectId,
            ':title' => $payload['title'],
            ':status' => 'todo',
            ':priority' => $payload['priority'],
            ':estimated' => $payload['estimated_hours'],
            ':actual' => 0,
            ':due_date' => $payload['due_date'],
            ':assignee' => $payload['assignee_id'],
        ];

        if ($this->db->columnExists('tasks', 'description') && isset($payload['description'])) {
            $columns[] = 'description';
            $values[] = ':description';
            $params[':description'] = $payload['description'];
        }

        return $this->db->insert(
            'INSERT INTO tasks (' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', $values) . ')',
            $params
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

    public function kanbanForTalent(int $talentId): array
    {
        $tasks = $this->db->fetchAll(
            'SELECT t.id, t.title, t.description, t.status, t.priority, t.estimated_hours, t.actual_hours,
                    t.due_date, t.project_id, p.name AS project, ta.name AS assignee
             FROM tasks t
             JOIN projects p ON p.id = t.project_id
             LEFT JOIN talents ta ON ta.id = t.assignee_id
             WHERE t.assignee_id = :talent_id
             ORDER BY FIELD(t.priority, "high", "medium", "low"), t.due_date ASC',
            [':talent_id' => $talentId]
        );

        return $this->groupByStatus($tasks);
    }

    public function kanbanForProject(int $projectId): array
    {
        $tasks = $this->db->fetchAll(
            'SELECT t.id, t.title, t.description, t.status, t.priority, t.estimated_hours, t.actual_hours,
                    t.due_date, t.project_id, p.name AS project, ta.name AS assignee
             FROM tasks t
             JOIN projects p ON p.id = t.project_id
             LEFT JOIN talents ta ON ta.id = t.assignee_id
             WHERE t.project_id = :project_id
             ORDER BY FIELD(t.priority, "high", "medium", "low"), t.due_date ASC',
            [':project_id' => $projectId]
        );

        return $this->groupByStatus($tasks);
    }

    public function kanbanAll(): array
    {
        $tasks = $this->db->fetchAll(
            'SELECT t.id, t.title, t.description, t.status, t.priority, t.estimated_hours, t.actual_hours,
                    t.due_date, t.project_id, p.name AS project, ta.name AS assignee, ta.id AS assignee_id
             FROM tasks t
             JOIN projects p ON p.id = t.project_id
             LEFT JOIN talents ta ON ta.id = t.assignee_id
             ORDER BY p.name ASC, FIELD(t.priority, "high", "medium", "low"), t.due_date ASC'
        );

        return $this->groupByStatus($tasks);
    }

    public function talentWorkloadSummary(): array
    {
        return $this->db->fetchAll(
            'SELECT ta.id AS talent_id, ta.name AS talent_name, ta.capacidad_horaria AS weekly_capacity,
                    COUNT(t.id) AS total_tasks,
                    SUM(CASE WHEN t.status NOT IN ("done","completed") THEN 1 ELSE 0 END) AS active_tasks,
                    COALESCE(SUM(t.estimated_hours), 0) AS total_estimated,
                    COALESCE(SUM(t.actual_hours), 0) AS total_actual,
                    SUM(CASE WHEN t.status = "blocked" THEN 1 ELSE 0 END) AS blocked_tasks
             FROM talents ta
             LEFT JOIN tasks t ON t.assignee_id = ta.id
             GROUP BY ta.id
             ORDER BY ta.name'
        );
    }

    public function stoppersForTasks(array $taskIds): array
    {
        if (empty($taskIds)) {
            return [];
        }

        if (!$this->db->tableExists('project_stoppers') || !$this->db->columnExists('project_stoppers', 'task_id')) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $rows = $this->db->fetchAll(
            'SELECT ps.task_id, ps.id, ps.title, ps.impact_level, ps.status
             FROM project_stoppers ps
             WHERE ps.task_id IN (' . $placeholders . ')
               AND ps.status IN ("abierto","en_gestion","escalado","resuelto")
             ORDER BY ps.task_id, FIELD(ps.impact_level, "critico","alto","medio","bajo")',
            array_values($taskIds)
        );

        $map = [];
        foreach ($rows as $row) {
            $tid = (int) ($row['task_id'] ?? 0);
            $map[$tid][] = $row;
        }

        return $map;
    }

    public function timesheetSummaryForTalent(int $talentId, string $weekStart, string $weekEnd): array
    {
        return $this->db->fetchAll(
            'SELECT ts.date, SUM(ts.hours) AS total_hours, COUNT(*) AS entries,
                    t.title AS task_title, p.name AS project_name
             FROM timesheets ts
             LEFT JOIN tasks t ON t.id = ts.task_id
             LEFT JOIN projects p ON p.id = ts.project_id
             WHERE ts.talent_id = :talent_id AND ts.date BETWEEN :start AND :end
             GROUP BY ts.date, ts.task_id
             ORDER BY ts.date ASC',
            [':talent_id' => $talentId, ':start' => $weekStart, ':end' => $weekEnd]
        );
    }

    public function weeklyHoursForTalent(int $talentId, string $weekStart, string $weekEnd): float
    {
        $row = $this->db->fetchOne(
            'SELECT COALESCE(SUM(hours), 0) AS total
             FROM timesheets
             WHERE talent_id = :talent_id AND date BETWEEN :start AND :end',
            [':talent_id' => $talentId, ':start' => $weekStart, ':end' => $weekEnd]
        );

        return (float) ($row['total'] ?? 0);
    }

    public function stoppersForTalent(int $talentId): array
    {
        if (!$this->db->tableExists('project_stoppers') || !$this->db->columnExists('project_stoppers', 'task_id')) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT ps.id, ps.title, ps.description, ps.impact_level, ps.status, ps.stopper_type,
                    ps.detected_at, ps.estimated_resolution_at, p.name AS project_name, t.title AS task_title
             FROM project_stoppers ps
             JOIN tasks t ON t.id = ps.task_id
             JOIN projects p ON p.id = ps.project_id
             WHERE t.assignee_id = :talent_id
               AND ps.status IN ("abierto","en_gestion","escalado","resuelto")
             ORDER BY FIELD(ps.impact_level, "critico","alto","medio","bajo"), ps.detected_at DESC',
            [':talent_id' => $talentId]
        );
    }

    public function deleteTask(int $taskId): void
    {
        if ($this->db->tableExists('timesheets') && $this->db->columnExists('timesheets', 'task_id')) {
            $this->db->execute('DELETE FROM timesheets WHERE task_id = :id', [':id' => $taskId]);
        }

        if ($this->db->tableExists('project_stoppers') && $this->db->columnExists('project_stoppers', 'task_id')) {
            $this->db->execute('UPDATE project_stoppers SET task_id = NULL WHERE task_id = :id', [':id' => $taskId]);
        }

        $this->db->execute('DELETE FROM tasks WHERE id = :id', [':id' => $taskId]);
    }

    public function allTasksTable(array $user): array
    {
        return $this->db->fetchAll(
            'SELECT t.id, t.title, t.description, t.status, t.priority, t.estimated_hours, t.actual_hours,
                    t.due_date, t.assignee_id, t.project_id,
                    p.name AS project, ta.name AS assignee,
                    (SELECT COUNT(*) FROM project_stoppers ps WHERE ps.task_id = t.id AND ps.status IN ("abierto","en_gestion","escalado","resuelto")) AS open_stoppers
             FROM tasks t
             JOIN projects p ON p.id = t.project_id
             LEFT JOIN talents ta ON ta.id = t.assignee_id
             ORDER BY p.name ASC, t.due_date ASC'
        );
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

    private function isPrivileged(array $user): bool
    {
        return in_array($user['role'] ?? '', ['Administrador', 'PMO'], true);
    }
}
