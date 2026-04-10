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
        [$conditions, $params] = $this->visibilityConditions($user, 't', 'p');
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        [$stopperJoin, $stopperSelect] = $this->taskStopperSql('t');
        $subtasksSelect = $this->taskSubtasksSelect('t');

        return $this->db->fetchAll(
            'SELECT t.id, t.title, t.status, t.priority, t.estimated_hours, t.actual_hours, t.due_date, t.schedule_activity_id,
                    p.id AS project_id, p.name AS project, p.phase AS project_phase,
                    t.assignee_id, ta.user_id AS assignee_user_id, ta.name AS assignee,
                    ' . $stopperSelect . ',
                    ' . $subtasksSelect . '
             FROM tasks t
             JOIN projects p ON p.id = t.project_id
             LEFT JOIN talents ta ON ta.id = t.assignee_id
             ' . $stopperJoin . '
             ' . $where . '
             ORDER BY p.name ASC, t.due_date ASC',
            $params
        );
    }

    public function find(int $taskId, array $user): ?array
    {
        [$conditions, $params] = $this->visibilityConditions($user, 't', 'p');
        $conditions[] = 't.id = :id';
        $params[':id'] = $taskId;
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        [$stopperJoin, $stopperSelect] = $this->taskStopperSql('t');
        $subtasksSelect = $this->taskSubtasksSelect('t');

        $row = $this->db->fetchOne(
            'SELECT t.id, t.title, t.status, t.priority, t.estimated_hours, t.actual_hours, t.due_date, t.assignee_id, t.schedule_activity_id,
                    ta.user_id AS assignee_user_id,
                    p.id AS project_id, p.name AS project, p.phase AS project_phase,
                    ' . $stopperSelect . ',
                    ' . $subtasksSelect . '
             FROM tasks t
             JOIN projects p ON p.id = t.project_id
             LEFT JOIN talents ta ON ta.id = t.assignee_id
             ' . $stopperJoin . '
             ' . $where . '
             LIMIT 1',
            $params
        );

        return $row ?: null;
    }

    public function updateTask(int $taskId, array $payload): void
    {
        $status = $this->normalizeStatus((string) ($payload['status'] ?? 'todo'));
        $isCompleted = $this->isCompletedStatus($status);
        $set = [
            'title = :title',
            'status = :status',
            'priority = :priority',
            'estimated_hours = :estimated',
            'due_date = :due_date',
            'assignee_id = :assignee',
            'schedule_activity_id = :schedule_activity_id',
            'updated_at = NOW()',
        ];
        $params = [
            ':title' => $payload['title'],
            ':status' => $status,
            ':priority' => $payload['priority'],
            ':estimated' => $payload['estimated_hours'],
            ':due_date' => $payload['due_date'],
            ':assignee' => $payload['assignee_id'],
            ':schedule_activity_id' => (int) ($payload['schedule_activity_id'] ?? 0) > 0 ? (int) $payload['schedule_activity_id'] : null,
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
        $status = $this->normalizeStatus((string) ($payload['status'] ?? 'todo'));
        return $this->db->insert(
            'INSERT INTO tasks (project_id, title, status, priority, estimated_hours, actual_hours, due_date, assignee_id, schedule_activity_id, created_at, updated_at)
             VALUES (:project_id, :title, :status, :priority, :estimated, :actual, :due_date, :assignee, :schedule_activity_id, NOW(), NOW())',
            [
                ':project_id' => $projectId,
                ':title' => $payload['title'],
                ':status' => $status,
                ':priority' => $payload['priority'],
                ':estimated' => $payload['estimated_hours'],
                ':actual' => 0,
                ':due_date' => $payload['due_date'],
                ':assignee' => $payload['assignee_id'],
                ':schedule_activity_id' => (int) ($payload['schedule_activity_id'] ?? 0) > 0 ? (int) $payload['schedule_activity_id'] : null,
            ]
        );
    }

    public function updateStatus(int $taskId, string $status): void
    {
        $normalizedStatus = $this->normalizeStatus($status);
        $params = [
            ':status' => $normalizedStatus,
            ':id' => $taskId,
        ];
        $set = 'status = :status, updated_at = NOW()';
        if ($this->db->columnExists('tasks', 'completed_at')) {
            $set .= ', completed_at = :completed_at';
            $params[':completed_at'] = $this->isCompletedStatus($normalizedStatus) ? date('Y-m-d H:i:s') : null;
        }

        $this->db->execute(
            'UPDATE tasks SET ' . $set . ' WHERE id = :id',
            $params
        );
    }

    public function forProject(int $projectId, array $user): array
    {
        [$conditions, $params] = $this->visibilityConditions($user, 't', 'p');
        $params[':project'] = $projectId;
        $where = $conditions ? ' AND ' . implode(' AND ', $conditions) : '';
        [$stopperJoin, $stopperSelect] = $this->taskStopperSql('t');
        $subtasksSelect = $this->taskSubtasksSelect('t');

        return $this->db->fetchAll(
            'SELECT t.id, t.title, t.status, t.priority, t.estimated_hours, t.actual_hours, t.due_date, t.schedule_activity_id,
                    t.assignee_id, ta.user_id AS assignee_user_id, ta.name AS assignee,
                    ' . $stopperSelect . ',
                    ' . $subtasksSelect . '
             FROM tasks t
             JOIN projects p ON p.id = t.project_id
             LEFT JOIN talents ta ON ta.id = t.assignee_id
             ' . $stopperJoin . '
             WHERE t.project_id = :project' . $where . '
             ORDER BY t.due_date ASC',
            $params
        );
    }

    public function canTalentUpdateTaskStatus(int $taskId, int $userId): bool
    {
        $talentId = $this->talentIdForUser($userId);
        if (
            $taskId <= 0
            || $talentId === null
            || !$this->db->columnExists('tasks', 'assignee_id')
        ) {
            return false;
        }

        $task = $this->db->fetchOne(
            'SELECT 1
             FROM tasks
             WHERE id = :task
               AND assignee_id = :talent
             LIMIT 1',
            [
                ':task' => $taskId,
                ':talent' => $talentId,
            ]
        );

        return $task !== null;
    }

    public function userCanCreateTaskInProject(array $user, int $projectId): bool
    {
        $userId = (int) ($user['id'] ?? 0);
        if ($projectId <= 0 || $userId <= 0) {
            return false;
        }

        if ($this->isPrivileged($user)) {
            return true;
        }

        if (strcasecmp((string) ($user['role'] ?? ''), 'Talento') !== 0) {
            return false;
        }

        if (
            !$this->db->tableExists('project_talent_assignments')
            || !$this->db->tableExists('projects')
        ) {
            return false;
        }

        $projectActiveCondition = $this->db->columnExists('projects', 'active')
            ? ' AND COALESCE(p.active, 1) = 1'
            : '';
        $hasAssignmentStatus = $this->db->columnExists('project_talent_assignments', 'assignment_status');
        $hasAssignmentActive = $this->db->columnExists('project_talent_assignments', 'active');
        $assignmentStatusCondition = match (true) {
            $hasAssignmentStatus && $hasAssignmentActive => "(a.assignment_status = 'active' OR (a.assignment_status IS NULL AND COALESCE(a.active, 1) = 1))",
            $hasAssignmentStatus => "a.assignment_status = 'active'",
            $hasAssignmentActive => "COALESCE(a.active, 1) = 1",
            default => '1 = 1',
        };

        $assignment = $this->db->fetchOne(
            'SELECT 1
             FROM project_talent_assignments a
             JOIN projects p ON p.id = a.project_id
             WHERE a.project_id = :project
               AND a.user_id = :user
               AND ' . $assignmentStatusCondition . $projectActiveCondition . '
             LIMIT 1',
            [
                ':project' => $projectId,
                ':user' => $userId,
            ]
        );

        return $assignment !== null;
    }

    public function assignedProjectsForUser(int $userId): array
    {
        if (
            $userId <= 0
            || !$this->db->tableExists('project_talent_assignments')
            || !$this->db->tableExists('projects')
        ) {
            return [];
        }

        $projectActiveCondition = $this->db->columnExists('projects', 'active')
            ? ' AND COALESCE(p.active, 1) = 1'
            : '';
        $hasAssignmentStatus = $this->db->columnExists('project_talent_assignments', 'assignment_status');
        $hasAssignmentActive = $this->db->columnExists('project_talent_assignments', 'active');
        $assignmentStatusCondition = match (true) {
            $hasAssignmentStatus && $hasAssignmentActive => "(a.assignment_status = 'active' OR (a.assignment_status IS NULL AND COALESCE(a.active, 1) = 1))",
            $hasAssignmentStatus => "a.assignment_status = 'active'",
            $hasAssignmentActive => "COALESCE(a.active, 1) = 1",
            default => '1 = 1',
        };

        return $this->db->fetchAll(
            'SELECT DISTINCT p.id, p.name
             FROM project_talent_assignments a
             JOIN projects p ON p.id = a.project_id
             WHERE a.user_id = :user
               AND ' . $assignmentStatusCondition . $projectActiveCondition . '
             ORDER BY p.name ASC',
            [':user' => $userId]
        );
    }

    public function scheduleActivitiesForProject(int $projectId): array
    {
        if ($projectId <= 0 || !$this->db->tableExists('project_schedule_activities')) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT id, name
             FROM project_schedule_activities
             WHERE project_id = :project
             ORDER BY sort_order ASC, id ASC',
            [':project' => $projectId]
        );
    }

    public function scheduleActivityBelongsToProject(int $projectId, int $activityId): bool
    {
        if ($projectId <= 0 || $activityId <= 0 || !$this->db->tableExists('project_schedule_activities')) {
            return false;
        }

        $row = $this->db->fetchOne(
            'SELECT 1
             FROM project_schedule_activities
             WHERE project_id = :project
               AND id = :activity
             LIMIT 1',
            [':project' => $projectId, ':activity' => $activityId]
        );

        return $row !== null;
    }

    public function talentIdForUser(int $userId): ?int
    {
        if ($userId <= 0 || !$this->db->tableExists('talents') || !$this->db->columnExists('talents', 'user_id')) {
            return null;
        }

        $talent = $this->db->fetchOne(
            'SELECT id FROM talents WHERE user_id = :user LIMIT 1',
            [':user' => $userId]
        );

        if (!$talent) {
            return null;
        }

        $talentId = (int) ($talent['id'] ?? 0);

        return $talentId > 0 ? $talentId : null;
    }

    public function deleteTask(int $taskId): void
    {
        $this->db->execute('DELETE FROM tasks WHERE id = :id', [':id' => $taskId]);
    }

    private function visibilityConditions(array $user, string $taskAlias, string $projectAlias): array
    {
        $conditions = [];
        $params = [];

        if ($this->isPrivileged($user)) {
            return [$conditions, $params];
        }

        $userId = (int) ($user['id'] ?? 0);
        $role = (string) ($user['role'] ?? '');
        if (strcasecmp($role, 'Talento') === 0) {
            if (!$this->db->columnExists('tasks', 'assignee_id')) {
                $conditions[] = '1 = 0';
                return [$conditions, $params];
            }
            $talentId = $this->talentIdForUser($userId);
            $conditions[] = $taskAlias . '.assignee_id = :assigneeId';
            $params[':assigneeId'] = $talentId ?? -1;
            return [$conditions, $params];
        }

        if ($this->db->columnExists('projects', 'pm_id')) {
            $conditions[] = $projectAlias . '.pm_id = :pmId';
            $params[':pmId'] = $userId;
        }

        return [$conditions, $params];
    }

    private function taskStopperSql(string $taskAlias): array
    {
        if (!$this->db->tableExists('project_stoppers') || !$this->db->columnExists('project_stoppers', 'task_id')) {
            return ['', '0 AS open_stoppers'];
        }

        $join = 'LEFT JOIN (
                    SELECT task_id, COUNT(*) AS open_stoppers
                    FROM project_stoppers
                    WHERE task_id IS NOT NULL
                      AND status <> "cerrado"
                    GROUP BY task_id
                 ) ps ON ps.task_id = ' . $taskAlias . '.id';

        return [$join, 'COALESCE(ps.open_stoppers, 0) AS open_stoppers'];
    }

    private function taskSubtasksSelect(string $taskAlias): string
    {
        $hasCompleted = $this->db->columnExists('tasks', 'subtasks_completed');
        $hasTotal = $this->db->columnExists('tasks', 'subtasks_total');

        if ($hasCompleted && $hasTotal) {
            return 'COALESCE(' . $taskAlias . '.subtasks_completed, 0) AS subtasks_completed, COALESCE(' . $taskAlias . '.subtasks_total, 0) AS subtasks_total';
        }

        return '0 AS subtasks_completed, 0 AS subtasks_total';
    }

    private function normalizeStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'pending' => 'todo',
            'completed' => 'done',
            default => strtolower(trim($status)),
        };
    }

    private function isCompletedStatus(string $status): bool
    {
        return in_array($status, ['done', 'completed'], true);
    }

    private function isPrivileged(array $user): bool
    {
        return in_array($user['role'] ?? '', ['Administrador', 'PMO'], true);
    }
}
