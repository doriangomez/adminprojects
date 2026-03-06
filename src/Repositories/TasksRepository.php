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
        if (!$this->isPrivileged($user)) {
            $talentId = $this->talentIdForUser((int) ($user['id'] ?? 0));
            if ($hasPmColumn && $talentId === null) {
                $conditions[] = 'p.pm_id = :pmId';
                $params[':pmId'] = $user['id'];
            } elseif ($talentId !== null) {
                $conditions[] = '(p.pm_id = :pmId OR t.assignee_id = :talentId)';
                $params[':pmId'] = $user['id'];
                $params[':talentId'] = $talentId;
            } elseif ($hasPmColumn) {
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

    private function talentIdForUser(int $userId): ?int
    {
        if ($userId <= 0 || !$this->db->tableExists('talents')) {
            return null;
        }
        $row = $this->db->fetchOne(
            'SELECT id FROM talents WHERE user_id = :user LIMIT 1',
            [':user' => $userId]
        );
        return $row ? (int) $row['id'] : null;
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

    /**
     * Kanban de tareas filtrado por talento asignado (assignee_id).
     * Para vista "Mis tareas" del talento.
     */
    public function kanbanByTalentId(int $talentId): array
    {
        $tasks = $this->db->fetchAll(
            'SELECT t.id, t.title, t.status, t.priority, t.estimated_hours, t.actual_hours, t.due_date, t.project_id,
                    p.name AS project
             FROM tasks t
             JOIN projects p ON p.id = t.project_id
             WHERE t.assignee_id = :talentId
             ORDER BY t.due_date ASC, t.id ASC',
            [':talentId' => $talentId]
        );
        return $this->groupTasksByStatus($tasks);
    }

    /**
     * Kanban para PMO/Admin: todas las tareas con talento asignado.
     * Opcionalmente filtrado por proyecto.
     */
    public function kanbanForPmo(array $user, ?int $projectId = null): array
    {
        if (!$this->isPrivileged($user)) {
            return $this->kanbanByTalentId(0);
        }

        $conditions = ['1=1'];
        $params = [];

        if ($projectId !== null && $projectId > 0) {
            $conditions[] = 't.project_id = :projectId';
            $params[':projectId'] = $projectId;
        }

        $tasks = $this->db->fetchAll(
            'SELECT t.id, t.title, t.status, t.priority, t.estimated_hours, t.actual_hours, t.due_date, t.project_id,
                    p.name AS project, ta.name AS assignee, ta.id AS assignee_id
             FROM tasks t
             JOIN projects p ON p.id = t.project_id
             LEFT JOIN talents ta ON ta.id = t.assignee_id
             WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY t.due_date ASC, t.id ASC',
            $params
        );
        return $this->groupTasksByStatus($tasks);
    }

    /**
     * Carga de trabajo por talento: tareas asignadas y horas estimadas.
     */
    public function workloadByTalent(array $user): array
    {
        if (!$this->isPrivileged($user)) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT ta.id AS talent_id, ta.name AS talent_name,
                    COUNT(t.id) AS task_count,
                    COALESCE(SUM(t.estimated_hours), 0) AS estimated_hours
             FROM talents ta
             LEFT JOIN tasks t ON t.assignee_id = ta.id
               AND t.status NOT IN (\'done\', \'completed\')
             WHERE EXISTS (SELECT 1 FROM project_talent_assignments a WHERE a.talent_id = ta.id)
             GROUP BY ta.id, ta.name
             ORDER BY task_count DESC, estimated_hours DESC'
        );
    }

    /**
     * Verifica si el talento es el asignado de la tarea.
     */
    public function isAssignee(int $taskId, int $talentId): bool
    {
        if ($taskId <= 0 || $talentId <= 0) {
            return false;
        }
        $row = $this->db->fetchOne(
            'SELECT 1 FROM tasks WHERE id = :taskId AND assignee_id = :talentId LIMIT 1',
            [':taskId' => $taskId, ':talentId' => $talentId]
        );
        return $row !== null;
    }

    private function groupTasksByStatus(array $tasks): array
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
