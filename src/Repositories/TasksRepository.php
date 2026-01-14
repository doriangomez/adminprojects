<?php

declare(strict_types=1);

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
