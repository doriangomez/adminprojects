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
            $grouped[$task['status']][] = $task;
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
        $this->db->execute(
            'UPDATE tasks
             SET title = :title,
                 status = :status,
                 priority = :priority,
                 estimated_hours = :estimated,
                 due_date = :due_date,
                 assignee_id = :assignee,
                 updated_at = NOW()
             WHERE id = :id',
            [
                ':title' => $payload['title'],
                ':status' => $payload['status'],
                ':priority' => $payload['priority'],
                ':estimated' => $payload['estimated_hours'],
                ':due_date' => $payload['due_date'],
                ':assignee' => $payload['assignee_id'],
                ':id' => $taskId,
            ]
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
        $this->db->execute(
            'UPDATE tasks SET status = :status, updated_at = NOW() WHERE id = :id',
            [
                ':status' => $status,
                ':id' => $taskId,
            ]
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
