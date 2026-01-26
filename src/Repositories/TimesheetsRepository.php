<?php

declare(strict_types=1);

class TimesheetsRepository
{
    private const ADMIN_ROLES = ['Administrador', 'PMO'];

    public function __construct(private Database $db)
    {
    }

    public function weekly(array $user): array
    {
        $conditions = [];
        $params = [];
        $hasPmColumn = $this->db->columnExists('projects', 'pm_id');
        $talentId = $this->talentIdForUser((int) ($user['id'] ?? 0));

        if (!$this->isPrivileged($user)) {
            if ($talentId !== null) {
                $conditions[] = 'ts.talent_id = :talentId';
                $params[':talentId'] = $talentId;
            } elseif ($hasPmColumn) {
                $conditions[] = 'p.pm_id = :pmId';
                $params[':pmId'] = $user['id'];
            }
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return $this->db->fetchAll(
            'SELECT ts.id, ts.date, ts.hours, ts.status, ts.billable, ts.comment, ts.approval_comment,
                    p.name AS project, t.title AS task, ta.name AS talent
             FROM timesheets ts
             JOIN tasks t ON t.id = ts.task_id
             JOIN projects p ON p.id = t.project_id
             JOIN clients c ON c.id = p.client_id
             JOIN talents ta ON ta.id = ts.talent_id
             ' . $where . '
             ORDER BY ts.date DESC',
            $params
        );
    }

    public function kpis(array $user): array
    {
        $conditions = [];
        $params = [];
        $hasPmColumn = $this->db->columnExists('projects', 'pm_id');
        $talentId = $this->talentIdForUser((int) ($user['id'] ?? 0));

        if (!$this->isPrivileged($user)) {
            if ($talentId !== null) {
                $conditions[] = 'ts.talent_id = :talentId';
                $params[':talentId'] = $talentId;
            } elseif ($hasPmColumn) {
                $conditions[] = 'p.pm_id = :pmId';
                $params[':pmId'] = $user['id'];
            }
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $data = $this->db->fetchAll(
            'SELECT ts.status, SUM(ts.hours) AS hours
             FROM timesheets ts
             JOIN tasks t ON t.id = ts.task_id
             JOIN projects p ON p.id = t.project_id
             JOIN clients c ON c.id = p.client_id
             ' . $where . '
             GROUP BY ts.status',
            $params
        );
        $totals = ['draft' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
        foreach ($data as $row) {
            $status = $row['status'] ?? '';
            if ($status === 'submitted' || $status === 'pending_approval') {
                $status = 'pending';
            }
            if (!array_key_exists($status, $totals)) {
                $totals[$status] = 0;
            }
            $totals[$status] = (float) $row['hours'];
        }
        return $totals;
    }

    public function hasTimesheetAssignments(int $userId): bool
    {
        if (!$this->db->tableExists('talents')) {
            return false;
        }

        $talent = $this->talentProfileForUser($userId);

        return $talent !== null && (int) ($talent['requiere_reporte_horas'] ?? 0) === 1;
    }

    public function tasksForTimesheetEntry(int $userId): array
    {
        if (!$this->db->tableExists('tasks') || !$this->db->tableExists('talents')) {
            return [];
        }

        $talent = $this->talentProfileForUser($userId);
        if (!$talent || (int) ($talent['requiere_reporte_horas'] ?? 0) !== 1) {
            return [];
        }

        $conditions = [
            't.assignee_id = :talent',
            "t.status = 'in_progress'",
        ];
        if ((int) ($talent['is_outsourcing'] ?? 0) !== 1 && $this->db->tableExists('outsourcing_services')) {
            $conditions[] = 'NOT EXISTS (SELECT 1 FROM outsourcing_services os WHERE os.project_id = t.project_id)';
        }

        return $this->db->fetchAll(
            "SELECT t.id, t.title, p.id AS project_id, p.name AS project
             FROM tasks t
             JOIN projects p ON p.id = t.project_id
             WHERE " . implode(' AND ', $conditions) . '
             ORDER BY p.name ASC, t.title ASC',
            [':talent' => (int) $talent['id']]
        );
    }

    public function assignmentForTask(int $taskId, int $userId): ?array
    {
        if (!$this->db->tableExists('tasks') || !$this->db->tableExists('talents')) {
            return null;
        }

        $talent = $this->talentProfileForUser($userId);
        if (!$talent) {
            return null;
        }

        $outsourcingSelect = $this->db->tableExists('outsourcing_services')
            ? '(SELECT COUNT(*) FROM outsourcing_services os WHERE os.project_id = t.project_id) AS outsourcing_total'
            : '0 AS outsourcing_total';

        $task = $this->db->fetchOne(
            'SELECT t.id, t.status, t.assignee_id, t.project_id, ' . $outsourcingSelect . '
             FROM tasks t
             WHERE t.id = :task
             LIMIT 1',
            [':task' => $taskId]
        );

        if (!$task || (int) ($task['assignee_id'] ?? 0) !== (int) $talent['id']) {
            return null;
        }

        if (!empty($task['outsourcing_total']) && (int) ($talent['is_outsourcing'] ?? 0) !== 1) {
            return null;
        }

        return [
            'id' => null,
            'task_status' => $task['status'] ?? '',
            'requiere_reporte_horas' => $talent['requiere_reporte_horas'] ?? 0,
            'requiere_aprobacion_horas' => $talent['requiere_aprobacion_horas'] ?? 0,
            'talent_id' => $talent['id'],
            'project_id' => $task['project_id'] ?? null,
        ];
    }

    public function createTimesheet(array $payload): int
    {
        $columns = [
            'task_id',
            'talent_id',
            'assignment_id',
            'date',
            'hours',
            'status',
            'comment',
            'approval_comment',
            'billable',
            'approved_by',
            'approved_at',
        ];

        $params = [
            ':task_id' => (int) $payload['task_id'],
            ':talent_id' => (int) $payload['talent_id'],
            ':assignment_id' => $payload['assignment_id'] !== null ? (int) $payload['assignment_id'] : null,
            ':date' => $payload['date'],
            ':hours' => (float) $payload['hours'],
            ':status' => $payload['status'],
            ':comment' => $payload['comment'],
            ':approval_comment' => $payload['approval_comment'] ?? null,
            ':billable' => (int) $payload['billable'],
            ':approved_by' => $payload['approved_by'],
            ':approved_at' => $payload['approved_at'],
        ];

        if ($this->db->columnExists('timesheets', 'project_id')) {
            $columns[] = 'project_id';
            $params[':project_id'] = $payload['project_id'] ?? null;
        }

        if ($this->db->columnExists('timesheets', 'user_id')) {
            $columns[] = 'user_id';
            $params[':user_id'] = $payload['user_id'] ?? null;
        }

        $columns[] = 'created_at';
        $columns[] = 'updated_at';

        $timesheetId = $this->db->insert(
            'INSERT INTO timesheets (' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', array_keys($params)) . ', NOW(), NOW())',
            $params
        );

        $this->refreshTaskActualHours((int) $payload['task_id']);

        return $timesheetId;
    }

    public function myTimesheets(int $talentId): array
    {
        if (!$this->db->tableExists('timesheets')) {
            return [];
        }

        return $this->db->fetchAll(
            'SELECT ts.id, ts.date, ts.hours, ts.status, ts.billable, ts.comment, ts.approval_comment, p.name AS project, t.title AS task,
                    ts.approved_at, ts.rejected_at, ua.name AS approved_by_name, ur.name AS rejected_by_name
             FROM timesheets ts
             JOIN tasks t ON t.id = ts.task_id
             JOIN projects p ON p.id = t.project_id
             LEFT JOIN users ua ON ua.id = ts.approved_by
             LEFT JOIN users ur ON ur.id = ts.rejected_by
             WHERE ts.talent_id = :talent
             ORDER BY ts.date DESC, ts.id DESC',
            [':talent' => $talentId]
        );
    }

    public function pendingApprovals(array $user): array
    {
        if (!$this->db->tableExists('timesheets')) {
            return [];
        }

        $conditions = ['ts.status IN ("pending", "submitted", "pending_approval")'];
        $params = [];

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return $this->db->fetchAll(
            'SELECT ts.id, ts.date, ts.hours, ts.status, ts.billable, ts.comment, ts.approval_comment, p.name AS project, t.title AS task, ta.name AS talent
             FROM timesheets ts
             JOIN tasks t ON t.id = ts.task_id
             JOIN projects p ON p.id = t.project_id
             JOIN talents ta ON ta.id = ts.talent_id
             ' . $where . '
             ORDER BY ts.date DESC, ts.id DESC',
            $params
        );
    }

    public function countPendingApprovals(): int
    {
        if (!$this->db->tableExists('timesheets')) {
            return 0;
        }

        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total
             FROM timesheets
             WHERE status IN ("pending", "submitted", "pending_approval")'
        );

        return (int) ($row['total'] ?? 0);
    }

    public function updateApprovalStatus(int $timesheetId, string $status, int $userId, ?string $comment = null): bool
    {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            throw new InvalidArgumentException('Estado de aprobación inválido.');
        }

        $column = $status === 'approved' ? 'approved_by' : 'rejected_by';
        $columnDate = $status === 'approved' ? 'approved_at' : 'rejected_at';

        $timesheet = $this->db->fetchOne(
            'SELECT ts.task_id, ta.user_id
             FROM timesheets ts
             JOIN talents ta ON ta.id = ts.talent_id
             WHERE ts.id = :id',
            [':id' => $timesheetId]
        );

        if ($timesheet && (int) ($timesheet['user_id'] ?? 0) === $userId) {
            throw new InvalidArgumentException('No puedes aprobar tus propias horas.');
        }

        $updated = $this->db->execute(
            "UPDATE timesheets
             SET status = :status,
                 {$column} = :user,
                 {$columnDate} = NOW(),
                 approval_comment = :comment,
                 updated_at = NOW()
             WHERE id = :id AND status IN ('pending', 'submitted', 'pending_approval')",
            [
                ':status' => $status,
                ':user' => $userId,
                ':comment' => $comment !== null ? trim($comment) : null,
                ':id' => $timesheetId,
            ]
        );

        if ($updated && $timesheet) {
            $this->refreshTaskActualHours((int) $timesheet['task_id']);
        }

        return $updated;
    }

    public function findOwnerId(int $timesheetId): ?int
    {
        if (!$this->db->tableExists('timesheets')) {
            return null;
        }

        $row = $this->db->fetchOne(
            'SELECT user_id FROM timesheets WHERE id = :id LIMIT 1',
            [':id' => $timesheetId]
        );

        return $row && isset($row['user_id']) ? (int) $row['user_id'] : null;
    }

    public function talentIdForUser(int $userId): ?int
    {
        if ($userId <= 0 || !$this->db->tableExists('talents')) {
            return null;
        }

        $row = $this->db->fetchOne(
            'SELECT id FROM talents WHERE user_id = :user LIMIT 1',
            [':user' => $userId]
        );

        if (!$row) {
            return null;
        }

        return (int) $row['id'];
    }

    public function talentProfileForUser(int $userId): ?array
    {
        if ($userId <= 0 || !$this->db->tableExists('talents')) {
            return null;
        }

        $row = $this->db->fetchOne(
            'SELECT id, requiere_reporte_horas, requiere_aprobacion_horas, is_outsourcing
             FROM talents
             WHERE user_id = :user
             LIMIT 1',
            [':user' => $userId]
        );

        return $row ?: null;
    }

    public function refreshTaskActualHours(int $taskId): void
    {
        if (!$this->db->tableExists('tasks') || !$this->db->tableExists('timesheets')) {
            return;
        }

        $taskRow = $this->db->fetchOne(
            'SELECT project_id FROM tasks WHERE id = :task LIMIT 1',
            [':task' => $taskId]
        );

        $row = $this->db->fetchOne(
            'SELECT COALESCE(SUM(hours), 0) AS total
             FROM timesheets
             WHERE task_id = :task AND status = \'approved\'',
            [':task' => $taskId]
        );

        $this->db->execute(
            'UPDATE tasks SET actual_hours = :hours, updated_at = NOW() WHERE id = :task',
            [
                ':hours' => (float) ($row['total'] ?? 0),
                ':task' => $taskId,
            ]
        );

        $projectId = (int) ($taskRow['project_id'] ?? 0);
        if ($projectId > 0 && $this->db->tableExists('projects') && $this->db->columnExists('projects', 'actual_hours')) {
            $projectHours = $this->db->fetchOne(
                'SELECT COALESCE(SUM(ts.hours), 0) AS total
                 FROM timesheets ts
                 JOIN tasks t ON t.id = ts.task_id
                 WHERE t.project_id = :project AND ts.status = \'approved\'',
                [':project' => $projectId]
            );

            $this->db->execute(
                'UPDATE projects SET actual_hours = :hours, updated_at = NOW() WHERE id = :project',
                [
                    ':hours' => (float) ($projectHours['total'] ?? 0),
                    ':project' => $projectId,
                ]
            );
        }
    }

    private function isPrivileged(array $user): bool
    {
        return in_array($user['role'] ?? '', self::ADMIN_ROLES, true);
    }
}
