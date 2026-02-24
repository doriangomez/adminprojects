<?php

declare(strict_types=1);

namespace App\Repositories;

use Database;
use InvalidArgumentException;

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
             LEFT JOIN tasks t ON t.id = ts.task_id
             LEFT JOIN projects p ON p.id = COALESCE(ts.project_id, t.project_id)
             LEFT JOIN clients c ON c.id = p.client_id
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
             LEFT JOIN tasks t ON t.id = ts.task_id
             LEFT JOIN projects p ON p.id = COALESCE(ts.project_id, t.project_id)
             LEFT JOIN clients c ON c.id = p.client_id
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



    public function weeklyGridForUser(int $userId, \DateTimeImmutable $weekStart): array
    {
        $weekEnd = $weekStart->modify('+6 days');
        $projects = $this->projectsForTimesheetEntry($userId);
        $projectMap = [];
        foreach ($projects as $project) {
            $projectId = (int) ($project['project_id'] ?? 0);
            if ($projectId <= 0 || isset($projectMap[$projectId])) {
                continue;
            }
            $projectMap[$projectId] = [
                'project_id' => $projectId,
                'project' => (string) ($project['project'] ?? ''),
                'assignment_id' => isset($project['assignment_id']) ? (int) $project['assignment_id'] : null,
            ];
        }

        $talentId = $this->talentIdForUser($userId);
        $entries = [];
        if ($talentId !== null) {
            $entries = $this->db->fetchAll(
                'SELECT id, project_id, date, hours, status, comment
                 FROM timesheets
                 WHERE user_id = :user
                   AND date BETWEEN :start AND :end',
                [
                    ':user' => $userId,
                    ':start' => $weekStart->format('Y-m-d'),
                    ':end' => $weekEnd->format('Y-m-d'),
                ]
            );
        }

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $weekStart->modify('+' . $i . ' days');
            $days[] = [
                'key' => $day->format('Y-m-d'),
                'label' => ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'][$i],
                'number' => $day->format('d'),
            ];
        }

        $cells = [];
        $dayTotals = array_fill_keys(array_column($days, 'key'), 0.0);
        foreach ($entries as $entry) {
            $projectId = (int) ($entry['project_id'] ?? 0);
            $date = (string) ($entry['date'] ?? '');
            if (!isset($projectMap[$projectId]) || !isset($dayTotals[$date])) {
                continue;
            }
            $cells[$projectId][$date] = [
                'id' => (int) ($entry['id'] ?? 0),
                'hours' => (float) ($entry['hours'] ?? 0),
                'status' => (string) ($entry['status'] ?? 'draft'),
                'comment' => (string) ($entry['comment'] ?? ''),
            ];
            $dayTotals[$date] += (float) ($entry['hours'] ?? 0);
        }

        $rows = [];
        foreach ($projectMap as $projectId => $project) {
            $rowTotal = 0.0;
            $rowCells = [];
            foreach ($days as $day) {
                $date = $day['key'];
                $cell = $cells[$projectId][$date] ?? null;
                $hours = $cell !== null ? (float) ($cell['hours'] ?? 0) : 0.0;
                $rowTotal += $hours;
                $rowCells[$date] = $cell ?? [
                    'id' => null,
                    'hours' => 0.0,
                    'status' => 'draft',
                    'comment' => '',
                ];
            }
            $rows[] = [
                'project_id' => $projectId,
                'project' => $project['project'],
                'assignment_id' => $project['assignment_id'],
                'cells' => $rowCells,
                'total' => $rowTotal,
            ];
        }

        $profile = $this->talentProfileForUser($userId) ?? [];
        $weeklyCapacity = (float) ($profile['capacidad_horaria'] ?? 0);
        if ($weeklyCapacity <= 0) {
            $weeklyCapacity = (float) ($profile['weekly_capacity'] ?? 0);
        }

        return [
            'days' => $days,
            'rows' => $rows,
            'day_totals' => $dayTotals,
            'week_total' => array_sum($dayTotals),
            'weekly_capacity' => $weeklyCapacity,
            'requires_full_report' => (int) ($profile['requiere_reporte_horas'] ?? 0) === 1,
        ];
    }

    public function upsertDraftCell(int $userId, int $projectId, string $date, float $hours, string $comment = ''): array
    {
        $assignment = $this->assignmentForProject($projectId, $userId);
        if (!$assignment) {
            throw new InvalidArgumentException('Proyecto no asignado o inactivo para este talento.');
        }

        $talentId = (int) ($assignment['talent_id'] ?? 0);
        if ($talentId <= 0) {
            throw new InvalidArgumentException('Tu usuario no tiene un talento asociado.');
        }

        $existing = $this->db->fetchOne(
            'SELECT id, status, task_id, hours
             FROM timesheets
             WHERE user_id = :user AND project_id = :project AND date = :date
             LIMIT 1',
            [':user' => $userId, ':project' => $projectId, ':date' => $date]
        );

        if ($existing && !in_array((string) ($existing['status'] ?? ''), ['draft'], true)) {
            throw new InvalidArgumentException('La celda no es editable porque la semana ya fue enviada.');
        }

        $dayTotalRow = $this->db->fetchOne(
            'SELECT COALESCE(SUM(hours), 0) AS total
             FROM timesheets
             WHERE user_id = :user AND date = :date AND id <> :id',
            [
                ':user' => $userId,
                ':date' => $date,
                ':id' => (int) ($existing['id'] ?? 0),
            ]
        );

        $currentDayTotal = (float) ($dayTotalRow['total'] ?? 0);
        if ($currentDayTotal + $hours > 24) {
            throw new InvalidArgumentException('No puedes registrar más de 24 horas en un mismo día.');
        }

        $approverUserId = (int) ($assignment['timesheet_approver_user_id'] ?? 0);
        $approver = $approverUserId > 0 ? $approverUserId : null;
        $taskId = $this->resolveTimesheetTaskId($projectId);

        if ($existing) {
            $this->db->execute(
                'UPDATE timesheets
                 SET hours = :hours,
                     comment = :comment,
                     status = :status,
                     approver_user_id = :approver,
                     updated_at = NOW()
                 WHERE id = :id',
                [
                    ':hours' => $hours,
                    ':comment' => $comment,
                    ':status' => 'draft',
                    ':approver' => $approver,
                    ':id' => (int) $existing['id'],
                ]
            );
            return ['id' => (int) $existing['id'], 'updated' => true];
        }

        $id = $this->createTimesheet([
            'task_id' => $taskId,
            'project_id' => $projectId,
            'talent_id' => $talentId,
            'user_id' => $userId,
            'assignment_id' => $assignment['id'] ?? null,
            'approver_user_id' => $approver,
            'date' => $date,
            'hours' => $hours,
            'status' => 'draft',
            'comment' => $comment,
            'billable' => 0,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        return ['id' => $id, 'updated' => false];
    }

    public function submitWeek(int $userId, \DateTimeImmutable $weekStart): int
    {
        $weekEnd = $weekStart->modify('+6 days');
        $profile = $this->talentProfileForUser($userId) ?? [];
        $requiresFull = (int) ($profile['requiere_reporte_horas'] ?? 0) === 1;

        if ($requiresFull) {
            $rows = $this->db->fetchAll(
                'SELECT date, COALESCE(SUM(hours), 0) AS hours
                 FROM timesheets
                 WHERE user_id = :user AND date BETWEEN :start AND :end
                 GROUP BY date',
                [
                    ':user' => $userId,
                    ':start' => $weekStart->format('Y-m-d'),
                    ':end' => $weekEnd->format('Y-m-d'),
                ]
            );
            $totalsByDate = [];
            foreach ($rows as $row) {
                $totalsByDate[(string) $row['date']] = (float) ($row['hours'] ?? 0);
            }
            for ($i = 0; $i < 7; $i++) {
                $day = $weekStart->modify('+' . $i . ' days')->format('Y-m-d');
                if (($totalsByDate[$day] ?? 0.0) <= 0) {
                    throw new InvalidArgumentException('No se puede enviar la semana: hay días sin horas registradas.');
                }
            }
        }

        $this->db->execute(
            'UPDATE timesheets
             SET status = :submitted,
                 approved_by = NULL,
                 approved_at = NULL,
                 rejected_by = NULL,
                 rejected_at = NULL,
                 updated_at = NOW()
             WHERE user_id = :user
               AND date BETWEEN :start AND :end
               AND status = :draft',
            [
                ':submitted' => 'submitted',
                ':draft' => 'draft',
                ':user' => $userId,
                ':start' => $weekStart->format('Y-m-d'),
                ':end' => $weekEnd->format('Y-m-d'),
            ]
        );

        $row = $this->db->fetchOne('SELECT ROW_COUNT() AS total');
        return (int) ($row['total'] ?? 0);
    }

    public function pendingApprovalsByWeek(array $user): array
    {
        $rows = $this->pendingApprovals($user);
        $grouped = [];
        foreach ($rows as $row) {
            $date = new \DateTimeImmutable((string) ($row['date'] ?? 'now'));
            $weekStart = $date->modify('monday this week')->format('Y-m-d');
            $project = (string) ($row['project'] ?? 'Sin proyecto');
            $grouped[$weekStart]['week_start'] = $weekStart;
            $grouped[$weekStart]['week_label'] = $date->modify('monday this week')->format('d/m') . ' - ' . $date->modify('sunday this week')->format('d/m');
            $grouped[$weekStart]['total_hours'] = ($grouped[$weekStart]['total_hours'] ?? 0) + (float) ($row['hours'] ?? 0);
            $grouped[$weekStart]['rows'][] = $row;
            $grouped[$weekStart]['projects'][$project] = ($grouped[$weekStart]['projects'][$project] ?? 0) + (float) ($row['hours'] ?? 0);
        }

        foreach ($grouped as &$week) {
            $summary = [];
            foreach ($week['projects'] as $project => $hours) {
                $summary[] = ['project' => $project, 'hours' => $hours];
            }
            usort($summary, static fn(array $a, array $b): int => strcmp($a['project'], $b['project']));
            $week['project_summary'] = $summary;
            unset($week['projects']);
        }
        unset($week);

        krsort($grouped);
        return array_values($grouped);
    }

    public function updateWeekApprovalStatus(int $approverUserId, string $weekStart, string $status, ?string $comment = null): int
    {
        $start = new \DateTimeImmutable($weekStart);
        $end = $start->modify('+6 days');
        $params = [
            ':status' => $status,
            ':comment' => $comment,
            ':approver' => $approverUserId,
            ':start' => $start->format('Y-m-d'),
            ':end' => $end->format('Y-m-d'),
        ];

        $column = $status === 'approved' ? 'approved_by = :approver, approved_at = NOW(), rejected_by = NULL, rejected_at = NULL' : 'rejected_by = :approver, rejected_at = NOW(), approved_by = NULL, approved_at = NULL';
        $this->db->execute(
            'UPDATE timesheets
             SET status = :status,
                 approval_comment = :comment,
                 ' . $column . ',
                 updated_at = NOW()
             WHERE approver_user_id = :approver
               AND date BETWEEN :start AND :end
               AND status IN ("submitted", "pending", "pending_approval")'
            ,
            $params
        );

        $row = $this->db->fetchOne('SELECT ROW_COUNT() AS total');
        return (int) ($row['total'] ?? 0);
    }
    public function hasTimesheetAssignments(int $userId): bool
    {
        if (!$this->db->tableExists('talents')) {
            return false;
        }

        $talent = $this->talentProfileForUser($userId);

        return $talent !== null && (int) ($talent['requiere_reporte_horas'] ?? 0) === 1;
    }

    public function projectsForTimesheetEntry(int $userId): array
    {
        if (!$this->db->tableExists('project_talent_assignments') || !$this->db->tableExists('projects')) {
            return [];
        }
        $projectStatusCondition = $this->activeProjectCondition('p');
        $assignmentStatusCondition = "(a.assignment_status = 'active' OR (a.assignment_status IS NULL AND a.active = 1))";

        return $this->db->fetchAll(
            'SELECT a.id AS assignment_id, a.project_id, a.requires_timesheet_approval, p.name AS project
             FROM project_talent_assignments a
             JOIN projects p ON p.id = a.project_id
             JOIN talents t ON t.user_id = a.user_id
             WHERE a.user_id = :user
               AND COALESCE(t.requiere_reporte_horas, 0) = 1
               AND ' . $assignmentStatusCondition .
             ($projectStatusCondition !== '' ? ' AND ' . $projectStatusCondition : '') . '
             ORDER BY p.name ASC',
            [':user' => $userId]
        );
    }

    public function assignmentForProject(int $projectId, int $userId): ?array
    {
        if (!$this->db->tableExists('project_talent_assignments') || !$this->db->tableExists('projects')) {
            return null;
        }
        $projectStatusCondition = $this->activeProjectCondition('p');
        $assignmentStatusCondition = "(a.assignment_status = 'active' OR (a.assignment_status IS NULL AND a.active = 1))";
        $hasTalentColumn = $this->db->columnExists('project_talent_assignments', 'talent_id');
        $talentSelect = $hasTalentColumn ? 'a.talent_id' : 'NULL AS talent_id';
        $talentJoin = $hasTalentColumn ? 'a.talent_id' : 'NULL';
        $assignment = $this->db->fetchOne(
            'SELECT a.id, ' . $talentSelect . ', a.requires_timesheet, a.requires_timesheet_approval,
                    a.project_id, p.name AS project,
                    tal.requiere_reporte_horas, tal.requiere_aprobacion_horas, tal.timesheet_approver_user_id
             FROM project_talent_assignments a
             JOIN projects p ON p.id = a.project_id
             LEFT JOIN talents tal ON tal.id = ' . $talentJoin . '
             WHERE a.user_id = :user
               AND a.project_id = :project
               AND ' . $assignmentStatusCondition .
            ($projectStatusCondition !== '' ? ' AND ' . $projectStatusCondition : '') . '
             LIMIT 1',
            [':user' => $userId, ':project' => $projectId]
        );

        if (!$assignment) {
            return null;
        }

        $talentId = $hasTalentColumn ? (int) ($assignment['talent_id'] ?? 0) : 0;
        if ($talentId <= 0) {
            $talentId = $this->talentIdForUser($userId) ?? 0;
        }

        return [
            'id' => $assignment['id'] ?? null,
            'requires_timesheet' => $assignment['requires_timesheet'] ?? 0,
            'requires_timesheet_approval' => $assignment['requires_timesheet_approval'] ?? 0,
            'talent_requires_report' => $assignment['requiere_reporte_horas'] ?? 0,
            'talent_requires_approval' => $assignment['requiere_aprobacion_horas'] ?? 0,
            'timesheet_approver_user_id' => $assignment['timesheet_approver_user_id'] ?? null,
            'talent_id' => $talentId,
            'project_id' => $assignment['project_id'] ?? null,
        ];
    }

    public function resolveTimesheetTaskId(int $projectId): int
    {
        if (!$this->db->tableExists('tasks')) {
            throw new InvalidArgumentException('No hay tareas disponibles para registrar horas.');
        }

        $task = $this->db->fetchOne(
            'SELECT id FROM tasks WHERE project_id = :project AND title = :title ORDER BY id ASC LIMIT 1',
            [':project' => $projectId, ':title' => 'Registro de horas']
        );

        if ($task && isset($task['id'])) {
            return (int) $task['id'];
        }

        return (int) $this->db->insert(
            'INSERT INTO tasks (project_id, title, status, priority, estimated_hours, actual_hours, created_at, updated_at)
             VALUES (:project, :title, :status, :priority, 0, 0, NOW(), NOW())',
            [
                ':project' => $projectId,
                ':title' => 'Registro de horas',
                ':status' => 'todo',
                ':priority' => 'medium',
            ]
        );
    }

    public function createTimesheet(array $payload): int
    {
        $columns = [
            'task_id',
            'talent_id',
            'assignment_id',
            'approver_user_id',
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
            ':approver_user_id' => $payload['approver_user_id'] ?? null,
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
             LEFT JOIN tasks t ON t.id = ts.task_id
             LEFT JOIN projects p ON p.id = COALESCE(ts.project_id, t.project_id)
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
        if (!$this->isPrivileged($user)) {
            $conditions[] = 'ts.approver_user_id = :approverUser';
            $params[':approverUser'] = (int) ($user['id'] ?? 0);
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return $this->db->fetchAll(
            'SELECT ts.id, ts.date, ts.hours, ts.status, ts.billable, ts.comment, ts.approval_comment, p.name AS project, t.title AS task, ta.name AS talent, ts.approver_user_id
             FROM timesheets ts
             LEFT JOIN tasks t ON t.id = ts.task_id
             LEFT JOIN projects p ON p.id = COALESCE(ts.project_id, t.project_id)
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
            'SELECT ts.task_id, ta.user_id, ts.approver_user_id
             FROM timesheets ts
             JOIN talents ta ON ta.id = ts.talent_id
             WHERE ts.id = :id',
            [':id' => $timesheetId]
        );

        if ($timesheet && (int) ($timesheet['user_id'] ?? 0) === $userId) {
            throw new InvalidArgumentException('No puedes aprobar tus propias horas.');
        }

        if ($timesheet && (int) ($timesheet['approver_user_id'] ?? 0) > 0 && (int) ($timesheet['approver_user_id'] ?? 0) !== $userId) {
            throw new InvalidArgumentException('No tienes permiso para aprobar o rechazar este registro de horas.');
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
            'SELECT id, requiere_reporte_horas, requiere_aprobacion_horas, timesheet_approver_user_id, is_outsourcing
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

    private function activeProjectCondition(string $alias): string
    {
        $conditions = [];
        if ($this->db->columnExists('projects', 'status_code')) {
            $conditions[] = $alias . '.status_code NOT IN ("closed", "cancelled")';
        }
        if ($this->db->columnExists('projects', 'status')) {
            $conditions[] = $alias . '.status NOT IN ("closed", "cancelled")';
        }
        if ($conditions === []) {
            return '';
        }

        return implode(' AND ', $conditions);
    }
}
